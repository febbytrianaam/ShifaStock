<?php

namespace App\Services;

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
            'notes' => 'Penyesuaian hasil pemeriksaan fisik; saldo sebelumnya '.$before.', hasil hitung '.$after.'.',
            'created_by' => $userId,
        ]);
    }
}
