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

class GuestRoleTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_view_inventory_pages_but_cannot_write(): void
    {
        $guest = User::factory()->create(['role' => Role::Guest]);
        $unit = Unit::create(['name' => 'Tablet']);
        $location = Location::create(['code' => 'APT', 'name' => 'Apotek']);
        $medicine = Medicine::create(['code' => 'OBT-G', 'name' => 'Obat Guest', 'dosage_form' => 'Tablet', 'unit_id' => $unit->id]);

        $this->actingAs($guest)->get('/dashboard')->assertOk();
        $this->get('/obat')->assertOk();
        $this->get('/obat-masuk')->assertOk()->assertDontSee('Posting Penerimaan');
        $this->get('/transfer')->assertOk()->assertDontSee('Posting Transfer');
        $this->get('/pengecekan-malam')->assertOk()->assertDontSee('Buka / Mulai Pemeriksaan');

        $this->post('/obat-masuk', [
            'reference' => 'GUEST-001',
            'medicine_id' => $medicine->id,
            'batch_number' => 'B-GUEST',
            'expiry_date' => '2027-01-01',
            'quantity' => 1,
            'occurred_at' => '2026-01-01T08:00',
        ])->assertForbidden();

        $this->post('/pengecekan-malam', [
            'date' => '2026-01-01',
            'location_id' => $location->id,
        ])->assertForbidden();
    }

    public function test_guest_cannot_review_submitted_checks(): void
    {
        $guest = User::factory()->create(['role' => Role::Guest]);
        $petugas = User::factory()->create(['role' => Role::Pharmacy]);
        $location = Location::create(['code' => 'APT', 'name' => 'Apotek']);
        $checkId = DB::table('nightly_checks')->insertGetId([
            'date' => '2026-01-01',
            'location_id' => $location->id,
            'created_by' => $petugas->id,
            'status' => 'submitted',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($guest)
            ->post('/pengecekan-malam/'.$checkId.'/review', ['action' => 'approve'])
            ->assertForbidden();
    }
}
