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
        $session->items()->delete();
        foreach ($snapshot['medicines'] as $medicineId => $medicine) {
            foreach ($medicine['batches'] as $batchId => $quantity) {
                if ((float) $quantity <= 0) continue;
                $session->items()->create(['medicine_id' => $medicineId, 'batch_id' => $batchId,
                    'system_quantity' => $quantity, 'unit_name' => $medicine['unit']]);
            }
        }
        $session->stock_snapshot = $snapshot;
        $session->save();
    }

    public function assertUnchanged(StockOpnameSession $session): void
    {
        if ($session->stock_snapshot === null || ! \App\Support\StockSnapshot::matches($session->stock_snapshot, $this->capture($session->location_id))) {
            throw ValidationException::withMessages(['items' => 'Stok, transaksi, atau satuan berubah sejak snapshot dibuat. Pilih Mulai Hitung Ulang, lalu periksa dan isi kembali jumlah fisik.']);
        }
    }
}
