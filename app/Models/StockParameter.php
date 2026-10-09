<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockParameter extends Model
{
    protected $fillable = ['medicine_id', 'location_id', 'minimum_quantity'];

    protected function casts(): array
    {
        return ['minimum_quantity' => 'decimal:4'];
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
