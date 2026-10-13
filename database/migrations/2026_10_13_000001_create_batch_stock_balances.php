<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('batch_stock_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('medicine_id')->constrained()->restrictOnDelete();
            $table->foreignId('batch_id')->constrained()->restrictOnDelete();
            $table->foreignId('location_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity', 16, 4)->default(0);
            $table->timestamps();
            $table->unique(['batch_id', 'location_id']);
            $table->index(['medicine_id', 'location_id']);
        });

        if (Schema::hasTable('inventory_movements')) {
            $balances = [];

            DB::table('inventory_movements')
                ->whereNotNull('batch_id')
                ->orderBy('occurred_at')
                ->orderBy('id')
                ->get()
                ->each(function ($movement) use (&$balances) {
                    foreach ([
                        $movement->source_location_id => -1,
                        $movement->destination_location_id => 1,
                    ] as $locationId => $direction) {
                        if (! $locationId) {
                            continue;
                        }

                        $key = $movement->batch_id.'-'.$locationId;
                        $balances[$key] ??= [
                            'medicine_id' => $movement->medicine_id,
                            'batch_id' => $movement->batch_id,
                            'location_id' => $locationId,
                            'quantity' => 0,
                        ];
                        $balances[$key]['quantity'] += ((float) $movement->quantity) * $direction;
                    }
                });

            foreach ($balances as $balance) {
                if ($balance['quantity'] <= 0) {
                    continue;
                }

                DB::table('batch_stock_balances')->insert([
                    'medicine_id' => $balance['medicine_id'],
                    'batch_id' => $balance['batch_id'],
                    'location_id' => $balance['location_id'],
                    'quantity' => $balance['quantity'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('batch_stock_balances');
    }
};
