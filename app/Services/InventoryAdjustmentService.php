<?php

namespace App\Services;

use App\Exceptions\StaleInventoryException;
use App\Models\Inventory;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class InventoryAdjustmentService
{
    public function adjust(int $inventoryId, int $countedQuantity, string $reason, User $admin, ?int $expectedQuantity = null): array
    {
        if ($countedQuantity < 0) {
            throw new InvalidArgumentException('Counted quantity cannot be negative.');
        }

        return DB::transaction(function () use ($inventoryId, $countedQuantity, $reason, $admin, $expectedQuantity) {
            $inventory = Inventory::query()
                ->whereKey($inventoryId)
                ->lockForUpdate()
                ->firstOrFail();

               $previousQuantity = $inventory->quantity;
           if ($expectedQuantity !== null && $expectedQuantity !== $previousQuantity) {
               throw new StaleInventoryException($expectedQuantity, $previousQuantity);
            }
               $difference = $countedQuantity - $previousQuantity;

            if ($difference === 0) {
                return ['inventory' => $inventory, 'previous_quantity' => $previousQuantity, 'movement' => null];
            }

            $inventory->update(['quantity' => $countedQuantity]);

            $movement = $inventory->movements()->create([
                'quantity_change' => $difference,
                'reason' => $reason,
                'created_by' => $admin->id,
            ]);

            return ['inventory' => $inventory, 'previous_quantity' => $previousQuantity, 'movement' => $movement];
        }, 3);
    }
}