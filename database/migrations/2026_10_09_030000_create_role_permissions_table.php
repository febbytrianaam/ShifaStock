<?php

use App\Support\Permissions;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('role_permissions', function (Blueprint $table) {
            $table->id();
            $table->string('role', 50);
            $table->string('permission', 80);
            $table->boolean('allowed')->default(false);
            $table->timestamps();
            $table->unique(['role', 'permission']);
        });

        $now = now();
        $rows = [];
        foreach (Permissions::defaults() as $role => $permissions) {
            foreach ($permissions as $permission => $allowed) {
                $rows[] = [
                    'role' => $role,
                    'permission' => $permission,
                    'allowed' => $allowed,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        DB::table('role_permissions')->insert($rows);
    }

    public function down(): void
    {
        Schema::dropIfExists('role_permissions');
    }
};
