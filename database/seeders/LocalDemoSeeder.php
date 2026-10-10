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

        $password = env('LOCAL_DEMO_PASSWORD') ?: $this->command?->secret('Password akun demo lokal');
        if (blank($password) || strlen($password) < 6) {
            throw new \RuntimeException('Set LOCAL_DEMO_PASSWORD di .env atau input password demo minimal 6 karakter saat diminta.');
        }

        $this->call(DatabaseSeeder::class);
        foreach (Role::cases() as $role) {
            $email = $role->value.'@shifa.test';
            $username = match ($role) {
                Role::Administrator => 'admin',
                Role::Pharmacy => 'petugas',
                Role::Pharmacist => 'kepala',
                Role::Guest => 'guest',
            };
            User::updateOrCreate(['email' => $email], ['username' => $username, 'name' => $role->label().' (Demo)', 'role' => $role, 'is_active' => true, 'password' => $password]);
            $this->command?->line($username.' | password tidak ditampilkan');
        }
    }
}
