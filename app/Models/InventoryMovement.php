<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryMovement extends Model
{
    protected $fillable = ['type', 'reversed_movement_id', 'reference', 'medicine_id', 'batch_id', 'source_location_id', 'destination_location_id', 'quantity', 'occurred_at', 'counterparty', 'notes', 'created_by'];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:4', 'occurred_at' => 'datetime'];
    }

    public function medicine(): BelongsTo
    {
        return $this->belongsTo(Medicine::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    public function sourceLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'source_location_id');
    }

    public function destinationLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'destination_location_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reversedMovement(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversed_movement_id');
    }
}
