<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Medicine extends Model
{
    protected $fillable = [
        'code',
        'name',
        'original_name',
        'generic_name',
        'strength',
        'dosage_form',
        'unit_id',
        'stock_unit_suggestion',
        'purchase_unit_name',
        'package_reference',
        'conversion_candidate',
        'conversion_type',
        'is_active',
        'verification_status',
        'verification_note',
        'research_priority',
        'evidence_status',
        'kfa_code',
        'bpom_nie',
        'source_reference_1',
        'source_reference_2',
        'imported_by',
        'imported_at',
        'verified_by',
        'verified_at',
        'import_source',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'conversion_candidate' => 'decimal:4',
            'imported_at' => 'datetime',
            'verified_at' => 'datetime',
            'import_source' => 'array',
        ];
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

    public function audits(): HasMany
    {
        return $this->hasMany(MedicineAudit::class);
    }
}
