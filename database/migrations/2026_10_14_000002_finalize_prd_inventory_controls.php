<?php

use App\Enums\Role;
use App\Support\Permissions;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_receipts', function (Blueprint $table) {
            if (! Schema::hasColumn('inventory_receipts', 'destination_location_id')) {
                $table->foreignId('destination_location_id')
                    ->nullable()
                    ->after('counterparty')
                    ->constrained('locations')
                    ->restrictOnDelete();
            }
        });

        Schema::table('inventory_receipt_items', function (Blueprint $table) {
            if (! Schema::hasColumn('inventory_receipt_items', 'input_quantity')) {
                $table->decimal('input_quantity', 16, 4)->nullable()->after('quantity');
            }
            if (! Schema::hasColumn('inventory_receipt_items', 'input_unit_name')) {
                $table->string('input_unit_name')->nullable()->after('input_quantity');
            }
            if (! Schema::hasColumn('inventory_receipt_items', 'conversion_factor')) {
                $table->decimal('conversion_factor', 16, 4)->nullable()->after('input_unit_name');
            }
        });

        $warehouseId = DB::table('locations')->where('code', 'GDG')->value('id');
        if ($warehouseId) {
            DB::table('inventory_receipts')
                ->whereNull('destination_location_id')
                ->update(['destination_location_id' => $warehouseId]);
        }

        if (Schema::hasTable('role_permissions')) {
            foreach (Role::cases() as $role) {
                DB::table('role_permissions')->updateOrInsert(
                    ['role' => $role->value, 'permission' => Permissions::REVERSE_INVENTORY_MOVEMENTS],
                    [
                        'allowed' => in_array($role, [Role::Administrator, Role::Pharmacist], true),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ],
                );
            }
        }
    }

    public function down(): void
    {
        Schema::table('inventory_receipt_items', function (Blueprint $table) {
            if (Schema::hasColumn('inventory_receipt_items', 'conversion_factor')) {
                $table->dropColumn('conversion_factor');
            }
            if (Schema::hasColumn('inventory_receipt_items', 'input_unit_name')) {
                $table->dropColumn('input_unit_name');
            }
            if (Schema::hasColumn('inventory_receipt_items', 'input_quantity')) {
                $table->dropColumn('input_quantity');
            }
        });

        Schema::table('inventory_receipts', function (Blueprint $table) {
            if (Schema::hasColumn('inventory_receipts', 'destination_location_id')) {
                $table->dropConstrainedForeignId('destination_location_id');
            }
        });
    }
};
