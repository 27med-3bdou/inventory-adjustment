<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

class StaleInventoryException extends Exception
{
    public function __construct(
        public readonly int $expectedQuantity,
        public readonly int $currentQuantity,
    ) {
        parent::__construct('Inventory was changed by another admin.');
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => 'This item was adjusted by someone else. Please review the current quantity and resubmit.',
            'expected_quantity' => $this->expectedQuantity,
            'current_quantity' => $this->currentQuantity,
        ], 409);
    }
}