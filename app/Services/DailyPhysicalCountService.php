<?php

namespace App\Services;

use App\Models\Batch;
use App\Models\BatchStockBalance;
use App\Models\InventoryMovement;
use App\Models\Medicine;
use App\Models\StockBalance;
use App\Support\Quantity;
use Illuminate\Validation\ValidationException;

class DailyPhysicalCountService
{
    public function counts(int $medicineId, array $input): array
    {
        $counts = [];
        foreach ($input as $batchId => $quantity) {
            if ($quantity === '' || $quantity === null) continue;
            if (filter_var($quantity, FILTER_VALIDATE_INT) === false || (int) $quantity < 0 || (int) $quantity > 2147483647) {
                throw ValidationException::withMessages(['items' => 'Jumlah fisik batch harus bilangan bulat 0 atau lebih.']);
            }
            if (! ctype_digit((string) $batchId) || ! Batch::whereKey($batchId)->where('medicine_id', $medicineId)->exists()) {
                throw ValidationException::withMessages(['items' => 'Batch hitungan tidak sesuai dengan obat yang diperiksa.']);
            }
            $counts[(int) $batchId] = (int) $quantity;
        }
        ksort($counts);

        return $counts;
    }

    public function snapshot(int $medicineId, int $locationId, bool $lock = false): array
    {
        $medicine = Medicine::with('unit')->findOrFail($medicineId);
        $totalQuery = StockBalance::where('medicine_id', $medicineId)->where('location_id', $locationId);
        $batchQuery = BatchStockBalance::with('batch')->where('medicine_id', $medicineId)->where('location_id', $locationId)->orderBy('batch_id');
        if ($lock) {
            $totalQuery->lockForUpdate();
            $batchQuery->lockForUpdate();
        }
        $total = $totalQuery->first();
        $batches = $batchQuery->get();
        if ($batches->contains(fn ($row) => $row->batch?->medicine_id !== $medicineId)) {
            throw ValidationException::withMessages(['items' => 'Identitas obat pada rincian batch tidak sesuai. Periksa rekonsiliasi stok.']);
        }

        return [
            'quantity' => Quantity::input($total?->quantity ?? 0),
            'batches' => $batches->mapWithKeys(fn ($row) => [$row->batch_id => Quantity::input($row->quantity)])->all(),
            'unit_id' => $medicine->unit_id,
            'unit' => $medicine->unit?->name,
            'last_movement_id' => (int) (InventoryMovement::where('medicine_id', $medicineId)
                ->where(fn ($query) => $query->where('source_location_id', $locationId)->orWhere('destination_location_id', $locationId))
                ->when($lock, fn ($query) => $query->lockForUpdate())->orderByDesc('id')->value('id') ?? 0),
        ];
    }

    public function validateTarget(object $item, int $quantity, string $unit, array $counts, array $snapshot): void
    {
        $key = 'items.'.$item->id.'.batch_counts';
        $total = (float) $snapshot['quantity'];
        $batchTotal = round(array_sum(array_map('floatval', $snapshot['batches'])), 4);
        if ($total !== $batchTotal) {
            throw ValidationException::withMessages([$key => $item->name.': total stok dan rincian batch sudah berbeda. Rekonsiliasi dahulu sebelum menyetujui hitungan baru.']);
        }
        if ($snapshot['unit'] && mb_strtolower(trim($unit)) !== mb_strtolower(trim($snapshot['unit']))) {
            throw ValidationException::withMessages(['items.'.$item->id.'.unit' => $item->name.': gunakan satuan stok '.$snapshot['unit'].'.']);
        }
        if ($quantity > 0 && ! $snapshot['unit']) {
            throw ValidationException::withMessages(['items.'.$item->id.'.unit' => $item->name.': lengkapi satuan stok di Data Obat sebelum mencatat stok fisik.']);
        }
        if (! $counts && (float) $quantity === $total) return;
        if (! $counts) {
            throw ValidationException::withMessages([$key => $item->name.': isi hasil hitung per batch agar selisih stok tidak dialokasikan secara tebakan. Jika batch belum ada, catat melalui stok awal atau penerimaan.']);
        }
        foreach ($snapshot['batches'] as $batchId => $before) {
            if ((float) $before > 0 && ! array_key_exists($batchId, $counts)) {
                throw ValidationException::withMessages([$key => $item->name.': isi semua batch yang memiliki stok; isi 0 jika batch benar-benar kosong.']);
            }
        }
        if (array_sum($counts) !== $quantity) {
            throw ValidationException::withMessages([$key => $item->name.': jumlah hasil hitung seluruh batch harus sama dengan jumlah fisik obat.']);
        }
    }

    public function apply(array $check, object $item, int $userId): void
    {
        $saved = json_decode($item->stock_snapshot ?? 'null', true);
        if (! is_array($saved) || $item->quantity === null) {
            throw ValidationException::withMessages(['items' => 'Laporan belum memiliki rincian pemeriksaan terbaru. Kembalikan untuk koreksi lalu kirim ulang.']);
        }
        $current = $this->snapshot($item->medicine_id, $check['location_id'], true);
        if ($saved !== $current) {
            throw ValidationException::withMessages(['items' => $item->name.': stok atau satuan berubah setelah laporan dikirim. Kembalikan untuk koreksi dan hitung ulang sebelum menyetujui.']);
        }
        $counts = $this->counts($item->medicine_id, json_decode($item->batch_counts ?? '[]', true));
        $this->validateTarget($item, (int) $item->quantity, (string) $item->unit, $counts, $current);
        $stock = app(InventoryStockService::class);
        $difference = round((float) $item->quantity - (float) $current['quantity'], 4);
        $balance = $stock->add($item->medicine_id, $check['location_id'], $difference);
        $balance->update(['last_counted_at' => now()]);
        $changes = [];
        foreach ($counts as $batchId => $quantity) {
            $delta = round($quantity - (float) ($current['batches'][$batchId] ?? 0), 4);
            if ($delta == 0.0) continue;
            $stock->addBatch($item->medicine_id, $batchId, $check['location_id'], $delta);
            $changes[] = ['batch_id' => $batchId, 'delta' => $delta];
        }
        // Keep a zero-value trace for unchanged counts; changed counts use only batch-linked movements.
        foreach ($changes ?: [['batch_id' => null, 'delta' => 0.0]] as $change) {
            $delta = $change['delta'];
            InventoryMovement::create([
                'type' => 'physical_count', 'reference' => 'CHECK-'.$check['id'],
                'medicine_id' => $item->medicine_id, 'batch_id' => $change['batch_id'],
                'source_location_id' => $delta < 0 ? $check['location_id'] : null,
                'destination_location_id' => $delta >= 0 ? $check['location_id'] : null,
                'quantity' => abs($delta), 'occurred_at' => now(), 'created_by' => $userId,
                'notes' => 'Hasil hitung fisik per batch; total sebelumnya '.Quantity::input($current['quantity']).', hasil hitung '.$item->quantity.'.',
            ]);
        }
    }
}
