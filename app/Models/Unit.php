<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Unit extends Model
{
    protected static function booted(): void
    {
        static::updating(fn (Unit $unit) => \App\Services\StockUnitGuard::unit($unit));
    }

    protected $fillable = ['name', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
