<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MedicinePackage extends Model
{
    protected $fillable = ['medicine_id', 'name', 'conversion_factor'];

    protected function casts(): array
    {
        return ['conversion_factor' => 'decimal:4'];
    }

    public function medicine(): BelongsTo
    {
        return $this->belongsTo(Medicine::class);
    }
}
