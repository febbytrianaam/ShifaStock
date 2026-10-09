<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockBalance extends Model
{
    protected $fillable = ['medicine_id', 'location_id', 'quantity', 'last_counted_at'];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:4', 'last_counted_at' => 'datetime'];
    }

    public function medicine(): BelongsTo
    {
        return $this->belongsTo(Medicine::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }
}
