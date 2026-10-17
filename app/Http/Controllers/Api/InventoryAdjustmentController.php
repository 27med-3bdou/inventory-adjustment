<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AdjustInventoryRequest;
use App\Models\Inventory;
use App\Services\InventoryAdjustmentService;
use Illuminate\Http\JsonResponse;

class InventoryAdjustmentController extends Controller
{
    public function __invoke(AdjustInventoryRequest $request, Inventory $inventory, InventoryAdjustmentService $service): JsonResponse
    {
        $result = $service->adjust(
            $inventory->id,
            $request->integer('counted_quantity'),
            $request->string('reason')->trim()->toString(),
            $request->user(),
        );

        $movement = $result['movement'];

        return response()->json([
            'message' => $movement ? 'Inventory adjusted successfully.' : 'No change. Counted quantity matches current stock.',
            'data' => [
                'inventory_id' => $result['inventory']->id,
                'previous_quantity' => $result['previous_quantity'],
                'quantity' => $result['inventory']->quantity,
                'movement' => $movement?->only(['id', 'quantity_change', 'reason', 'created_by', 'created_at']),
            ],
        ], $movement ? 201 : 200);
    }
}