<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Batch;
use App\Models\Location;
use App\Models\Medicine;
use App\Models\Unit;
use App\Models\User;
use App\Support\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class NightlyPrintTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    private int $checkId;

    private Medicine $medicine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->staff = User::factory()->create(['role' => Role::Pharmacy, 'name' => 'Petugas Simulasi']);
        $unit = Unit::create(['name' => 'Tablet']);
        $location = Location::create(['code' => 'APT', 'name' => 'Apotek']);
        $this->medicine = Medicine::create(['code' => 'PRINT-A', 'name' => 'Obat Cetak', 'unit_id' => $unit->id, 'is_active' => true]);
        $this->checkId = DB::table('nightly_checks')->insertGetId([
            'date' => '2026-01-01', 'location_id' => $location->id, 'created_by' => $this->staff->id, 'scope' => 'selected', 'status' => 'draft',
        ]);
        DB::table('nightly_check_items')->insert([
            'nightly_check_id' => $this->checkId, 'medicine_id' => $this->medicine->id,
            'code' => $this->medicine->code, 'name' => $this->medicine->name, 'unit' => 'Tablet', 'quantity' => null,
        ]);
        $this->actingAs($this->staff);
    }

    public function test_print_route_renders_a_clinic_report_without_application_or_edit_controls(): void
    {
        $this->get(route('nightly.show', $this->checkId))->assertOk()
            ->assertSee(route('nightly.print', $this->checkId))->assertSee('data-nightly-print', false);
        $response = $this->get(route('nightly.print', $this->checkId))->assertOk()
            ->assertSee('LAPORAN PEMERIKSAAN STOK HARIAN')->assertSee('Petugas Simulasi')->assertSee('Apotek')
            ->assertSee('Obat Cetak')->assertSee('CHECK-'.$this->checkId)->assertSee('DRAF - Belum disetujui')
            ->assertSee('bukan seluruh persediaan')->assertSee('Belum diperiksa')->assertDontSee('data-nightly-count-form', false)
            ->assertDontSee('sidebar')->assertDontSee('nightly-search')->assertDontSee('Tambah Obat')
            ->assertSee('size: A4 portrait;', false)->assertSee('width: 210mm;', false)->assertDontSee('A4 landscape');
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $document = new \DOMDocument;
        $document->loadHTML($response->getContent(), LIBXML_NOERROR | LIBXML_NOWARNING);
        $this->assertSame(1, $document->getElementsByTagName('img')->length);
        foreach (['input', 'select', 'textarea', 'form'] as $tag) {
            $this->assertSame(0, $document->getElementsByTagName($tag)->length);
        }
        $this->assertDatabaseHas('nightly_checks', ['id' => $this->checkId, 'status' => 'draft']);
        $this->assertDatabaseCount('inventory_movements', 0);
        $this->assertDatabaseCount('stock_balances', 0);
    }

    public function test_saved_zero_requests_batch_counts_and_escaped_notes_are_printed(): void
    {
        $batch = Batch::create(['medicine_id' => $this->medicine->id, 'number' => 'PRINT-BATCH', 'expiry_date' => '2027-12-01']);
        DB::table('nightly_check_items')->update(['quantity' => 0, 'requested_quantity' => 20, 'note' => '<script>alert(1)</script>', 'batch_counts' => json_encode([$batch->id => 0])]);
        $this->get(route('nightly.print', $this->checkId))->assertOk()->assertSee('Kosong')
            ->assertSee('PRINT-BATCH')->assertSee('01/12/2027')->assertSee('20')->assertSee('&lt;script&gt;', false)->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('RINCIAN HASIL HITUNG PER BATCH')->assertViewHas('items', fn ($items) => $items->first()->quantity === 0 && $items->first()->saved_batch_counts[$batch->id] === 0);
    }

    public function test_status_and_actual_reviewer_are_not_misrepresented_as_approval(): void
    {
        $reviewer = User::factory()->create(['role' => Role::Pharmacist, 'name' => 'Apoteker Simulasi']);
        DB::table('nightly_checks')->where('id', $this->checkId)->update(['status' => 'submitted']);
        $this->get(route('nightly.print', $this->checkId))->assertOk()->assertSee('MENUNGGU TINJAUAN - Belum disetujui')->assertSee('Belum tercatat');
        DB::table('nightly_checks')->where('id', $this->checkId)->update(['status' => 'approved', 'reviewed_by' => $reviewer->id, 'approved_at' => now(), 'review_note' => 'Sudah ditinjau']);
        $this->get(route('nightly.print', $this->checkId))->assertOk()->assertSee('DISETUJUI')->assertSee('Apoteker Simulasi')
            ->assertSee('Sudah ditinjau')->assertDontSee('Laporan ini belum disetujui');
    }

    public function test_guest_can_print_but_a_role_without_daily_view_permission_cannot(): void
    {
        $guest = User::factory()->create(['role' => Role::Guest]);
        $this->actingAs($guest)->get(route('nightly.print', $this->checkId))->assertOk()->assertSee('Obat Cetak');
        DB::table('role_permissions')->updateOrInsert(['role' => Role::Pharmacy->value, 'permission' => Permissions::VIEW_NIGHTLY_CHECKS], ['allowed' => false, 'created_at' => now(), 'updated_at' => now()]);
        $this->actingAs($this->staff)->get(route('nightly.print', $this->checkId))->assertForbidden();
    }

    public function test_print_requires_login_and_unknown_reports_are_not_found(): void
    {
        $this->actingAs($this->staff)->get(route('nightly.print', 999999))->assertNotFound();
        auth()->logout();
        $this->get(route('nightly.print', $this->checkId))->assertRedirect(route('login'));
    }

    public function test_print_uses_the_recorded_unit_not_a_reinterpretation_of_the_current_master(): void
    {
        // A legacy manual draft may carry another unit; printing must not convert it.
        DB::table('nightly_check_items')->where('nightly_check_id', $this->checkId)->update(['unit' => 'Botol']);
        $this->get(route('nightly.print', $this->checkId))->assertOk()->assertSee('Botol')->assertDontSee('Tablet');
    }

    public function test_all_items_are_printed_without_ui_pagination_or_filters(): void
    {
        for ($index = 1; $index <= 30; $index++) {
            $medicine = Medicine::create(['code' => 'P-'.$index, 'name' => 'Obat Simulasi '.$index, 'is_active' => false]);
            DB::table('nightly_check_items')->insert(['nightly_check_id' => $this->checkId, 'medicine_id' => $medicine->id, 'code' => $medicine->code, 'name' => $medicine->name, 'unit' => 'Tablet']);
        }
        $this->get(route('nightly.print', $this->checkId))->assertOk()->assertViewHas('items', fn ($items) => $items->count() === 31)
            ->assertSee('Obat Simulasi 30')->assertSee('31 obat');
    }
}
