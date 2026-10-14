<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryReceipt extends Model
{
    protected $fillable = ['reference', 'status', 'counterparty', 'destination_location_id', 'received_at', 'posted_at', 'notes', 'created_by', 'posted_by'];

    protected function casts(): array
    {
        return ['received_at' => 'datetime', 'posted_at' => 'datetime'];
    }

    public function items(): HasMany
    {
        return $this->hasMany(InventoryReceiptItem::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function poster(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    public function destinationLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'destination_location_id');
    }
}
