<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminUserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_administrator_can_manage_users_and_reset_passwords(): void
    {
        $admin = User::factory()->create(['role' => Role::Administrator]);
        $petugas = User::factory()->create(['role' => Role::Pharmacy]);

        $this->actingAs($petugas)->get('/admin/pengguna')->assertForbidden();

        $this->actingAs($admin)->get('/admin/pengguna')->assertOk()->assertSee('Pengguna');
        $this->post('/admin/pengguna', [
            'name' => 'Kepala Apoteker',
            'username' => 'kepala-baru',
            'email' => 'kepala@example.test',
            'role' => Role::Pharmacist->value,
            'is_active' => '1',
            'password' => 'rahasia1',
            'password_confirmation' => 'rahasia1',
        ])->assertRedirect('/admin/pengguna');

        $created = User::where('username', 'kepala-baru')->firstOrFail();
        $this->assertTrue(Hash::check('rahasia1', $created->password));

        $this->put('/admin/pengguna/'.$created->id, [
            'name' => 'Kepala Apoteker',
            'username' => 'kepala-baru',
            'email' => 'kepala@example.test',
            'role' => Role::Pharmacist->value,
            'is_active' => '1',
            'password' => 'rahasia2',
            'password_confirmation' => 'rahasia2',
        ])->assertRedirect('/admin/pengguna');

        $this->assertTrue(Hash::check('rahasia2', $created->fresh()->password));
    }
}
