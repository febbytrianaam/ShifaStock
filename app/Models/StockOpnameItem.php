<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockOpnameItem extends Model
{
    protected $fillable = ['stock_opname_session_id', 'medicine_id', 'batch_id', 'system_quantity', 'physical_quantity', 'difference', 'unit_name', 'reason'];

    protected function casts(): array
    {
        return [
            'system_quantity' => 'decimal:4',
            'physical_quantity' => 'decimal:4',
            'difference' => 'decimal:4',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(StockOpnameSession::class, 'stock_opname_session_id');
    }

    public function medicine(): BelongsTo
    {
        return $this->belongsTo(Medicine::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }
}
