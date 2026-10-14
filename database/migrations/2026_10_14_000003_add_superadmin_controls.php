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
        Schema::table('units', function (Blueprint $table) {
            if (! Schema::hasColumn('units', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('name');
            }
        });

        if (Schema::hasTable('role_permissions')) {
            foreach (Role::cases() as $role) {
                foreach ([Permissions::MANAGE_SYSTEM_REFERENCES, Permissions::MANAGE_SYSTEM_SETTINGS] as $permission) {
                    DB::table('role_permissions')->updateOrInsert(
                        ['role' => $role->value, 'permission' => $permission],
                        [
                            'allowed' => $role === Role::Administrator,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ],
                    );
                }
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('role_permissions')) {
            DB::table('role_permissions')
                ->whereIn('permission', [Permissions::MANAGE_SYSTEM_REFERENCES, Permissions::MANAGE_SYSTEM_SETTINGS])
                ->delete();
        }

        Schema::table('units', function (Blueprint $table) {
            if (Schema::hasColumn('units', 'is_active')) {
                $table->dropColumn('is_active');
            }
        });
    }
};
