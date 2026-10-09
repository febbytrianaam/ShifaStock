<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Medicine extends Model
{
    protected $fillable = ['code', 'name', 'dosage_form', 'unit_id', 'is_active', 'import_source'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'import_source' => 'array'];
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function batches(): HasMany
    {
        return $this->hasMany(Batch::class);
    }

    public function packages(): HasMany
    {
        return $this->hasMany(MedicinePackage::class);
    }

    public function balances(): HasMany
    {
        return $this->hasMany(StockBalance::class);
    }

    public function stockParameters(): HasMany
    {
        return $this->hasMany(StockParameter::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }
}
