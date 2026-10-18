<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Batch;
use App\Models\BatchStockBalance;
use App\Models\InventoryMovement;
use App\Models\InventoryReceipt;
use App\Models\Location;
use App\Models\Medicine;
use App\Models\StockBalance;
use App\Models\StockOpnameSession;
use App\Models\Unit;
use App\Models\User;
use App\Services\InventoryReconciliationService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class InventoryIntegrationTest extends TestCase
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
        $this->travelTo(Carbon::parse('2026-10-16 10:00:00', 'Asia/Jakarta'));
        $this->staff = User::factory()->create(['role' => Role::Pharmacy]);
        $this->pharmacist = User::factory()->create(['role' => Role::Pharmacist]);
        $unit = Unit::create(['name' => 'Tablet']);
        $this->medicine = Medicine::create(['code' => 'INTEGRATION', 'name' => 'Obat Integrasi', 'unit_id' => $unit->id, 'dosage_form' => 'Tablet', 'is_active' => true]);
        $this->warehouse = Location::create(['code' => 'GDG', 'name' => 'Gudang', 'is_active' => true]);
        $this->pharmacy = Location::create(['code' => 'APT', 'name' => 'Apotek', 'is_active' => true]);
        $this->actingAs($this->staff);
    }

    private function receipt(string $reference, array $items, string $action = 'post'): void
    {
        $this->post(route('receipts.store'), [
            'reference' => $reference, 'action' => $action, 'occurred_at' => '2026-10-16T08:00',
            'destination_location_id' => $this->warehouse->id,
            'items' => array_map(fn ($item) => ['medicine_id' => $this->medicine->id] + $item, $items),
        ])->assertRedirect()->assertSessionHasNoErrors();
    }

    private function assertReconciled(): void
    {
        $report = app(InventoryReconciliationService::class)->report();
        $this->assertSame(0, $report['mismatched_pairs'], json_encode($report));
        $this->assertSame([], $report['batch_identity_issues']);
    }

    public function test_special_adjustment_requires_batch_and_keeps_stock_reconciled(): void
    {
        $this->receipt('SA-SEED', [['batch_number' => 'SA-BATCH', 'expiry_date' => '2027-12-31', 'quantity' => 10]]);
        $batch = Batch::where('number', 'SA-BATCH')->firstOrFail();
        $payload = ['reference' => 'SA-1', 'medicine_id' => $this->medicine->id,
            'location_id' => $this->warehouse->id, 'direction' => 'decrease', 'condition' => 'rusak',
            'quantity' => 2, 'occurred_at' => '2026-10-16T09:00'];
        $this->post(route('special-adjustments.store'), $payload)->assertSessionHasErrors('batch_id');
        $this->post(route('special-adjustments.store'), $payload + ['batch_id' => $batch->id])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertReconciled();
        $this->assertSame(8.0, (float) StockBalance::first()->quantity);
        $this->post(route('special-adjustments.store'), array_replace($payload,
            ['reference' => 'SA-TINY', 'batch_id' => $batch->id, 'quantity' => 0.00001]))->assertSessionHasErrors('quantity');
        $this->assertDatabaseMissing('inventory_movements', ['reference' => 'SA-TINY']);
        $other = Medicine::create(['code' => 'SA-OTHER', 'name' => 'Other', 'unit_id' => $this->medicine->unit_id, 'dosage_form' => 'Tablet']);
        $wrong = Batch::create(['medicine_id' => $other->id, 'number' => 'WRONG']);
        $this->post(route('special-adjustments.store'), array_replace($payload, ['reference' => 'SA-WRONG', 'batch_id' => $wrong->id]))->assertSessionHasErrors('batch_id');
        $empty = Batch::create(['medicine_id' => $this->medicine->id, 'number' => 'EMPTY']);
        BatchStockBalance::create(['medicine_id' => $this->medicine->id, 'batch_id' => $empty->id,
            'location_id' => $this->warehouse->id, 'quantity' => 0]);
        $this->get(route('special-adjustments.index'))->assertOk()->assertSee('Pilih batch yang diperiksa')->assertSee('EMPTY');
        $this->post(route('special-adjustments.store'), array_replace($payload, ['reference' => 'SA-EMPTY',
            'batch_id' => $empty->id, 'condition' => 'lainnya', 'direction' => 'increase']))->assertSessionHasNoErrors();
        $this->assertReconciled();
        $movement = InventoryMovement::where('reference', 'SA-EMPTY')->sole();
        $this->actingAs($this->pharmacist)->post(route('movements.reverse', $movement), ['reason' => 'Koreksi dibatalkan'])->assertSessionHasNoErrors();
        $this->post(route('movements.reverse', $movement), ['reason' => 'Pengiriman ulang'])->assertSessionHasErrors('movement');
        $this->assertReconciled();
        $this->actingAs(User::factory()->create(['role' => Role::Guest]))
            ->post(route('special-adjustments.store'), $payload + ['batch_id' => $batch->id])->assertForbidden();
        $this->assertReconciled();
    }

    public function test_opname_rejects_changed_snapshot_and_repeated_finalization(): void
    {
        $this->receipt('OP-SEED', [['batch_number' => 'OP-BATCH', 'expiry_date' => '2027-12-31', 'quantity' => 10]]);
        $this->post(route('opname.store'), ['period' => '2026-10', 'location_id' => $this->warehouse->id,
            'cutoff_at' => '2026-10-16T09:00'])->assertSessionHasNoErrors();
        $session = StockOpnameSession::firstOrFail();
        $item = $session->items()->firstOrFail();
        $this->post(route('opname.save', $session), ['items' => [$item->id => ['physical_quantity' => 8]]])
            ->assertSessionHasNoErrors();
        $this->actingAs($this->pharmacist)->post(route('opname.finalize', $session))->assertSessionHasNoErrors();
        $this->post(route('opname.finalize', $session))->assertStatus(422);
        $this->assertSame(8.0, (float) StockBalance::first()->quantity);
        $this->assertReconciled();

        $session->refresh()->update(['status' => 'draft']);
        $this->post(route('opname.finalize', $session))->assertSessionHasErrors('items');
        $this->assertSame(8.0, (float) StockBalance::first()->quantity);
        $this->assertReconciled();
    }

    private function approveCount(Location $location, int $quantity): void
    {
        $this->actingAs($this->staff)->post(route('nightly.store'), ['date' => '2026-10-16', 'location_id' => $location->id])->assertSessionHasNoErrors();
        $check = DB::table('nightly_checks')->where('location_id', $location->id)->first();
        $item = DB::table('nightly_check_items')->where('nightly_check_id', $check->id)->first();
        $this->post(route('nightly.save', $check->id), ['action' => 'submit', 'items' => [$item->id => ['quantity' => $quantity, 'unit' => 'Tablet']]])->assertSessionHasNoErrors();
        $this->actingAs($this->pharmacist)->post(route('nightly.review', $check->id), ['action' => 'approve'])->assertSessionHasNoErrors();
    }

    public function test_complete_stock_cycle_reconciles_after_each_batch_aware_operation(): void
    {
        $this->receipt('CYCLE', [
            ['batch_number' => 'EARLY', 'expiry_date' => '2026-11-16', 'input_quantity' => 2, 'input_unit_name' => 'Box', 'conversion_factor' => 30],
            ['batch_number' => 'LATE', 'expiry_date' => '2027-06-16', 'input_quantity' => 1, 'input_unit_name' => 'Box', 'conversion_factor' => 40],
        ], 'draft');
        $this->assertDatabaseCount('inventory_movements', 0);
        $this->post(route('receipts.post', InventoryReceipt::first()))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('stock_balances', ['location_id' => $this->warehouse->id, 'quantity' => 100]);
        $this->assertReconciled();

        $this->post(route('transfers.store'), ['reference' => 'CYCLE-TRANSFER', 'medicine_id' => $this->medicine->id,
            'source_location_id' => $this->warehouse->id, 'destination_location_id' => $this->pharmacy->id,
            'quantity' => 70, 'occurred_at' => '2026-10-16T09:00'])->assertSessionHasNoErrors();
        $this->assertSame(2, InventoryMovement::where('reference', 'CYCLE-TRANSFER')->count());
        $this->assertReconciled();

        $this->post(route('issues.store'), ['reference' => 'CYCLE-ISSUE', 'medicine_id' => $this->medicine->id,
            'source_location_id' => $this->pharmacy->id, 'quantity' => 65, 'occurred_at' => '2026-10-16T09:10'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('stock_balances', ['location_id' => $this->pharmacy->id, 'quantity' => 5]);
        $this->assertReconciled();

        $late = Batch::where('number', 'LATE')->sole();
        $this->post(route('returns.store'), ['reference' => 'CYCLE-RETURN', 'medicine_id' => $this->medicine->id, 'batch_id' => $late->id,
            'source_location_id' => $this->warehouse->id, 'quantity' => 2, 'reason' => 'Salah kirim', 'occurred_at' => '2026-10-16T09:20'])->assertSessionHasNoErrors();
        $this->assertReconciled();
        $this->post(route('special-adjustments.store'), ['reference' => 'CYCLE-DAMAGE', 'medicine_id' => $this->medicine->id, 'batch_id' => $late->id,
            'location_id' => $this->warehouse->id, 'quantity' => 1, 'direction' => 'decrease', 'condition' => 'rusak', 'occurred_at' => '2026-10-16T09:30'])->assertSessionHasNoErrors();
        $this->assertReconciled();
        $damage = InventoryMovement::where('reference', 'CYCLE-DAMAGE')->sole();
        $this->actingAs($this->pharmacist)->post(route('movements.reverse', $damage), ['reason' => 'Pencatatan ganda'])->assertSessionHasNoErrors();
        $this->assertReconciled();

        // A count matching the current quantity must preserve all three representations.
        $this->approveCount($this->pharmacy, 5);
        $this->assertReconciled();
        $this->actingAs($this->staff)->post(route('opname.store'), ['period' => '2026-10', 'location_id' => $this->warehouse->id, 'cutoff_at' => '2026-10-16T10:00'])->assertSessionHasNoErrors();
        $session = StockOpnameSession::with('items')->sole();
        $this->post(route('opname.save', $session), ['items' => [$session->items->sole()->id => ['physical_quantity' => 26, 'reason' => 'Hitung ulang batch']]])->assertSessionHasNoErrors();
        $this->actingAs($this->pharmacist)->post(route('opname.finalize', $session))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('stock_balances', ['location_id' => $this->warehouse->id, 'quantity' => 26]);
        $this->assertReconciled();

        $filters = ['medicine_id' => $this->medicine->id, 'location_id' => $this->warehouse->id, 'start' => '2026-10-16', 'end' => '2026-10-16'];
        $this->get(route('stock-card.index', $filters))->assertOk()->assertViewHas('rows', fn ($rows) => (float) $rows->last()->getAttribute('card_running_balance') === 26.0);
        $this->get(route('stock-card.print', $filters))->assertOk()->assertSee('Obat Integrasi');
        $this->get(route('preview.reports', ['start' => '2026-10-16', 'end' => '2026-10-16']))->assertOk()->assertSee('Obat Integrasi');
        $this->artisan('inventory:reconcile')->assertExitCode(0);
    }

    public function test_daily_count_difference_requires_explicit_batch_counts_before_submission(): void
    {
        $this->receipt('COUNT-GAP', [['batch_number' => 'COUNT-BATCH', 'expiry_date' => '2027-01-01', 'quantity' => 10]]);
        $this->post(route('nightly.store'), ['date' => '2026-10-16', 'location_id' => $this->warehouse->id])->assertSessionHasNoErrors();
        $check = DB::table('nightly_checks')->first();
        $item = DB::table('nightly_check_items')->first();
        $this->postJson(route('nightly.save', $check->id), ['action' => 'submit', 'items' => [$item->id => ['quantity' => 7, 'unit' => 'Tablet']]])
            ->assertUnprocessable()->assertJsonValidationErrors('items.'.$item->id.'.batch_counts');
        $this->assertDatabaseHas('nightly_checks', ['id' => $check->id, 'status' => 'draft']);
        $this->assertSame(10.0, (float) StockBalance::first()->quantity);
        $this->assertSame(10.0, (float) BatchStockBalance::first()->quantity);
        $this->assertReconciled();
    }

    public function test_fractional_fefo_can_consume_exact_available_stock_without_float_residue(): void
    {
        $this->receipt('FRACTIONS', [
            ['batch_number' => 'FRACTION-A', 'expiry_date' => '2027-01-01', 'quantity' => 0.1],
            ['batch_number' => 'FRACTION-B', 'expiry_date' => '2027-06-01', 'quantity' => 0.7],
        ]);
        $this->post(route('transfers.store'), ['reference' => 'FRACTION-TRANSFER', 'medicine_id' => $this->medicine->id,
            'source_location_id' => $this->warehouse->id, 'destination_location_id' => $this->pharmacy->id,
            'quantity' => 0.8, 'occurred_at' => '2026-10-16T09:00'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('stock_balances', ['location_id' => $this->warehouse->id, 'quantity' => 0]);
        $this->assertDatabaseHas('stock_balances', ['location_id' => $this->pharmacy->id, 'quantity' => 0.8]);
        $this->assertReconciled();
    }

    public function test_failed_transfers_and_expired_batches_leave_every_stock_representation_unchanged(): void
    {
        $this->receipt('EXPIRY-GUARD', [
            ['batch_number' => 'EXPIRED', 'expiry_date' => '2026-09-01', 'quantity' => 10],
            ['batch_number' => 'USABLE', 'expiry_date' => '2027-01-01', 'quantity' => 5],
        ]);
        $expired = Batch::where('number', 'EXPIRED')->sole();
        $before = [StockBalance::all()->toJson(), BatchStockBalance::all()->toJson(), InventoryMovement::all()->toJson()];
        $payload = ['reference' => 'REJECTED', 'medicine_id' => $this->medicine->id,
            'source_location_id' => $this->warehouse->id, 'destination_location_id' => $this->pharmacy->id,
            'quantity' => 6, 'occurred_at' => '2026-10-16T09:00'];
        $this->postJson(route('transfers.store'), $payload)->assertUnprocessable()->assertJsonValidationErrors('quantity');
        $this->postJson(route('transfers.store'), array_merge($payload, ['quantity' => 1, 'batch_id' => $expired->id]))
            ->assertUnprocessable()->assertJsonValidationErrors('batch_id');
        $this->postJson(route('transfers.store'), array_merge($payload, ['source_location_id' => $this->pharmacy->id, 'destination_location_id' => $this->warehouse->id]))
            ->assertUnprocessable()->assertJsonValidationErrors('quantity');
        $this->assertSame($before, [StockBalance::all()->toJson(), BatchStockBalance::all()->toJson(), InventoryMovement::all()->toJson()]);
        $this->assertReconciled();
    }
}
