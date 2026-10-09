<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('medicine_id')->constrained()->restrictOnDelete();
            $table->foreignId('location_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity', 16, 4)->default(0);
            $table->timestamp('last_counted_at')->nullable();
            $table->timestamps();
            $table->unique(['medicine_id', 'location_id']);
        });

        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->id();
            $table->string('type');
            $table->string('reference')->index();
            $table->foreignId('medicine_id')->constrained()->restrictOnDelete();
            $table->foreignId('batch_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('source_location_id')->nullable()->constrained('locations')->restrictOnDelete();
            $table->foreignId('destination_location_id')->nullable()->constrained('locations')->restrictOnDelete();
            $table->decimal('quantity', 16, 4);
            $table->dateTime('occurred_at');
            $table->string('counterparty')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        // Recover the latest approved physical count when this migration is
        // installed over an existing prototype database.
        if (Schema::hasTable('nightly_checks')) {
            $latestChecks = DB::table('nightly_checks')
                ->where('status', 'approved')
                ->orderByDesc('date')
                ->orderByDesc('id')
                ->get()
                ->unique('location_id');

            foreach ($latestChecks as $check) {
                foreach (DB::table('nightly_check_items')->where('nightly_check_id', $check->id)->whereNotNull('quantity')->get() as $item) {
                    DB::table('stock_balances')->updateOrInsert(
                        ['medicine_id' => $item->medicine_id, 'location_id' => $check->location_id],
                        ['quantity' => $item->quantity, 'last_counted_at' => $check->approved_at, 'created_at' => now(), 'updated_at' => now()],
                    );
                }
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_movements');
        Schema::dropIfExists('stock_balances');
    }
};
