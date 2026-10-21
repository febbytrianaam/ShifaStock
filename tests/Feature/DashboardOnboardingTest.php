<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Batch;
use App\Models\BatchStockBalance;
use App\Models\InventoryMovement;
use App\Models\Location;
use App\Models\Medicine;
use App\Models\Unit;
use App\Models\User;
use App\Support\Onboarding;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DashboardOnboardingTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_is_real_even_when_old_demo_query_is_supplied(): void
    {
        $this->actingAs(User::factory()->create(['role' => Role::Pharmacy]));
        $this->get('/dashboard?data=demo')->assertOk()
            ->assertViewHas('stats', ['verified' => 0, 'stocked' => 0, 'shortages' => 0, 'expiring' => 0])
            ->assertViewHas('hasApprovedChecks', false)->assertDontSee('Mode Demo')->assertDontSee('Data Demo');
        $this->assertDatabaseCount('medicines', 0);
    }

    public function test_shortage_count_is_not_limited_to_the_eight_preview_rows(): void
    {
        $user = User::factory()->create(['role' => Role::Pharmacy]);
        $location = Location::create(['code' => 'GDG', 'name' => 'Gudang']);
        $check = DB::table('nightly_checks')->insertGetId(['date' => '2026-10-20', 'location_id' => $location->id, 'created_by' => $user->id, 'status' => 'approved']);
        foreach (range(1, 12) as $number) {
            $medicine = Medicine::create(['code' => 'REAL-'.$number, 'name' => 'Obat '.$number]);
            DB::table('nightly_check_items')->insert(['nightly_check_id' => $check, 'medicine_id' => $medicine->id,
                'code' => $medicine->code, 'name' => $medicine->name, 'quantity' => 0, 'is_low' => true]);
        }
        $this->actingAs($user)->get('/dashboard')->assertOk()
            ->assertViewHas('stats', fn ($stats) => $stats['shortages'] === 12)
            ->assertViewHas('shortages', fn ($rows) => $rows->count() === 8);
    }

    public function test_expiry_excludes_empty_batches_and_chart_counts_documents_not_fefo_splits(): void
    {
        $user = User::factory()->create(['role' => Role::Pharmacy]);
        $unit = Unit::create(['name' => 'Tablet']);
        $medicine = Medicine::create(['code' => 'REAL', 'name' => 'Obat', 'unit_id' => $unit->id, 'dosage_form' => 'Tablet', 'verification_status' => 'terverifikasi']);
        Medicine::create(['code' => 'INACTIVE', 'name' => 'Tidak aktif', 'unit_id' => $unit->id, 'dosage_form' => 'Tablet', 'verification_status' => 'terverifikasi', 'is_active' => false]);
        $location = Location::create(['code' => 'GDG', 'name' => 'Gudang']);
        foreach ([0, 5] as $index => $quantity) {
            $batch = Batch::create(['medicine_id' => $medicine->id, 'number' => 'B-'.$index, 'expiry_date' => now()->addMonth()]);
            BatchStockBalance::create(['medicine_id' => $medicine->id, 'batch_id' => $batch->id, 'location_id' => $location->id, 'quantity' => $quantity]);
            InventoryMovement::create(['medicine_id' => $medicine->id, 'batch_id' => $batch->id, 'type' => 'receipt', 'reference' => 'SAME-DOCUMENT', 'quantity' => 5, 'destination_location_id' => $location->id, 'occurred_at' => now(), 'created_by' => $user->id]);
        }
        $this->actingAs($user)->get('/dashboard')->assertOk()
            ->assertViewHas('stats', fn ($stats) => $stats['expiring'] === 1 && $stats['verified'] === 1)
            ->assertViewHas('chart', fn ($chart) => array_sum($chart['receipts']) === 1);
    }

    public function test_finishing_or_skipping_persists_only_the_current_account_and_is_idempotent(): void
    {
        $user = User::factory()->create(['role' => Role::Guest]);
        $other = User::factory()->create();
        $this->actingAs($user)->get('/dashboard')->assertOk()->assertSee('data-onboarding-config', false);
        $this->postJson(route('onboarding.finish'), ['outcome' => 'invalid'])->assertUnprocessable();
        $this->assertNull($user->fresh()->onboarding_seen_at);
        $this->postJson(route('onboarding.finish'), ['outcome' => 'skipped', 'user_id' => $other->id])->assertOk()->assertJson(['saved' => true]);
        $seen = $user->fresh()->onboarding_seen_at;
        $this->assertNotNull($seen);
        $this->assertNull($other->fresh()->onboarding_seen_at);
        $this->travel(1)->hours();
        $this->postJson(route('onboarding.finish'), ['outcome' => 'completed'])->assertOk();
        $this->assertTrue($seen->equalTo($user->fresh()->onboarding_seen_at));
        $this->assertDatabaseCount('inventory_movements', 0);
        // A new HTTP session resolves the account from the persisted database row.
        $response = $this->actingAs($user->fresh())->get('/dashboard')->assertOk();
        preg_match('/data-onboarding-config>(.*?)<\/script>/s', $response->getContent(), $matches);
        $this->assertFalse(json_decode($matches[1], true, flags: JSON_THROW_ON_ERROR)['autoStart']);
    }

    public function test_tour_matches_role_permissions_and_requires_active_authentication(): void
    {
        foreach (Role::cases() as $role) {
            $user = User::factory()->create(['role' => $role]);
            $steps = Onboarding::steps($user);
            $this->assertSame('Selamat datang di ShifaStock', $steps[0]['title']);
            $this->assertArrayNotHasKey('element', $steps[0]);
            $titles = array_column($steps, 'title');
            $this->assertSame($user->can('manage-users'), in_array('Pengguna dan hak akses', $titles));
            $this->assertSame($user->can('view-receipts'), in_array('Penerimaan obat', $titles));
            if ($role === Role::Guest) {
                $this->assertStringContainsString('hanya dapat melihat', json_encode($steps));
            }
        }
        $this->postJson(route('onboarding.finish'), ['outcome' => 'skipped'])->assertUnauthorized();
        $this->actingAs(User::factory()->create(['is_active' => false]))
            ->postJson(route('onboarding.finish'), ['outcome' => 'skipped'])->assertRedirect('/login');
    }

    public function test_custom_permissions_are_reflected_in_the_tour_and_admin_totals_are_real(): void
    {
        $user = User::factory()->create(['role' => Role::Administrator]);
        User::factory()->create(['is_active' => false]);
        DB::table('role_permissions')->updateOrInsert(['role' => Role::Administrator->value, 'permission' => 'view-receipts'], ['allowed' => true]);
        $this->assertContains('Penerimaan obat', array_column(Onboarding::steps($user), 'title'));
        $this->actingAs($user)->get('/dashboard')->assertOk()
            ->assertViewHas('adminStats', ['users' => 2, 'active_users' => 1, 'roles' => 4]);
    }
}
