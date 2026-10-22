<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('nightly_checks', function (Blueprint $table) {
            $table->unsignedBigInteger('draft_revision')->default(0);
        });
        Schema::table('nightly_check_items', function (Blueprint $table) {
            $table->foreignId('last_edited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('last_edited_name')->nullable();
            $table->timestamp('last_edited_at')->nullable();
        });
        Schema::create('nightly_check_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('nightly_check_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_name');
            $table->string('action', 24);
            $table->json('metadata')->nullable();
            $table->timestamp('created_at');
            $table->index(['nightly_check_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nightly_check_activities');
        Schema::table('nightly_check_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('last_edited_by');
            $table->dropColumn(['last_edited_name', 'last_edited_at']);
        });
        Schema::table('nightly_checks', function (Blueprint $table) {
            $table->dropColumn('draft_revision');
        });
    }
};
