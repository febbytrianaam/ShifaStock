<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class LocalDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local')) {
            throw new \RuntimeException('Akun demo hanya boleh dibuat pada environment local.');
        }
        $this->call(DatabaseSeeder::class);
        foreach (Role::cases() as $role) {
            $email = $role->value.'@shifa.test';
            $username = match ($role) {
                Role::Administrator => 'admin',
                Role::Pharmacy => 'petugas',
                Role::Pharmacist => 'kepala',
            };
            User::updateOrCreate(['email' => $email], ['username' => $username, 'name' => $role->label().' (Demo)', 'role' => $role, 'is_active' => true, 'password' => '123']);
            $this->command?->line($username.' | 123');
        }
    }
}
