<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IssueImport extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['payload', 'file_hash'];

    protected function casts(): array
    {
        return ['payload' => 'encrypted:array', 'starts_at' => 'datetime', 'period_start' => 'datetime',
            'period_end' => 'datetime', 'posted_at' => 'datetime'];
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function poster(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }
}
