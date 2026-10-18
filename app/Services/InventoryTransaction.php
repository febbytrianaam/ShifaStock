<?php

namespace App\Services;

use Closure;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class InventoryTransaction
{
    public static function run(Closure $callback, int $attempts = 3): mixed
    {
        return DB::transaction(function () use ($callback) {
            // Lock before any stock reads, including missing balance rows and FEFO selection.
            if (! DB::table('inventory_write_locks')->where('id', 1)->lockForUpdate()->first()) {
                throw new RuntimeException('Pengunci transaksi stok belum tersedia. Jalankan migrasi aplikasi.');
            }

            return $callback();
        }, $attempts);
    }
}
