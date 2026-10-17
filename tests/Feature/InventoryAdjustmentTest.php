<?php

namespace Tests\Feature;

use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\User;
use App\Services\InventoryAdjustmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use RuntimeException;
use Tests\TestCase;

class InventoryAdjustmentTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Inventory $inventory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['is_admin' => true]);
        $this->inventory = Inventory::factory()->create(['quantity' => 12]);
    }

    private function adjust(array $payload)
    {
        return $this->postJson("/api/inventories/{$this->inventory->id}/adjustments", $payload);
    }

    public function test_admin_can_adjust_stock_after_physical_count(): void
    {
        Sanctum::actingAs($this->admin);

        $this->adjust(['counted_quantity' => 9, 'reason' => 'Damaged items'])
            ->assertCreated()
            ->assertJsonPath('data.previous_quantity', 12)
            ->assertJsonPath('data.quantity', 9)
            ->assertJsonPath('data.movement.quantity_change', -3);

        $this->assertSame(9, $this->inventory->fresh()->quantity);
        $this->assertDatabaseHas('inventory_movements', [
            'inventory_id' => $this->inventory->id,
            'quantity_change' => -3,
            'reason' => 'Damaged items',
            'created_by' => $this->admin->id,
        ]);
    }

    public function test_higher_count_records_positive_movement(): void
    {
        Sanctum::actingAs($this->admin);

        $this->adjust(['counted_quantity' => 15, 'reason' => 'Found items'])
            ->assertCreated()
            ->assertJsonPath('data.movement.quantity_change', 3);
    }

    public function test_same_count_records_no_movement(): void
    {
        Sanctum::actingAs($this->admin);

        $this->adjust(['counted_quantity' => 12, 'reason' => 'Routine count'])
            ->assertOk()
            ->assertJsonPath('data.movement', null);

        $this->assertDatabaseCount('inventory_movements', 0);
    }

    public function test_negative_quantity_is_rejected(): void
    {
        Sanctum::actingAs($this->admin);

        $this->adjust(['counted_quantity' => -1, 'reason' => 'Damaged items'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('counted_quantity');

        $this->assertSame(12, $this->inventory->fresh()->quantity);
    }

    public function test_required_fields_are_validated(): void
    {
        Sanctum::actingAs($this->admin);

        $this->adjust([])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['counted_quantity', 'reason']);
    }

    public function test_non_admin_cannot_adjust(): void
    {
        Sanctum::actingAs(User::factory()->create(['is_admin' => false]));

        $this->adjust(['counted_quantity' => 9, 'reason' => 'Damaged items'])->assertForbidden();

        $this->assertSame(12, $this->inventory->fresh()->quantity);
    }

    public function test_guest_cannot_adjust(): void
    {
        $this->adjust(['counted_quantity' => 9, 'reason' => 'Damaged items'])->assertUnauthorized();
    }

    public function test_update_and_movement_roll_back_together(): void
    {
        InventoryMovement::creating(fn () => throw new RuntimeException('Simulated failure'));

        try {
            app(InventoryAdjustmentService::class)->adjust($this->inventory->id, 9, 'Damaged items', $this->admin);
            $this->fail('Exception was not thrown.');
        } catch (RuntimeException) {
        }

        $this->assertSame(12, $this->inventory->fresh()->quantity);
        $this->assertDatabaseCount('inventory_movements', 0);
    }

    public function test_stale_expected_quantity_returns_conflict(): void
    {
        Sanctum::actingAs(User::factory()->create(['is_admin' => true]));
        $this->adjust(['counted_quantity' => 10, 'reason' => 'Damaged items', 'expected_quantity' => 12])
            ->assertCreated();

        Sanctum::actingAs($this->admin);
        $this->adjust(['counted_quantity' => 9, 'reason' => 'Damaged items', 'expected_quantity' => 12])
            ->assertStatus(409)
            ->assertJsonPath('current_quantity', 10);

        $this->assertSame(10, $this->inventory->fresh()->quantity);
        $this->assertDatabaseCount('inventory_movements', 1);
    }

    public function test_ledger_stays_consistent_across_adjustments(): void
    {
        $service = app(InventoryAdjustmentService::class);

        $service->adjust($this->inventory->id, 9, 'Damaged items', $this->admin);
        $service->adjust($this->inventory->id, 10, 'Recount', User::factory()->create(['is_admin' => true]));

        $final = $this->inventory->fresh()->quantity;

        $this->assertSame(10, $final);
        $this->assertSame($final, 12 + (int) $this->inventory->movements()->sum('quantity_change'));
    }
}