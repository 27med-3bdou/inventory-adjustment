<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryMovement extends Model
{
    Use HasFactory;
    public const UPDATED_AT = null;
    protected $fillable = [
        'inventory_id',
        'quantity_change',
        'reason',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'inventory_id' => 'integer',
            'quantity_change' => 'integer',
            'reason' => 'string',
            'created_by' => 'integer',
        ];
    }

        public function inventory(): BelongsTo
    {
        return $this->belongsTo(Inventory::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
