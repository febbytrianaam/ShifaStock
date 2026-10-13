<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Batch extends Model
{
    protected $fillable = ['medicine_id', 'number', 'expiry_date', 'expiry_precision'];

    protected function casts(): array
    {
        return ['expiry_date' => 'date'];
    }

    public function medicine(): BelongsTo
    {
        return $this->belongsTo(Medicine::class);
    }

    public function balances(): HasMany
    {
        return $this->hasMany(BatchStockBalance::class);
    }
}
