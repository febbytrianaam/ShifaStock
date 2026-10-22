<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Location;
use App\Models\Medicine;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class NightlyCheckTest extends TestCase
{
    use RefreshDatabase;

    public function test_physical_count_workflow_preserves_zero_and_requires_pharmacist_review(): void
    {
        $p = User::factory()->create(['role' => Role::Pharmacy]);
        $k = User::factory()->create(['role' => Role::Pharmacist]);
        $u = Unit::create(['name' => 'Tablet']);
        $l = Location::create(['code' => 'APT', 'name' => 'Apotek']);
        Medicine::create(['code' => 'A', 'name' => 'Obat A', 'dosage_form' => 'Tablet', 'unit_id' => $u->id]);
        $this->actingAs($p)->get('/pengecekan-malam')->assertOk();
        $this->post('/pengecekan-malam', ['date' => '2026-01-01', 'location_id' => $l->id])->assertRedirect();
        $c = DB::table('nightly_checks')->first();
        $i = DB::table('nightly_check_items')->first();
        $url = '/pengecekan-malam/'.$c->id;
        $this->get($url)->assertOk()
            ->assertSee('Belum dicek')
            ->assertSee('Cari obat')
            ->assertSee('25 obat')
            ->assertSee('data-nightly-row', false);
        $this->post($url, ['draft_revision' => 0, 'action' => 'submit', 'items' => [$i->id => ['quantity' => null, 'unit' => 'Tablet']]])->assertSessionHasErrors('items');
        $this->assertDatabaseHas('nightly_checks', ['id' => $c->id, 'status' => 'draft']);
        $this->post($url, ['draft_revision' => 0, 'action' => 'save', 'items' => [$i->id => ['quantity' => 0, 'unit' => 'Tablet', 'requested_quantity' => 20]]])->assertRedirect();
        $this->assertDatabaseHas('nightly_check_items', ['id' => $i->id, 'quantity' => 0]);
        $this->post($url, ['draft_revision' => 1, 'action' => 'submit', 'items' => [$i->id => ['quantity' => 0, 'unit' => 'Tablet', 'requested_quantity' => 20]]])->assertRedirect();
        $this->post($url.'/review', ['action' => 'approve'])->assertForbidden();
        $this->actingAs($k)->post($url.'/review', ['action' => 'return', 'review_note' => 'Cek satuan'])->assertRedirect();
        $this->assertDatabaseHas('nightly_checks', ['id' => $c->id, 'status' => 'draft']);
        $this->actingAs($p)->post($url, ['draft_revision' => 3, 'action' => 'submit', 'items' => [$i->id => ['quantity' => 0, 'unit' => 'Tablet', 'requested_quantity' => 20]]])->assertRedirect();
        $this->actingAs($k)->post($url.'/review', ['action' => 'approve'])->assertRedirect();
        $this->assertDatabaseHas('nightly_checks', ['id' => $c->id, 'status' => 'approved']);
        $this->get('/kekurangan-permintaan')->assertOk()
            ->assertSee('Stok Fisik')->assertSee('Jumlah Diminta')
            ->assertSee('Obat A')
            ->assertSee('Kosong');
        $this->get('/riwayat-stok?search=Obat')->assertOk()->assertSee('Obat A');
        $this->get($url)->assertOk()->assertSee('(kosong)')->assertSee('permintaan 20 Tablet');
        $this->actingAs($p)->post($url, ['draft_revision' => 5, 'action' => 'save', 'items' => [$i->id => ['quantity' => 5, 'unit' => 'Tablet']]])->assertForbidden();
        $this->post('/pengecekan-malam', ['date' => '2026-01-02', 'location_id' => $l->id])->assertRedirect();
        $next = DB::table('nightly_checks')->where('date', '2026-01-02')->first();
        $this->get('/pengecekan-malam/'.$next->id)->assertOk()->assertSee('2026-01-01')->assertSee('0 Tablet');
        $this->post('/pengecekan-malam', ['date' => '2026-01-02', 'location_id' => $l->id]);
        $this->assertDatabaseCount('nightly_checks', 2);
    }

    public function test_other_staff_can_continue_but_unverified_names_still_need_a_counting_unit(): void
    {
        $p = User::factory()->create(['role' => Role::Pharmacy]);
        $other = User::factory()->create(['role' => Role::Pharmacy]);
        $l = Location::create(['code' => 'APT', 'name' => 'Apotek']);
        Medicine::create(['code' => 'IM', 'name' => 'Impor', 'is_active' => false, 'import_source' => ['file' => 'obat.xlsx']]);
        $this->actingAs($p)->post('/pengecekan-malam', ['date' => '2026-01-01', 'location_id' => $l->id]);
        $c = DB::table('nightly_checks')->first();
        $i = DB::table('nightly_check_items')->first();
        $url = '/pengecekan-malam/'.$c->id;
        $this->post($url, ['draft_revision' => 0, 'action' => 'save', 'items' => [$i->id => ['quantity' => 1, 'unit' => '']]])->assertSessionHasErrors('items');
        $this->actingAs($other)->post($url, ['draft_revision' => 0, 'action' => 'save', 'items' => [$i->id => ['quantity' => 1, 'unit' => 'Botol']]])->assertRedirect();
        $this->assertDatabaseHas('nightly_check_items', ['id' => $i->id, 'quantity' => 1, 'last_edited_by' => $other->id]);
    }
}
