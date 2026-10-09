<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('petugas_farmasi');
            $table->boolean('is_active')->default(true);
        });
        Schema::create('units', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });
        Schema::create('locations', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        Schema::create('medicines', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('dosage_form');
            $table->foreignId('unit_id')->constrained()->restrictOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        Schema::create('medicine_packages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('medicine_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->decimal('conversion_factor', 16, 4);
            $table->timestamps();
            $table->unique(['medicine_id', 'name']);
        });
        Schema::create('batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('medicine_id')->constrained()->restrictOnDelete();
            $table->string('number');
            $table->date('expiry_date')->nullable();
            $table->string('expiry_precision')->default('day');
            $table->timestamps();
            $table->unique(['medicine_id', 'number']);
        });
        Schema::create('stock_parameters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('medicine_id')->constrained()->restrictOnDelete();
            $table->foreignId('location_id')->constrained()->restrictOnDelete();
            $table->decimal('minimum_quantity', 16, 4)->nullable();
            $table->timestamps();
            $table->unique(['medicine_id', 'location_id']);
        });
    }

    public function down(): void
    {
        foreach (['stock_parameters', 'batches', 'medicine_packages', 'medicines', 'locations', 'units'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['role', 'is_active']));
    }
};
