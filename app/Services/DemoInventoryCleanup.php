<?php

namespace App\Services;

use App\Models\Medicine;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class DemoInventoryCleanup
{
    // Exact records reviewed and approved for the local clinic preparation.
    private const MEDICINES = [
        'OBT-001' => 'Paracetamol 500 mg', 'OBT-002' => 'Amoxicillin 500 mg',
        'OBT-003' => 'Cetirizine 10 mg', 'OBT-004' => 'Antasida DOEN',
        'OBT-005' => 'Vitamin B Complex', 'OBT-006' => 'Salbutamol 2 mg',
    ];

    private const MOVEMENTS = [
        'STOK-2026-001' => ['ACI', 'test batch 1', 'opening_balance', 20.0, null, 'GDG'],
        'TRF-2026-001' => ['ACI', 'test batch 1', 'transfer', 10.0, 'GDG', 'APT'],
        'FKT-2026-001' => ['BETA', 'test', 'receipt', 15.0, null, 'GDG'],
    ];

    public function run(bool $apply = false): array
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('Pembersihan ini hanya diizinkan di lingkungan lokal atau pengujian.');
        }

        return InventoryTransaction::run(function () use ($apply) {
            $dummy = Medicine::whereIn('code', array_keys(self::MEDICINES))->get()->filter(function ($medicine) {
                // Imported records are protected even when a code resembles an old seed.
                if ($medicine->import_source || $medicine->imported_at || $medicine->imported_by) {
                    return false;
                }
                if ($medicine->name !== self::MEDICINES[$medicine->code]) {
                    throw new RuntimeException('Nama kandidat demo berubah; periksa ulang sebelum membersihkan.');
                }

                return true;
            })->pluck('id')->all();
            $batches = DB::table('batches as b')->join('medicines as m', 'm.id', '=', 'b.medicine_id')
                ->where(fn ($q) => $q->where(fn ($q) => $q->where('m.code', 'ACI')->where('b.number', 'test batch 1'))
                    ->orWhere(fn ($q) => $q->where('m.code', 'BETA')->where('b.number', 'test')))
                ->pluck('b.id')->all();
            $batchIds = array_merge($batches, DB::table('batches')->whereIn('medicine_id', $dummy)->pluck('id')->all());
            $affected = array_unique(array_merge($dummy, DB::table('batches')->whereIn('id', $batches)->pluck('medicine_id')->all()));
            $movements = DB::table('inventory_movements')->whereIn('medicine_id', $affected)->get();
            $locations = DB::table('locations')->pluck('code', 'id');
            $medicines = DB::table('medicines')->pluck('code', 'id');
            $numbers = DB::table('batches')->pluck('number', 'id');
            $seen = [];
            foreach ($movements as $movement) {
                $actual = [$medicines[$movement->medicine_id], $numbers[$movement->batch_id] ?? null, $movement->type,
                    (float) $movement->quantity, $locations[$movement->source_location_id] ?? null, $locations[$movement->destination_location_id] ?? null];
                if (! in_array($movement->batch_id, $batches) || $movement->reversed_movement_id !== null
                    || isset($seen[$movement->reference]) || $actual !== (self::MOVEMENTS[$movement->reference] ?? null)) {
                    throw new RuntimeException('Terdapat transaksi di luar manifest uji. Tidak ada data dihapus.');
                }
                $seen[$movement->reference] = true;
            }
            if (DB::table('inventory_movements')->whereIn('reversed_movement_id', $movements->pluck('id'))->exists()
                || DB::table('batches')->whereIn('medicine_id', $affected)->whereNotIn('id', $batchIds)->exists()
                || DB::table('inventory_receipt_items')->whereIn('medicine_id', $affected)
                    ->where(fn ($q) => $q->whereNull('batch_id')->orWhereNotIn('batch_id', $batchIds))->exists()) {
                throw new RuntimeException('Riwayat campuran atau reversal harus diperiksa manual.');
            }
            $receiptIds = DB::table('inventory_receipt_items')->whereIn('medicine_id', $affected)->pluck('inventory_receipt_id')->unique()->all();
            if (DB::table('inventory_receipt_items')->whereIn('inventory_receipt_id', $receiptIds)->whereNotIn('medicine_id', $affected)->exists()) {
                throw new RuntimeException('Faktur berisi item lain; pembersihan dibatalkan.');
            }
            $checkIds = DB::table('nightly_check_items')->whereIn('medicine_id', $affected)->pluck('nightly_check_id')->unique()->all();
            if (DB::table('nightly_checks')->whereIn('id', $checkIds)->where('status', '<>', 'draft')->exists()
                || DB::table('nightly_check_items')->whereIn('nightly_check_id', $checkIds)
                    ->where(fn ($q) => $q->whereNotNull('quantity')->orWhereNotNull('batch_counts')->orWhereNotNull('note')->orWhereNotNull('requested_quantity')->orWhere('is_low', true))->exists()) {
                throw new RuntimeException('Pemeriksaan sudah diisi atau diajukan; tidak dihapus otomatis.');
            }
            $sessionIds = DB::table('stock_opname_items')->whereIn('medicine_id', $affected)->pluck('stock_opname_session_id')->unique()->all();
            if (DB::table('stock_opname_sessions')->whereIn('id', $sessionIds)->where('status', '<>', 'draft')->exists()
                || DB::table('stock_opname_items')->whereIn('stock_opname_session_id', $sessionIds)
                    ->where(fn ($q) => $q->whereNotIn('medicine_id', $affected)->orWhereNotNull('physical_quantity')->orWhereNotNull('reason'))->exists()) {
                throw new RuntimeException('Sesi opname berisi penghitungan atau data lain; periksa manual.');
            }
            $report = app(InventoryReconciliationService::class)->report();
            if ($report['mismatched_pairs'] || $report['batch_identity_issues']) {
                throw new RuntimeException('Rekonsiliasi awal tidak sesuai. Pembersihan dibatalkan.');
            }
            $summary = ['dummy_medicines' => count($dummy), 'test_movements' => $movements->count(),
                'batches' => count($batchIds), 'receipts' => count($receiptIds),
                'empty_daily_drafts' => count($checkIds), 'empty_opname_drafts' => count($sessionIds),
                'preserved_medicines' => Medicine::whereNotIn('id', $dummy)->count(), 'applied' => false];
            if (! $apply || (! $dummy && ! $batchIds)) {
                return $summary;
            }

            $preserved = DB::table('medicines')->whereNotIn('id', $dummy)->orderBy('id')->get()->toJson();
            $tables = ['medicines', 'medicine_packages', 'medicine_audits', 'stock_parameters', 'batches',
                'stock_balances', 'batch_stock_balances', 'inventory_movements', 'inventory_receipts',
                'inventory_receipt_items', 'nightly_checks', 'nightly_check_items', 'stock_opname_sessions', 'stock_opname_items'];
            $snapshot = [];
            foreach ($tables as $table) {
                $snapshot[$table] = DB::table($table)->orderBy('id')->get()->all();
            }
            $directory = storage_path('app/private/backups');
            if (! is_dir($directory) && ! mkdir($directory, 0700, true)) {
                throw new RuntimeException('Folder backup tidak dapat dibuat.');
            }
            $backup = $directory.'/before-demo-cleanup-'.now()->format('Ymd-His').'-'.bin2hex(random_bytes(6)).'.json';
            $json = json_encode(['created_at' => now()->toIso8601String(), 'summary' => $summary, 'tables' => $snapshot], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
            if (file_put_contents($backup, $json, LOCK_EX) !== strlen($json)) {
                throw new RuntimeException('Backup tidak lengkap. Tidak ada data dihapus.');
            }
            @chmod($backup, 0600);

            DB::table('inventory_receipts')->whereIn('id', $receiptIds)->delete();
            DB::table('nightly_checks')->whereIn('id', $checkIds)->delete();
            DB::table('stock_opname_sessions')->whereIn('id', $sessionIds)->delete();
            DB::table('inventory_movements')->whereIn('id', $movements->pluck('id'))->delete();
            DB::table('batch_stock_balances')->whereIn('medicine_id', $affected)->delete();
            DB::table('stock_balances')->whereIn('medicine_id', $affected)->delete();
            DB::table('batches')->whereIn('id', $batchIds)->delete();
            DB::table('medicine_packages')->whereIn('medicine_id', $dummy)->delete();
            DB::table('stock_parameters')->whereIn('medicine_id', $dummy)->delete();
            DB::table('medicines')->whereIn('id', $dummy)->delete();
            if ($preserved !== DB::table('medicines')->whereNotIn('id', $dummy)->orderBy('id')->get()->toJson()) {
                throw new RuntimeException('Master obat resmi berubah. Seluruh perubahan dibatalkan.');
            }
            $report = app(InventoryReconciliationService::class)->report();
            if ($report['mismatched_pairs'] || $report['batch_identity_issues']) {
                throw new RuntimeException('Rekonsiliasi gagal. Seluruh perubahan dibatalkan.');
            }

            return array_merge($summary, ['applied' => true, 'backup' => $backup, 'reconciliation' => 'sesuai']);
        }, 1);
    }
}
