<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\BatchStockBalance;
use App\Models\InventoryMovement;
use App\Models\Location;
use App\Models\Medicine;
use App\Models\StockBalance;
use App\Models\Unit;
use App\Models\User;
use App\Services\DemoInventoryCleanup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class DemoInventoryCleanupTest extends TestCase
{
    use RefreshDatabase;

    private function medicine(string $code, string $name, array $extra = []): Medicine
    {
        $unit = Unit::firstOrCreate(['name' => 'Tablet']);

        return Medicine::create($extra + ['code' => $code, 'name' => $name, 'unit_id' => $unit->id, 'dosage_form' => 'Tablet']);
    }

    public function test_cleanup_is_dry_run_by_default_and_preserves_every_imported_master(): void
    {
        $dummy = $this->medicine('OBT-001', 'Paracetamol 500 mg');
        $official = $this->medicine('OBT-002', 'Amoxicillin 500 mg', ['import_source' => ['file' => 'obat.xlsx']]);
        $other = $this->medicine('Pil 34', 'Pil KB', ['import_source' => ['file' => 'obat.xlsx']]);
        $before = $official->fresh()->toJson().$other->fresh()->toJson();
        $service = app(DemoInventoryCleanup::class);
        $this->assertFalse($service->run()['applied']);
        $this->assertDatabaseCount('medicines', 3);
        $result = $service->run(true);
        $this->assertSame(1, $result['dummy_medicines']);
        $this->assertFileExists($result['backup']);
        $backup = json_decode(file_get_contents($result['backup']), true, flags: JSON_THROW_ON_ERROR);
        $this->assertCount(3, $backup['tables']['medicines']);
        $this->assertArrayNotHasKey('users', $backup['tables']);
        $this->assertDatabaseMissing('medicines', ['id' => $dummy->id]);
        $this->assertSame($before, $official->fresh()->toJson().$other->fresh()->toJson());
        $this->assertFalse($service->run(true)['applied']);
    }

    public function test_approved_test_ledger_is_removed_without_deleting_the_official_medicine(): void
    {
        $medicine = $this->medicine('ACI', 'ACIFAR', ['import_source' => ['source' => 'review.xlsx']]);
        $user = User::factory()->create();
        $location = Location::create(['code' => 'GDG', 'name' => 'Gudang']);
        $batch = Batch::create(['medicine_id' => $medicine->id, 'number' => 'test batch 1']);
        InventoryMovement::create(['reference' => 'STOK-2026-001', 'type' => 'opening_balance', 'medicine_id' => $medicine->id,
            'batch_id' => $batch->id, 'quantity' => 20, 'destination_location_id' => $location->id, 'occurred_at' => now(), 'created_by' => $user->id]);
        StockBalance::create(['medicine_id' => $medicine->id, 'location_id' => $location->id, 'quantity' => 20]);
        BatchStockBalance::create(['medicine_id' => $medicine->id, 'batch_id' => $batch->id, 'location_id' => $location->id, 'quantity' => 20]);
        $result = app(DemoInventoryCleanup::class)->run(true);
        $this->assertSame('sesuai', $result['reconciliation']);
        $this->assertDatabaseHas('medicines', ['id' => $medicine->id]);
        foreach (['inventory_movements', 'batches', 'stock_balances', 'batch_stock_balances'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
    }

    public function test_unknown_history_aborts_without_deleting_anything(): void
    {
        $this->medicine('OBT-001', 'Paracetamol 500 mg');
        $medicine = $this->medicine('ACI', 'ACIFAR');
        Batch::create(['medicine_id' => $medicine->id, 'number' => 'test batch 1']);
        Batch::create(['medicine_id' => $medicine->id, 'number' => 'REAL-BATCH']);
        try {
            app(DemoInventoryCleanup::class)->run(true);
            $this->fail('Mixed real inventory must block cleanup.');
        } catch (RuntimeException $error) {
            $this->assertStringContainsString('campuran', $error->getMessage());
        }
        $this->assertDatabaseCount('medicines', 2);
        $this->assertDatabaseCount('batches', 2);
    }

    public function test_cleanup_refuses_production_environment(): void
    {
        $this->app->instance('env', 'production');
        $this->expectException(RuntimeException::class);
        app(DemoInventoryCleanup::class)->run(true);
    }

    public function test_filled_daily_draft_blocks_cleanup_and_preserves_the_counts(): void
    {
        $medicine = $this->medicine('OBT-001', 'Paracetamol 500 mg');
        $user = User::factory()->create();
        $location = Location::create(['code' => 'GDG', 'name' => 'Gudang']);
        $check = DB::table('nightly_checks')->insertGetId(['date' => '2026-10-20', 'location_id' => $location->id, 'created_by' => $user->id, 'status' => 'draft']);
        DB::table('nightly_check_items')->insert(['nightly_check_id' => $check, 'medicine_id' => $medicine->id,
            'code' => $medicine->code, 'name' => $medicine->name, 'quantity' => 7]);
        try {
            app(DemoInventoryCleanup::class)->run(true);
            $this->fail('Filled daily checks must be preserved.');
        } catch (RuntimeException $error) {
            $this->assertStringContainsString('sudah diisi', $error->getMessage());
        }
        $this->assertDatabaseHas('nightly_check_items', ['nightly_check_id' => $check, 'quantity' => 7]);
        $this->assertDatabaseHas('medicines', ['id' => $medicine->id]);
    }
}
