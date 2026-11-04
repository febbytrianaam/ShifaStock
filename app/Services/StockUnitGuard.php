<?php

namespace App\Services;

use App\Models\Medicine;
use App\Models\Unit;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StockUnitGuard
{
    public static function hasHistory(int $medicineId, bool $ignoreUncountedDraftNightlyItems = false): bool
    {
        foreach (['inventory_movements', 'stock_balances', 'batch_stock_balances', 'inventory_receipt_items', 'stock_opname_items'] as $table) {
            if (DB::table($table)->where('medicine_id', $medicineId)->exists()) return true;
        }

        return DB::table('nightly_check_items as i')->join('nightly_checks as c', 'c.id', '=', 'i.nightly_check_id')
            ->where('i.medicine_id', $medicineId)
            ->when($ignoreUncountedDraftNightlyItems, fn ($query) => $query->where(fn ($history) => $history
                ->where('c.status', '<>', 'draft')->orWhereNotNull('i.quantity')
                ->orWhereNotNull('i.stock_snapshot')->orWhere(fn ($counts) => $counts
                    ->whereNotNull('i.batch_counts')->whereNotIn('i.batch_counts', ['[]', '{}']))
                ->orWhereRaw("NULLIF(TRIM(i.unit), '') IS NOT NULL")))
            ->exists();
    }

    public static function medicine(Medicine $medicine): void
    {
        $initialCompletion = $medicine->getRawOriginal('unit_id') === null && $medicine->unit_id !== null;
        if ($medicine->isDirty('unit_id') && self::hasHistory($medicine->id, $initialCompletion)) {
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
