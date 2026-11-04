<?php

namespace App\Services;

use App\Models\BatchStockBalance;
use App\Models\InventoryMovement;
use App\Models\StockBalance;
use App\Models\StockOpnameSession;
use Illuminate\Validation\ValidationException;

class OpnameSnapshotService
{
    public function capture(int $locationId): array
    {
        $medicineIds = StockBalance::where('location_id', $locationId)->pluck('medicine_id')
            ->merge(BatchStockBalance::where('location_id', $locationId)->pluck('medicine_id'))->unique()->sort()->values();
        $medicines = [];
        foreach ($medicineIds as $medicineId) {
            $snapshot = app(DailyPhysicalCountService::class)->snapshot($medicineId, $locationId, true);
            if (round((float) $snapshot['quantity'], 4) !== round(array_sum(array_map('floatval', $snapshot['batches'])), 4)) {
                throw ValidationException::withMessages(['items' => 'Total stok dan rincian batch berbeda. Rekonsiliasi stok sebelum membuat snapshot opname.']);
            }
            $medicines[(string) $medicineId] = $snapshot;
        }

        return ['medicines' => $medicines, 'last_movement_id' => (int) (InventoryMovement::where(fn ($q) => $q
            ->where('source_location_id', $locationId)->orWhere('destination_location_id', $locationId))
            ->orderByDesc('id')->lockForUpdate()->value('id') ?? 0)];
    }

    public function resetItems(StockOpnameSession $session): void
    {
        $snapshot = $this->capture($session->location_id);
        foreach ($snapshot['medicines'] as $medicineId => $medicine) {
            foreach ($medicine['batches'] as $batchId => $quantity) {
                // Preserve existing rows, including exhausted batches; earlier counts are retained in the recount audit log.
                $session->items()->updateOrCreate(['medicine_id' => $medicineId, 'batch_id' => $batchId],
                    ['system_quantity' => $quantity, 'unit_name' => $medicine['unit'],
                        'physical_quantity' => null, 'difference' => null, 'reason' => null]);
            }
        }
        $session->stock_snapshot = $snapshot;
        $session->save();
    }

    public function version(StockOpnameSession $session): string
    {
        // Sign the same loaded rows shown in the form, including counts, reasons, status and snapshot.
        $session->loadMissing('items');
        $state = ['session' => $session->getAttributes(), 'items' => $session->items->sortBy('id')
            ->map(fn ($item) => $item->getAttributes())->values()->all()];

        return hash_hmac('sha256', json_encode($state, JSON_THROW_ON_ERROR), (string) config('app.key'));
    }

    public function assertVersion(StockOpnameSession $session, mixed $version): void
    {
        if (! is_string($version) || ! hash_equals($this->version($session), $version)) {
            throw ValidationException::withMessages(['version' => 'Sesi sudah diperbarui atau versi formulir tidak valid. Isian Anda belum disimpan. Muat ulang halaman dan periksa hasil hitung terbaru sebelum melanjutkan.']);
        }
    }

    public function assertUnchanged(StockOpnameSession $session): void
    {
        if ($session->stock_snapshot === null || ! \App\Support\StockSnapshot::matches($session->stock_snapshot, $this->capture($session->location_id))) {
            throw ValidationException::withMessages(['items' => 'Stok, transaksi, atau satuan berubah sejak snapshot dibuat. Pilih Mulai Hitung Ulang, lalu periksa dan isi kembali jumlah fisik.']);
        }
    }
}
