<?php

use App\Enums\Role;
use App\Support\Permissions;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        foreach (Role::cases() as $role) {
            DB::table('role_permissions')->insertOrIgnore([
                'role' => $role->value, 'permission' => Permissions::VIEW_MONITORING_PARAMETERS,
                'allowed' => $role === Role::Guest || Permissions::roleCan($role, Permissions::MANAGE_MONITORING_PARAMETERS),
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        foreach (Permissions::all() as $permission) {
            DB::table('role_permissions')->updateOrInsert(['role' => Role::Guest->value, 'permission' => $permission], [
                'allowed' => Permissions::defaults()[Role::Guest->value][$permission] ?? false,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('role_permissions')->where('permission', Permissions::VIEW_MONITORING_PARAMETERS)->delete();
    }
};
