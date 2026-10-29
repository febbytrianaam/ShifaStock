<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('issue_import_policies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('location_id')->unique()->constrained()->restrictOnDelete();
            $table->dateTime('starts_at');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
        Schema::create('issue_import_mappings', function (Blueprint $table) {
            $table->id();
            $table->string('source_code', 100);
            $table->string('source_unit', 50);
            $table->foreignId('medicine_id')->constrained()->restrictOnDelete();
            $table->foreignId('unit_id')->constrained()->restrictOnDelete();
            $table->decimal('factor', 16, 4);
            $table->foreignId('confirmed_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['source_code', 'source_unit']);
        });
        Schema::create('issue_imports', function (Blueprint $table) {
            $table->id();
            $table->char('file_hash', 64)->index();
            $table->foreignId('location_id')->constrained()->restrictOnDelete();
            $table->dateTime('starts_at');
            $table->string('status', 20)->default('draft');
            $table->unsignedInteger('row_count');
            $table->unsignedInteger('sale_count');
            $table->unsignedInteger('compound_count');
            $table->dateTime('period_start');
            $table->dateTime('period_end');
            $table->longText('payload')->nullable();
            $table->unsignedInteger('posted_count')->default(0);
            $table->unsignedInteger('duplicate_count')->default(0);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('posted_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->dateTime('posted_at')->nullable();
            $table->timestamps();
        });
        Schema::create('issue_import_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('issue_import_id')->constrained()->restrictOnDelete();
            // Identity belongs to the source transaction, not the uploaded file or stock location.
            $table->char('source_key', 64)->unique();
            $table->char('fingerprint', 64);
            $table->foreignId('medicine_id')->constrained()->restrictOnDelete();
            $table->foreignId('unit_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity', 16, 4);
            $table->dateTime('occurred_at');
            $table->timestamps();
        });
        Schema::create('issue_import_line_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('issue_import_line_id')->constrained()->restrictOnDelete();
            $table->foreignId('inventory_movement_id')->unique()->constrained()->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('issue_import_line_movements');
        Schema::dropIfExists('issue_import_lines');
        Schema::dropIfExists('issue_imports');
        Schema::dropIfExists('issue_import_mappings');
        Schema::dropIfExists('issue_import_policies');
    }
};
