<?php

namespace App\Queries;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class LatestNightlyItems
{
    public static function query(?string $beforeDate = null): Builder
    {
        // A partial report replaces only the results of the medicines it contains.
        return DB::table('nightly_check_items as i')
            ->join('nightly_checks as c', 'c.id', '=', 'i.nightly_check_id')
            ->where('c.status', 'approved')
            ->whereNotNull('i.quantity')
            ->when($beforeDate, fn ($query) => $query->where('c.date', '<', $beforeDate))
            ->whereNotExists(function (Builder $query) use ($beforeDate) {
                $query->selectRaw('1')
                    ->from('nightly_check_items as newer_item')
                    ->join('nightly_checks as newer', 'newer.id', '=', 'newer_item.nightly_check_id')
                    ->whereColumn('newer_item.medicine_id', 'i.medicine_id')
                    ->whereColumn('newer.location_id', 'c.location_id')
                    ->where('newer.status', 'approved')
                    ->whereNotNull('newer_item.quantity')
                    ->when($beforeDate, fn ($query) => $query->where('newer.date', '<', $beforeDate))
                    ->where(fn ($query) => $query->whereColumn('newer.date', '>', 'c.date')
                        ->orWhere(fn ($query) => $query->whereColumn('newer.date', 'c.date')->whereColumn('newer.id', '>', 'c.id')));
            });
    }
}
