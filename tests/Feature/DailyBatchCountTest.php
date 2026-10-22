<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Batch;
use App\Models\BatchStockBalance;
use App\Models\InventoryMovement;
use App\Models\Location;
use App\Models\Medicine;
use App\Models\StockBalance;
use App\Models\Unit;
use App\Models\User;
use App\Services\InventoryReconciliationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DailyBatchCountTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;
    private User $pharmacist;
    private Medicine $medicine;
    private Location $location;
    private int $checkId;
    private int $itemId;
    private int $early;
    private int $late;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(\Carbon\Carbon::parse('2026-10-16 10:00', 'Asia/Jakarta'));
        $this->staff = User::factory()->create(['role' => Role::Pharmacy]);
        $this->pharmacist = User::factory()->create(['role' => Role::Pharmacist]);
        $unit = Unit::create(['name' => 'Tablet']);
        $this->medicine = Medicine::create(['code' => 'COUNT', 'name' => 'Obat Hitung', 'unit_id' => $unit->id, 'dosage_form' => 'Tablet', 'is_active' => true]);
        $this->location = Location::create(['code' => 'GDG', 'name' => 'Gudang', 'is_active' => true]);
        $this->actingAs($this->staff)->post(route('receipts.store'), ['reference' => 'COUNT-INITIAL', 'occurred_at' => '2026-10-16T08:00', 'destination_location_id' => $this->location->id, 'items' => [
            ['medicine_id' => $this->medicine->id, 'batch_number' => 'EARLY', 'expiry_date' => '2026-11-01', 'quantity' => 6],
            ['medicine_id' => $this->medicine->id, 'batch_number' => 'LATE', 'expiry_date' => '2027-01-01', 'quantity' => 4],
        ]])->assertSessionHasNoErrors();
        $this->early = Batch::where('number', 'EARLY')->value('id');
        $this->late = Batch::where('number', 'LATE')->value('id');
        $this->post(route('nightly.store'), ['date' => '2026-10-16', 'location_id' => $this->location->id])->assertSessionHasNoErrors();
        $this->checkId = DB::table('nightly_checks')->value('id');
        $this->itemId = DB::table('nightly_check_items')->value('id');
    }

    private function submit(int $quantity, array $counts, string $unit = 'Tablet')
    {
        return $this->actingAs($this->staff)->postJson(route('nightly.save', $this->checkId), [
            'draft_revision' => DB::table('nightly_checks')->where('id', $this->checkId)->value('draft_revision'),
            'action' => 'submit', 'items' => [$this->itemId => ['quantity' => $quantity, 'unit' => $unit, 'batch_counts' => $counts]],
        ]);
    }

    private function approve()
    {
        return $this->actingAs($this->pharmacist)->postJson(route('nightly.review', $this->checkId), ['action' => 'approve']);
    }

    private function assertStock(int $total, int $early, int $late): void
    {
        $this->assertSame((float) $total, (float) StockBalance::where('medicine_id', $this->medicine->id)->where('location_id', $this->location->id)->value('quantity'));
        $this->assertSame((float) $early, (float) BatchStockBalance::where('batch_id', $this->early)->where('location_id', $this->location->id)->value('quantity'));
        $this->assertSame((float) $late, (float) BatchStockBalance::where('batch_id', $this->late)->where('location_id', $this->location->id)->value('quantity'));
        $this->assertSame(0, app(InventoryReconciliationService::class)->report()['mismatched_pairs']);
    }

    public function test_decrease_updates_total_each_batch_and_batch_stock_cards_together(): void
    {
        $this->submit(7, [$this->early => 3, $this->late => 4])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertStock(10, 6, 4);
        $this->approve()->assertRedirect()->assertSessionHasNoErrors();
        $this->assertStock(7, 3, 4);
        $this->assertDatabaseHas('inventory_movements', ['reference' => 'CHECK-'.$this->checkId, 'batch_id' => $this->early, 'quantity' => 3]);
        $this->get(route('stock-card.index', ['medicine_id' => $this->medicine->id, 'location_id' => $this->location->id, 'batch_id' => $this->early, 'start' => '2026-10-16', 'end' => '2026-10-16']))
            ->assertOk()->assertViewHas('rows', fn ($rows) => (float) $rows->last()->getAttribute('card_running_balance') === 3.0);
        $this->approve()->assertConflict();
        $this->assertStock(7, 3, 4);
    }

    public function test_equal_total_can_move_counts_between_batches_without_losing_ledger_agreement(): void
    {
        $this->submit(10, [$this->early => 4, $this->late => 6])->assertRedirect();
        $this->approve()->assertRedirect();
        $this->assertStock(10, 4, 6);
        $this->assertSame(2, InventoryMovement::where('reference', 'CHECK-'.$this->checkId)->count());
    }

    public function test_explicit_zero_is_preserved_for_every_batch(): void
    {
        $this->submit(0, [$this->early => 0, $this->late => 0])->assertRedirect();
        $this->approve()->assertRedirect();
        $this->assertStock(0, 0, 0);
    }

    public function test_invalid_sum_missing_batch_wrong_owner_and_wrong_unit_cannot_be_submitted(): void
    {
        $key = 'items.'.$this->itemId.'.batch_counts';
        $this->submit(7, [$this->early => 2, $this->late => 4])->assertUnprocessable()->assertJsonValidationErrors($key);
        $this->submit(7, [$this->early => 7])->assertUnprocessable()->assertJsonValidationErrors($key);
        $other = Medicine::create(['code' => 'OTHER', 'name' => 'Obat lain', 'unit_id' => $this->medicine->unit_id]);
        $batch = Batch::create(['medicine_id' => $other->id, 'number' => 'WRONG', 'expiry_date' => '2027-01-01']);
        $this->submit(7, [$batch->id => 7])->assertUnprocessable()->assertJsonValidationErrors('items');
        $this->submit(7, [$this->early => 3, $this->late => 4], 'Box')->assertUnprocessable()->assertJsonValidationErrors('items.'.$this->itemId.'.unit');
        $this->assertDatabaseHas('nightly_checks', ['id' => $this->checkId, 'status' => 'draft']);
        $this->assertStock(10, 6, 4);
    }

    public function test_stock_changes_while_awaiting_review_require_return_and_resubmission(): void
    {
        $this->submit(7, [$this->early => 3, $this->late => 4])->assertRedirect();
        $this->actingAs($this->staff)->post(route('receipts.store'), ['reference' => 'AFTER-SUBMIT', 'medicine_id' => $this->medicine->id,
            'batch_number' => 'EARLY', 'expiry_date' => '2026-11-01', 'quantity' => 1, 'destination_location_id' => $this->location->id, 'occurred_at' => '2026-10-16T10:01'])->assertSessionHasNoErrors();
        $this->approve()->assertUnprocessable()->assertJsonValidationErrors('items');
        $this->assertStock(11, 7, 4);
        $this->postJson(route('nightly.review', $this->checkId), ['action' => 'return', 'review_note' => 'Hitung ulang setelah penerimaan'])->assertRedirect();
        $this->submit(7, [$this->early => 3, $this->late => 4])->assertRedirect();
        $this->approve()->assertRedirect();
        $this->assertStock(7, 3, 4);
    }

    public function test_legacy_submitted_report_without_snapshot_cannot_update_stock(): void
    {
        DB::table('nightly_check_items')->where('id', $this->itemId)->update(['quantity' => 7, 'unit' => 'Tablet']);
        DB::table('nightly_checks')->where('id', $this->checkId)->update(['status' => 'submitted']);
        $this->approve()->assertUnprocessable()->assertJsonValidationErrors('items');
        $this->assertStock(10, 6, 4);
    }

    public function test_json_batch_payload_is_persisted_and_rendered_for_review(): void
    {
        $this->postJson(route('nightly.save', $this->checkId), ['draft_revision' => 0, 'action' => 'submit',
            'items' => [$this->itemId => ['quantity' => 13, 'unit' => 'Tablet']],
            'batch_counts_payload' => json_encode([$this->itemId => [$this->early => 9, $this->late => 4]]),
        ])->assertRedirect();
        $this->get(route('nightly.show', $this->checkId))->assertOk()->assertSee('Hasil hitung per batch')->assertSee('9');
        $this->approve()->assertRedirect();
        $this->assertStock(13, 9, 4);
    }

    public function test_net_zero_transfers_after_submission_are_still_detected(): void
    {
        $this->submit(7, [$this->early => 3, $this->late => 4])->assertRedirect();
        $other = Location::create(['code' => 'APT', 'name' => 'Apotek', 'is_active' => true]);
        $payload = ['reference' => 'OUT', 'medicine_id' => $this->medicine->id, 'batch_id' => $this->early,
            'source_location_id' => $this->location->id, 'destination_location_id' => $other->id, 'quantity' => 1, 'occurred_at' => '2026-10-16T10:01'];
        $this->actingAs($this->staff)->post(route('transfers.store'), $payload)->assertSessionHasNoErrors();
        $this->post(route('transfers.store'), array_merge($payload, ['reference' => 'BACK', 'source_location_id' => $other->id, 'destination_location_id' => $this->location->id]))->assertSessionHasNoErrors();
        $this->approve()->assertUnprocessable()->assertJsonValidationErrors('items');
        $this->assertStock(10, 6, 4);
    }

    public function test_later_item_failure_rolls_back_changes_to_earlier_items_in_the_same_approval(): void
    {
        $other = Medicine::create(['code' => 'SECOND', 'name' => 'Obat Kedua', 'unit_id' => $this->medicine->unit_id, 'dosage_form' => 'Tablet', 'is_active' => true]);
        $receipt = ['reference' => 'SECOND', 'medicine_id' => $other->id, 'batch_number' => 'SECOND-BATCH', 'expiry_date' => '2027-01-01',
            'quantity' => 5, 'destination_location_id' => $this->location->id, 'occurred_at' => '2026-10-16T09:00'];
        $this->post(route('receipts.store'), $receipt)->assertSessionHasNoErrors();
        $batchId = Batch::where('number', 'SECOND-BATCH')->value('id');
        $otherItem = DB::table('nightly_check_items')->insertGetId(['nightly_check_id' => $this->checkId, 'medicine_id' => $other->id, 'code' => $other->code, 'name' => $other->name, 'unit' => 'Tablet']);
        $this->postJson(route('nightly.save', $this->checkId), ['draft_revision' => 0, 'action' => 'submit', 'items' => [
            $this->itemId => ['quantity' => 7, 'unit' => 'Tablet', 'batch_counts' => [$this->early => 3, $this->late => 4]],
            $otherItem => ['quantity' => 3, 'unit' => 'Tablet', 'batch_counts' => [$batchId => 3]],
        ]])->assertRedirect();
        $this->post(route('receipts.store'), array_merge($receipt, ['reference' => 'SECOND-AFTER', 'quantity' => 1]))->assertSessionHasNoErrors();
        $this->approve()->assertUnprocessable();
        $this->assertStock(10, 6, 4);
        $this->assertSame(0, InventoryMovement::where('reference', 'CHECK-'.$this->checkId)->count());
        $this->assertDatabaseHas('nightly_checks', ['id' => $this->checkId, 'status' => 'submitted']);
    }

    public function test_web_submission_rejects_stock_changed_since_the_form_was_opened(): void
    {
        $revision = InventoryMovement::max('id');
        $this->post(route('receipts.store'), ['reference' => 'WHILE-COUNTING', 'medicine_id' => $this->medicine->id,
            'batch_number' => 'EARLY', 'expiry_date' => '2026-11-01', 'quantity' => 1,
            'destination_location_id' => $this->location->id, 'occurred_at' => '2026-10-16T10:01'])->assertSessionHasNoErrors();
        $this->postJson(route('nightly.save', $this->checkId), ['draft_revision' => 0, 'action' => 'submit', 'observed_revision' => $revision,
            'items' => [$this->itemId => ['quantity' => 7, 'unit' => 'Tablet', 'batch_counts' => [$this->early => 3, $this->late => 4]]],
        ])->assertUnprocessable()->assertJsonValidationErrors('items');
        $this->assertDatabaseHas('nightly_checks', ['id' => $this->checkId, 'status' => 'draft']);
        $this->assertStock(11, 7, 4);
    }
}
