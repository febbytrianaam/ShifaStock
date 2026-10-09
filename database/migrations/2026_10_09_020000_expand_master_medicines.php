<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('medicines', function (Blueprint $table) {
            $table->string('original_name')->nullable()->after('name');
            $table->string('generic_name')->nullable()->after('original_name');
            $table->string('strength')->nullable()->after('generic_name');
            $table->string('stock_unit_suggestion')->nullable()->after('unit_id');
            $table->string('purchase_unit_name')->nullable()->after('stock_unit_suggestion');
            $table->text('package_reference')->nullable()->after('purchase_unit_name');
            $table->decimal('conversion_candidate', 16, 4)->nullable()->after('package_reference');
            $table->string('conversion_type')->nullable()->after('conversion_candidate');
            $table->string('verification_status')->default('belum_diverifikasi')->after('is_active');
            $table->text('verification_note')->nullable()->after('verification_status');
            $table->string('research_priority')->nullable()->after('verification_note');
            $table->string('evidence_status')->nullable()->after('research_priority');
            $table->string('kfa_code')->nullable()->after('evidence_status');
            $table->string('bpom_nie')->nullable()->after('kfa_code');
            $table->text('source_reference_1')->nullable()->after('bpom_nie');
            $table->text('source_reference_2')->nullable()->after('source_reference_1');
            $table->foreignId('imported_by')->nullable()->after('source_reference_2')->constrained('users')->nullOnDelete();
            $table->timestamp('imported_at')->nullable()->after('imported_by');
            $table->foreignId('verified_by')->nullable()->after('imported_at')->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable()->after('verified_by');
        });

        Schema::create('medicine_audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('medicine_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action');
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('medicine_audits');
        Schema::table('medicines', function (Blueprint $table) {
            $table->dropConstrainedForeignId('verified_by');
            $table->dropConstrainedForeignId('imported_by');
            $table->dropColumn([
                'original_name',
                'generic_name',
                'strength',
                'stock_unit_suggestion',
                'purchase_unit_name',
                'package_reference',
                'conversion_candidate',
                'conversion_type',
                'verification_status',
                'verification_note',
                'research_priority',
                'evidence_status',
                'kfa_code',
                'bpom_nie',
                'source_reference_1',
                'source_reference_2',
                'imported_at',
                'verified_at',
            ]);
        });
    }
};
