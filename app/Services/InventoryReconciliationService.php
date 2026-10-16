<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class InventoryReconciliationService
{
    public function report(): array
    {
        return DB::transaction(function () {
            $totals = DB::table('stock_balances')->get()->keyBy(fn ($row) => $row->medicine_id.':'.$row->location_id);
            $batches = DB::table('batch_stock_balances')->selectRaw('medicine_id, location_id, SUM(quantity) as quantity')
                ->groupBy('medicine_id', 'location_id')->get()->keyBy(fn ($row) => $row->medicine_id.':'.$row->location_id);
            $incoming = DB::table('inventory_movements')->whereNotNull('destination_location_id')
                ->selectRaw('medicine_id, destination_location_id as location_id, SUM(quantity) as quantity')
                ->groupBy('medicine_id', 'destination_location_id')->get()->keyBy(fn ($row) => $row->medicine_id.':'.$row->location_id);
            $outgoing = DB::table('inventory_movements')->whereNotNull('source_location_id')
                ->selectRaw('medicine_id, source_location_id as location_id, SUM(quantity) as quantity')
                ->groupBy('medicine_id', 'source_location_id')->get()->keyBy(fn ($row) => $row->medicine_id.':'.$row->location_id);
            $keys = $totals->keys()->merge($batches->keys())->merge($incoming->keys())->merge($outgoing->keys())->unique()->sort()->values();
            $medicines = DB::table('medicines')->pluck('code', 'id');
            $locations = DB::table('locations')->pluck('name', 'id');
            $rows = $keys->map(function ($key) use ($totals, $batches, $incoming, $outgoing, $medicines, $locations) {
                [$medicineId, $locationId] = array_map('intval', explode(':', $key));
                $total = round((float) ($totals->get($key)?->quantity ?? 0), 4);
                $batchTotal = round((float) ($batches->get($key)?->quantity ?? 0), 4);
                $ledger = round((float) ($incoming->get($key)?->quantity ?? 0) - (float) ($outgoing->get($key)?->quantity ?? 0), 4);

                return [
                    'medicine_id' => $medicineId, 'medicine_code' => $medicines[$medicineId] ?? null,
                    'location_id' => $locationId, 'location' => $locations[$locationId] ?? null,
                    'stock_quantity' => $total, 'batch_quantity' => $batchTotal, 'ledger_quantity' => $ledger,
                    'stock_minus_batch' => round($total - $batchTotal, 4),
                    'stock_minus_ledger' => round($total - $ledger, 4),
                    'matches' => $total === $batchTotal && $total === $ledger,
                ];
            });
            $identityIssues = DB::table('batch_stock_balances as s')->join('batches as b', 'b.id', '=', 's.batch_id')
                ->whereColumn('s.medicine_id', '<>', 'b.medicine_id')
                ->get(['s.id', 's.batch_id', 's.medicine_id', 'b.medicine_id as batch_medicine_id', 's.location_id']);

            return [
                'checked_pairs' => $rows->count(),
                'mismatched_pairs' => $rows->where('matches', false)->count(),
                'batch_identity_issues' => $identityIssues->all(),
                'rows' => $rows->all(),
            ];
        });
    }
}
