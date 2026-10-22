<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Location;
use App\Models\Medicine;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class SharedNightlyCheckTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;
    private User $pharmacist;
    private Location $location;
    private int $check;
    private array $itemIds;

    protected function setUp(): void
    {
        parent::setUp();
        $this->staff = User::factory()->create(['role' => Role::Pharmacy, 'name' => 'Petugas Hitung']);
        $this->pharmacist = User::factory()->create(['role' => Role::Pharmacist, 'name' => 'Apoteker Pembuat']);
        $this->location = Location::create(['code' => 'APT', 'name' => 'Apotek']);
        $unit = Unit::create(['name' => 'Tablet']);
        foreach (['A', 'B'] as $code) {
            Medicine::create(['code' => 'SHARED-'.$code, 'name' => 'Obat Bersama '.$code, 'unit_id' => $unit->id, 'is_active' => true]);
        }
        $this->actingAs($this->pharmacist)->post(route('nightly.store'), [
            'date' => '2026-01-15', 'location_id' => $this->location->id, 'scope' => 'complete',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->check = DB::table('nightly_checks')->value('id');
        $this->itemIds = DB::table('nightly_check_items')->orderBy('id')->pluck('id')->all();
    }

    private function payload(array $overrides = []): array
    {
        $items = DB::table('nightly_check_items')->where('nightly_check_id', $this->check)->get()
            ->mapWithKeys(fn ($item) => [$item->id => [
                'quantity' => $item->quantity, 'unit' => $item->unit, 'is_low' => (bool) $item->is_low,
                'requested_quantity' => $item->requested_quantity, 'note' => $item->note,
                'batch_counts' => json_decode($item->batch_counts ?? '[]', true),
            ]])->all();

        return array_replace(['action' => 'save', 'draft_revision' => DB::table('nightly_checks')->where('id', $this->check)->value('draft_revision'), 'items' => $items], $overrides);
    }

    private function saveAs(User $user, array $payload): void
    {
        $this->actingAs($user)->postJson(route('nightly.save', $this->check), $payload)->assertRedirect()->assertSessionHasNoErrors();
    }

    public function test_staff_can_continue_pharmacist_draft_without_replacing_creator(): void
    {
        $this->actingAs($this->staff)->get(route('nightly.show', $this->check))->assertOk()
            ->assertSee('data-nightly-count-form', false)->assertSee('Dibuat oleh: Apoteker Pembuat');
        $payload = $this->payload();
        $payload['items'][$this->itemIds[0]]['quantity'] = 0;
        $payload['items'][$this->itemIds[0]]['note'] = 'Dihitung petugas';
        $payload['created_by'] = $this->staff->id;
        $payload['actor_name'] = 'Tidak boleh dipakai';
        $this->saveAs($this->staff, $payload);
        $this->assertDatabaseHas('nightly_checks', ['id' => $this->check, 'created_by' => $this->pharmacist->id, 'draft_revision' => 1]);
        $this->assertDatabaseHas('nightly_check_items', ['id' => $this->itemIds[0], 'quantity' => 0, 'last_edited_by' => $this->staff->id, 'last_edited_name' => 'Petugas Hitung']);
        $this->assertDatabaseHas('nightly_check_activities', ['nightly_check_id' => $this->check, 'actor_id' => $this->staff->id, 'actor_name' => 'Petugas Hitung', 'action' => 'saved']);
        $this->get(route('nightly.show', $this->check))->assertSee('Dikerjakan terakhir: Petugas Hitung')->assertSee('Dibuat oleh: Apoteker Pembuat');
        $this->get(route('nightly.print', $this->check))->assertOk()->assertSee('Dikerjakan terakhir:')->assertSee('Petugas Hitung')->assertSee('Apoteker Pembuat');
        $this->assertDatabaseCount('inventory_movements', 0);
        $this->assertDatabaseCount('stock_balances', 0);
    }

    public function test_pharmacist_can_continue_staff_draft_and_add_medicines(): void
    {
        DB::table('nightly_checks')->where('id', $this->check)->update(['created_by' => $this->staff->id]);
        $medicine = Medicine::create(['code' => 'SHARED-C', 'name' => 'Obat Tambahan', 'unit_id' => Unit::value('id'), 'is_active' => true]);
        $this->actingAs($this->pharmacist)->get(route('nightly.show', $this->check))->assertOk()
            ->assertViewHas('availableMedicines', fn ($medicines) => $medicines->contains('id', $medicine->id));
        $payload = $this->payload(['action' => 'add', 'medicine_ids' => [$medicine->id]]);
        $payload['items'][$this->itemIds[0]]['note'] = 'Dikerjakan apoteker';
        $this->saveAs($this->pharmacist, $payload);
        $this->assertDatabaseHas('nightly_checks', ['id' => $this->check, 'created_by' => $this->staff->id]);
        $this->assertDatabaseHas('nightly_check_items', ['medicine_id' => $medicine->id, 'quantity' => null]);
        $this->assertDatabaseHas('nightly_check_items', ['id' => $this->itemIds[0], 'last_edited_by' => $this->pharmacist->id]);
        $this->assertDatabaseHas('nightly_check_activities', ['action' => 'added', 'actor_id' => $this->pharmacist->id]);
    }

    public function test_each_medicine_keeps_the_actor_who_actually_changed_its_fields(): void
    {
        $first = $this->payload();
        $first['items'][$this->itemIds[0]]['quantity'] = 0;
        $this->saveAs($this->staff, $first);
        $second = $this->payload();
        $second['items'][$this->itemIds[1]]['quantity'] = 0;
        $this->saveAs($this->pharmacist, $second);
        $this->assertDatabaseHas('nightly_check_items', ['id' => $this->itemIds[0], 'last_edited_by' => $this->staff->id]);
        $this->assertDatabaseHas('nightly_check_items', ['id' => $this->itemIds[1], 'last_edited_by' => $this->pharmacist->id]);
        $last = DB::table('nightly_check_activities')->latest('id')->first();
        $this->assertSame([$this->itemIds[1]], json_decode($last->metadata, true)['changed_item_ids']);
        $this->saveAs($this->pharmacist, $this->payload());
        $this->assertDatabaseHas('nightly_check_items', ['id' => $this->itemIds[0], 'last_edited_by' => $this->staff->id]);
        $this->assertSame(0, json_decode(DB::table('nightly_check_activities')->latest('id')->value('metadata'), true)['changed_count']);
    }

    public function test_missing_and_invalid_draft_revisions_cannot_write_or_create_activity(): void
    {
        foreach ([null, -1, 'invalid'] as $revision) {
            $this->actingAs($this->staff)->postJson(route('nightly.save', $this->check), $this->payload(['draft_revision' => $revision]))
                ->assertUnprocessable()->assertJsonValidationErrors('draft_revision');
        }
        $this->assertDatabaseCount('nightly_check_activities', 1);
        $this->assertDatabaseHas('nightly_checks', ['id' => $this->check, 'draft_revision' => 0]);
    }

    public function test_stale_save_add_and_submit_cannot_overwrite_newer_work(): void
    {
        $old = $this->payload();
        $current = $this->payload();
        $current['items'][$this->itemIds[0]]['quantity'] = 0;
        $current['items'][$this->itemIds[0]]['note'] = 'Pekerjaan terbaru';
        $this->saveAs($this->staff, $current);
        $medicine = Medicine::create(['code' => 'SHARED-STALE', 'name' => 'Tidak boleh ditambahkan', 'unit_id' => Unit::value('id'), 'is_active' => true]);
        foreach (['save', 'add', 'submit'] as $action) {
            $stale = array_replace($old, ['action' => $action, 'medicine_ids' => [$medicine->id], 'confirm_partial' => true]);
            $stale['items'][$this->itemIds[0]]['quantity'] = 999;
            $this->actingAs($this->pharmacist)->postJson(route('nightly.save', $this->check), $stale)
                ->assertUnprocessable()->assertJsonValidationErrors('draft_revision');
        }
        $this->assertDatabaseHas('nightly_check_items', ['id' => $this->itemIds[0], 'quantity' => 0, 'note' => 'Pekerjaan terbaru', 'last_edited_by' => $this->staff->id]);
        $this->assertDatabaseCount('nightly_check_items', 2);
        $this->assertDatabaseCount('nightly_check_activities', 2);
        $this->assertDatabaseHas('nightly_checks', ['id' => $this->check, 'status' => 'draft', 'draft_revision' => 1]);
    }

    public function test_stale_web_form_keeps_unsaved_input_and_provides_safe_latest_link(): void
    {
        $old = $this->payload();
        $this->saveAs($this->staff, $this->payload());
        $old['items'][$this->itemIds[0]]['note'] = 'Catatan belum disimpan';
        $this->actingAs($this->pharmacist)->from(route('nightly.show', $this->check))->followingRedirects()
            ->post(route('nightly.save', $this->check), $old)->assertOk()
            ->assertSee('data-nightly-latest', false)->assertSee('Catatan belum disimpan');
        $this->assertDatabaseHas('nightly_check_items', ['id' => $this->itemIds[0], 'note' => null]);
    }

    public function test_returned_report_can_be_continued_by_another_operator_with_a_fresh_revision(): void
    {
        $data = $this->payload(['action' => 'submit']);
        foreach ($this->itemIds as $id) $data['items'][$id]['quantity'] = 0;
        $this->saveAs($this->staff, $data);
        $this->actingAs($this->pharmacist)->postJson(route('nightly.review', $this->check), ['action' => 'return', 'review_note' => 'Periksa kemasan kembali'])->assertRedirect();
        $this->assertDatabaseHas('nightly_checks', ['id' => $this->check, 'status' => 'draft', 'draft_revision' => 2]);
        $other = User::factory()->create(['role' => Role::Pharmacy]);
        $this->saveAs($other, $this->payload());
        $this->assertDatabaseHas('nightly_check_activities', ['action' => 'returned', 'actor_id' => $this->pharmacist->id]);
        $this->assertDatabaseHas('nightly_checks', ['id' => $this->check, 'created_by' => $this->pharmacist->id, 'draft_revision' => 3]);
    }

    public function test_locked_states_and_guest_or_view_only_accounts_still_cannot_edit(): void
    {
        foreach (['submitted', 'approved'] as $status) {
            DB::table('nightly_checks')->where('id', $this->check)->update(['status' => $status]);
            $this->actingAs($this->staff)->postJson(route('nightly.save', $this->check), $this->payload())->assertForbidden();
        }
        DB::table('nightly_checks')->where('id', $this->check)->update(['status' => 'draft']);
        $this->actingAs(User::factory()->create(['role' => Role::Guest]))->postJson(route('nightly.save', $this->check), $this->payload())->assertForbidden();
        Gate::define('operate-inventory', fn () => false);
        $this->actingAs($this->staff)->postJson(route('nightly.save', $this->check), $this->payload())->assertForbidden();
        $this->get(route('nightly.show', $this->check))->assertOk()->assertDontSee('data-nightly-count-form', false);
        $this->assertDatabaseCount('nightly_check_activities', 1);
    }

    public function test_cancellation_stays_owner_only_and_cannot_delete_a_newer_revision(): void
    {
        $this->actingAs($this->staff)->deleteJson(route('nightly.destroy', $this->check), ['draft_revision' => 0])->assertForbidden();
        $this->saveAs($this->staff, $this->payload());
        $this->actingAs($this->pharmacist)->deleteJson(route('nightly.destroy', $this->check), ['draft_revision' => 0])->assertUnprocessable()->assertJsonValidationErrors('draft_revision');
        $this->assertDatabaseHas('nightly_checks', ['id' => $this->check]);
        $this->deleteJson(route('nightly.destroy', $this->check), ['draft_revision' => 1])->assertRedirect();
        $this->assertDatabaseCount('nightly_checks', 0);
    }

    public function test_reopening_existing_report_preserves_creator_and_creation_activity(): void
    {
        $this->actingAs($this->staff)->post(route('nightly.store'), ['date' => '2026-01-15', 'location_id' => $this->location->id])
            ->assertRedirect(route('nightly.show', $this->check));
        $this->assertDatabaseCount('nightly_checks', 1);
        $this->assertDatabaseCount('nightly_check_activities', 1);
        $this->assertDatabaseHas('nightly_check_activities', ['action' => 'created', 'actor_id' => $this->pharmacist->id]);
    }

    public function test_list_displays_creator_and_supplies_current_revision_for_owner_cancellation(): void
    {
        $this->saveAs($this->staff, $this->payload());
        $this->actingAs($this->pharmacist)->get(route('preview.nightly'))->assertOk()
            ->assertSee('Dibuat oleh: Apoteker Pembuat')
            ->assertSee('name="draft_revision" value="1"', false);
        $this->actingAs($this->staff)->get(route('preview.nightly'))->assertOk()
            ->assertDontSee('data-confirm-cancel-draft', false);
    }

    public function test_actor_name_snapshot_survives_account_rename(): void
    {
        $payload = $this->payload();
        $payload['items'][$this->itemIds[0]]['quantity'] = 0;
        $this->saveAs($this->staff, $payload);
        $this->staff->update(['name' => 'Nama Baru']);
        $this->get(route('nightly.show', $this->check))->assertOk()
            ->assertSee('Dikerjakan terakhir: Petugas Hitung')->assertSee('Pembaruan terakhir: Petugas Hitung');
        $this->assertDatabaseHas('nightly_check_activities', ['action' => 'saved', 'actor_name' => 'Petugas Hitung']);
    }

    public function test_legacy_reports_do_not_claim_unrecorded_workers_or_activities(): void
    {
        DB::table('nightly_check_activities')->where('nightly_check_id', $this->check)->delete();
        $this->actingAs($this->staff)->get(route('nightly.show', $this->check))->assertOk()
            ->assertSee('Dibuat oleh: Apoteker Pembuat')->assertSee('Dikerjakan terakhir: Belum tercatat');
        $this->assertDatabaseCount('nightly_check_activities', 0);
    }

    public function test_late_validation_failure_rolls_back_item_attribution_revision_and_activity(): void
    {
        $payload = $this->payload();
        $payload['items'][$this->itemIds[0]]['quantity'] = 0;
        $payload['items'][$this->itemIds[1]] = ['quantity' => 0, 'unit' => ''];
        $this->actingAs($this->staff)->postJson(route('nightly.save', $this->check), $payload)->assertUnprocessable();
        $this->assertDatabaseHas('nightly_check_items', ['id' => $this->itemIds[0], 'quantity' => null, 'last_edited_by' => null]);
        $this->assertDatabaseHas('nightly_checks', ['id' => $this->check, 'draft_revision' => 0]);
        $this->assertDatabaseCount('nightly_check_activities', 1);
    }

    public function test_sender_and_reviewer_are_logged_without_stealing_row_author_credit(): void
    {
        $payload = $this->payload();
        foreach ($this->itemIds as $id) $payload['items'][$id]['quantity'] = 0;
        $this->saveAs($this->staff, $payload);
        $this->saveAs($this->pharmacist, $this->payload(['action' => 'submit']));
        $this->actingAs($this->staff)->postJson(route('nightly.review', $this->check), ['action' => 'approve'])->assertForbidden();
        $this->actingAs($this->pharmacist)->postJson(route('nightly.review', $this->check), ['action' => 'approve'])->assertRedirect();
        $this->assertDatabaseHas('nightly_check_items', ['id' => $this->itemIds[0], 'last_edited_by' => $this->staff->id]);
        $this->assertDatabaseHas('nightly_check_activities', ['action' => 'submitted', 'actor_id' => $this->pharmacist->id]);
        $this->assertDatabaseHas('nightly_check_activities', ['action' => 'approved', 'actor_id' => $this->pharmacist->id]);
        $this->assertDatabaseHas('nightly_checks', ['id' => $this->check, 'status' => 'approved', 'created_by' => $this->pharmacist->id]);
    }
}
