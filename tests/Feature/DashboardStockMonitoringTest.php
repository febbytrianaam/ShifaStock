<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Location;
use App\Models\Medicine;
use App\Models\StockBalance;
use App\Models\StockParameter;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DashboardStockMonitoringTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    private Medicine $medicine;

    private Location $warehouse;

    protected function setUp(): void
    {
        parent::setUp();
        $this->staff = User::factory()->create(['role' => Role::Pharmacy]);
        $unit = Unit::create(['name' => 'Tablet']);
        $this->medicine = Medicine::create(['code' => 'MON-001', 'name' => 'Obat Monitoring', 'unit_id' => $unit->id, 'is_active' => true]);
        $this->warehouse = Location::create(['code' => 'GDG', 'name' => 'Gudang']);
        $this->actingAs($this->staff);
    }

    private function minimum(Location $location, ?int $quantity = 60): void
    {
        StockParameter::create(['medicine_id' => $this->medicine->id, 'location_id' => $location->id, 'minimum_quantity' => $quantity]);
    }

    private function balance(Location $location, int $quantity): StockBalance
    {
        return StockBalance::create(['medicine_id' => $this->medicine->id, 'location_id' => $location->id, 'quantity' => $quantity]);
    }

    public function test_dashboard_matches_minimum_monitoring_without_any_daily_report(): void
    {
        $this->minimum($this->warehouse);
        $this->balance($this->warehouse, 50);
        $response = $this->get(route('dashboard'))->assertOk()
            ->assertViewHas('stats', fn ($stats) => $stats['shortages'] === 1)
            ->assertSee('Perlu Restock')->assertDontSee('Belum Ada Laporan')
            ->assertSee(route('shortages.index', ['search' => 'MON-001', 'location_id' => $this->warehouse->id]).'#stok-minimum');
        $rows = $response->viewData('shortages');
        $this->assertCount(1, $rows);
        $this->assertSame(50.0, (float) $rows->first()->quantity);
        $this->assertSame('Tablet', $rows->first()->unit);
        $monitor = $this->get(route('shortages.index'))->assertOk()->assertSee('id="stok-minimum"', false);
        $this->assertEquals($monitor->viewData('parameterRows')->all(), $rows->all());
        $this->assertDatabaseCount('nightly_checks', 0);
        $this->assertDatabaseCount('inventory_movements', 0);
        $this->assertSame('50.0000', StockBalance::sole()->quantity);
    }

    public function test_stale_approved_shortage_does_not_override_current_stock(): void
    {
        $this->minimum($this->warehouse);
        $balance = $this->balance($this->warehouse, 80);
        $check = DB::table('nightly_checks')->insertGetId(['date' => '2026-01-01', 'location_id' => $this->warehouse->id, 'created_by' => $this->staff->id, 'status' => 'approved']);
        DB::table('nightly_check_items')->insert(['nightly_check_id' => $check, 'medicine_id' => $this->medicine->id, 'code' => 'MON-001', 'name' => 'Obat Monitoring', 'quantity' => 0, 'is_low' => true, 'unit' => 'Tablet']);
        $this->get(route('dashboard'))->assertOk()->assertViewHas('stats', fn ($stats) => $stats['shortages'] === 0)
            ->assertSee('Tidak ada stok pada atau di bawah minimum');
        $balance->update(['quantity' => 20]);
        $this->get(route('dashboard'))->assertOk()->assertViewHas('stats', fn ($stats) => $stats['shortages'] === 1)
            ->assertViewHas('shortages', fn ($rows) => (float) $rows->sole()->quantity === 20.0);
        $balance->update(['quantity' => 90]);
        $this->get(route('dashboard'))->assertOk()->assertViewHas('shortages', fn ($rows) => $rows->isEmpty());
        $this->assertDatabaseHas('nightly_check_items', ['nightly_check_id' => $check, 'quantity' => 0]);
    }

    public function test_threshold_and_location_are_respected(): void
    {
        $pharmacy = Location::create(['code' => 'APT', 'name' => 'Apotek']);
        $this->minimum($this->warehouse);
        $this->minimum($pharmacy, 10);
        $this->balance($this->warehouse, 60);
        $balance = $this->balance($pharmacy, 11);
        $this->get(route('dashboard'))->assertOk()->assertViewHas('shortages', fn ($rows) => $rows->count() === 1 && $rows->first()->location_id === $this->warehouse->id);
        $balance->update(['quantity' => 0]);
        $response = $this->get(route('dashboard'))->assertOk()->assertViewHas('stats', fn ($stats) => $stats['shortages'] === 2);
        $this->assertSame($pharmacy->id, $response->viewData('shortages')->first()->location_id);
        $this->get(route('shortages.index', ['location_id' => $pharmacy->id, 'status' => 'empty']))->assertOk()
            ->assertViewHas('parameterRows', fn ($rows) => $rows->count() === 1 && (float) $rows->first()->quantity === 0.0);
    }

    public function test_configured_minimum_without_stock_is_zero_but_unconfigured_master_is_not_a_shortage(): void
    {
        $this->minimum($this->warehouse, 0);
        Medicine::create(['code' => 'NO-RULE', 'name' => 'Master belum diatur']);
        $this->get(route('dashboard'))->assertOk()->assertViewHas('stats', fn ($stats) => $stats['shortages'] === 1)
            ->assertViewHas('shortages', fn ($rows) => $rows->count() === 1 && (float) $rows->first()->quantity === 0.0 && $rows->first()->stock_updated_at === null)
            ->assertSee('Belum ada transaksi stok di lokasi ini.');
    }

    public function test_no_minimum_is_not_presented_as_all_medicines_having_sufficient_stock(): void
    {
        $this->minimum($this->warehouse, null);
        $this->balance($this->warehouse, 0);
        $this->get(route('dashboard'))->assertOk()->assertViewHas('hasMonitoringParameters', false)
            ->assertViewHas('stats', fn ($stats) => $stats['shortages'] === 0)
            ->assertSee('Batas minimum stok belum diatur')->assertDontSee('Tidak Ada Kekurangan');
    }

    public function test_guest_sees_real_alerts_but_cannot_change_stock_or_parameters(): void
    {
        $this->minimum($this->warehouse);
        $this->balance($this->warehouse, 50);
        $this->actingAs(User::factory()->create(['role' => Role::Guest]))->get(route('dashboard'))->assertOk()
            ->assertViewHas('stats', fn ($stats) => $stats['shortages'] === 1)->assertSee('Obat Monitoring');
        $this->postJson(route('monitoring-parameters.store'), [])->assertForbidden();
        $this->postJson(route('issues.store'), [])->assertForbidden();
        $this->assertSame('50.0000', StockBalance::sole()->quantity);
    }
}
