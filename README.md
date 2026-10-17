# Inventory Adjustment API

Laravel API endpoint that lets an admin submit a physical stock count for an inventory item.

## Setup

```bash
composer install
cp .env.example .env
php artisan key:generate
# set DB_* in .env (MySQL)
php artisan migrate
```

Tests use a separate MySQL database (`inventory_adjustment_testing`, set in `phpunit.xml`):

```bash
php artisan test
```

## Endpoint

`POST /api/inventories/{inventory}/adjustments` (Sanctum token, admin only)

```json
{
  "counted_quantity": 9,
  "reason": "Damaged items",
  "expected_quantity": 12
}
```

`expected_quantity` is optional (see Concurrency).

| Status | Meaning |
| --- | --- |
| 201 | Stock adjusted and movement recorded |
| 200 | Count matches current stock, nothing recorded |
| 401 | Not authenticated |
| 403 | Not an admin |
| 404 | Inventory not found |
| 409 | Stock changed since the admin last saw it |
| 422 | Validation error |

## Implementation Decisions

**The client sends the counted quantity, not the difference.** The server calculates `quantity_change = counted - current`, so the difference is always based on the real current value.

**Atomicity.** Updating the quantity and creating the movement run inside one `DB::transaction()`. If either fails, both are rolled back. The transaction retries up to 3 times on deadlock.

**Concurrency: pessimistic locking.** Inside the transaction the row is read with `lockForUpdate()` (`SELECT ... FOR UPDATE`). If two admins adjust the same item at the same time, the second waits until the first commits, then calculates its difference from the updated quantity. This keeps the ledger consistent: `initial quantity + sum of movements = current quantity`.

**Concurrency: stale count detection.** Locking prevents corrupted numbers, but on its own the last count simply wins. If the client sends `expected_quantity` (the stock shown to the admin before counting) and the stock has changed since, the API returns `409 Conflict` instead of silently overwriting another admin's adjustment. The check runs after the lock, against the real value.

**Never negative, enforced in three places.** Validation (`min:0`), a guard in the service (for callers outside HTTP such as jobs or commands), and an `UNSIGNED` column in the database.

**Audit trail.** `inventory_movements` is append-only (no `updated_at`). `created_by` is taken from the authenticated user, never from the request body. Foreign keys use `restrictOnDelete` so history can't be deleted with its inventory or user.

**Layers.** Authorization and validation in a Form Request, business logic in `InventoryAdjustmentService` (no HTTP dependency, reusable from jobs or commands), and a thin invokable controller.

## Assumptions

- **Admin check:** a simple `is_admin` flag on `users`, checked through a Gate (`adjust-inventory`). In a production system with multiple roles I would use a roles/permissions package (e.g. `spatie/laravel-permission`); only the Gate definition would need to change.
- **Products and warehouses:** `product_id` and `warehouse_id` are not foreign keys because those tables are outside the task's scope.
- **Item lookup:** the endpoint uses the inventory id. The SKU (`BAG-001`) belongs to products; a SKU + warehouse lookup could be added.
- **No-op counts:** if the counted quantity equals current stock, no movement is recorded.
- **Quantities** are whole units.
- **Reason** is free text (max 255). It could become a fixed list (damaged, lost, found, recount).
- **Project structure:** given the task's size I kept Laravel's default structure. In a larger codebase I would group code by domain (e.g. `Inventory/`) or use a modular structure.

## Tests

`tests/Feature/InventoryAdjustmentTest.php` covers:

- The task scenario (12 → 9, movement of −3 with reason and admin)
- Increase and no-change counts
- Negative and missing input (422)
- Guest (401) and non-admin (403)
- Rollback: if creating the movement fails, the quantity is unchanged
- Stale `expected_quantity` returns 409 and changes nothing
- Ledger consistency across consecutive adjustments by different admins

True parallel requests can't be reproduced reliably inside a single PHPUnit process. Locking was designed for MySQL/InnoDB and can be verified manually with two concurrent DB sessions.