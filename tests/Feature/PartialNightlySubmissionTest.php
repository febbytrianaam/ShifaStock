<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Batch;
use App\Models\BatchStockBalance;
use App\Models\Location;
use App\Models\Medicine;
use App\Models\StockBalance;
use App\Models\Unit;
use App\Models\User;
use App\Queries\LatestNightlyItems;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PartialNightlySubmissionTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    private User $pharmacist;

    private Location $location;

    private array $medicines = [];

    private array $batches = [];

    private array $items;

    private int $check;

    protected function setUp(): void
    {
        parent::setUp();
        $this->staff = User::factory()->create(['role' => Role::Pharmacy]);
        $this->pharmacist = User::factory()->create(['role' => Role::Pharmacist]);
        $this->location = Location::create(['code' => 'APT', 'name' => 'Apotek']);
        $unit = Unit::create(['name' => 'Tablet']);
        foreach ([10, 7] as $index => $quantity) {
            $medicine = Medicine::create(['code' => 'PART-'.$index, 'name' => 'Obat Parsial '.$index, 'unit_id' => $unit->id, 'is_active' => true]);
            $batch = Batch::create(['medicine_id' => $medicine->id, 'number' => 'BATCH-'.$index, 'expiry_date' => '2027-12-01']);
            StockBalance::create(['medicine_id' => $medicine->id, 'location_id' => $this->location->id, 'quantity' => $quantity]);
            BatchStockBalance::create(['medicine_id' => $medicine->id, 'batch_id' => $batch->id, 'location_id' => $this->location->id, 'quantity' => $quantity]);
            $this->medicines[] = $medicine;
            $this->batches[] = $batch;
        }
        $this->actingAs($this->staff)->post(route('nightly.store'), ['date' => '2026-01-15', 'location_id' => $this->location->id, 'scope' => 'complete'])->assertSessionHasNoErrors();
        $this->check = DB::table('nightly_checks')->value('id');
        $this->items = DB::table('nightly_check_items')->orderBy('medicine_id')->pluck('id')->all();
    }

    private function payload(array $override = []): array
    {
        return array_replace([
            'draft_revision' => DB::table('nightly_checks')->where('id', $this->check)->value('draft_revision'),
            'action' => 'submit', 'confirm_partial' => 1,
            'items' => [
                $this->items[0] => ['quantity' => 5, 'unit' => 'Tablet', 'batch_counts' => [$this->batches[0]->id => 5]],
                $this->items[1] => ['quantity' => null, 'unit' => 'Tablet', 'note' => 'Belum sempat diperiksa'],
            ],
        ], $override);
    }

    private function submit(): void
    {
        $this->postJson(route('nightly.save', $this->check), $this->payload())->assertRedirect()->assertSessionHasNoErrors();
    }

    public function test_complete_list_can_save_an_incomplete_draft_without_confirming_or_changing_stock(): void
    {
        $this->postJson(route('nightly.save', $this->check), $this->payload(['action' => 'save', 'confirm_partial' => 0]))
            ->assertRedirect()->assertSessionHas('status', fn ($text) => str_contains($text, '1 obat belum diperiksa'));
        $this->assertDatabaseHas('nightly_check_items', ['id' => $this->items[0], 'quantity' => 5, 'stock_snapshot' => null]);
        $this->assertDatabaseHas('nightly_check_items', ['id' => $this->items[1], 'quantity' => null, 'note' => 'Belum sempat diperiksa']);
        $this->assertDatabaseHas('nightly_checks', ['id' => $this->check, 'scope' => 'complete', 'status' => 'draft']);
        $this->assertDatabaseHas('stock_balances', ['medicine_id' => $this->medicines[0]->id, 'quantity' => 10]);
        $this->assertDatabaseCount('inventory_movements', 0);
    }

    public function test_partial_submission_requires_explicit_confirmation_and_rolls_back_on_rejection(): void
    {
        $this->postJson(route('nightly.save', $this->check), $this->payload(['confirm_partial' => 0]))
            ->assertUnprocessable()->assertJsonValidationErrors('confirm_partial');
        $this->assertDatabaseHas('nightly_check_items', ['id' => $this->items[0], 'quantity' => null]);
        $this->assertDatabaseHas('nightly_checks', ['id' => $this->check, 'status' => 'draft']);
    }

    public function test_approval_updates_only_counted_medicines_and_keeps_unchecked_rows_explicit(): void
    {
        $this->submit();
        $this->assertDatabaseHas('nightly_checks', ['id' => $this->check, 'status' => 'submitted', 'scope' => 'complete']);
        $this->assertDatabaseHas('nightly_check_items', ['id' => $this->items[1], 'quantity' => null, 'stock_snapshot' => null]);
        $this->assertDatabaseCount('inventory_movements', 0);
        $this->actingAs($this->pharmacist)->postJson(route('nightly.review', $this->check), ['action' => 'approve'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('stock_balances', ['medicine_id' => $this->medicines[0]->id, 'quantity' => 5]);
        $this->assertDatabaseHas('batch_stock_balances', ['batch_id' => $this->batches[0]->id, 'quantity' => 5]);
        $this->assertDatabaseHas('stock_balances', ['medicine_id' => $this->medicines[1]->id, 'quantity' => 7, 'last_counted_at' => null]);
        $this->assertDatabaseHas('batch_stock_balances', ['batch_id' => $this->batches[1]->id, 'quantity' => 7]);
        $this->assertDatabaseMissing('inventory_movements', ['medicine_id' => $this->medicines[1]->id]);
        $this->get(route('nightly.show', $this->check))->assertOk()->assertSee('1 obat belum diperiksa')->assertSee('Pemeriksaan dikirim sebagian');
        $this->get(route('nightly.print', $this->check))->assertOk()->assertSee('1 obat sudah diperiksa dan 1 obat belum diperiksa');
    }

    public function test_zero_is_a_count_but_all_blank_is_not_submittable_even_with_confirmation(): void
    {
        $payload = $this->payload();
        $payload['items'][$this->items[0]] = ['quantity' => null, 'unit' => 'Tablet'];
        $this->postJson(route('nightly.save', $this->check), $payload)->assertUnprocessable()->assertJsonValidationErrors('items');
        $payload['items'][$this->items[0]] = ['quantity' => 0, 'unit' => 'Tablet', 'batch_counts' => [$this->batches[0]->id => 0]];
        $this->postJson(route('nightly.save', $this->check), $payload)->assertRedirect()->assertSessionHasNoErrors();
        $this->actingAs($this->pharmacist)->postJson(route('nightly.review', $this->check), ['action' => 'approve'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('stock_balances', ['medicine_id' => $this->medicines[0]->id, 'quantity' => 0]);
        $this->assertDatabaseHas('stock_balances', ['medicine_id' => $this->medicines[1]->id, 'quantity' => 7]);
    }

    public function test_full_submission_still_works_without_partial_confirmation(): void
    {
        $payload = $this->payload(['confirm_partial' => 0]);
        $payload['items'][$this->items[1]] = ['quantity' => 7, 'unit' => 'Tablet'];
        $this->postJson(route('nightly.save', $this->check), $payload)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('nightly_checks', ['id' => $this->check, 'status' => 'submitted']);
    }

    public function test_partial_confirmation_does_not_bypass_batch_validation_or_missing_rows(): void
    {
        $payload = $this->payload();
        unset($payload['items'][$this->items[0]]['batch_counts']);
        $this->postJson(route('nightly.save', $this->check), $payload)->assertUnprocessable()->assertJsonValidationErrors('items.'.$this->items[0].'.batch_counts');
        $payload = $this->payload();
        unset($payload['items'][$this->items[1]]);
        $this->postJson(route('nightly.save', $this->check), $payload)->assertUnprocessable()->assertJsonValidationErrors('items');
        $this->assertDatabaseHas('nightly_checks', ['id' => $this->check, 'status' => 'draft']);
        $this->assertDatabaseHas('nightly_check_items', ['id' => $this->items[0], 'quantity' => null]);
    }

    public function test_unchecked_batch_counts_must_not_be_silently_ignored(): void
    {
        $payload = $this->payload();
        $payload['items'][$this->items[1]]['batch_counts'] = [$this->batches[1]->id => 7];
        $this->postJson(route('nightly.save', $this->check), $payload)->assertUnprocessable()->assertJsonValidationErrors('items.'.$this->items[1].'.quantity');
    }

    public function test_unchecked_stock_changes_do_not_block_review_but_checked_stock_changes_do(): void
    {
        $this->submit();
        StockBalance::where('medicine_id', $this->medicines[1]->id)->update(['quantity' => 8]);
        BatchStockBalance::where('batch_id', $this->batches[1]->id)->update(['quantity' => 8]);
        StockBalance::where('medicine_id', $this->medicines[0]->id)->update(['quantity' => 11]);
        BatchStockBalance::where('batch_id', $this->batches[0]->id)->update(['quantity' => 11]);
        $this->actingAs($this->pharmacist)->postJson(route('nightly.review', $this->check), ['action' => 'approve'])->assertUnprocessable();
        $this->assertDatabaseHas('nightly_checks', ['id' => $this->check, 'status' => 'submitted']);
        StockBalance::where('medicine_id', $this->medicines[0]->id)->update(['quantity' => 10]);
        BatchStockBalance::where('batch_id', $this->batches[0]->id)->update(['quantity' => 10]);
        $this->postJson(route('nightly.review', $this->check), ['action' => 'approve'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('stock_balances', ['medicine_id' => $this->medicines[1]->id, 'quantity' => 8]);
    }

    public function test_unchecked_rows_do_not_replace_prior_history_or_enter_periodic_counts(): void
    {
        $older = DB::table('nightly_checks')->insertGetId(['date' => '2026-01-14', 'location_id' => $this->location->id, 'created_by' => $this->staff->id, 'status' => 'approved']);
        DB::table('nightly_check_items')->insert(['nightly_check_id' => $older, 'medicine_id' => $this->medicines[1]->id, 'code' => 'PART-1', 'name' => 'Obat Parsial 1', 'quantity' => 3, 'unit' => 'Tablet']);
        $this->submit();
        $this->actingAs($this->pharmacist)->postJson(route('nightly.review', $this->check), ['action' => 'approve'])->assertRedirect()->assertSessionHasNoErrors();
        $latest = LatestNightlyItems::query()->where('i.medicine_id', $this->medicines[1]->id)->select('i.quantity', 'c.date')->sole();
        $this->assertSame(3, $latest->quantity);
        $this->assertSame('2026-01-14', $latest->date);
        $this->get(route('nightly.history'))->assertOk()->assertViewHas('rows', fn ($rows) => $rows->total() === 2 && $rows->every(fn ($row) => $row->quantity !== null));
        $this->get(route('preview.opname', ['period' => '2026-01']))->assertOk()->assertViewHas('rows', fn ($rows) => $rows->total() === 2 && $rows->every(fn ($row) => $row->quantity !== null));
    }

    public function test_partial_confirmation_does_not_bypass_guest_permissions(): void
    {
        foreach ([Role::Guest] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))->postJson(route('nightly.save', $this->check), $this->payload())->assertForbidden();
            $this->get(route('nightly.show', $this->check))->assertOk()->assertSee('data-nightly-readonly', false)
                ->assertSee('hanya memiliki akses melihat laporan');
        }
        $this->assertDatabaseHas('nightly_checks', ['id' => $this->check, 'status' => 'draft']);
    }
}
