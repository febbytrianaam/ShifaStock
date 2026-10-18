<?php

namespace App\Services;

use App\Models\Medicine;
use App\Models\Unit;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StockUnitGuard
{
    public static function hasHistory(int $medicineId): bool
    {
        foreach (['inventory_movements', 'stock_balances', 'batch_stock_balances', 'inventory_receipt_items', 'nightly_check_items', 'stock_opname_items'] as $table) {
            if (DB::table($table)->where('medicine_id', $medicineId)->exists()) return true;
        }

        return false;
    }

    public static function medicine(Medicine $medicine): void
    {
        if ($medicine->isDirty('unit_id') && self::hasHistory($medicine->id)) {
            throw ValidationException::withMessages(['unit_id' => 'Satuan stok tidak dapat diganti atau dikosongkan karena obat sudah memiliki stok, draf, atau riwayat transaksi. Perubahan kemasan beli tetap dapat diatur tanpa mengubah satuan stok.']);
        }
    }

    public static function unit(Unit $unit): void
    {
        if (! $unit->isDirty('name')) return;
        foreach (Medicine::where('unit_id', $unit->id)->pluck('id') as $medicineId) {
            if (self::hasHistory($medicineId)) {
                throw ValidationException::withMessages(['name' => 'Nama satuan tidak dapat diubah karena sudah digunakan pada obat dengan stok atau riwayat. Buat satuan baru untuk penggunaan berikutnya.']);
            }
        }
    }
}
