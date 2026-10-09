<?php

namespace App\Console\Commands;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class CreateUser extends Command
{
    protected $signature = 'shifa:create-user {username} {--email=} {--name=} {--role=administrator}';

    protected $description = 'Buat akun individu; password dimasukkan secara tersembunyi.';

    public function handle(): int
    {
        $data = [
            'username' => $this->argument('username'),
            'email' => $this->option('email') ?: $this->argument('username').'@shifa.test',
            'name' => $this->option('name') ?: $this->ask('Nama pengguna'),
            'role' => $this->option('role'),
            'password' => $this->secret('Password (minimal 12 karakter)'),
        ];
        $validator = Validator::make($data, [
            'username' => ['required', 'alpha_dash:ascii', 'max:50', 'unique:users,username'],
            'email' => ['required', 'email', 'unique:users,email'],
            'name' => ['required', 'string', 'max:255'],
            'role' => ['required', Rule::enum(Role::class)],
            'password' => ['required', 'string', 'min:12'],
        ]);
        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }
        User::create([...$data, 'is_active' => true]);
        $this->info('Akun berhasil dibuat.');

        return self::SUCCESS;
    }
}
