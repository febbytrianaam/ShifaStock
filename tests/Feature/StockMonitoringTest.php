<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Livewire\StockMonitoring;
use App\Models\Batch;
use App\Models\BatchStockBalance;
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

class StockMonitoringTest extends TestCase
{
    use RefreshDatabase;

    private Unit $unit;

    private Location $warehouse;

    private Location $pharmacy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-11-08 19:40:00');
        $this->unit = Unit::create(['name' => 'Tablet']);
        $this->warehouse = Location::create(['code' => 'GDG', 'name' => 'Gudang']);
        $this->pharmacy = Location::create(['code' => 'APT', 'name' => 'Apotek']);
        $this->actingAs(User::factory()->create(['role' => Role::Pharmacy]));
    }

    /** @param array{0: int, 1: ?int} $warehouse @param array{0: int, 1: ?int} $pharmacy [quantity, minimum] */
    private function medicine(string $code, string $name, array $warehouse, array $pharmacy): Medicine
    {
        $medicine = Medicine::create(['code' => $code, 'name' => $name, 'unit_id' => $this->unit->id, 'dosage_form' => 'Tablet', 'is_active' => true]);
        foreach ([[$this->warehouse, $warehouse], [$this->pharmacy, $pharmacy]] as [$location, [$quantity, $minimum]]) {
            StockBalance::create(['medicine_id' => $medicine->id, 'location_id' => $location->id, 'quantity' => $quantity]);
            if ($minimum !== null) {
                StockParameter::create(['medicine_id' => $medicine->id, 'location_id' => $location->id, 'minimum_quantity' => $minimum]);
            }
        }

        return $medicine;
    }

    private function prototypeStock(): array
    {
        return [
            'AMX' => $this->medicine('AMX', 'Amoksisilin', [600, 100], [0, 60]),
            'CTZ' => $this->medicine('CTZ', 'Cetirizin', [0, 50], [4, 20]),
            'AMB' => $this->medicine('AMB', 'Ambroksol', [12, null], [6, 10]),
            'ANT' => $this->medicine('ANT', 'Antasida', [40, 100], [35, 20]),
            'PCT' => $this->medicine('PCT', 'Parasetamol', [15, 10], [0, 10]),
            'OK' => $this->medicine('OK', 'Obat Aman', [100, 10], [50, 10]),
        ];
    }

    public function test_one_row_per_medicine_suggests_warehouse_transfers_before_pbf_orders(): void
    {
        $meds = $this->prototypeStock();
        $page = $this->monitoring()->assertSee('id="stok-minimum"', false)->assertSee('Buka form transfer (3 obat)')->assertSee('Salin daftar pesanan');
        $medicines = collect($page->viewData('medicines'));

        $this->assertSame(['AMX', 'CTZ', 'PCT', 'AMB', 'ANT'], $medicines->pluck('code')->all());
        $this->assertSame([
            'AMX' => 'Transfer 60 Tablet dari Gudang',
            'CTZ' => 'Pesan ± 66 Tablet ke PBF',
            'PCT' => 'Transfer 5 Tablet dari Gudang',
            'AMB' => 'Transfer 4 Tablet dari Gudang',
            'ANT' => 'Pesan ± 60 Tablet ke PBF',
        ], $medicines->mapWithKeys(fn ($row) => [$row['code'] => $row['suggestion']['label']])->all());
        $this->assertSame('Semua lokasi di bawah minimum', $medicines->firstWhere('code', 'CTZ')['suggestion']['note']);
        $this->assertSame('Stok Gudang di bawah minimum', $medicines->firstWhere('code', 'ANT')['suggestion']['note']);
        $this->assertStringContainsString('sisa ± 5 Tablet dipesan ke PBF', $medicines->firstWhere('code', 'PCT')['suggestion']['note']);
        $this->assertSame(['kosong', 'aman'], array_column(array_reverse($medicines->firstWhere('code', 'AMX')['cells']), 'status'));
        $this->assertSame('none', $medicines->firstWhere('code', 'AMB')['cells'][0]['status']);

        $transfer = $page->viewData('transfers')[0];
        $this->assertSame('Apotek', $transfer['destination']);
        $this->assertSame([['AMB', 4.0], ['AMX', 60.0], ['PCT', 5.0]], array_map(fn ($item) => [$item['code'], $item['quantity']], $transfer['items']));
        $this->assertSame(route('preview.transfers', [
            'source_location_id' => $this->warehouse->id, 'destination_location_id' => $this->pharmacy->id,
            'items' => [['medicine_id' => $meds['AMB']->id, 'quantity' => '4'], ['medicine_id' => $meds['AMX']->id, 'quantity' => '60'], ['medicine_id' => $meds['PCT']->id, 'quantity' => '5']],
        ]), $transfer['url']);
        $this->assertSame([['Antasida', 60.0], ['Cetirizin', 66.0], ['Parasetamol', 5.0]], array_map(fn ($order) => [$order['name'], $order['quantity']], $page->viewData('orders')));
        $this->assertSame("Daftar pesanan ke PBF, 8 November 2026\n1. Antasida (ANT): ± 60 Tablet\n2. Cetirizin (CTZ): ± 66 Tablet\n3. Parasetamol (PCT): ± 5 Tablet", $page->viewData('orderText'));
        $page->assertViewHas('stats', fn ($stats) => $stats['kosong'] === 3 && $stats['menipis'] === 3 && $stats['kosongSplit'] === '2 di Apotek · 1 di Gudang');
        $this->assertDatabaseCount('inventory_movements', 0);
    }

    public function test_stock_exactly_at_minimum_is_flagged_without_inventing_a_quantity(): void
    {
        $this->medicine('EDGE', 'Obat Batas', [50, null], [10, 10]);
        $this->medicine('EDGE2', 'Obat Batas Gudang', [20, 20], [5, null]);
        $medicines = collect($this->monitoring()->viewData('medicines'));

        $this->assertSame('Transfer dari Gudang', $medicines->firstWhere('code', 'EDGE')['suggestion']['label']);
        $this->assertSame('Pesan ke PBF', $medicines->firstWhere('code', 'EDGE2')['suggestion']['label']);
        $this->assertNull($this->monitoring()->viewData('orders')[0]['quantity']);
        $this->assertStringContainsString('Obat Batas Gudang (EDGE2): jumlah ditentukan', $this->monitoring()->viewData('orderText'));
    }

    public function test_search_location_and_kpi_filters_narrow_the_list(): void
    {
        $this->prototypeStock();
        $codes = fn ($page) => array_column($page->viewData('visibleMedicines'), 'code');

        $this->assertSame(['CTZ', 'ANT'], $codes($this->monitoring(['location_id' => $this->warehouse->id])));
        $this->assertSame(['AMX', 'CTZ', 'PCT'], $codes($this->monitoring(['status' => 'empty'])));
        $this->assertSame(['ANT'], $codes($this->monitoring(['location_id' => $this->warehouse->id, 'status' => 'low'])));
        $this->assertSame(['AMX'], $codes($this->monitoring(['search' => 'amx'])));

        $page = Livewire::withQueryParams([])->test(StockMonitoring::class)->set('search', 'sida');
        $this->assertSame(['ANT'], $codes($page));
        $page->set('search', '')->call('toggleFilter', 'menipis')->assertSet('filter', 'menipis');
        $this->assertSame(['CTZ', 'AMB', 'ANT'], $codes($page));
        $page->call('toggleFilter', 'menipis')->assertSet('filter', null)->call('setLocation', $this->pharmacy->id);
        $this->assertSame(['AMX', 'CTZ', 'PCT', 'AMB'], $codes($page));
        $page->call('toggleFilter', 'expired')->assertSet('tab', 'ed')->call('setTab', 'minimum')->assertSet('filter', null)->call('setLocation');
        $this->assertCount(5, $page->viewData('visibleMedicines'));
        $page->call('setTab', 'bogus')->assertSet('tab', 'minimum')->call('toggleFilter', 'bogus')->assertSet('filter', null);
    }

    public function test_expiry_tab_groups_batches_by_urgency_with_matching_actions(): void
    {
        $medicine = $this->medicine('SAL', 'Salbutamol', [0, null], [0, null]);
        $batch = fn (Location $location, string $number, string $expiry) => BatchStockBalance::create([
            'medicine_id' => $medicine->id, 'location_id' => $location->id, 'quantity' => 30,
            'batch_id' => Batch::create(['medicine_id' => $medicine->id, 'number' => $number, 'expiry_date' => $expiry])->id,
        ]);
        $expired = $batch($this->pharmacy, 'SB-LEWAT', '2026-11-02');
        $batch($this->warehouse, 'SB-30', '2026-11-28');
        $batch($this->warehouse, 'SB-LATER', '2027-01-25');
        $batch($this->warehouse, 'SB-AMAN', '2027-06-01');

        $page = $this->monitoring(['tab' => 'ed'])->assertSee('Lewat 6 hari')->assertSee('20 hari lagi')->assertDontSee('SB-AMAN')
            ->assertViewHas('stats', fn ($stats) => $stats['expired'] === 1 && $stats['near'] === 2 && $stats['nearestDays'] === 20)
            ->assertViewHas('expiryBatchCount', 3);
        $groups = $page->viewData('visibleExpiries');
        $this->assertSame(['expired', 'd30', 'later'], array_keys($groups));
        $this->assertSame(['Catat kondisi khusus', 'Retur ke PBF', 'Kartu stok'], array_column($groups['expired'][0]['actions'], 'label'));
        $this->assertSame(route('special-adjustments.index', ['medicine_id' => $medicine->id, 'batch_id' => $expired->batch_id, 'location_id' => $this->pharmacy->id, 'condition' => 'expired']),
            $groups['expired'][0]['actions'][0]['url']);
        $this->assertSame(['Kartu stok'], array_column($groups['d30'][0]['actions'], 'label'));

        $near = $this->monitoring(['status' => 'near'])->assertSet('tab', 'ed')->viewData('visibleExpiries');
        $this->assertSame(['d30', 'later'], array_keys($near));
    }

    public function test_count_tab_lists_latest_approved_results_and_copyable_requests(): void
    {
        $first = $this->medicine('REQ1', 'Obat Diminta', [0, null], [0, null]);
        $second = $this->medicine('REQ2', 'Obat Belum Diminta', [0, null], [0, null]);
        $check = DB::table('nightly_checks')->insertGetId(['date' => '2026-11-07', 'location_id' => $this->pharmacy->id, 'created_by' => auth()->id(), 'status' => 'approved']);
        DB::table('nightly_check_items')->insert([
            ['nightly_check_id' => $check, 'medicine_id' => $first->id, 'code' => 'REQ1', 'name' => 'Obat Diminta', 'quantity' => 0, 'is_low' => true, 'requested_quantity' => 30, 'unit' => 'Tablet'],
            ['nightly_check_id' => $check, 'medicine_id' => $second->id, 'code' => 'REQ2', 'name' => 'Obat Belum Diminta', 'quantity' => 3, 'is_low' => true, 'requested_quantity' => null, 'unit' => 'Tablet'],
        ]);

        $page = $this->monitoring(['tab' => 'hitung'])->assertSee('Salin daftar permintaan')->assertSee(route('nightly.show', $check))
            ->assertViewHas('stats', fn ($stats) => $stats['requested'] === 1 && $stats['unrequested'] === 1);
        $this->assertSame([['REQ2', 'menipis', null], ['REQ1', 'kosong', '30 Tablet']],
            array_map(fn ($row) => [$row['code'], $row['status'], $row['requested']], $page->viewData('visibleCounts')));
        $this->assertSame("Permintaan dari pemeriksaan, 8 November 2026\n1. Obat Belum Diminta (Apotek): jumlah belum ditentukan\n2. Obat Diminta (Apotek): 30 Tablet", $page->viewData('countText'));
    }

    public function test_guest_reads_the_same_plan_without_transaction_shortcuts(): void
    {
        $this->prototypeStock();
        $this->actingAs(User::factory()->create(['role' => Role::Guest]));
        $page = $this->monitoring()->assertSee('Transfer 60 Tablet dari Gudang')->assertDontSee('Buka form transfer')->assertDontSee(route('preview.transfers'), false);

        $this->assertNull($page->viewData('transfers')[0]['url']);
        $this->assertTrue(collect($page->viewData('medicines'))->every(fn ($row) => $row['suggestion']['url'] === null));
    }

    public function test_expiry_tab_is_hidden_when_the_role_cannot_view_expiry(): void
    {
        DB::table('role_permissions')->updateOrInsert(['role' => Role::Pharmacy->value, 'permission' => 'view-expiry'], ['allowed' => false]);

        $this->monitoring(['tab' => 'ed'])->assertViewHas('activeTab', 'minimum')->assertDontSee('data-monitor-tab="ed"', false)->assertDontSee('Lewat ED');
    }

    public function test_query_parameters_are_validated(): void
    {
        $this->getJson(route('shortages.index', ['status' => 'semua']))->assertUnprocessable();
        $this->getJson(route('shortages.index', ['tab' => 'laporan']))->assertUnprocessable();
        $this->getJson(route('shortages.index', ['search' => ['x']]))->assertUnprocessable();
        $this->get(route('shortages.index', ['location_id' => 999]))->assertRedirect(route('shortages.index'));
    }

    public function test_transfer_form_accepts_several_prefilled_lines(): void
    {
        $meds = $this->prototypeStock();
        $url = route('preview.transfers', [
            'source_location_id' => $this->warehouse->id, 'destination_location_id' => $this->pharmacy->id,
            'items' => [['medicine_id' => $meds['AMX']->id, 'quantity' => '60'], ['medicine_id' => $meds['AMB']->id]],
        ]);

        $this->get($url)->assertOk()
            ->assertViewHas('prefillItems', [['medicine_id' => (string) $meds['AMX']->id, 'batch_id' => '', 'quantity' => '60'], ['medicine_id' => (string) $meds['AMB']->id, 'batch_id' => '', 'quantity' => '']])
            ->assertSee('name="items[1][quantity]"', false)->assertDontSee('name="items[2][quantity]"', false)
            ->assertSee('id="transfer-quantity" name="items[0][quantity]" data-transfer-quantity type="number" min="0.0001" max="999999999999.9999" step="0.0001" placeholder="0" value="60"', false);

        $foreign = Batch::create(['medicine_id' => $meds['CTZ']->id, 'number' => 'LAIN', 'expiry_date' => '2027-12-31']);
        $this->getJson(route('preview.transfers', ['items' => [['medicine_id' => $meds['AMX']->id, 'batch_id' => $foreign->id]]]))->assertUnprocessable();
        $this->getJson(route('preview.transfers', ['items' => [['quantity' => '5']]]))->assertUnprocessable();
        $this->getJson(route('preview.transfers', ['items' => array_fill(0, 51, ['medicine_id' => $meds['AMX']->id])]))->assertUnprocessable();
        $this->get(route('preview.transfers'))->assertOk()->assertViewHas('prefillItems', [])->assertSee('name="items[1][quantity]"', false);
    }
}
