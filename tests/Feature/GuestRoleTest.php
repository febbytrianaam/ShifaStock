<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Livewire\MedicineIndex;
use App\Models\Location;
use App\Models\Medicine;
use App\Models\StockParameter;
use App\Models\Unit;
use App\Models\User;
use App\Support\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class GuestRoleTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_view_inventory_pages_but_cannot_write(): void
    {
        $guest = User::factory()->create(['role' => Role::Guest]);
        $unit = Unit::create(['name' => 'Tablet']);
        $location = Location::create(['code' => 'APT', 'name' => 'Apotek']);
        $medicine = Medicine::create(['code' => 'OBT-G', 'name' => 'Obat Guest', 'dosage_form' => 'Tablet', 'unit_id' => $unit->id]);

        $this->actingAs($guest)->get('/dashboard')->assertOk();
        $this->get('/obat')->assertOk();
        $this->get('/obat-masuk')->assertOk()->assertDontSee('Posting Penerimaan');
        $this->get('/transfer')->assertOk()->assertDontSee('Posting Transfer');
        $this->get('/pengecekan-malam')->assertOk()->assertDontSee('Buka / Mulai Pemeriksaan');

        $this->post('/obat-masuk', [
            'reference' => 'GUEST-001',
            'medicine_id' => $medicine->id,
            'batch_number' => 'B-GUEST',
            'expiry_date' => '2027-01-01',
            'quantity' => 1,
            'occurred_at' => '2026-01-01T08:00',
        ])->assertForbidden();

        $this->post('/pengecekan-malam', [
            'date' => '2026-01-01',
            'location_id' => $location->id,
        ])->assertForbidden();
    }

    public function test_guest_cannot_review_submitted_checks(): void
    {
        $guest = User::factory()->create(['role' => Role::Guest]);
        $petugas = User::factory()->create(['role' => Role::Pharmacy]);
        $location = Location::create(['code' => 'APT', 'name' => 'Apotek']);
        $checkId = DB::table('nightly_checks')->insertGetId([
            'date' => '2026-01-01',
            'location_id' => $location->id,
            'created_by' => $petugas->id,
            'status' => 'submitted',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($guest)
            ->post('/pengecekan-malam/'.$checkId.'/review', ['action' => 'approve'])
            ->assertForbidden();
    }

    public function test_guest_has_all_operational_views_including_pharmacist_only_menus(): void
    {
        $guest = User::factory()->create(['role' => Role::Guest]);
        $unit = Unit::create(['name' => 'Tablet']);
        $location = Location::create(['code' => 'APT', 'name' => 'Apotek']);
        $medicine = Medicine::create(['code' => 'UAT-REF', 'name' => 'Obat Referensi', 'unit_id' => $unit->id, 'dosage_form' => 'Tablet']);
        StockParameter::create(['medicine_id' => $medicine->id, 'location_id' => $location->id, 'minimum_quantity' => 20]);
        $this->actingAs($guest);
        foreach (['/dashboard', '/obat', '/stok-awal', '/obat-masuk', '/transfer', '/obat-keluar', '/retur-pbf',
            '/pengecekan-malam', '/stock-opname', '/riwayat-stok', '/kekurangan-permintaan', '/kedaluwarsa',
            '/kondisi-khusus', '/laporan', '/kartu-stok', '/parameter-monitoring'] as $url) {
            $this->get($url)->assertOk();
        }
        $this->get('/parameter-monitoring')->assertSee('Obat Referensi')->assertSee('20')
            ->assertDontSee('Simpan Parameter')->assertDontSee('name="minimums[', false);
        $this->get('/stok-awal')->assertDontSee('id="opening-quantity"', false);
        $this->get('/dashboard')->assertSee('Stok Awal')->assertSee('Parameter Monitoring')->assertDontSee('ADMINISTRASI');
        foreach (array_keys(Permissions::definitions()['Menu']) as $permission) {
            $this->assertTrue($guest->can($permission), $permission);
        }
    }

    public function test_guest_cannot_gain_write_or_superadmin_access_even_from_a_tampered_matrix(): void
    {
        $guest = User::factory()->create(['role' => Role::Guest]);
        foreach (Permissions::forbidden()[Role::Guest->value] as $permission) {
            DB::table('role_permissions')->updateOrInsert(['role' => Role::Guest->value, 'permission' => $permission], ['allowed' => true]);
            $this->assertFalse($guest->can($permission), $permission);
            $this->assertFalse(Permissions::matrix()[Role::Guest->value][$permission], $permission);
        }
        $this->actingAs($guest);
        foreach (['/stok-awal', '/saldo-awal', '/obat-masuk', '/transfer', '/obat-keluar', '/retur-pbf',
            '/kondisi-khusus', '/pengecekan-malam', '/stock-opname', '/parameter-monitoring',
            '/admin/pengguna', '/admin/pengguna/hak-akses', '/admin/referensi-sistem/lokasi', '/admin/pengaturan-sistem'] as $url) {
            $this->post($url, [])->assertForbidden();
        }
        foreach (['/admin/pengguna', '/admin/pengguna/create', '/admin/audit-log', '/admin/referensi-sistem', '/admin/pengaturan-sistem'] as $url) {
            $this->get($url)->assertForbidden();
        }
        Livewire::actingAs($guest)->test(MedicineIndex::class)->call('save')->assertForbidden();
        Livewire::actingAs($guest)->test(MedicineIndex::class)->call('create')->assertForbidden();
        $this->assertDatabaseCount('inventory_movements', 0);
    }

    public function test_matrix_save_and_reset_cannot_unlock_guest_writes(): void
    {
        Permissions::syncMatrix([Role::Guest->value => array_fill_keys(Permissions::all(), true)]);
        foreach (Permissions::forbidden()[Role::Guest->value] as $permission) {
            $this->assertDatabaseHas('role_permissions', ['role' => Role::Guest->value, 'permission' => $permission, 'allowed' => false]);
        }
        Permissions::syncMatrix(Permissions::defaults());
        $this->assertTrue(Permissions::roleCan(Role::Guest, Permissions::VIEW_OPENING_BALANCES));
        $this->assertTrue(Permissions::roleCan(Role::Guest, Permissions::VIEW_MONITORING_PARAMETERS));
        $this->assertFalse(Permissions::roleCan(Role::Guest, Permissions::MANAGE_MONITORING_PARAMETERS));
    }

    public function test_guest_sees_opname_and_daily_details_without_editable_count_forms(): void
    {
        $guest = User::factory()->create(['role' => Role::Guest]);
        $staff = User::factory()->create(['role' => Role::Pharmacy]);
        $unit = Unit::create(['name' => 'Tablet']);
        $location = Location::create(['code' => 'APT', 'name' => 'Apotek']);
        $medicine = Medicine::create(['code' => 'UAT-COUNT', 'name' => 'Obat Hitung', 'unit_id' => $unit->id, 'dosage_form' => 'Tablet']);
        $session = DB::table('stock_opname_sessions')->insertGetId(['reference' => 'UAT-COUNT', 'period' => now()->format('Y-m'),
            'location_id' => $location->id, 'cutoff_at' => now(), 'created_by' => $staff->id, 'status' => 'draft']);
        DB::table('stock_opname_items')->insert(['stock_opname_session_id' => $session, 'medicine_id' => $medicine->id,
            'system_quantity' => 10, 'physical_quantity' => 10, 'unit_name' => 'Tablet']);
        $check = DB::table('nightly_checks')->insertGetId(['date' => now()->toDateString(), 'location_id' => $location->id,
            'created_by' => $staff->id, 'status' => 'draft']);
        DB::table('nightly_check_items')->insert(['nightly_check_id' => $check, 'medicine_id' => $medicine->id, 'code' => $medicine->code, 'name' => $medicine->name]);
        $this->actingAs($guest)->get('/stock-opname?session='.$session)->assertOk()->assertSee('Obat Hitung')
            ->assertDontSee('[physical_quantity]', false)->assertDontSee('Simpan Isian')->assertDontSee('Finalisasi Kepala Apoteker');
        $this->get('/pengecekan-malam/'.$check)->assertOk()->assertSee('Obat Hitung')->assertDontSee('data-nightly-count-form', false);
        foreach (['/stock-opname/'.$session.'/save', '/stock-opname/'.$session.'/finalize', '/stock-opname/'.$session.'/recount',
            '/pengecekan-malam/'.$check, '/pengecekan-malam/'.$check.'/review'] as $url) {
            $this->post($url, [])->assertForbidden();
        }
    }

    public function test_hiding_guest_inventory_dashboard_does_not_expose_admin_summary(): void
    {
        $guest = User::factory()->create(['role' => Role::Guest]);
        DB::table('role_permissions')->where('role', Role::Guest->value)->where('permission', Permissions::VIEW_INVENTORY)->update(['allowed' => false]);
        $this->actingAs($guest)->get('/dashboard')->assertOk()->assertDontSee('Portal Administrator')->assertDontSee('Pengguna Terdaftar');
    }

    public function test_uat_print_and_export_are_labelled_without_changing_the_csv_columns(): void
    {
        $guest = User::factory()->create(['role' => Role::Guest]);
        $unit = Unit::create(['name' => 'Tablet']);
        $location = Location::create(['code' => 'GDG', 'name' => 'Gudang']);
        $medicine = Medicine::create(['code' => 'UAT-PRINT', 'name' => 'Obat Cetak', 'unit_id' => $unit->id, 'dosage_form' => 'Tablet']);
        $this->app->instance('env', 'uat');
        $this->actingAs($guest)->get(route('stock-card.print', ['medicine_id' => $medicine->id, 'location_id' => $location->id,
            'start' => now()->toDateString(), 'end' => now()->toDateString()]))->assertOk()->assertSee('UAT / SIMULASI');
        $response = $this->get('/laporan?export=csv')->assertOk();
        $this->assertStringContainsString('uat-laporan-persediaan-', $response->headers->get('content-disposition'));
        $this->assertStringContainsString('Obat,Kode,Lokasi', $response->streamedContent());
    }
}
