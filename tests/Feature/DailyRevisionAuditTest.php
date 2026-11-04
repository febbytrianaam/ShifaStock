<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Batch;
use App\Models\Location;
use App\Models\Medicine;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DailyRevisionAuditTest extends TestCase
{
    use RefreshDatabase;

    private Location $location;
    private Medicine $medicine;
    private int $checkId;
    private int $itemId;
    private int $batchId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(\Carbon\Carbon::parse('2026-10-16 10:00', 'Asia/Jakarta'));
        $this->actingAs(User::factory()->create(['role' => Role::Pharmacy]));
        $unit = Unit::create(['name' => 'Tablet']);
        $this->medicine = Medicine::create(['code' => 'REVISION', 'name' => 'Obat Revisi', 'dosage_form' => 'Tablet', 'unit_id' => $unit->id, 'is_active' => true]);
        $this->location = Location::create(['code' => 'APT', 'name' => 'Apotek', 'is_active' => true]);
        $this->receive('INITIAL', 10);
        $this->batchId = Batch::where('number', 'REVISION-BATCH')->sole()->id;
        $this->post(route('nightly.store'), ['date' => '2026-10-16', 'location_id' => $this->location->id])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->checkId = DB::table('nightly_checks')->sole()->id;
        $this->itemId = DB::table('nightly_check_items')->sole()->id;
    }

    private function receive(string $reference, int $quantity): void
    {
        $this->post(route('receipts.store'), [
            'reference' => $reference, 'medicine_id' => $this->medicine->id,
            'destination_location_id' => $this->location->id, 'batch_number' => 'REVISION-BATCH',
            'expiry_date' => '2027-12-01', 'quantity' => $quantity, 'occurred_at' => '2026-10-16T09:00',
        ])->assertRedirect()->assertSessionHasNoErrors();
    }

    private function payload(): array
    {
        return ['draft_revision' => 0, 'action' => 'submit', 'items' => [
            $this->itemId => ['quantity' => 8, 'unit' => 'Tablet', 'batch_counts' => [$this->batchId => 8]],
        ]];
    }

    private function persistedState(): array
    {
        $state = [];
        foreach (['nightly_checks', 'nightly_check_items', 'nightly_check_activities', 'inventory_movements', 'stock_balances', 'batch_stock_balances'] as $table) {
            $state[$table] = DB::table($table)->orderBy('id')->get()->toJson();
        }

        return $state;
    }

    public static function absentRevisions(): array
    {
        return ['missing' => [[]], 'null' => [['observed_revision' => null]]];
    }

    #[DataProvider('absentRevisions')]
    public function test_submit_requires_observed_revision_even_after_stock_changes(array $revision): void
    {
        $this->nightlyObservedRevision($this->checkId);
        $this->receive('WHILE-COUNTING', 1);
        $before = $this->persistedState();

        $this->postJson(route('nightly.save', $this->checkId), $this->payload() + $revision)
            ->assertUnprocessable()->assertJsonValidationErrors('observed_revision');

        $this->assertSame($before, $this->persistedState());
    }

    public function test_stale_observed_revision_rejects_submit_without_persisting_changes(): void
    {
        $revision = $this->nightlyObservedRevision($this->checkId);
        $this->receive('WHILE-COUNTING', 1);
        $this->assertGreaterThan($revision, $this->nightlyObservedRevision($this->checkId));
        $before = $this->persistedState();

        $this->postJson(route('nightly.save', $this->checkId), $this->payload() + ['observed_revision' => $revision])
            ->assertUnprocessable()->assertJsonValidationErrors('items');

        $this->assertSame($before, $this->persistedState());
    }

    #[DataProvider('absentRevisions')]
    public function test_draft_save_allows_missing_or_null_observed_revision(array $revision): void
    {
        $this->nightlyObservedRevision($this->checkId);
        $this->receive('WHILE-COUNTING', 1);
        $payload = array_replace($this->payload(), ['action' => 'save']) + $revision;
        $this->postJson(route('nightly.save', $this->checkId), $payload)
            ->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('nightly_checks', ['id' => $this->checkId, 'status' => 'draft', 'draft_revision' => 1, 'submitted_at' => null]);
        $this->assertDatabaseHas('nightly_check_items', ['id' => $this->itemId, 'quantity' => 8, 'stock_snapshot' => null]);
        $this->assertDatabaseHas('nightly_check_activities', ['nightly_check_id' => $this->checkId, 'action' => 'saved']);
        $this->assertDatabaseHas('stock_balances', ['location_id' => $this->location->id, 'quantity' => 11]);
        $this->assertDatabaseHas('batch_stock_balances', ['batch_id' => $this->batchId, 'quantity' => 11]);
        $this->assertDatabaseCount('inventory_movements', 2);
    }

    public function test_fresh_rendered_revision_submits_and_can_be_approved(): void
    {
        $oldRevision = $this->nightlyObservedRevision($this->checkId);
        $this->receive('WHILE-COUNTING', 1);
        $page = $this->get(route('nightly.show', $this->checkId))->assertOk();
        $revision = $page->viewData('observedRevision');
        $this->assertGreaterThan($oldRevision, $revision);
        $page->assertSee('name="observed_revision" value="'.$revision.'"', false);

        $this->postJson(route('nightly.save', $this->checkId), $this->payload() + ['observed_revision' => $revision])
            ->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('nightly_checks', ['id' => $this->checkId, 'status' => 'submitted', 'draft_revision' => 1]);
        $this->assertNotNull(DB::table('nightly_check_items')->where('id', $this->itemId)->value('stock_snapshot'));
        $this->assertDatabaseHas('nightly_check_activities', ['nightly_check_id' => $this->checkId, 'action' => 'submitted']);
        $this->assertDatabaseHas('stock_balances', ['location_id' => $this->location->id, 'quantity' => 11]);
        $this->assertDatabaseCount('inventory_movements', 2);

        $this->actingAs(User::factory()->create(['role' => Role::Pharmacist]))
            ->postJson(route('nightly.review', $this->checkId), ['action' => 'approve'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('nightly_checks', ['id' => $this->checkId, 'status' => 'approved']);
        $this->assertDatabaseHas('stock_balances', ['location_id' => $this->location->id, 'quantity' => 8]);
        $this->assertDatabaseHas('batch_stock_balances', ['batch_id' => $this->batchId, 'quantity' => 8]);
    }

    public function test_rendered_zero_revision_is_valid_for_a_location_without_movements(): void
    {
        $empty = Location::create(['code' => 'EMPTY', 'name' => 'Lokasi Kosong', 'is_active' => true]);
        $this->post(route('nightly.store'), ['date' => '2026-10-16', 'location_id' => $empty->id])
            ->assertRedirect()->assertSessionHasNoErrors();
        $checkId = DB::table('nightly_checks')->where('location_id', $empty->id)->sole()->id;
        $itemId = DB::table('nightly_check_items')->where('nightly_check_id', $checkId)->sole()->id;
        $revision = $this->nightlyObservedRevision($checkId);
        $this->assertSame(0, $revision);

        $this->postJson(route('nightly.save', $checkId), [
            'draft_revision' => 0, 'action' => 'submit', 'observed_revision' => $revision,
            'items' => [$itemId => ['quantity' => 0, 'unit' => 'Tablet']],
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('nightly_checks', ['id' => $checkId, 'status' => 'submitted']);
    }
}
