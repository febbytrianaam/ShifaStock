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
use Tests\TestCase;

class AddNightlyMedicinesTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    private Medicine $first;

    private Medicine $other;

    private int $checkId;

    private int $itemId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->staff = User::factory()->create(['role' => Role::Pharmacy]);
        $unit = Unit::create(['name' => 'Tablet']);
        $bottle = Unit::create(['name' => 'Botol']);
        $location = Location::create(['code' => 'APT', 'name' => 'Apotek']);
        $this->first = Medicine::create(['code' => 'ADD-A', 'name' => 'Obat Awal', 'unit_id' => $unit->id, 'dosage_form' => 'Tablet', 'is_active' => true]);
        $this->other = Medicine::create(['code' => 'ADD-B', 'name' => 'Obat Tambahan', 'unit_id' => $bottle->id, 'dosage_form' => 'Sirup', 'is_active' => true]);
        $this->actingAs($this->staff)->post(route('nightly.store'), [
            'date' => '2026-01-01', 'location_id' => $location->id, 'scope' => 'selected', 'medicine_ids' => [$this->first->id],
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->checkId = DB::table('nightly_checks')->value('id');
        $this->itemId = DB::table('nightly_check_items')->value('id');
    }

    private function payload(array $item = []): array
    {
        return ['draft_revision' => DB::table('nightly_checks')->where('id', $this->checkId)->value('draft_revision'), 'action' => 'add', 'medicine_ids' => [$this->other->id],
            'items' => [$this->itemId => array_replace(['quantity' => null, 'unit' => 'Tablet'], $item)]];
    }

    public function test_add_saves_current_unsaved_counts_batches_flags_requests_and_notes_without_stock_writes(): void
    {
        $batch = Batch::create(['medicine_id' => $this->first->id, 'number' => 'ADD-BATCH', 'expiry_date' => '2027-12-01']);
        $payload = $this->payload(['quantity' => 7, 'is_low' => 1, 'requested_quantity' => 20, 'note' => 'Catatan belum disimpan']);
        $payload['batch_counts_payload'] = json_encode([$this->itemId => [$batch->id => 7]], JSON_THROW_ON_ERROR);
        $this->postJson(route('nightly.save', $this->checkId), $payload)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('nightly_check_items', ['id' => $this->itemId, 'quantity' => 7, 'unit' => 'Tablet', 'is_low' => true, 'requested_quantity' => 20, 'note' => 'Catatan belum disimpan']);
        $this->assertSame([(string) $batch->id => 7], json_decode(DB::table('nightly_check_items')->where('id', $this->itemId)->value('batch_counts'), true));
        $this->assertDatabaseHas('nightly_check_items', ['nightly_check_id' => $this->checkId, 'medicine_id' => $this->other->id, 'unit' => 'Botol', 'quantity' => null]);
        $this->assertDatabaseHas('nightly_checks', ['id' => $this->checkId, 'status' => 'draft', 'scope' => 'selected']);
        $this->assertDatabaseCount('nightly_check_items', 2);
        $this->assertDatabaseCount('stock_balances', 0);
        $this->assertDatabaseCount('batch_stock_balances', 0);
        $this->assertDatabaseCount('inventory_movements', 0);
    }

    public function test_blank_draft_can_add_medicines_but_partial_submission_requires_confirmation(): void
    {
        $this->postJson(route('nightly.save', $this->checkId), $this->payload())->assertRedirect();
        $items = DB::table('nightly_check_items')->get()->mapWithKeys(fn ($item) => [$item->id => ['unit' => $item->unit, 'quantity' => $item->id === $this->itemId ? 0 : null]])->all();
        $this->postJson(route('nightly.save', $this->checkId), ['draft_revision' => 1, 'action' => 'submit', 'items' => $items])->assertUnprocessable()->assertJsonValidationErrors('confirm_partial');
        $this->assertDatabaseHas('nightly_checks', ['id' => $this->checkId, 'status' => 'draft']);
    }

    public function test_invalid_or_ineligible_selection_does_not_save_old_edits_or_add_any_rows(): void
    {
        $this->other->update(['is_active' => false]);
        foreach ([[], [999999], [$this->other->id], [$this->first->id, $this->first->id]] as $ids) {
            $payload = $this->payload(['quantity' => 9, 'note' => 'Tidak boleh tersimpan sebagian']);
            $payload['medicine_ids'] = $ids;
            $this->postJson(route('nightly.save', $this->checkId), $payload)->assertUnprocessable();
            $this->assertDatabaseHas('nightly_check_items', ['id' => $this->itemId, 'quantity' => null, 'note' => null]);
            $this->assertDatabaseCount('nightly_check_items', 1);
        }
    }

    public function test_existing_and_repeated_medicines_do_not_create_duplicate_rows(): void
    {
        $payload = $this->payload(['quantity' => 0, 'note' => 'Tetap ada']);
        $payload['medicine_ids'] = [$this->first->id, $this->other->id];
        $this->postJson(route('nightly.save', $this->checkId), $payload)->assertRedirect();
        $otherItem = DB::table('nightly_check_items')->where('medicine_id', $this->other->id)->sole();
        $payload['items'][$otherItem->id] = ['unit' => 'Botol', 'quantity' => null];
        $payload['draft_revision'] = 1;
        $this->postJson(route('nightly.save', $this->checkId), $payload)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseCount('nightly_check_items', 2);
        $this->assertDatabaseHas('nightly_check_items', ['id' => $this->itemId, 'quantity' => 0, 'note' => 'Tetap ada']);
        $this->assertDatabaseHas('nightly_check_items', ['id' => $otherItem->id, 'quantity' => null]);
    }

    public function test_other_operators_can_add_but_guest_cannot(): void
    {
        foreach ([Role::Pharmacy, Role::Pharmacist] as $role) {
            $otherUser = User::factory()->create(['role' => $role]);
            $this->actingAs($otherUser)->get(route('nightly.show', $this->checkId))->assertOk()->assertSee('data-nightly-add-open', false);
            $payload = $this->payload();
            foreach (DB::table('nightly_check_items')->get() as $item) {
                $payload['items'][$item->id] = ['quantity' => $item->quantity, 'unit' => $item->unit];
            }
            $this->postJson(route('nightly.save', $this->checkId), $payload)->assertRedirect()->assertSessionHasNoErrors();
        }
        $this->actingAs(User::factory()->create(['role' => Role::Guest]))->postJson(route('nightly.save', $this->checkId), $this->payload())->assertForbidden();
        $this->get(route('nightly.show', $this->checkId))->assertOk()->assertDontSee('data-nightly-add-open', false);
        $this->assertDatabaseCount('nightly_check_items', 2);
        $this->assertDatabaseHas('nightly_checks', ['id' => $this->checkId, 'created_by' => $this->staff->id]);
    }

    public function test_submitted_and_approved_reports_reject_additions_until_returned_for_correction(): void
    {
        foreach (['submitted', 'approved'] as $status) {
            DB::table('nightly_checks')->where('id', $this->checkId)->update(['status' => $status]);
            $this->postJson(route('nightly.save', $this->checkId), $this->payload())->assertForbidden();
            $this->get(route('nightly.show', $this->checkId))->assertOk()->assertDontSee('data-nightly-add-open', false);
            $this->assertDatabaseCount('nightly_check_items', 1);
        }
        DB::table('nightly_checks')->where('id', $this->checkId)->update(['status' => 'submitted']);
        $reviewer = User::factory()->create(['role' => Role::Pharmacist]);
        $this->actingAs($reviewer)->postJson(route('nightly.review', $this->checkId), ['action' => 'return', 'review_note' => 'Tambahkan obat yang tertinggal'])->assertRedirect();
        $this->actingAs($this->staff)->postJson(route('nightly.save', $this->checkId), $this->payload())->assertRedirect();
        $this->assertDatabaseHas('nightly_checks', ['id' => $this->checkId, 'status' => 'draft', 'review_note' => 'Tambahkan obat yang tertinggal']);
        $this->assertDatabaseCount('nightly_check_items', 2);
    }

    public function test_stale_tab_missing_an_existing_row_rolls_back_edits_and_additions(): void
    {
        $this->postJson(route('nightly.save', $this->checkId), $this->payload(['quantity' => 5]))->assertRedirect();
        $third = Medicine::create(['code' => 'ADD-C', 'name' => 'Obat Ketiga', 'unit_id' => $this->first->unit_id, 'is_active' => true]);
        $payload = $this->payload(['quantity' => 999]);
        $payload['medicine_ids'] = [$third->id];
        $this->postJson(route('nightly.save', $this->checkId), $payload)->assertUnprocessable()->assertJsonValidationErrors('items');
        $this->assertDatabaseHas('nightly_check_items', ['id' => $this->itemId, 'quantity' => 5]);
        $this->assertDatabaseCount('nightly_check_items', 2);
        $this->assertDatabaseMissing('nightly_check_items', ['medicine_id' => $third->id]);
    }

    public function test_picker_excludes_existing_and_ineligible_medicines_but_includes_imported_masters(): void
    {
        $this->other->update(['is_active' => false]);
        $imported = Medicine::create(['code' => 'ADD-IM', 'name' => 'Obat Impor', 'is_active' => false, 'import_source' => ['file' => 'simulasi.xlsx']]);
        $this->get(route('nightly.show', $this->checkId))->assertOk()->assertSee('data-nightly-add-open', false)
            ->assertViewHas('availableMedicines', fn ($medicines) => $medicines->count() === 1 && $medicines->first()->id === $imported->id);
    }

    public function test_complete_draft_can_include_newly_created_masters_without_changing_its_scope(): void
    {
        DB::table('nightly_checks')->where('id', $this->checkId)->update(['scope' => 'complete']);
        $this->postJson(route('nightly.save', $this->checkId), $this->payload())->assertRedirect();
        $this->assertDatabaseHas('nightly_checks', ['id' => $this->checkId, 'scope' => 'complete', 'status' => 'draft']);
        $this->assertDatabaseCount('nightly_check_items', 2);
    }
}
