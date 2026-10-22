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

class NightlyDraftCancelTest extends TestCase
{
    use RefreshDatabase;

    public function test_creator_can_cancel_draft_check_only(): void
    {
        $creator = User::factory()->create(['role' => Role::Pharmacy]);
        $other = User::factory()->create(['role' => Role::Pharmacy]);
        $unit = Unit::create(['name' => 'Tablet']);
        $location = Location::create(['code' => 'APT', 'name' => 'Apotek']);
        $medicine = Medicine::create(['code' => 'OBT', 'name' => 'Obat', 'dosage_form' => 'Tablet', 'unit_id' => $unit->id]);
        $draftId = DB::table('nightly_checks')->insertGetId([
            'date' => '2026-01-01',
            'location_id' => $location->id,
            'created_by' => $creator->id,
            'status' => 'draft',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('nightly_check_items')->insert([
            'nightly_check_id' => $draftId,
            'medicine_id' => $medicine->id,
            'code' => 'OBT',
            'name' => 'Obat',
        ]);

        $this->actingAs($other)->delete('/pengecekan-malam/'.$draftId, ['draft_revision' => 0])->assertForbidden();
        $this->assertDatabaseHas('nightly_checks', ['id' => $draftId]);

        $this->actingAs($creator)->delete('/pengecekan-malam/'.$draftId, ['draft_revision' => 0])->assertRedirect('/pengecekan-malam');
        $this->assertDatabaseMissing('nightly_checks', ['id' => $draftId]);
        $this->assertDatabaseMissing('nightly_check_items', ['nightly_check_id' => $draftId]);
    }
}
