<?php

namespace App\Queries;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class MinimumStockAlerts
{
    public static function query(): Builder
    {
        return DB::table('stock_parameters as p')
            ->join('medicines as m', 'm.id', '=', 'p.medicine_id')
            ->join('locations as l', 'l.id', '=', 'p.location_id')
            ->leftJoin('stock_balances as b', fn ($join) => $join
                ->on('b.medicine_id', '=', 'p.medicine_id')
                ->on('b.location_id', '=', 'p.location_id'))
            ->leftJoin('units as u', 'u.id', '=', 'm.unit_id')
            ->whereNotNull('p.minimum_quantity')
            ->whereRaw('COALESCE(b.quantity, 0) <= p.minimum_quantity')
            ->selectRaw('m.id as medicine_id, m.code, m.name, l.id as location_id, l.name as location, COALESCE(b.quantity, 0) as quantity, p.minimum_quantity, u.name as unit, b.updated_at as stock_updated_at');
    }
}
