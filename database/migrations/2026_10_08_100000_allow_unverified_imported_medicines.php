<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {
  Schema::table('medicines', function (Blueprint $table) {
   $table->string('dosage_form')->nullable()->change();
   $table->unsignedBigInteger('unit_id')->nullable()->change();
   $table->json('import_source')->nullable();
  });
 }
 public function down(): void {
  Schema::table('medicines', fn (Blueprint $table) => $table->dropColumn('import_source'));
 }
};
