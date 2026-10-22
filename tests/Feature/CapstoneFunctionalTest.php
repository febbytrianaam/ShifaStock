<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Livewire\MedicineIndex;
use App\Models\Batch;
use App\Models\BatchStockBalance;
use App\Models\InventoryMovement;
use App\Models\InventoryReceipt;
use App\Models\Location;
use App\Models\Medicine;
use App\Models\StockBalance;
use App\Models\StockOpnameSession;
use App\Models\StockParameter;
use App\Models\Unit;
use App\Models\User;
use App\Services\InventoryReconciliationService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class CapstoneFunctionalTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;
    private User $pharmacist;
    private Medicine $medicine;
    private Location $warehouse;
    private Location $pharmacy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-10-18 12:00:00', 'Asia/Jakarta'));
        $this->staff = User::factory()->create(['role' => Role::Pharmacy]);
        $this->pharmacist = User::factory()->create(['role' => Role::Pharmacist]);
        $unit = Unit::create(['name' => 'Tablet']);
        $this->medicine = Medicine::create(['code' => 'FINAL-001', 'name' => 'Obat Simulasi Final', 'unit_id' => $unit->id, 'dosage_form' => 'Tablet', 'is_active' => true]);
        $this->warehouse = Location::create(['code' => 'GDG', 'name' => 'Gudang', 'is_active' => true]);
        $this->pharmacy = Location::create(['code' => 'APT', 'name' => 'Apotek', 'is_active' => true]);
        $this->actingAs($this->staff);
    }

    private function receipt(string $reference = 'SIM-FKT', string $action = 'post'): InventoryReceipt
    {
        $this->post(route('receipts.store'), ['reference' => $reference, 'action' => $action, 'occurred_at' => '2026-10-18T08:00',
            'destination_location_id' => $this->warehouse->id, 'items' => [
                ['medicine_id' => $this->medicine->id, 'batch_number' => 'SIM-EARLY', 'expiry_date' => '2026-11-01', 'quantity' => 20],
                ['medicine_id' => $this->medicine->id, 'batch_number' => 'SIM-LATE', 'expiry_date' => '2028-01-01', 'quantity' => 30],
            ]])->assertRedirect()->assertSessionHasNoErrors();

        return InventoryReceipt::where('reference', $reference)->sole();
    }

    private function transfer(float $quantity = 25): void
    {
        $this->post(route('transfers.store'), ['reference' => 'SIM-TRF', 'medicine_id' => $this->medicine->id,
            'source_location_id' => $this->warehouse->id, 'destination_location_id' => $this->pharmacy->id,
            'quantity' => $quantity, 'occurred_at' => '2026-10-18T09:00'])->assertRedirect()->assertSessionHasNoErrors();
    }

    private function assertReconciled(): void
    {
        $report = app(InventoryReconciliationService::class)->report();
        $this->assertSame(0, $report['mismatched_pairs']);
        $this->assertSame([], $report['batch_identity_issues']);
    }

    public function test_authentication_valid_and_invalid_credentials(): void
    {
        $this->post('/logout')->assertRedirect('/login');
        $secret = bin2hex(random_bytes(24));
        $this->staff->update(['password' => $secret]);
        $this->post('/login', ['username' => $this->staff->username, 'password' => bin2hex(random_bytes(24))])
            ->assertSessionHasErrors('username');
        $this->assertGuest();
        $this->post('/login', ['username' => $this->staff->username, 'password' => $secret])->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($this->staff);
    }

    public function test_permissions_restrict_guest_write_and_staff_master_edit(): void
    {
        $guest = User::factory()->create(['role' => Role::Guest]);
        $this->actingAs($guest)->get(route('special-adjustments.index'))->assertOk()->assertDontSee('id="adjust-batch"', false);
        $this->post(route('special-adjustments.store'), [])->assertForbidden();
        $this->get(route('admin.users.index'))->assertForbidden();
        Livewire::actingAs($this->staff)->test(MedicineIndex::class)->call('edit', $this->medicine->id)->assertForbidden();
        $this->assertDatabaseCount('inventory_movements', 0);
    }

    public function test_master_medicine_create_and_edit(): void
    {
        $component = Livewire::actingAs($this->pharmacist)->test(MedicineIndex::class)
            ->set('code', 'FINAL-NEW')->set('name', 'Obat Simulasi Baru')->set('dosage_form', 'Tablet')
            ->set('unit_id', (string) $this->medicine->unit_id)->call('save')->assertHasNoErrors();
        $created = Medicine::where('code', 'FINAL-NEW')->sole();
        $component->call('edit', $created->id)->set('name', 'Obat Simulasi Direvisi')->call('save')->assertHasNoErrors();
        $this->assertSame('Obat Simulasi Direvisi', $created->fresh()->name);
        $this->assertDatabaseCount('medicines', 2);
    }

    public function test_receipt_draft_then_post_adds_stock_once(): void
    {
        $receipt = $this->receipt(action: 'draft');
        $this->assertSame('draft', $receipt->status);
        $this->assertDatabaseCount('inventory_movements', 0);
        $this->assertDatabaseCount('stock_balances', 0);
        $this->post(route('receipts.post', $receipt))->assertSessionHasNoErrors();
        $this->assertSame('posted', $receipt->fresh()->status);
        $this->assertDatabaseHas('stock_balances', ['medicine_id' => $this->medicine->id, 'quantity' => 50]);
        $this->post(route('receipts.post', $receipt))->assertUnprocessable();
        $this->assertDatabaseCount('inventory_movements', 2);
        $this->assertReconciled();
    }

    public function test_transfer_allocates_earliest_expiry_first_across_batches(): void
    {
        $this->receipt();
        $this->transfer();
        $movements = InventoryMovement::with('batch')->where('reference', 'SIM-TRF')->get()->keyBy('batch.number');
        $this->assertCount(2, $movements);
        $this->assertSame(20.0, (float) $movements['SIM-EARLY']->quantity);
        $this->assertSame(5.0, (float) $movements['SIM-LATE']->quantity);
        foreach ([$this->warehouse, $this->pharmacy] as $location) {
            $this->assertDatabaseHas('stock_balances', ['location_id' => $location->id, 'quantity' => 25]);
        }
        $this->assertReconciled();
    }

    public function test_issue_decreases_stock_and_records_movement(): void
    {
        $this->receipt();
        $this->transfer();
        $this->post(route('issues.store'), ['reference' => 'SIM-OUT', 'medicine_id' => $this->medicine->id,
            'source_location_id' => $this->pharmacy->id, 'quantity' => 22, 'occurred_at' => '2026-10-18T10:00'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('stock_balances', ['location_id' => $this->pharmacy->id, 'quantity' => 3]);
        $this->assertEquals(22, InventoryMovement::where('type', 'issue')->where('reference', 'SIM-OUT')->sum('quantity'));
        $this->assertReconciled();
    }

    public function test_dashboard_minimum_alerts_follow_real_postings_without_daily_checks(): void
    {
        foreach ([[$this->warehouse, 60], [$this->pharmacy, 20]] as [$location, $minimum]) {
            StockParameter::create(['medicine_id' => $this->medicine->id, 'location_id' => $location->id, 'minimum_quantity' => $minimum]);
        }
        $assertAlerts = function (array $expected): void {
            $dashboard = $this->get(route('dashboard'))->assertOk()
                ->assertViewHas('stats', fn ($stats) => $stats['shortages'] === count($expected));
            $monitoring = $this->get(route('shortages.index'))->assertOk();
            foreach ([$dashboard->viewData('shortages'), $monitoring->viewData('parameterRows')] as $rows) {
                $actual = $rows->mapWithKeys(fn ($row) => [$row->location_id => (float) $row->quantity])->all();
                $this->assertEquals($expected, $actual);
            }
        };
        $this->receipt('SIM-DRAFT', 'draft');
        $assertAlerts([$this->warehouse->id => 0.0, $this->pharmacy->id => 0.0]);
        $this->receipt();
        $assertAlerts([$this->warehouse->id => 50.0, $this->pharmacy->id => 0.0]);
        $this->transfer();
        $assertAlerts([$this->warehouse->id => 25.0]);
        $this->post(route('issues.store'), ['reference' => 'SIM-MON-OUT', 'medicine_id' => $this->medicine->id,
            'source_location_id' => $this->pharmacy->id, 'quantity' => 6, 'occurred_at' => '2026-10-18T10:00'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $assertAlerts([$this->warehouse->id => 25.0, $this->pharmacy->id => 19.0]);
        $this->receipt('SIM-RESTOCK');
        $assertAlerts([$this->pharmacy->id => 19.0]);
        $this->assertDatabaseCount('nightly_checks', 0);
        $this->assertReconciled();
    }

    public function test_stock_card_history_matches_posted_transactions(): void
    {
        $this->receipt();
        $this->transfer();
        $filters = ['medicine_id' => $this->medicine->id, 'location_id' => $this->warehouse->id, 'start' => '2026-10-18', 'end' => '2026-10-18'];
        $this->get(route('stock-card.index', $filters))->assertOk()
            ->assertViewHas('rows', fn ($rows) => $rows->count() === 4 && (float) $rows->last()->card_running_balance === 25.0)
            ->assertSee('SIM-FKT')->assertSee('SIM-TRF');
        $this->get(route('stock-card.print', $filters))->assertOk()->assertSee('Obat Simulasi Final');
        $this->assertReconciled();
    }

    public function test_daily_count_submission_and_pharmacist_review(): void
    {
        $this->receipt();
        $this->post(route('nightly.store'), ['date' => '2026-10-18', 'location_id' => $this->warehouse->id])->assertSessionHasNoErrors();
        $check = DB::table('nightly_checks')->sole();
        $item = DB::table('nightly_check_items')->where('nightly_check_id', $check->id)->sole();
        $counts = Batch::all()->mapWithKeys(fn ($batch) => [$batch->id => $batch->number === 'SIM-EARLY' ? 18 : 30])->all();
        $this->post(route('nightly.save', $check->id), ['draft_revision' => $check->draft_revision, 'action' => 'submit', 'items' => [$item->id => ['quantity' => 48, 'unit' => 'Tablet', 'batch_counts' => $counts]]])
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('nightly_checks', ['id' => $check->id, 'status' => 'submitted']);
        $this->assertDatabaseHas('stock_balances', ['location_id' => $this->warehouse->id, 'quantity' => 50]);
        $this->actingAs($this->pharmacist)->post(route('nightly.review', $check->id), ['action' => 'approve'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('nightly_checks', ['id' => $check->id, 'status' => 'approved']);
        $this->assertDatabaseHas('stock_balances', ['location_id' => $this->warehouse->id, 'quantity' => 48]);
        $this->assertReconciled();
    }

    public function test_opname_count_recount_and_finalize(): void
    {
        $this->receipt();
        $this->post(route('opname.store'), ['period' => '2026-10', 'location_id' => $this->warehouse->id, 'cutoff_at' => '2026-10-18T12:00'])->assertSessionHasNoErrors();
        $session = StockOpnameSession::sole();
        $counts = fn () => $session->fresh()->items->mapWithKeys(fn ($item) => [$item->id => ['physical_quantity' => $item->system_quantity, 'reason' => 'Simulasi sesuai stok']])->all();
        $this->post(route('opname.save', $session), ['items' => $counts()])->assertSessionHasNoErrors();
        $this->post(route('opname.recount', $session))->assertSessionHasNoErrors();
        $this->assertTrue($session->fresh()->items->every(fn ($item) => $item->physical_quantity === null));
        $this->post(route('opname.save', $session), ['items' => $counts()])->assertSessionHasNoErrors();
        $this->actingAs($this->pharmacist)->post(route('opname.finalize', $session))->assertSessionHasNoErrors();
        $this->assertSame('finalized', $session->fresh()->status);
        $this->post(route('opname.recount', $session))->assertUnprocessable();
        $this->assertReconciled();
    }

    public function test_monitoring_minimum_and_expiry_indicators_with_location_filter(): void
    {
        $this->receipt();
        StockParameter::create(['medicine_id' => $this->medicine->id, 'location_id' => $this->warehouse->id, 'minimum_quantity' => 60]);
        $this->get(route('shortages.index', ['location_id' => $this->warehouse->id]))->assertOk()
            ->assertViewHas('parameterRows', fn ($rows) => $rows->count() === 1 && (float) $rows->first()->quantity === 50.0)
            ->assertSee('Obat Simulasi Final')->assertSee('Gudang');
        $this->get(route('shortages.index', ['location_id' => $this->pharmacy->id]))->assertViewHas('parameterRows', fn ($rows) => $rows->isEmpty());
        $early = Batch::where('number', 'SIM-EARLY')->sole();
        $early->update(['expiry_date' => '2026-10-17']);
        $this->get(route('preview.expiry', ['location_id' => $this->warehouse->id]))->assertOk()
            ->assertViewHas('counts', ['expired' => 1, 'near' => 0, 'safe' => 1])->assertSee('SIM-EARLY')->assertSee('Gudang');
        $this->get(route('preview.expiry', ['status' => 'expired']))->assertViewHas('batches', fn ($rows) => $rows->count() === 1 && $rows->first()->id === $early->id);
        $early->update(['expiry_date' => '2026-11-01']);
        $this->get(route('preview.expiry'))->assertViewHas('counts', ['expired' => 0, 'near' => 1, 'safe' => 1]);
        $this->assertReconciled();
    }

    public function test_shared_batch_adjustment_changes_only_selected_location(): void
    {
        $this->receipt();
        $this->transfer(10);
        $batch = Batch::where('number', 'SIM-EARLY')->sole();
        $page = $this->get(route('special-adjustments.index', ['medicine_id' => $this->medicine->id, 'location_id' => $this->pharmacy->id, 'batch_id' => $batch->id]));
        $page->assertOk()->assertViewHas('batchBalances', fn ($rows) => $rows->where('batch_id', $batch->id)->pluck('location_id')->unique()->count() === 2);
        $this->post(route('special-adjustments.store'), ['reference' => 'SIM-SHARED', 'medicine_id' => $this->medicine->id, 'batch_id' => $batch->id,
            'location_id' => $this->pharmacy->id, 'direction' => 'decrease', 'condition' => 'rusak', 'quantity' => 2, 'occurred_at' => '2026-10-18T10:00'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('batch_stock_balances', ['batch_id' => $batch->id, 'location_id' => $this->warehouse->id, 'quantity' => 10]);
        $this->assertDatabaseHas('batch_stock_balances', ['batch_id' => $batch->id, 'location_id' => $this->pharmacy->id, 'quantity' => 8]);
        $this->assertDatabaseHas('inventory_movements', ['reference' => 'SIM-SHARED', 'source_location_id' => $this->pharmacy->id, 'quantity' => 2]);
        $this->assertReconciled();
    }

    public function test_batch_unavailable_at_selected_location_is_rejected(): void
    {
        $this->receipt();
        $batch = Batch::where('number', 'SIM-EARLY')->sole();
        $this->post(route('special-adjustments.store'), ['reference' => 'SIM-WRONG-LOCATION', 'medicine_id' => $this->medicine->id,
            'batch_id' => $batch->id, 'location_id' => $this->pharmacy->id, 'direction' => 'decrease', 'condition' => 'rusak',
            'quantity' => 2, 'occurred_at' => '2026-10-18T10:00'])->assertSessionHasErrors();
        $this->assertDatabaseMissing('inventory_movements', ['reference' => 'SIM-WRONG-LOCATION']);
        $this->assertDatabaseHas('stock_balances', ['location_id' => $this->warehouse->id, 'quantity' => 50]);
        $this->assertReconciled();
    }
}
