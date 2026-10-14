<?php

namespace App\Services;

use App\Models\BatchStockBalance;
use App\Models\InventoryMovement;
use App\Models\StockBalance;
use Illuminate\Validation\ValidationException;

class InventoryStockService
{
    public function add(int $medicineId, int $locationId, float $quantity): StockBalance
    {
        StockBalance::firstOrCreate(
            ['medicine_id' => $medicineId, 'location_id' => $locationId],
            ['quantity' => 0],
        );
        $balance = StockBalance::where('medicine_id', $medicineId)
            ->where('location_id', $locationId)
            ->lockForUpdate()
            ->firstOrFail();
        $balance->quantity = (float) $balance->quantity + $quantity;
        if ((float) $balance->quantity < 0) {
            throw ValidationException::withMessages(['quantity' => 'Stok sumber tidak mencukupi.']);
        }
        $balance->save();

        return $balance;
    }

    public function addBatch(int $medicineId, int $batchId, int $locationId, float $quantity): BatchStockBalance
    {
        BatchStockBalance::firstOrCreate(
            ['batch_id' => $batchId, 'location_id' => $locationId],
            ['medicine_id' => $medicineId, 'quantity' => 0],
        );

        $balance = BatchStockBalance::where('batch_id', $batchId)
            ->where('location_id', $locationId)
            ->lockForUpdate()
            ->firstOrFail();

        $balance->quantity = (float) $balance->quantity + $quantity;
        if ((float) $balance->quantity < 0) {
            throw ValidationException::withMessages(['quantity' => 'Stok batch sumber tidak mencukupi.']);
        }
        $balance->save();

        return $balance;
    }

    public function fefoAllocations(int $medicineId, int $locationId, float $quantity): array
    {
        $remaining = $quantity;
        $allocations = [];

        $balances = BatchStockBalance::with('batch')
            ->where('medicine_id', $medicineId)
            ->where('location_id', $locationId)
            ->where('quantity', '>', 0)
            ->whereHas('batch', fn ($query) => $query
                ->whereNull('expiry_date')
                ->orWhereDate('expiry_date', '>=', now('Asia/Jakarta')->toDateString()))
            ->lockForUpdate()
            ->get()
            ->sortBy(fn (BatchStockBalance $balance) => [
                $balance->batch?->expiry_date?->timestamp ?? PHP_INT_MAX,
                $balance->batch?->number ?? '',
                $balance->id,
            ]);

        foreach ($balances as $balance) {
            if ($remaining <= 0) {
                break;
            }

            $allocated = min((float) $balance->quantity, $remaining);
            if ($allocated <= 0) {
                continue;
            }

            $allocations[] = ['batch_id' => $balance->batch_id, 'quantity' => $allocated];
            $remaining -= $allocated;
        }

        if ($remaining > 0) {
            throw ValidationException::withMessages(['quantity' => 'Stok batch layak pakai tidak mencukupi untuk FEFO. Pilih batch yang sesuai atau cek stok fisik.']);
        }

        return $allocations;
    }

    public function setPhysicalCount(array $check, object $item, int $userId): void
    {
        StockBalance::firstOrCreate(
            ['medicine_id' => $item->medicine_id, 'location_id' => $check['location_id']],
            ['quantity' => 0],
        );
        $balance = StockBalance::where('medicine_id', $item->medicine_id)
            ->where('location_id', $check['location_id'])
            ->lockForUpdate()
            ->firstOrFail();
        $before = (float) $balance->quantity;
        $after = (float) $item->quantity;
        $difference = $after - $before;
        $balance->update(['quantity' => $after, 'last_counted_at' => now()]);

        InventoryMovement::create([
            'type' => 'physical_count',
            'reference' => 'CHECK-'.$check['id'],
            'medicine_id' => $item->medicine_id,
            'source_location_id' => $difference < 0 ? $check['location_id'] : null,
            'destination_location_id' => $difference >= 0 ? $check['location_id'] : null,
            'quantity' => abs($difference),
            'occurred_at' => now(),
            'notes' => 'Penyesuaian hasil pemeriksaan fisik; stok sebelumnya '.$before.', hasil hitung '.$after.'.',
            'created_by' => $userId,
        ]);
    }
}
