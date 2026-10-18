<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_opname_sessions', fn (Blueprint $table) => $table->json('stock_snapshot')->nullable());
    }

    public function down(): void
    {
        Schema::table('stock_opname_sessions', fn (Blueprint $table) => $table->dropColumn('stock_snapshot'));
    }
};
