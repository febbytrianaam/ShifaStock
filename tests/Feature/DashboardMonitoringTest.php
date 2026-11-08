<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Livewire\Dashboard;
use App\Models\Batch;
use App\Models\BatchStockBalance;
use App\Models\InventoryMovement;
use App\Models\IssueImport;
use App\Models\Location;
use App\Models\Medicine;
use App\Models\StockBalance;
use App\Models\StockParameter;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class DashboardMonitoringTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    private Medicine $medicine;

    private Location $warehouse;

    private Location $pharmacy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-11-08 19:40:00');
        $this->staff = User::factory()->create(['role' => Role::Pharmacy]);
        $unit = Unit::create(['name' => 'Tablet']);
        $this->medicine = Medicine::create(['code' => 'DSH-001', 'name' => 'Obat Dashboard', 'unit_id' => $unit->id, 'is_active' => true]);
        $this->warehouse = Location::create(['code' => 'GDG', 'name' => 'Gudang']);
        $this->pharmacy = Location::create(['code' => 'APT', 'name' => 'Apotek']);
        $this->actingAs($this->staff);
    }

    private function stock(Location $location, int $quantity, ?int $minimum = null): void
    {
        StockBalance::create(['medicine_id' => $this->medicine->id, 'location_id' => $location->id, 'quantity' => $quantity]);
        if ($minimum !== null) {
            StockParameter::create(['medicine_id' => $this->medicine->id, 'location_id' => $location->id, 'minimum_quantity' => $minimum]);
        }
    }

    private function batch(Location $location, string $number, string $expiry, int $quantity): Batch
    {
        $batch = Batch::create(['medicine_id' => $this->medicine->id, 'number' => $number, 'expiry_date' => $expiry]);
        BatchStockBalance::create(['medicine_id' => $this->medicine->id, 'batch_id' => $batch->id, 'location_id' => $location->id, 'quantity' => $quantity]);

        return $batch;
    }

    private function movement(string $type, string $reference, int $quantity, string $at, ?Location $from = null, ?Location $to = null): void
    {
        InventoryMovement::create(['type' => $type, 'reference' => $reference, 'medicine_id' => $this->medicine->id, 'quantity' => $quantity,
            'source_location_id' => $from?->id, 'destination_location_id' => $to?->id, 'occurred_at' => $at, 'created_by' => $this->staff->id]);
    }

    private function check(Location $location, string $status, array $extra = [], array $quantities = [5, null]): int
    {
        $id = DB::table('nightly_checks')->insertGetId(['date' => '2026-11-08', 'location_id' => $location->id, 'created_by' => $this->staff->id, 'status' => $status] + $extra);
        foreach ($quantities as $index => $quantity) {
            $medicine = $index === 0 ? $this->medicine : Medicine::create(['code' => 'CHK-'.$id.'-'.$index, 'name' => 'Obat cek '.$index]);
            DB::table('nightly_check_items')->insert(['nightly_check_id' => $id, 'medicine_id' => $medicine->id, 'code' => $medicine->code, 'name' => $medicine->name, 'quantity' => $quantity]);
        }

        return $id;
    }

    public function test_empty_pharmacy_stock_suggests_a_prefilled_transfer_only_to_staff_who_can_operate(): void
    {
        $this->stock($this->warehouse, 600);
        $this->stock($this->pharmacy, 0, 60);
        $transfer = route('preview.transfers', ['medicine_id' => $this->medicine->id, 'source_location_id' => $this->warehouse->id, 'destination_location_id' => $this->pharmacy->id]);
        $this->dashboard()->assertViewHas('stats', fn ($stats) => $stats['kosong'] === 1 && $stats['menipis'] === 0 && $stats['tracked'] === 1)
            ->assertViewHas('visibleActions', fn ($items) => $items[0]['group'] === 'segera' && $items[0]['action'] === 'Transfer dari Gudang' && $items[0]['actionUrl'] === $transfer)
            ->assertSee('Gudang masih 600 Tablet')->assertSee($transfer);
        $this->actingAs(User::factory()->create(['role' => Role::Guest]));
        $this->dashboard()->assertViewHas('visibleActions', fn ($items) => $items[0]['action'] === 'Lihat')->assertDontSee('Transfer dari Gudang');
    }

    public function test_stock_below_its_own_minimum_is_not_offered_for_transfer(): void
    {
        $this->stock($this->warehouse, 30, 50);
        $this->stock($this->pharmacy, 4, 20);
        $this->dashboard()->assertViewHas('visibleActions', fn ($items) => count($items) === 2
            && collect($items)->every(fn ($item) => $item['group'] === 'pantau' && $item['action'] === 'Ajukan permintaan' && str_contains($item['meta'], 'perlu pesanan ke PBF')))
            ->assertDontSee('Transfer dari');
    }

    public function test_expired_stock_is_counted_and_offered_as_a_special_condition(): void
    {
        $expired = $this->batch($this->pharmacy, 'SB2309', '2026-11-02', 30);
        $this->batch($this->warehouse, 'DX0411', '2026-11-28', 200);
        $this->batch($this->warehouse, 'LATE', '2027-06-01', 10);
        $adjust = route('special-adjustments.index', ['medicine_id' => $this->medicine->id, 'batch_id' => $expired->id, 'location_id' => $this->pharmacy->id, 'condition' => 'expired']);
        $this->dashboard()
            ->assertViewHas('stats', fn ($stats) => $stats['expired'] === 1 && $stats['expiring'] === 1)
            ->assertViewHas('buckets', ['expired' => 1, 'd30' => 1, 'd60' => 0, 'later' => 0])
            ->assertViewHas('actions', fn ($items) => collect($items)->firstWhere('chip', 'Lewat ED')['actionUrl'] === $adjust
                && collect($items)->firstWhere('chip', 'ED 20 hari')['group'] === 'pantau')
            ->assertViewHas('radar', fn ($radar) => count($radar['markers']) === 2 && $radar['markers'][0]['when'] === 'lewat 6 hari')
            ->assertSee('lewat 6 hari');
    }

    public function test_tabs_filter_the_follow_up_list_and_reject_unknown_values(): void
    {
        $this->stock($this->pharmacy, 0, 10);
        $this->check($this->warehouse, 'submitted', ['submitted_at' => '2026-11-08 19:12:00']);
        Livewire::test(Dashboard::class)
            ->assertViewHas('actionCounts', fn ($counts) => $counts['semua'] === 2 && $counts['segera'] === 1 && $counts['menunggu'] === 1 && $counts['pantau'] === 0)
            ->call('setTab', 'menunggu')->assertSet('tab', 'menunggu')
            ->assertViewHas('visibleActions', fn ($items) => count($items) === 1 && $items[0]['chip'] === 'Persetujuan' && $items[0]['action'] === 'Lihat laporan')
            ->call('setTab', 'semua; drop')->assertSet('tab', 'semua');
        $this->actingAs(User::factory()->create(['role' => Role::Pharmacist]));
        Livewire::test(Dashboard::class)->call('setTab', 'menunggu')->assertViewHas('visibleActions', fn ($items) => $items[0]['action'] === 'Tinjau');
    }

    public function test_workflow_steps_follow_todays_checks_and_sales_import(): void
    {
        $this->check($this->warehouse, 'draft', [], [5, null, null, 2]);
        $this->check($this->pharmacy, 'submitted', ['submitted_at' => '2026-11-08 19:12:00']);
        IssueImport::create(['file_hash' => str_repeat('a', 64), 'location_id' => $this->pharmacy->id, 'starts_at' => '2026-11-07 00:00:00', 'status' => 'posted',
            'row_count' => 3, 'sale_count' => 3, 'compound_count' => 0, 'period_start' => '2026-11-07 08:00:00', 'period_end' => '2026-11-07 20:00:00',
            'posted_count' => 3, 'created_by' => $this->staff->id, 'posted_by' => $this->staff->id, 'posted_at' => '2026-11-07 22:05:00']);
        $this->dashboard()->assertViewHas('steps', function ($steps) {
            $steps = collect($steps)->keyBy('title');

            return $steps['Hitung Gudang']['status'] === 'Sedang diisi' && $steps['Hitung Gudang']['progress'] === 50
                && $steps['Hitung Apotek']['status'] === 'Terkirim' && $steps['Persetujuan']['status'] === 'Menunggu'
                && $steps['Impor penjualan']['status'] === 'Belum' && str_contains($steps['Impor penjualan']['detail'], '22.05');
        })->assertViewHas('locations', fn ($rows) => $rows[0]['today'] === 'Sedang diisi' && $rows[1]['today'] === 'Terkirim');
        DB::table('nightly_checks')->where('status', 'draft')->update(['reviewed_by' => $this->staff->id, 'review_note' => 'Hitung ulang rak 2']);
        $this->dashboard()->assertViewHas('steps', fn ($steps) => $steps[0]['status'] === 'Dikembalikan')
            ->assertViewHas('actions', fn ($items) => collect($items)->contains(fn ($item) => $item['chip'] === 'Dikembalikan' && $item['meta'] === 'Catatan: Hitung ulang rak 2'));
    }

    public function test_trend_rebuilds_yesterdays_balance_from_later_movements(): void
    {
        $this->stock($this->pharmacy, 0, 5);
        $this->movement('issue', 'OUT-TODAY', 10, '2026-11-08 10:00:00', $this->pharmacy);
        $this->movement('receipt', 'IN-EARLY', 10, '2026-11-05 09:00:00', null, $this->pharmacy);
        $this->dashboard()->assertViewHas('trend', fn ($trend) => $trend['kosong'] === [1, 1, 1, 0, 0, 0, 1] && $trend['menipis'] === [0, 0, 0, 0, 0, 0, 0])
            ->assertSee('▲ 1 dari kemarin');
    }

    public function test_chart_counts_documents_and_ignores_unchanged_counts(): void
    {
        $this->movement('issue', 'OUT-IMP-5-1', 1, '2026-11-08 08:00:00', $this->pharmacy);
        $this->movement('issue', 'OUT-IMP-5-2', 2, '2026-11-08 09:00:00', $this->pharmacy);
        $this->movement('issue', 'OUT-2026-001', 1, '2026-11-07 09:00:00', $this->pharmacy);
        $this->movement('physical_count', 'CHECK-1', 0, '2026-11-08 21:00:00', $this->pharmacy);
        $this->movement('supplier_return', 'RET-001', 3, '2026-11-06 11:00:00', $this->warehouse);
        $this->movement('opening_balance', 'STOK-001', 50, '2026-11-06 07:00:00', null, $this->warehouse);
        $this->movement('receipt', 'OLD', 5, '2026-10-01 07:00:00', null, $this->warehouse);
        $this->dashboard()->assertViewHas('chart', fn ($chart) => $chart['issues'] === [0, 0, 0, 0, 0, 1, 1] && $chart['adjustments'] === [0, 0, 0, 0, 1, 0, 0]
            && array_sum($chart['receipts']) === 0 && $chart['total'] === 3 && $chart['labels'][6] === 'Hari ini')
            ->assertSee('id="stockChart"', false);
    }

    public function test_menu_counts_shortages_and_submitted_checks_on_every_page(): void
    {
        $this->stock($this->warehouse, 2, 10);
        $this->check($this->pharmacy, 'submitted', ['submitted_at' => '2026-11-08 19:12:00']);
        $this->get(route('preview.reports'))->assertOk()
            ->assertSee('data-nav-count="shortages.index"', false)->assertSee('data-nav-count="preview.nightly"', false);
        $this->actingAs(User::factory()->create(['role' => Role::Administrator]))->get(route('dashboard'))->assertOk()
            ->assertDontSee('data-nav-count', false)->assertSee('Portal Administrator');
    }
}
