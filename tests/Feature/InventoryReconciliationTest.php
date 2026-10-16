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
use App\Services\InventoryReconciliationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class InventoryReconciliationTest extends TestCase
{
    use RefreshDatabase;

    public function test_empty_database_is_reported_as_zero_pairs_not_as_a_full_stock_validation(): void
    {
        $report = app(InventoryReconciliationService::class)->report();
        $this->assertSame(0, $report['checked_pairs']);
        $this->assertSame([], $report['rows']);
        $this->artisan('inventory:reconcile')->assertExitCode(0);
    }

    public function test_orphan_totals_batches_and_ledger_are_all_included_and_audit_is_read_only(): void
    {
        $unit = Unit::create(['name' => 'Tablet']);
        $location = Location::create(['code' => 'GDG', 'name' => 'Gudang']);
        $user = User::factory()->create(['role' => Role::Pharmacy]);
        $medicines = collect(['TOTAL-ONLY', 'BATCH-ONLY', 'LEDGER-ONLY'])->map(fn ($code) => Medicine::create([
            'code' => $code, 'name' => $code, 'unit_id' => $unit->id, 'dosage_form' => 'Tablet',
        ]));
        StockBalance::create(['medicine_id' => $medicines[0]->id, 'location_id' => $location->id, 'quantity' => 3]);
        $batch = Batch::create(['medicine_id' => $medicines[1]->id, 'number' => 'ORPHAN', 'expiry_date' => '2028-01-01']);
        BatchStockBalance::create(['medicine_id' => $medicines[1]->id, 'batch_id' => $batch->id, 'location_id' => $location->id, 'quantity' => 2]);
        InventoryMovement::create(['type' => 'receipt', 'reference' => 'ORPHAN-LEDGER', 'medicine_id' => $medicines[2]->id,
            'destination_location_id' => $location->id, 'quantity' => 4, 'occurred_at' => now(), 'created_by' => $user->id]);
        $before = [StockBalance::all()->toJson(), BatchStockBalance::all()->toJson(), InventoryMovement::all()->toJson()];

        $this->assertSame(1, Artisan::call('inventory:reconcile', ['--json' => true]));
        $report = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame(3, $report['checked_pairs']);
        $this->assertSame(3, $report['mismatched_pairs']);
        $rows = collect($report['rows'])->keyBy('medicine_code');
        $this->assertEquals(3, $rows['TOTAL-ONLY']['stock_minus_batch']);
        $this->assertEquals(-2, $rows['BATCH-ONLY']['stock_minus_batch']);
        $this->assertEquals(-4, $rows['LEDGER-ONLY']['stock_minus_ledger']);
        $this->assertSame($before, [StockBalance::all()->toJson(), BatchStockBalance::all()->toJson(), InventoryMovement::all()->toJson()]);
    }

    public function test_equal_quantities_do_not_hide_a_batch_assigned_to_the_wrong_medicine(): void
    {
        $unit = Unit::create(['name' => 'Tablet']);
        $location = Location::create(['code' => 'GDG', 'name' => 'Gudang']);
        $user = User::factory()->create();
        $a = Medicine::create(['code' => 'A', 'name' => 'A', 'unit_id' => $unit->id]);
        $b = Medicine::create(['code' => 'B', 'name' => 'B', 'unit_id' => $unit->id]);
        $batch = Batch::create(['medicine_id' => $b->id, 'number' => 'WRONG-OWNER', 'expiry_date' => '2028-01-01']);
        StockBalance::create(['medicine_id' => $a->id, 'location_id' => $location->id, 'quantity' => 5]);
        BatchStockBalance::create(['medicine_id' => $a->id, 'batch_id' => $batch->id, 'location_id' => $location->id, 'quantity' => 5]);
        InventoryMovement::create(['type' => 'receipt', 'reference' => 'WRONG-OWNER', 'medicine_id' => $a->id,
            'destination_location_id' => $location->id, 'quantity' => 5, 'occurred_at' => now(), 'created_by' => $user->id]);

        $report = app(InventoryReconciliationService::class)->report();
        $this->assertSame(0, $report['mismatched_pairs']);
        $this->assertCount(1, $report['batch_identity_issues']);
        $this->artisan('inventory:reconcile')->assertExitCode(1);
    }
}
