<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockOpnameSession extends Model
{
    protected $fillable = ['reference', 'period', 'location_id', 'cutoff_at', 'status', 'notes', 'created_by', 'finalized_by', 'finalized_at'];

    protected function casts(): array
    {
        return ['cutoff_at' => 'datetime', 'finalized_at' => 'datetime', 'stock_snapshot' => 'array'];
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function finalizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'finalized_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(StockOpnameItem::class);
    }
}
