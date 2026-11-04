<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Batch;
use App\Models\BatchStockBalance;
use App\Models\InventoryMovement;
use App\Models\Location;
use App\Models\Medicine;
use App\Models\StockBalance;
use App\Models\StockOpnameSession;
use App\Models\Unit;
use App\Models\User;
use App\Services\InventoryReconciliationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OpnameAuditRegressionTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;
    private User $reviewer;
    private Medicine $medicine;
    private Location $location;
    private Batch $batch;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('sqlite', DB::connection()->getDriverName());
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        $this->travelTo(\Carbon\Carbon::parse('2026-11-03 10:00:00', 'Asia/Jakarta'));
        $this->staff = User::factory()->create(['role' => Role::Pharmacy]);
        $this->reviewer = User::factory()->create(['role' => Role::Pharmacist]);
        $unit = Unit::create(['name' => 'Tablet']);
        $this->medicine = Medicine::create(['code' => 'REG-OPN', 'name' => 'Obat Regresi', 'dosage_form' => 'Tablet', 'unit_id' => $unit->id, 'is_active' => true]);
        $this->location = Location::create(['code' => 'GDG', 'name' => 'Gudang', 'is_active' => true]);
        $this->actingAs($this->staff)->post(route('receipts.store'), [
            'reference' => 'REG-SEED', 'action' => 'post', 'destination_location_id' => $this->location->id,
            'occurred_at' => '2026-11-03T08:00',
            'items' => [['medicine_id' => $this->medicine->id, 'batch_number' => 'REG-BATCH', 'expiry_date' => '2028-01-01', 'quantity' => 10]],
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->batch = Batch::sole();
    }

    private function createOpnameSession(string $period = '2026-11', ?Location $location = null): StockOpnameSession
    {
        $this->actingAs($this->staff)->post(route('opname.store'), [
            'period' => $period, 'location_id' => ($location ?? $this->location)->id, 'cutoff_at' => '2026-11-03T09:00',
        ])->assertRedirect()->assertSessionHasNoErrors();

        return StockOpnameSession::latest('id')->firstOrFail();
    }

    private function version(StockOpnameSession $session): string
    {
        $html = $this->get(route('preview.opname', ['period' => $session->period, 'session' => $session->id]))->assertOk()->getContent();
        $this->assertSame(1, preg_match('/name="version" value="([^"]+)"/', $html, $match));

        return $match[1];
    }

    private function saveCount(StockOpnameSession $session, int $quantity): void
    {
        $this->post(route('opname.save', $session), ['version' => $this->version($session),
            'items' => [$session->items()->sole()->id => ['physical_quantity' => $quantity]],
        ])->assertSessionHasNoErrors();
    }

    public function test_draft_references_are_reserved_across_locations_and_periods(): void
    {
        $first = $this->createOpnameSession();
        $otherLocation = Location::create(['code' => 'APT', 'name' => 'Apotek', 'is_active' => true]);
        $second = $this->createOpnameSession('2026-11', $otherLocation);
        $third = $this->createOpnameSession('2026-12');
        $this->assertSame(['OPN-2026-001', 'OPN-2026-002', 'OPN-2026-003'], [$first->reference, $second->reference, $third->reference]);
        $this->assertDatabaseCount('stock_opname_sessions', 3);
        $this->assertSame(0, InventoryMovement::where('type', 'stock_opname_adjustment')->count());
    }

    public function test_matching_monthly_count_blocks_an_older_submitted_daily_count(): void
    {
        $this->post(route('nightly.store'), ['date' => '2026-11-02', 'location_id' => $this->location->id])->assertSessionHasNoErrors();
        $check = DB::table('nightly_checks')->sole();
        $item = DB::table('nightly_check_items')->sole();
        $observedRevision = $this->get(route('nightly.show', $check->id))->assertOk()->viewData('observedRevision');
        $this->post(route('nightly.save', $check->id), ['draft_revision' => $check->draft_revision,
            'observed_revision' => $observedRevision, 'action' => 'submit',
            'items' => [$item->id => ['quantity' => 8, 'unit' => 'Tablet', 'batch_counts' => [$this->batch->id => 8]]],
        ])->assertSessionHasNoErrors();
        $session = $this->createOpnameSession();
        $this->saveCount($session, 10);
        $this->actingAs($this->reviewer)->post(route('opname.finalize', $session), ['version' => $this->version($session)])->assertSessionHasNoErrors();
        $this->post(route('nightly.review', $check->id), ['action' => 'approve'])->assertSessionHasErrors('items');
        $this->assertDatabaseHas('nightly_checks', ['id' => $check->id, 'status' => 'submitted']);
        $trace = InventoryMovement::where('reference', $session->reference)->sole();
        $this->assertSame(0.0, (float) $trace->quantity);
        $this->assertSame($this->location->id, $trace->destination_location_id);
        $this->assertSame($this->batch->id, $trace->batch_id);
        $this->assertSame($this->reviewer->id, $trace->created_by);
        $this->assertNotNull(StockBalance::sole()->last_counted_at);
        $this->assertSame(10.0, (float) StockBalance::sole()->quantity);
        $this->assertSame(10.0, (float) BatchStockBalance::sole()->quantity);
        $this->assertSame(0, app(InventoryReconciliationService::class)->report()['mismatched_pairs']);
    }

    public function test_zero_balance_batch_can_be_counted_and_corrected_without_losing_rows(): void
    {
        $this->post(route('issues.store'), ['reference' => 'REG-EMPTY', 'medicine_id' => $this->medicine->id,
            'source_location_id' => $this->location->id, 'batch_id' => $this->batch->id,
            'quantity' => 10, 'occurred_at' => '2026-11-03T08:30',
        ])->assertSessionHasNoErrors();
        $balanceId = BatchStockBalance::sole()->id;
        $session = $this->createOpnameSession();
        $this->assertSame(1, $session->items()->count());
        $item = $session->items()->sole();
        $this->assertSame(0.0, (float) $item->system_quantity);
        $this->saveCount($session, 3);
        $this->actingAs($this->reviewer)->post(route('opname.finalize', $session), ['version' => $this->version($session)])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('batch_stock_balances', ['id' => $balanceId, 'quantity' => 3]);
        $this->assertDatabaseHas('stock_opname_items', ['id' => $item->id, 'physical_quantity' => 3]);
        $this->assertSame(3.0, (float) StockBalance::sole()->quantity);
        $this->assertDatabaseHas('inventory_movements', ['reference' => 'REG-EMPTY', 'quantity' => 10]);
        $this->assertSame(0, app(InventoryReconciliationService::class)->report()['mismatched_pairs']);
    }

    public function test_stale_forms_cannot_save_finalize_or_recount_newer_counts(): void
    {
        $session = $this->createOpnameSession();
        $version = $this->version($session);
        $item = $session->items()->sole();
        $this->saveCount($session, 9);
        $this->post(route('opname.save', $session), ['version' => $version,
            'items' => [$item->id => ['physical_quantity' => 99]],
        ])->assertSessionHasErrors('version');
        $this->post(route('opname.recount', $session), ['version' => $version])->assertSessionHasErrors('version');
        $this->actingAs($this->reviewer)->post(route('opname.finalize', $session), ['version' => $version])->assertSessionHasErrors('version');
        $this->assertSame(9.0, (float) $item->fresh()->physical_quantity);
        $this->assertSame('draft', $session->fresh()->status);
        $this->assertSame(10.0, (float) StockBalance::sole()->quantity);
        $this->assertSame(0, InventoryMovement::where('reference', $session->reference)->count());
        $this->post(route('opname.finalize', $session), ['version' => $this->version($session)])->assertSessionHasNoErrors();
        $this->assertSame(9.0, (float) StockBalance::sole()->quantity);
    }

    public function test_mutations_require_a_present_authentic_session_specific_version(): void
    {
        $session = $this->createOpnameSession();
        $this->saveCount($session, 10);
        $other = $this->createOpnameSession('2026-12');
        $foreignVersion = $this->version($other);
        foreach ([null, str_repeat('a', 64), $foreignVersion] as $version) {
            $payload = $version === null ? [] : ['version' => $version];
            $this->actingAs($this->staff)->post(route('opname.save', $session), $payload + [
                'items' => [$session->items()->sole()->id => ['physical_quantity' => 50]],
            ])->assertSessionHasErrors('version');
            $this->post(route('opname.recount', $session), $payload)->assertSessionHasErrors('version');
            $this->actingAs($this->reviewer)->post(route('opname.finalize', $session), $payload)->assertSessionHasErrors('version');
        }
        $this->assertSame(10.0, (float) $session->items()->sole()->physical_quantity);
        $this->assertSame('draft', $session->fresh()->status);
    }

    public function test_recount_preserves_item_identity_and_rejects_the_pre_recount_form(): void
    {
        $session = $this->createOpnameSession();
        $this->saveCount($session, 8);
        $item = $session->items()->sole();
        $version = $this->version($session);
        $this->post(route('opname.recount', $session), ['version' => $version])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('stock_opname_items', ['id' => $item->id, 'physical_quantity' => null]);
        $this->post(route('opname.save', $session), ['version' => $version,
            'items' => [$item->id => ['physical_quantity' => 99]],
        ])->assertSessionHasErrors('version');
        $this->assertNull($item->fresh()->physical_quantity);
        $this->assertSame(8.0, (float) \App\Models\AuditLog::where('event', 'inventory.opname_session.recounted')->sole()->metadata['before']['items'][0]['physical_quantity']);
    }
}
