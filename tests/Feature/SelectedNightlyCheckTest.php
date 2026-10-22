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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SelectedNightlyCheckTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    private User $pharmacist;

    private Location $location;

    private Medicine $first;

    private Medicine $other;

    protected function setUp(): void
    {
        parent::setUp();
        $this->staff = User::factory()->create(['role' => Role::Pharmacy]);
        $this->pharmacist = User::factory()->create(['role' => Role::Pharmacist]);
        $this->location = Location::create(['code' => 'APT', 'name' => 'Apotek']);
        $unit = Unit::create(['name' => 'Tablet']);
        $this->first = Medicine::create(['code' => 'SELECT-A', 'name' => 'Obat Pilihan', 'unit_id' => $unit->id, 'dosage_form' => 'Tablet', 'is_active' => true]);
        $this->other = Medicine::create(['code' => 'SELECT-B', 'name' => 'Obat Lain', 'unit_id' => $unit->id, 'dosage_form' => 'Tablet', 'is_active' => true]);
        $this->actingAs($this->staff);
    }

    private function start(array $overrides = []): int
    {
        $this->post(route('nightly.store'), array_replace([
            'date' => '2026-01-01', 'location_id' => $this->location->id,
            'scope' => 'selected', 'medicine_ids' => [$this->first->id],
        ], $overrides))->assertRedirect()->assertSessionHasNoErrors();

        return DB::table('nightly_checks')->latest('id')->value('id');
    }

    private function approved(string $date, array $values, ?int $location = null): int
    {
        $id = DB::table('nightly_checks')->insertGetId([
            'date' => $date, 'location_id' => $location ?? $this->location->id,
            'created_by' => $this->staff->id, 'status' => 'approved',
            'scope' => count($values) === 1 ? 'selected' : 'complete',
        ]);
        foreach ($values as $medicineId => [$quantity, $low]) {
            $medicine = Medicine::findOrFail($medicineId);
            DB::table('nightly_check_items')->insert([
                'nightly_check_id' => $id, 'medicine_id' => $medicineId, 'code' => $medicine->code,
                'name' => $medicine->name, 'unit' => 'Tablet', 'quantity' => $quantity, 'is_low' => $low,
            ]);
        }

        return $id;
    }

    public function test_selected_report_contains_only_chosen_medicines_and_labels_its_scope(): void
    {
        $this->get(route('preview.nightly'))->assertOk()->assertSee('Obat tertentu')->assertSee('data-nightly-start-form', false);
        $id = $this->start();
        $this->assertDatabaseHas('nightly_checks', ['id' => $id, 'scope' => 'selected']);
        $this->assertDatabaseCount('nightly_check_items', 1);
        $this->assertDatabaseHas('nightly_check_items', ['nightly_check_id' => $id, 'medicine_id' => $this->first->id, 'quantity' => null]);
        $this->get(route('nightly.show', $id))->assertOk()->assertSee('Obat tertentu')
            ->assertSee('Obat Pilihan')->assertViewHas('items', fn ($items) => $items->count() === 1 && $items->first()->medicine_id === $this->first->id);
    }

    public function test_selection_is_required_unique_and_limited_to_eligible_master_medicines(): void
    {
        $base = ['date' => '2026-01-01', 'location_id' => $this->location->id, 'scope' => 'selected'];
        $this->postJson(route('nightly.store'), $base)->assertUnprocessable()->assertJsonValidationErrors('medicine_ids');
        $this->postJson(route('nightly.store'), $base + ['medicine_ids' => []])->assertUnprocessable();
        $this->postJson(route('nightly.store'), $base + ['medicine_ids' => [$this->first->id, $this->first->id]])->assertUnprocessable();
        $this->postJson(route('nightly.store'), $base + ['medicine_ids' => [999999]])->assertUnprocessable();
        $this->other->update(['is_active' => false]);
        $this->postJson(route('nightly.store'), $base + ['medicine_ids' => [$this->other->id]])->assertUnprocessable()->assertJsonValidationErrors('medicine_ids');
        $this->assertDatabaseCount('nightly_checks', 0);
    }

    private function unitSelect(int $id): \DOMElement
    {
        $response = $this->get(route('nightly.show', $id))->assertOk();
        $document = new \DOMDocument;
        $document->loadHTML($response->getContent(), LIBXML_NOERROR | LIBXML_NOWARNING);
        $select = (new \DOMXPath($document))->query('//select[@data-nightly-unit]')->item(0);
        $this->assertInstanceOf(\DOMElement::class, $select);

        return $select;
    }

    public function test_count_unit_dropdown_automatically_uses_only_the_medicine_stock_unit(): void
    {
        Unit::create(['name' => 'Box']);
        $id = $this->start();
        $select = $this->unitSelect($id);
        $options = $select->getElementsByTagName('option');
        $this->assertSame(1, $options->length);
        $this->assertSame('Tablet', $options->item(0)->getAttribute('value'));
        $this->assertTrue($options->item(0)->hasAttribute('selected'));
        $this->assertSame('items['.DB::table('nightly_check_items')->value('id').'][unit]', $select->getAttribute('name'));
    }

    public function test_missing_master_unit_uses_active_references_without_guessing_and_preserves_positive_stock_guard(): void
    {
        Unit::create(['name' => 'Referensi Nonaktif', 'is_active' => false]);
        $this->first->update(['unit_id' => null]);
        $id = $this->start();
        $select = $this->unitSelect($id);
        $values = [];
        foreach ($select->getElementsByTagName('option') as $option) {
            $values[] = $option->getAttribute('value');
        }
        $this->assertSame(['', 'Tablet'], $values);
        $this->get(route('nightly.show', $id))->assertSee('Satuan Data Obat belum diatur');
        $item = DB::table('nightly_check_items')->sole();
        $this->postJson(route('nightly.save', $id), ['draft_revision' => 0, 'action' => 'submit', 'items' => [$item->id => ['quantity' => 1, 'unit' => 'Tablet']]])
            ->assertUnprocessable()->assertJsonValidationErrors('items.'.$item->id.'.unit');
        $this->assertDatabaseHas('nightly_checks', ['id' => $id, 'status' => 'draft']);
    }

    public function test_old_draft_with_another_unit_requires_confirmation_without_reinterpreting_its_quantity(): void
    {
        $id = $this->start();
        DB::table('nightly_check_items')->where('nightly_check_id', $id)->update(['quantity' => 5, 'unit' => 'Box']);
        $select = $this->unitSelect($id);
        $options = $select->getElementsByTagName('option');
        $this->assertSame('', $options->item(0)->getAttribute('value'));
        $this->assertFalse($options->item(1)->hasAttribute('selected'));
        $this->get(route('nightly.show', $id))->assertSee('Satuan tersimpan berbeda dari Data Obat');
        $this->assertDatabaseHas('nightly_check_items', ['nightly_check_id' => $id, 'quantity' => 5, 'unit' => 'Box']);
    }

    public function test_complete_mode_accepts_incomplete_drafts_and_requires_partial_confirmation(): void
    {
        $id = $this->start(['scope' => 'complete']);
        $this->assertDatabaseCount('nightly_check_items', 2);
        $items = DB::table('nightly_check_items')->where('nightly_check_id', $id)->get();
        $payload = $items->mapWithKeys(fn ($item) => [$item->id => ['quantity' => $item->medicine_id === $this->first->id ? 0 : null, 'unit' => 'Tablet']])->all();
        $this->postJson(route('nightly.save', $id), ['draft_revision' => 0, 'action' => 'save', 'items' => $payload])->assertRedirect();
        $this->postJson(route('nightly.save', $id), ['draft_revision' => 1, 'action' => 'submit', 'items' => $payload])->assertUnprocessable()->assertJsonValidationErrors('confirm_partial');
        $this->assertDatabaseHas('nightly_checks', ['id' => $id, 'scope' => 'complete', 'status' => 'draft']);
    }

    public function test_selected_mode_also_requires_confirmation_before_sending_only_some_counts(): void
    {
        $id = $this->start(['medicine_ids' => [$this->first->id, $this->other->id]]);
        $items = DB::table('nightly_check_items')->where('nightly_check_id', $id)->get();
        $payload = $items->mapWithKeys(fn ($item) => [$item->id => ['quantity' => null, 'unit' => 'Tablet']])->all();
        $this->postJson(route('nightly.save', $id), ['draft_revision' => 0, 'action' => 'save', 'items' => $payload])->assertRedirect();
        $payload[$items->first()->id]['quantity'] = 0;
        $this->postJson(route('nightly.save', $id), ['draft_revision' => 1, 'action' => 'submit', 'items' => $payload])->assertUnprocessable();
        $this->assertDatabaseHas('nightly_checks', ['id' => $id, 'status' => 'draft']);
    }

    public function test_approval_changes_only_selected_medicine_at_selected_location_and_preserves_batch_rules(): void
    {
        $warehouse = Location::create(['code' => 'GDG', 'name' => 'Gudang']);
        $this->actingAs($this->pharmacist);
        foreach ([[$this->first, $this->location, 10], [$this->other, $this->location, 25], [$this->first, $warehouse, 40]] as $index => [$medicine, $location, $quantity]) {
            $this->post(route('opening-balances.store'), [
                'reference' => 'SELECT-STOCK-'.$index, 'medicine_id' => $medicine->id, 'location_id' => $location->id,
                'batch_number' => 'SELECT-BATCH-'.$index, 'expiry_date' => now()->addYear()->toDateString(),
                'quantity' => $quantity, 'occurred_at' => now('Asia/Jakarta')->format('Y-m-d\TH:i'),
            ])->assertRedirect()->assertSessionHasNoErrors();
        }
        $this->actingAs($this->staff);
        $id = $this->start(['date' => now('Asia/Jakarta')->toDateString()]);
        $item = DB::table('nightly_check_items')->where('nightly_check_id', $id)->sole();
        $batch = Batch::where('number', 'SELECT-BATCH-0')->sole();
        $payload = ['draft_revision' => 0, 'action' => 'submit', 'items' => [$item->id => ['quantity' => 0, 'unit' => 'Tablet', 'requested_quantity' => 30]]];
        $this->postJson(route('nightly.save', $id), $payload)->assertUnprocessable()->assertJsonValidationErrors('items.'.$item->id.'.batch_counts');
        $payload['items'][$item->id]['batch_counts'] = [$batch->id => 0];
        $this->postJson(route('nightly.save', $id), $payload)->assertRedirect();
        $this->assertSame(10.0, (float) StockBalance::where('medicine_id', $this->first->id)->where('location_id', $this->location->id)->value('quantity'));
        $this->postJson(route('nightly.review', $id), ['action' => 'approve'])->assertForbidden();
        $beforeOther = StockBalance::where('medicine_id', $this->other->id)->first()->getRawOriginal();
        $beforeWarehouse = StockBalance::where('location_id', $warehouse->id)->first()->getRawOriginal();
        $beforeOtherBatches = BatchStockBalance::where('medicine_id', $this->other->id)->get()->toArray();
        $this->actingAs($this->pharmacist)->postJson(route('nightly.review', $id), ['action' => 'approve'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(0.0, (float) StockBalance::where('medicine_id', $this->first->id)->where('location_id', $this->location->id)->value('quantity'));
        $this->assertSame(0.0, (float) BatchStockBalance::where('batch_id', $batch->id)->where('location_id', $this->location->id)->value('quantity'));
        $this->assertSame($beforeOther, StockBalance::where('medicine_id', $this->other->id)->first()->getRawOriginal());
        $this->assertSame($beforeWarehouse, StockBalance::where('location_id', $warehouse->id)->first()->getRawOriginal());
        $this->assertSame($beforeOtherBatches, BatchStockBalance::where('medicine_id', $this->other->id)->get()->toArray());
        $this->assertSame(1, InventoryMovement::where('reference', 'CHECK-'.$id)->count());
        $this->get(route('shortages.index'))->assertOk()->assertViewHas('rows', fn ($rows) => $rows->count() === 1 && $rows->first()->requested_quantity === 30);
    }

    public function test_existing_daily_report_is_reopened_without_changing_scope_or_items(): void
    {
        $id = $this->start();
        $this->post(route('nightly.store'), ['date' => '2026-01-01', 'location_id' => $this->location->id, 'scope' => 'complete'])
            ->assertRedirect(route('nightly.show', $id));
        $this->assertDatabaseCount('nightly_checks', 1);
        $this->assertDatabaseCount('nightly_check_items', 1);
        $this->assertDatabaseHas('nightly_checks', ['id' => $id, 'scope' => 'selected']);
    }

    public function test_guest_can_read_but_cannot_start_selected_checks(): void
    {
        $id = $this->start();
        $guest = User::factory()->create(['role' => Role::Guest]);
        $this->actingAs($guest)->get(route('preview.nightly'))->assertOk()->assertDontSee('data-nightly-start-form', false);
        $this->get(route('nightly.show', $id))->assertOk()->assertDontSee('data-nightly-count-form', false);
        $this->postJson(route('nightly.store'), ['date' => '2026-01-02', 'location_id' => $this->location->id, 'scope' => 'selected', 'medicine_ids' => [$this->first->id]])->assertForbidden();
        $this->assertDatabaseCount('nightly_checks', 1);
    }

    public function test_partial_reports_preserve_other_shortages_and_remove_only_rechecked_shortages(): void
    {
        $this->approved('2026-01-01', [$this->first->id => [0, false], $this->other->id => [2, true]]);
        $this->approved('2026-01-02', [$this->first->id => [5, false]]);
        $this->get(route('shortages.index'))->assertOk()
            ->assertViewHas('summary', fn ($summary) => $summary['total'] === 1 && $summary['low'] === 1 && $summary['empty'] === 0)
            ->assertViewHas('rows', fn ($rows) => $rows->count() === 1 && $rows->first()->medicine_id === $this->other->id);
        // These history-only fixtures do not create current-stock minimum alerts.
        $this->get(route('dashboard'))->assertOk()->assertViewHas('stats', fn ($stats) => $stats['shortages'] === 0);
        $id = $this->start(['date' => '2026-01-03', 'scope' => 'complete']);
        $this->get(route('nightly.show', $id))->assertOk()->assertViewHas('baseline', function ($baseline) {
            return $baseline[$this->first->id]->quantity === 5 && $baseline[$this->first->id]->checked_date === '2026-01-02'
                && $baseline[$this->other->id]->quantity === 2 && $baseline[$this->other->id]->checked_date === '2026-01-01';
        });
    }

    public function test_results_are_latest_per_medicine_and_location_and_exclude_drafts_and_future_baselines(): void
    {
        $warehouse = Location::create(['code' => 'GDG', 'name' => 'Gudang']);
        $this->approved('2026-01-03', [$this->first->id => [4, false]]);
        $this->approved('2026-01-01', [$this->first->id => [0, false], $this->other->id => [0, false]]);
        $this->approved('2026-01-02', [$this->first->id => [0, false]], $warehouse->id);
        $id = $this->start(['date' => '2026-01-02']);
        $this->get(route('nightly.show', $id))->assertOk()->assertViewHas('baseline', fn ($baseline) => $baseline[$this->first->id]->quantity === 0 && $baseline[$this->first->id]->checked_date === '2026-01-01');
        $this->get(route('shortages.index'))->assertOk()->assertViewHas('rows', function ($rows) use ($warehouse) {
            return $rows->count() === 2 && $rows->contains(fn ($row) => $row->medicine_id === $this->first->id && $row->location_id === $warehouse->id)
                && $rows->contains(fn ($row) => $row->medicine_id === $this->other->id && $row->location_id === $this->location->id);
        });
        $this->get(route('dashboard'))->assertOk()->assertViewHas('stats', fn ($stats) => $stats['shortages'] === 0);
    }
}
