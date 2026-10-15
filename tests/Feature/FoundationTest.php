<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Livewire\MedicineIndex;
use App\Models\Medicine;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Tests\TestCase;

class FoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_and_dashboard_render(): void
    {
        $this->get('/login')->assertOk()->assertSee('Klinik Shifa Medika');
        $user = User::factory()->create(['role' => Role::Pharmacy]);
        $this->post('/login', ['username' => $user->username, 'password' => 'password'])->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);
        $this->get('/dashboard')->assertOk();
        $this->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_inactive_accounts_cannot_login_or_keep_using_an_existing_session(): void
    {
        $user = User::factory()->create(['is_active' => false]);
        $this->post('/login', ['username' => $user->username, 'password' => 'password'])->assertSessionHasErrors('username');
        $this->assertGuest();
        $this->actingAs($user)->get('/dashboard')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_pharmacy_can_read_but_cannot_create_or_change_master_data(): void
    {
        $user = User::factory()->create(['role' => Role::Pharmacy]);
        $unit = Unit::create(['name' => 'Tablet']);
        $medicine = Medicine::create(['code' => 'OBT-001', 'name' => 'Paracetamol', 'dosage_form' => 'Tablet', 'unit_id' => $unit->id]);
        $this->actingAs($user)->get('/obat')->assertOk()->assertSee('Paracetamol');
        Livewire::actingAs($user)->test(MedicineIndex::class)->call('save')->assertForbidden();
        Livewire::actingAs($user)->test(MedicineIndex::class)->call('toggleActive', $medicine->id)->assertForbidden();
        $this->assertTrue($medicine->fresh()->is_active);
        $this->assertFalse(Gate::forUser($user)->allows('finalize-opname'));
    }

    public function test_pharmacist_can_create_unique_medicine_and_deactivate_without_deleting(): void
    {
        $user = User::factory()->create(['role' => Role::Pharmacist]);
        $unit = Unit::create(['name' => 'Tablet']);
        $component = Livewire::actingAs($user)->test(MedicineIndex::class)
            ->set('code', 'OBT-001')->set('name', 'Paracetamol')->set('dosage_form', 'Tablet')
            ->set('unit_id', (string) $unit->id)->call('save')->assertHasNoErrors();
        $medicine = Medicine::sole();
        $component->set('code', 'OBT-001')->set('name', 'Duplikat')->set('dosage_form', 'Tablet')
            ->set('unit_id', (string) $unit->id)->call('save')->assertHasErrors(['code' => 'unique']);
        $component->call('toggleActive', $medicine->id);
        $this->assertDatabaseCount('medicines', 1);
        $this->assertFalse($medicine->fresh()->is_active);
        $this->assertTrue(Gate::forUser($user)->allows('finalize-opname'));
    }

    public function test_imported_names_are_visible_but_cannot_activate_without_verified_units(): void
    {
        $user = User::factory()->create(['role' => Role::Pharmacist]);
        $medicine = Medicine::create([
            'code' => 'ALLOPURNOLBPJS', 'name' => 'ALLOPURINOL 100 MG',
            'unit_id' => null, 'dosage_form' => null, 'is_active' => false,
            'import_source' => ['file' => 'obat.xlsx', 'row' => 90],
        ]);
        $this->actingAs($user)->get('/obat')->assertOk()->assertSee('ALLOPURINOL 100 MG')->assertSee('Belum Diverifikasi');
        Livewire::actingAs($user)->test(MedicineIndex::class)->call('toggleActive', $medicine->id);
        $this->assertFalse($medicine->fresh()->is_active);
        $this->assertNull($medicine->fresh()->unit_id);
        $this->assertSame('obat.xlsx', $medicine->fresh()->import_source['file']);
    }

    public function test_administrator_is_not_given_operational_access(): void
    {
        $user = User::factory()->create(['role' => Role::Administrator]);
        $this->actingAs($user)->get('/dashboard')->assertOk();
        $this->get('/obat')->assertForbidden();
        Livewire::actingAs($user)->test(MedicineIndex::class)->assertForbidden();
        $this->assertTrue(Gate::forUser($user)->allows('manage-users'));
        $this->assertFalse(Gate::forUser($user)->allows('manage-medicines'));
    }

    public function test_login_is_rate_limited(): void
    {
        for ($attempt = 0; $attempt < 6; $attempt++) {
            $response = $this->post('/login', ['username' => 'missing', 'password' => 'incorrect']);
        }
        $response->assertSessionHasErrors('username');
        $this->assertStringContainsString('Terlalu banyak', session('errors')->first('username'));
    }
}
