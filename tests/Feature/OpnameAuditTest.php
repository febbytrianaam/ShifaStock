<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\AuditLog;
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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OpnameAuditTest extends TestCase
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
        $this->staff = User::factory()->create(['role' => Role::Pharmacy]);
        $this->reviewer = User::factory()->create(['role' => Role::Pharmacist]);
        $unit = Unit::create(['name' => 'Tablet']);
        $this->medicine = Medicine::create(['code' => 'AUDIT', 'name' => 'Obat Audit', 'dosage_form' => 'Tablet', 'unit_id' => $unit->id, 'is_active' => true]);
        $this->location = Location::create(['code' => 'GDG', 'name' => 'Gudang', 'is_active' => true]);
        $this->actingAs($this->staff);
        $this->receipt('AUDIT-SEED');
        $this->batch = Batch::where('number', 'AUDIT-BATCH')->sole();
    }

    private function receipt(string $reference, string $action = 'post', string $batch = 'AUDIT-BATCH'): void
    {
        $this->post(route('receipts.store'), ['reference' => $reference, 'action' => $action,
            'destination_location_id' => $this->location->id, 'occurred_at' => '2026-10-18T08:00',
            'items' => [['medicine_id' => $this->medicine->id, 'batch_number' => $batch, 'expiry_date' => '2028-01-01', 'quantity' => 10]],
        ])->assertSessionHasNoErrors();
    }

    private function createCountSession(): StockOpnameSession
    {
        $this->post(route('opname.store'), ['period' => '2026-10', 'location_id' => $this->location->id,
            'cutoff_at' => '2026-10-18T09:00'])->assertSessionHasNoErrors();
        $session = StockOpnameSession::sole();
        $this->post(route('opname.save', $session), ['items' => [$session->items()->sole()->id => ['physical_quantity' => 8]]])
            ->assertSessionHasNoErrors();

        return $session;
    }

    public function test_net_zero_transactions_require_recount_and_old_form_cannot_save(): void
    {
        $session = $this->createCountSession();
        $oldItem = $session->items()->sole();
        $payload = ['medicine_id' => $this->medicine->id, 'batch_id' => $this->batch->id,
            'location_id' => $this->location->id, 'condition' => 'lainnya', 'quantity' => 1, 'occurred_at' => '2026-10-18T10:00'];
        $this->post(route('special-adjustments.store'), $payload + ['reference' => 'AUDIT-OUT', 'direction' => 'decrease'])->assertSessionHasNoErrors();
        $this->post(route('special-adjustments.store'), $payload + ['reference' => 'AUDIT-IN', 'direction' => 'increase'])->assertSessionHasNoErrors();
        $this->actingAs($this->reviewer)->post(route('opname.finalize', $session))->assertSessionHasErrors('items');
        $this->assertSame(10.0, (float) StockBalance::sole()->quantity);
        $this->actingAs($this->staff)->post(route('opname.recount', $session))->assertSessionHasNoErrors();
        $fresh = $session->items()->sole();
        $this->assertNotSame($oldItem->id, $fresh->id);
        $this->assertNull($fresh->physical_quantity);
        $log = AuditLog::where('event', 'inventory.opname_session.recounted')->sole();
        $this->assertSame(8.0, (float) $log->metadata['before']['items'][0]['physical_quantity']);
        $this->post(route('opname.save', $session), ['items' => [$oldItem->id => ['physical_quantity' => 99]]])->assertSessionHasErrors('items');
        $this->post(route('opname.save', $session), ['items' => [$fresh->id => ['physical_quantity' => 9]]])->assertSessionHasNoErrors();
        $this->actingAs($this->reviewer)->post(route('opname.finalize', $session))->assertSessionHasNoErrors();
        $this->assertSame(9.0, (float) StockBalance::sole()->quantity);
        $this->assertSame(0, app(InventoryReconciliationService::class)->report()['mismatched_pairs']);
        $this->actingAs($this->staff)->post(route('opname.recount', $session))->assertStatus(422);
    }

    public function test_new_batch_unit_change_and_legacy_snapshot_block_finalization(): void
    {
        $session = $this->createCountSession();
        $this->receipt('AUDIT-NEW', 'post', 'NEW-BATCH');
        $this->actingAs($this->reviewer)->post(route('opname.finalize', $session))->assertSessionHasErrors('items');
        $this->actingAs($this->staff)->post(route('opname.recount', $session))->assertSessionHasNoErrors();
        $this->assertSame(2, $session->items()->count());
        $items = $session->items()->get()->mapWithKeys(fn ($item) => [$item->id => ['physical_quantity' => 10]])->all();
        $this->post(route('opname.save', $session), ['items' => $items])->assertSessionHasNoErrors();
        // Simulate legacy/direct-SQL corruption; application model updates now reject this change.
        \Illuminate\Support\Facades\DB::table('medicines')->where('id', $this->medicine->id)
            ->update(['unit_id' => Unit::create(['name' => 'Botol'])->id]);
        $this->actingAs($this->reviewer)->post(route('opname.finalize', $session))->assertSessionHasErrors('items');
        $session->refresh();
        $session->stock_snapshot = null;
        $session->save();
        $this->post(route('opname.finalize', $session))->assertSessionHasErrors('items');
        $this->assertDatabaseMissing('inventory_movements', ['type' => 'stock_opname_adjustment']);
        $this->actingAs(User::factory()->create(['role' => Role::Guest]))->post(route('opname.recount', $session))->assertForbidden();
    }

    public function test_posting_draft_never_silently_changes_inactive_destination(): void
    {
        $this->receipt('INACTIVE-DRAFT', 'draft');
        $receipt = InventoryReceipt::where('reference', 'INACTIVE-DRAFT')->sole();
        Location::create(['code' => 'APT', 'name' => 'Apotek', 'is_active' => true]);
        $this->location->update(['is_active' => false]);
        $this->post(route('receipts.post', $receipt))->assertSessionHasErrors('destination_location_id');
        $this->assertSame('draft', $receipt->fresh()->status);
        $this->assertSame($this->location->id, $receipt->fresh()->destination_location_id);
        $this->assertDatabaseMissing('inventory_movements', ['reference' => 'INACTIVE-DRAFT']);
    }

    public function test_stock_endpoints_reject_unrepresentable_positive_quantities(): void
    {
        foreach (['0.00001', '1.00001', '1000000000000'] as $quantity) {
            $payload = ['reference' => 'BAD-QTY', 'medicine_id' => $this->medicine->id,
                'source_location_id' => $this->location->id, 'batch_id' => $this->batch->id,
                'quantity' => $quantity, 'occurred_at' => '2026-10-18T09:00'];
            $this->post(route('issues.store'), $payload)->assertSessionHasErrors('quantity');
        }
        $this->assertSame(10.0, (float) BatchStockBalance::sole()->quantity);
        $this->assertDatabaseMissing('inventory_movements', ['reference' => 'BAD-QTY']);
    }
}
