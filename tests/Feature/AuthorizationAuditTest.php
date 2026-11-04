<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use App\Support\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class AuthorizationAuditTest extends TestCase
{
    use RefreshDatabase;

    private function grant(Role $role, string $permission): void
    {
        DB::table('role_permissions')->updateOrInsert(['role' => $role->value, 'permission' => $permission], ['allowed' => true]);
    }

    private function details(User $user): array
    {
        return ['name' => $user->name, 'username' => $user->username, 'email' => $user->email,
            'role' => $user->role->value, 'is_active' => 1];
    }

    public function test_delegated_user_manager_cannot_create_or_promote_an_administrator(): void
    {
        $delegate = User::factory()->create(['role' => Role::Pharmacy]);
        $target = User::factory()->create(['role' => Role::Pharmacist]);
        $this->grant(Role::Pharmacy, Permissions::MANAGE_USERS);
        $password = Str::random(24);
        $this->actingAs($delegate)->post(route('admin.users.store'), [
            'name' => 'Simulasi', 'username' => 'simulasi-admin', 'email' => 'simulasi@example.test',
            'role' => Role::Administrator->value, 'password' => $password, 'password_confirmation' => $password,
        ])->assertForbidden();
        $this->put(route('admin.users.update', $target), array_replace($this->details($target), [
            'role' => Role::Administrator->value,
        ]))->assertForbidden();
        $this->assertDatabaseMissing('users', ['username' => 'simulasi-admin']);
        $this->assertSame(Role::Pharmacist, $target->fresh()->role);
    }

    public function test_delegated_user_manager_cannot_edit_deactivate_or_reset_an_administrator(): void
    {
        $delegate = User::factory()->create(['role' => Role::Pharmacy]);
        $admin = User::factory()->create(['role' => Role::Administrator]);
        $before = $admin->fresh()->getAttributes();
        $this->grant(Role::Pharmacy, Permissions::MANAGE_USERS);
        $this->actingAs($delegate)->get(route('admin.users.edit', $admin))->assertForbidden();
        $this->put(route('admin.users.update', $admin), $this->details($admin))->assertForbidden();
        $this->delete(route('admin.users.destroy', $admin))->assertForbidden();
        $this->assertSame($before, $admin->fresh()->getAttributes());
    }

    public function test_delegate_can_manage_operational_accounts_but_only_admin_can_reset_passwords(): void
    {
        $delegate = User::factory()->create(['role' => Role::Pharmacy]);
        $target = User::factory()->create(['role' => Role::Pharmacist]);
        $this->grant(Role::Pharmacy, Permissions::MANAGE_USERS);
        $password = Str::random(24);
        $this->actingAs($delegate)->put(route('admin.users.update', $target), array_replace($this->details($target), [
            'password' => $password, 'password_confirmation' => $password,
        ]))->assertForbidden();
        $this->put(route('admin.users.update', $target), array_replace($this->details($target), ['name' => 'Nama diperbaiki']))
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('Nama diperbaiki', $target->fresh()->name);
        $this->post(route('admin.users.store'), ['name' => 'Simulasi', 'username' => 'simulasi-petugas',
            'email' => 'petugas@example.test', 'role' => Role::Pharmacy->value, 'is_active' => 1,
            'password' => $password, 'password_confirmation' => $password])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check($password, User::where('username', 'simulasi-petugas')->sole()->password));
    }

    public function test_permission_editor_can_open_matrix_and_sidebar_without_user_management(): void
    {
        $editor = User::factory()->create(['role' => Role::Pharmacist]);
        $this->grant(Role::Pharmacist, Permissions::MANAGE_PERMISSIONS);
        $this->actingAs($editor)->get(route('admin.users.index'))->assertOk()
            ->assertViewHas('activeTab', 'permissions')->assertViewHas('users', null)
            ->assertSee('Hak Akses')->assertSee('data-tour-menu="admin.users.index"', false);
        $this->get(route('admin.users.index', ['tab' => 'permissions']))->assertOk();
        $this->get(route('admin.users.index', ['tab' => 'users']))->assertForbidden();
        $this->get(route('admin.users.create'))->assertForbidden();
        $this->get(route('admin.audit-logs.index'))->assertForbidden();
    }

    public function test_missing_permission_table_fails_closed_for_operational_permissions(): void
    {
        Schema::shouldReceive('hasTable')->once()->with('role_permissions')->andReturnFalse();
        $this->assertFalse(Permissions::roleCan(Role::Pharmacy, Permissions::OPERATE_INVENTORY));
        $this->assertTrue(Permissions::roleCan(Role::Administrator, Permissions::MANAGE_PERMISSIONS));
        $this->assertFalse(Permissions::roleCan(Role::Guest, Permissions::OPERATE_INVENTORY));
    }

    public function test_malformed_permission_matrix_is_rejected_without_changing_grants(): void
    {
        $admin = User::factory()->create(['role' => Role::Administrator]);
        $before = Permissions::matrix();
        $this->actingAs($admin)->postJson(route('admin.users.permissions.update'), [
            'permissions' => [Role::Pharmacy->value => 'invalid'],
        ])->assertUnprocessable()->assertJsonValidationErrors('permissions.'.Role::Pharmacy->value);
        $this->postJson(route('admin.users.permissions.update'), [
            'permissions' => [Role::Pharmacy->value => [Permissions::MANAGE_USERS => ['invalid']]],
        ])->assertUnprocessable()->assertJsonValidationErrors('permissions.'.Role::Pharmacy->value.'.'.Permissions::MANAGE_USERS);
        $this->assertSame($before, Permissions::matrix());
    }

    public function test_permission_query_failure_does_not_restore_default_grants(): void
    {
        Schema::shouldReceive('hasTable')->once()->with('role_permissions')->andReturnTrue();
        DB::partialMock()->shouldReceive('table')->with('role_permissions')->once()
            ->andThrow(new \RuntimeException('Simulated permission lookup failure'));
        $this->assertFalse(Permissions::roleCan(Role::Pharmacy, Permissions::OPERATE_INVENTORY));
    }

    public function test_password_reset_rotates_remember_token_and_rejects_old_authenticated_session(): void
    {
        $admin = User::factory()->create(['role' => Role::Administrator]);
        $target = User::factory()->create(['role' => Role::Pharmacy, 'remember_token' => Str::random(60)]);
        $oldToken = $target->remember_token;
        $guard = Auth::guard('web');
        $sessionName = $guard->getName();
        $oldHash = $guard->hashPasswordForCookie($target->password);
        $password = Str::random(24);
        $this->actingAs($admin)->put(route('admin.users.update', $target), $this->details($target) + [
            'password' => $password, 'password_confirmation' => $password,
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check($password, $target->fresh()->password));
        $this->assertNotSame($oldToken, $target->fresh()->remember_token);
        Auth::forgetGuards();
        $this->withSession([$sessionName => $target->id, 'password_hash_web' => $oldHash])
            ->getJson(route('dashboard'))->assertUnauthorized();
    }

    public function test_admin_self_password_reset_keeps_current_session_but_invalidates_other_sessions(): void
    {
        $admin = User::factory()->create(['role' => Role::Administrator]);
        $guard = Auth::guard('web');
        $sessionName = $guard->getName();
        $oldHash = $guard->hashPasswordForCookie($admin->password);
        $password = Str::random(24);
        $this->actingAs($admin)->withSession(['password_hash_web' => $oldHash])
            ->put(route('admin.users.update', $admin), $this->details($admin) + [
                'password' => $password, 'password_confirmation' => $password,
            ])->assertRedirect()->assertSessionHasNoErrors();
        $this->get(route('dashboard'))->assertOk();
        Auth::forgetGuards();
        $this->withSession([$sessionName => $admin->id, 'password_hash_web' => $oldHash])
            ->getJson(route('dashboard'))->assertUnauthorized();
    }
}
