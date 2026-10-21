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
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MultiTransferTest extends TestCase
{
    use RefreshDatabase;

    private Medicine $a;

    private Medicine $b;

    private Location $source;

    private Location $target;

    private int $early;

    private int $bBatch;

    private User $staff;

    private User $pharmacist;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-10-21 10:00:00', 'Asia/Jakarta'));
        $this->staff = User::factory()->create(['role' => Role::Pharmacy]);
        $this->pharmacist = User::factory()->create(['role' => Role::Pharmacist]);
        $this->actingAs($this->staff);
        $this->source = Location::create(['code' => 'GDG', 'name' => 'Gudang']);
        $this->target = Location::create(['code' => 'APT', 'name' => 'Apotek']);
        $tablet = Unit::create(['name' => 'Tablet']);
        $bottle = Unit::create(['name' => 'Botol']);
        $this->a = Medicine::create(['code' => 'MULTI-A', 'name' => 'Obat Multi A', 'unit_id' => $tablet->id, 'dosage_form' => 'Tablet']);
        $this->b = Medicine::create(['code' => 'MULTI-B', 'name' => 'Obat Multi B', 'unit_id' => $bottle->id, 'dosage_form' => 'Sirup']);
        $this->early = $this->seedBatch($this->a, 'A-EARLY', 5, '2027-01-01');
        $this->seedBatch($this->a, 'A-LATE', 10, '2027-06-01');
        $this->bBatch = $this->seedBatch($this->b, 'B-ONE', 8, '2027-05-01');
    }

    private function seedBatch(Medicine $medicine, string $batch, float $quantity, string $expiry, ?int $location = null): int
    {
        $this->actingAs($this->pharmacist)->post('/stok-awal', ['reference' => 'SEED-'.$batch, 'medicine_id' => $medicine->id,
            'location_id' => $location ?? $this->source->id, 'batch_number' => $batch, 'quantity' => $quantity,
            'expiry_date' => $expiry, 'occurred_at' => '2026-10-21T08:00'])->assertRedirect()->assertSessionHasNoErrors();
        $this->actingAs($this->staff);

        return Batch::where('number', $batch)->sole()->id;
    }

    private function payload(array $items, string $reference = 'MULTI-TRANSFER'): array
    {
        return ['reference' => $reference, 'source_location_id' => $this->source->id,
            'destination_location_id' => $this->target->id, 'occurred_at' => '2026-10-21T09:00',
            'notes' => 'Simulasi transfer banyak obat', 'items' => $items];
    }

    private function items(): array
    {
        return [['medicine_id' => $this->a->id, 'batch_id' => '', 'quantity' => 8],
            ['medicine_id' => $this->b->id, 'batch_id' => $this->bBatch, 'quantity' => 2]];
    }

    private function snapshot(): array
    {
        return collect(['stock_balances', 'batch_stock_balances', 'inventory_movements', 'audit_logs'])
            ->mapWithKeys(fn ($table) => [$table => DB::table($table)->orderBy('id')->get()->toJson()])->all();
    }

    private function assertReconciled(): void
    {
        $report = app(InventoryReconciliationService::class)->report();
        $this->assertSame(0, $report['mismatched_pairs']);
        $this->assertSame([], $report['batch_identity_issues']);
    }

    public function test_multiple_medicines_share_one_reference_with_fefo_and_explicit_batches(): void
    {
        $this->post('/transfer', $this->payload($this->items()))->assertRedirect()->assertSessionHasNoErrors();
        $rows = InventoryMovement::where('reference', 'MULTI-TRANSFER')->get();
        $this->assertCount(3, $rows);
        $this->assertSame(5.0, (float) $rows->firstWhere('batch_id', $this->early)->quantity);
        $this->assertSame(3.0, (float) $rows->firstWhere('batch_id', Batch::where('number', 'A-LATE')->value('id'))->quantity);
        $this->assertSame(7.0, (float) StockBalance::where('medicine_id', $this->a->id)->where('location_id', $this->source->id)->value('quantity'));
        $this->assertSame(8.0, (float) StockBalance::where('medicine_id', $this->a->id)->where('location_id', $this->target->id)->value('quantity'));
        $this->assertSame(6.0, (float) BatchStockBalance::where('batch_id', $this->bBatch)->where('location_id', $this->source->id)->value('quantity'));
        $this->assertSame(2.0, (float) BatchStockBalance::where('batch_id', $this->bBatch)->where('location_id', $this->target->id)->value('quantity'));
        $audit = DB::table('audit_logs')->where('event', 'inventory.transfer.posted')->sole();
        $this->assertSame(2, json_decode($audit->metadata, true)['item_count']);
        $this->get('/dashboard')->assertOk()->assertViewHas('chart', fn ($chart) => array_sum($chart['transfers']) === 1);
        foreach ([$this->a, $this->b] as $medicine) {
            $this->get('/kartu-stok?medicine_id='.$medicine->id.'&location_id='.$this->target->id.'&start=2026-10-21&end=2026-10-21')
                ->assertOk()->assertSee('MULTI-TRANSFER');
        }
        $this->assertReconciled();
    }

    public function test_second_item_failure_rolls_back_first_item_and_audit(): void
    {
        $before = $this->snapshot();
        $items = $this->items();
        $items[1]['quantity'] = 99;
        $this->from('/transfer')->post('/transfer', $this->payload($items))->assertSessionHasErrors('items.1.batch_id');
        $this->assertStringContainsString('Baris 2:', session('errors')->first('items.1.batch_id'));
        $this->assertSame($before, $this->snapshot());
        $this->get('/transfer')->assertOk()->assertSee('value="99"', false);
        $this->assertReconciled();
    }

    public function test_duplicate_medicine_rows_use_remaining_stock_not_original_stock(): void
    {
        $before = $this->snapshot();
        $items = [['medicine_id' => $this->a->id, 'quantity' => 8], ['medicine_id' => $this->a->id, 'quantity' => 8]];
        $this->post('/transfer', $this->payload($items))->assertSessionHasErrors('items.1.quantity');
        $this->assertSame($before, $this->snapshot());
        $items[1]['quantity'] = 7;
        $this->post('/transfer', $this->payload($items))->assertSessionHasNoErrors();
        $this->assertSame(15.0, (float) StockBalance::where('medicine_id', $this->a->id)->where('location_id', $this->target->id)->value('quantity'));
        $this->assertReconciled();
    }

    public function test_repeated_explicit_batch_cannot_be_overdrawn_across_rows(): void
    {
        $before = $this->snapshot();
        $item = ['medicine_id' => $this->a->id, 'batch_id' => $this->early, 'quantity' => 3];
        $this->post('/transfer', $this->payload([$item, $item]))->assertSessionHasErrors('items.1.batch_id');
        $this->assertSame($before, $this->snapshot());
    }

    public function test_wrong_medicine_wrong_source_and_expired_batches_are_rejected_atomically(): void
    {
        $wrongSource = $this->seedBatch($this->b, 'B-APOTEK', 4, '2027-07-01', $this->target->id);
        $expired = $this->seedBatch($this->b, 'B-EXPIRED', 4, '2026-10-01');
        $before = $this->snapshot();
        foreach ([$this->early, $wrongSource, $expired] as $batch) {
            $items = $this->items();
            $items[1]['batch_id'] = $batch;
            $this->post('/transfer', $this->payload($items))->assertSessionHasErrors('items.1.batch_id');
            $this->assertSame($before, $this->snapshot());
        }
    }

    public function test_inactive_medicine_cannot_be_transferred_as_a_later_item(): void
    {
        $this->b->update(['is_active' => false]);
        $before = $this->snapshot();
        $this->post('/transfer', $this->payload($this->items()))->assertSessionHasErrors('items.1.medicine_id');
        $this->assertSame($before, $this->snapshot());
    }

    public function test_same_or_inactive_locations_do_not_change_stock(): void
    {
        $before = $this->snapshot();
        $payload = $this->payload($this->items());
        $payload['destination_location_id'] = $this->source->id;
        $this->post('/transfer', $payload)->assertSessionHasErrors('destination_location_id');
        $this->target->update(['is_active' => false]);
        $this->post('/transfer', $this->payload($this->items()))->assertSessionHasErrors('destination_location_id');
        $this->assertSame($before, $this->snapshot());
    }

    public function test_empty_spare_rows_are_ignored_but_partial_rows_must_be_completed(): void
    {
        $before = $this->snapshot();
        $items = $this->items();
        $items[] = ['medicine_id' => $this->a->id, 'quantity' => ''];
        $this->post('/transfer', $this->payload($items))->assertSessionHasErrors('items.2.quantity');
        $this->assertSame($before, $this->snapshot());
        $items[2] = ['medicine_id' => '', 'batch_id' => '', 'quantity' => ''];
        $this->post('/transfer', $this->payload($items))->assertSessionHasNoErrors();
        $this->assertSame(3, InventoryMovement::where('reference', 'MULTI-TRANSFER')->count());
        $this->assertReconciled();
    }

    public function test_malformed_empty_and_oversized_items_do_not_fall_back_to_legacy_payload(): void
    {
        $before = $this->snapshot();
        foreach ([null, 'not-an-array', [], [['medicine_id' => '', 'quantity' => '']], array_fill(0, 51, $this->items()[0])] as $items) {
            $payload = $this->payload([]);
            $payload['items'] = $items;
            $payload += ['medicine_id' => $this->a->id, 'quantity' => 1];
            $this->post('/transfer', $payload)->assertSessionHasErrors('items');
            $this->assertSame($before, $this->snapshot());
            $this->get('/transfer')->assertOk();
        }
    }

    public function test_invalid_row_quantities_have_row_errors_and_no_writes(): void
    {
        $before = $this->snapshot();
        foreach ([0, -1, 0.00001, 'invalid', ['unexpected']] as $quantity) {
            $items = $this->items();
            $items[1]['quantity'] = $quantity;
            $this->post('/transfer', $this->payload($items))->assertSessionHasErrors('items.1.quantity');
            $this->assertSame($before, $this->snapshot());
            $this->get('/transfer')->assertOk();
        }
    }

    public function test_repeated_reference_cannot_post_a_second_multi_item_transfer(): void
    {
        $this->post('/transfer', $this->payload($this->items()))->assertSessionHasNoErrors();
        $before = $this->snapshot();
        $this->post('/transfer', $this->payload($this->items()))->assertSessionHasErrors('reference');
        $this->assertSame($before, $this->snapshot());
    }

    public function test_legacy_single_item_requests_still_work_and_guest_remains_read_only(): void
    {
        $payload = $this->payload([]);
        unset($payload['items']);
        $payload += ['medicine_id' => $this->a->id, 'quantity' => 2];
        $this->post('/transfer', $payload)->assertSessionHasNoErrors();
        $this->assertReconciled();
        $before = $this->snapshot();
        $this->actingAs(User::factory()->create(['role' => Role::Guest]));
        $this->get('/transfer')->assertOk()->assertDontSee('data-transfer-form', false)->assertSee('MULTI-TRANSFER');
        $this->post('/transfer', $this->payload($this->items(), 'FORBIDDEN'))->assertForbidden();
        $this->assertSame($before, $this->snapshot());
    }
}
