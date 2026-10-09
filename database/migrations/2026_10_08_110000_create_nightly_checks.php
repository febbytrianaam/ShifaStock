<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nightly_checks', function (Blueprint $t) {
            $t->id();
            $t->date('date');
            $t->foreignId('location_id')->constrained()->restrictOnDelete();
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $t->string('status')->default('draft');
            $t->text('review_note')->nullable();
            $t->foreignId('reviewed_by')->nullable()->constrained('users')->restrictOnDelete();
            $t->timestamp('submitted_at')->nullable();
            $t->timestamp('approved_at')->nullable();
            $t->timestamps();
            $t->unique(['date', 'location_id']);
        });
        Schema::create('nightly_check_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('nightly_check_id')->constrained()->cascadeOnDelete();
            $t->foreignId('medicine_id')->constrained()->restrictOnDelete();
            $t->string('code');
            $t->string('name');
            $t->string('unit')->nullable();
            $t->unsignedInteger('quantity')->nullable();
            $t->boolean('is_low')->default(false);
            $t->unsignedInteger('requested_quantity')->nullable();
            $t->text('note')->nullable();
            $t->unique(['nightly_check_id', 'medicine_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nightly_check_items');
        Schema::dropIfExists('nightly_checks');
    }
};
