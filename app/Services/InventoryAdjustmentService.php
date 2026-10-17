<?php

namespace App\Services;

use App\Models\Inventory;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class InventoryAdjustmentService
{
    public function adjust(int $inventoryId, int $countedQuantity, string $reason, User $admin): array
    {
        if ($countedQuantity < 0) {
            throw new InvalidArgumentException('Counted quantity cannot be negative.');
        }

        return DB::transaction(function () use ($inventoryId, $countedQuantity, $reason, $admin) {
            $inventory = Inventory::query()
                ->whereKey($inventoryId)
                ->lockForUpdate()
                ->firstOrFail();

            $previousQuantity = $inventory->quantity;
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