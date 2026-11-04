<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Batch;
use App\Models\BatchStockBalance;
use App\Models\InventoryMovement;
use App\Models\Location;
use App\Models\Medicine;
use App\Models\StockBalance;
use App\Models\Unit;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditRegressionTest extends TestCase
{
    use RefreshDatabase;

    private Medicine $medicine;
    private Location $warehouse;
    private Location $pharmacy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-03 10:00:00', 'Asia/Jakarta'));
        $this->actingAs(User::factory()->create(['role' => Role::Pharmacist]));
        $unit = Unit::create(['name' => 'Tablet']);
        $this->medicine = Medicine::create(['code' => 'AUDIT', 'name' => 'Obat Audit', 'unit_id' => $unit->id,
            'dosage_form' => 'Tablet', 'is_active' => true]);
        $this->warehouse = Location::create(['code' => 'GDG', 'name' => 'Gudang', 'is_active' => true]);
        $this->pharmacy = Location::create(['code' => 'APT', 'name' => 'Apotek', 'is_active' => true]);
    }

    private function batch(string $number, string $expiry, float $warehouse, float $pharmacy = 0): Batch
    {
        $batch = Batch::create(['medicine_id' => $this->medicine->id, 'number' => $number, 'expiry_date' => $expiry]);
        foreach ([[$this->warehouse, $warehouse], [$this->pharmacy, $pharmacy]] as [$location, $quantity]) {
            BatchStockBalance::create(['medicine_id' => $this->medicine->id, 'batch_id' => $batch->id,
                'location_id' => $location->id, 'quantity' => $quantity]);
        }

        return $batch;
    }

    public function test_expiry_does_not_count_or_display_empty_batches(): void
    {
        $this->batch('EMPTY', '2026-10-01', 0);
        $this->batch('STOCKED', '2026-12-01', 10);
        $this->get(route('preview.expiry'))->assertOk()
            ->assertViewHas('counts', ['expired' => 0, 'near' => 1, 'safe' => 0])
            ->assertViewHas('batches', fn ($rows) => $rows->total() === 1 && $rows->first()->number === 'STOCKED');
    }

    public function test_expiry_location_filter_scopes_displayed_locations_and_totals(): void
    {
        $this->batch('SHARED', '2026-12-01', 100, 7);
        $this->get(route('preview.expiry', ['location_id' => $this->pharmacy->id]))->assertOk()
            ->assertViewHas('batches', fn ($rows) => $rows->first()->balances->count() === 1
                && (int) $rows->first()->balances->first()->location_id === $this->pharmacy->id
                && (float) $rows->first()->balances->sum('quantity') === 7.0);
    }

    public function test_expiry_badge_matches_calendar_month_cutoff(): void
    {
        $this->travelTo(Carbon::parse('2026-01-01 10:00:00', 'Asia/Jakarta'));
        $this->batch('AFTER-THREE-MONTHS', '2026-04-02', 1);
        $this->get(route('preview.expiry', ['status' => 'safe']))->assertOk()
            ->assertViewHas('counts', ['expired' => 0, 'near' => 0, 'safe' => 1])
            ->assertSee('Aman')->assertDontSee('91 hari lagi');
    }

    public function test_expiry_accepts_upper_date_without_lower_date(): void
    {
        $this->getJson(route('preview.expiry', ['to' => '2026-12-31']))->assertOk();
    }

    public function test_history_accepts_upper_date_without_lower_date(): void
    {
        $this->getJson(route('nightly.history', ['to' => '2026-11-03']))->assertOk();
    }

    public function test_reports_accept_upper_date_without_explicit_start(): void
    {
        $this->getJson(route('preview.reports', ['end' => '2026-11-03']))->assertOk();
    }

    public function test_monitoring_rejects_non_array_rows_without_writing_settings(): void
    {
        $this->postJson(route('monitoring-parameters.store'), ['expiry_warning_months' => 6,
            'minimums' => [$this->medicine->id => 10]])->assertUnprocessable();
        $this->assertDatabaseMissing('system_settings', ['key' => 'expiry_warning_months']);
        $this->assertDatabaseCount('stock_parameters', 0);
    }

    public function test_monitoring_rejects_unknown_foreign_keys_without_partial_changes(): void
    {
        $this->postJson(route('monitoring-parameters.store'), ['expiry_warning_months' => 6,
            'minimums' => [$this->medicine->id => [$this->warehouse->id => 5], 999999 => [$this->warehouse->id => 4]]])
            ->assertUnprocessable();
        $this->assertDatabaseMissing('system_settings', ['key' => 'expiry_warning_months']);
        $this->assertDatabaseCount('stock_parameters', 0);
    }

    public function test_receipt_rejects_scalar_rows_without_server_error(): void
    {
        $this->postJson(route('receipts.store'), ['items' => ['not-a-row']])->assertUnprocessable();
        $this->assertDatabaseCount('inventory_receipts', 0);
        $this->assertDatabaseCount('inventory_movements', 0);
    }

    public function test_receipt_rejects_nested_medicine_id_without_server_error(): void
    {
        $this->postJson(route('receipts.store'), ['items' => [['medicine_id' => [$this->medicine->id],
            'input_unit_name' => 'Tablet']]])->assertUnprocessable();
        $this->assertDatabaseCount('inventory_receipts', 0);
    }

    public function test_scalar_inventory_filters_do_not_accept_array_ids(): void
    {
        $filters = ['medicine_id' => [$this->medicine->id], 'location_id' => $this->warehouse->id,
            'start' => '2026-11-01', 'end' => '2026-11-03'];
        $this->getJson(route('stock-card.index', $filters))->assertUnprocessable();
        $this->getJson(route('preview.expiry', ['location_id' => [$this->warehouse->id]]))->assertUnprocessable();
        $this->getJson(route('shortages.index', ['location_id' => [$this->warehouse->id]]))->assertUnprocessable();
        $this->assertDatabaseCount('inventory_movements', 0);
    }

    public function test_csv_export_keeps_text_from_becoming_spreadsheet_formulas(): void
    {
        $this->medicine->update(['name' => '=1+1', 'code' => '@AUDIT']);
        StockBalance::create(['medicine_id' => $this->medicine->id, 'location_id' => $this->warehouse->id, 'quantity' => 2]);
        InventoryMovement::create(['type' => 'opening_balance', 'reference' => 'AUDIT-OPEN',
            'medicine_id' => $this->medicine->id, 'destination_location_id' => $this->warehouse->id,
            'quantity' => 2, 'occurred_at' => now(), 'created_by' => auth()->id()]);
        $response = $this->get(route('preview.reports', ['start' => '2026-11-01', 'end' => '2026-11-03', 'export' => 'csv']))->assertOk();
        $lines = explode("\n", trim($response->streamedContent()));
        $row = str_getcsv($lines[1], ',', '"', '');
        $this->assertSame("'=1+1", $row[0]);
        $this->assertSame("'@AUDIT", $row[1]);
        $this->assertSame('2', $row[6]);
        $this->assertDatabaseHas('medicines', ['id' => $this->medicine->id, 'name' => '=1+1']);
    }
}
