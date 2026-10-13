<?php

use App\Enums\Role;
use App\Support\Permissions;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('role_permissions')) {
            return;
        }

        foreach (Role::cases() as $role) {
            foreach ([Permissions::VIEW_RETURNS, Permissions::VIEW_STOCK_CARD, Permissions::VIEW_SPECIAL_ADJUSTMENTS] as $permission) {
                DB::table('role_permissions')->updateOrInsert(
                    ['role' => $role->value, 'permission' => $permission],
                    ['allowed' => Permissions::defaults()[$role->value][$permission] ?? false, 'created_at' => now(), 'updated_at' => now()],
                );
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('role_permissions')) {
            return;
        }

        DB::table('role_permissions')
            ->whereIn('permission', [Permissions::VIEW_RETURNS, Permissions::VIEW_STOCK_CARD, Permissions::VIEW_SPECIAL_ADJUSTMENTS])
            ->delete();
    }
};
