<?php

namespace App\Console\Commands;

use App\Services\InventoryReconciliationService;
use App\Support\Quantity;
use Illuminate\Console\Command;

class ReconcileInventory extends Command
{
    protected $signature = 'inventory:reconcile {--json : Output a machine-readable report}';

    protected $description = 'Audit total stock, batch balances, and movement ledger without changing data';

    public function handle(InventoryReconciliationService $service): int
    {
        $report = $service->report();
        $failed = $report['mismatched_pairs'] > 0 || count($report['batch_identity_issues']) > 0;
        if ($this->option('json')) {
            $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        } else {
            $this->info('Audit baca-saja: '.$report['checked_pairs'].' pasangan obat/lokasi diperiksa.');
            $rows = collect($report['rows'])->where('matches', false)->map(fn ($row) => [
                $row['medicine_code'] ?? $row['medicine_id'], $row['location'] ?? $row['location_id'],
                Quantity::format($row['stock_quantity']), Quantity::format($row['batch_quantity']), Quantity::format($row['ledger_quantity']),
                Quantity::format($row['stock_minus_batch']), Quantity::format($row['stock_minus_ledger']),
            ])->all();
            if ($rows) $this->table(['Obat', 'Lokasi', 'Total', 'Batch', 'Kartu stok', 'Total - batch', 'Total - kartu'], $rows);
            if ($report['batch_identity_issues']) $this->error('Ada rincian batch yang mengacu ke obat berbeda. Lihat --json.');
            $failed ? $this->warn('Ditemukan selisih. Data tidak diubah otomatis.') : $this->info('Tidak ditemukan selisih.');
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
