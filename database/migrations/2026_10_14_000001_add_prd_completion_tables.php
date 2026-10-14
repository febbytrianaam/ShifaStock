<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_movements', function (Blueprint $table) {
            $table->foreignId('reversed_movement_id')->nullable()->after('id')->constrained('inventory_movements')->restrictOnDelete();
            $table->index('reversed_movement_id');
        });

        Schema::create('system_settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        Schema::create('stock_opname_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->string('period', 7)->index();
            $table->foreignId('location_id')->constrained()->restrictOnDelete();
            $table->dateTime('cutoff_at');
            $table->string('status')->default('draft')->index();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('finalized_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->dateTime('finalized_at')->nullable();
            $table->timestamps();
            $table->unique(['period', 'location_id']);
        });

        Schema::create('stock_opname_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_opname_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('medicine_id')->constrained()->restrictOnDelete();
            $table->foreignId('batch_id')->nullable()->constrained()->restrictOnDelete();
            $table->decimal('system_quantity', 16, 4)->default(0);
            $table->decimal('physical_quantity', 16, 4)->nullable();
            $table->decimal('difference', 16, 4)->nullable();
            $table->string('unit_name')->nullable();
            $table->text('reason')->nullable();
            $table->timestamps();
            $table->unique(['stock_opname_session_id', 'medicine_id', 'batch_id'], 'opname_item_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_opname_items');
        Schema::dropIfExists('stock_opname_sessions');
        Schema::dropIfExists('system_settings');
        Schema::table('inventory_movements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reversed_movement_id');
        });
    }
};
