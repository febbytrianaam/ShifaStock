<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Location;
use App\Models\Medicine;
use App\Models\BatchStockBalance;
use App\Models\StockBalance;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class InventoryFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_receipt_transfer_and_approved_count_share_one_stock_balance(): void
    {
        $this->travelTo(\Carbon\Carbon::parse('2026-01-01 10:00:00', 'Asia/Jakarta'));
        $petugas = User::factory()->create(['role' => Role::Pharmacy]);
        $kepala = User::factory()->create(['role' => Role::Pharmacist]);
        $unit = Unit::create(['name' => 'Tablet']);
        $gudang = Location::create(['code' => 'GDG', 'name' => 'Gudang']);
        $apotek = Location::create(['code' => 'APT', 'name' => 'Apotek']);
        $medicine = Medicine::create(['code' => 'OBT-A', 'name' => 'Obat A', 'dosage_form' => 'Tablet', 'unit_id' => $unit->id]);

        $this->actingAs($petugas)->post('/obat-masuk', [
            'reference' => 'FKT-001', 'medicine_id' => $medicine->id, 'batch_number' => 'BA-01',
            'expiry_date' => '2027-12-31', 'quantity' => 100, 'occurred_at' => '2026-01-01T08:00',
            'counterparty' => 'PBF Sehat', 'destination_location_id' => $gudang->id,
        ])->assertRedirect();
        $this->assertSame(100.0, (float) StockBalance::whereBelongsTo($medicine)->whereBelongsTo($gudang)->value('quantity'));
        $batchId = DB::table('batches')->where('number', 'BA-01')->value('id');
        $this->assertSame(100.0, (float) BatchStockBalance::where('batch_id', $batchId)->whereBelongsTo($gudang)->value('quantity'));

        $this->post('/transfer', [
            'reference' => 'TRF-001', 'medicine_id' => $medicine->id,
            'source_location_id' => $gudang->id, 'destination_location_id' => $apotek->id,
            'quantity' => 30, 'occurred_at' => '2026-01-01T09:00',
        ])->assertRedirect();
        $this->assertSame(70.0, (float) StockBalance::whereBelongsTo($medicine)->whereBelongsTo($gudang)->value('quantity'));
        $this->assertSame(30.0, (float) StockBalance::whereBelongsTo($medicine)->whereBelongsTo($apotek)->value('quantity'));
        $this->assertSame(70.0, (float) BatchStockBalance::where('batch_id', $batchId)->whereBelongsTo($gudang)->value('quantity'));
        $this->assertSame(30.0, (float) BatchStockBalance::where('batch_id', $batchId)->whereBelongsTo($apotek)->value('quantity'));

        $this->post('/pengecekan-malam', ['date' => '2026-01-01', 'location_id' => $apotek->id]);
        $check = DB::table('nightly_checks')->first();
        $item = DB::table('nightly_check_items')->first();
        $this->post('/pengecekan-malam/'.$check->id, ['action' => 'submit', 'items' => [
            $item->id => ['quantity' => 25, 'unit' => 'Tablet', 'is_low' => 1, 'requested_quantity' => 20, 'batch_counts' => [$batchId => 25]],
        ]]);
        $this->actingAs($kepala)->post('/pengecekan-malam/'.$check->id.'/review', ['action' => 'approve'])->assertRedirect();
        $this->assertSame(25.0, (float) StockBalance::whereBelongsTo($medicine)->whereBelongsTo($apotek)->value('quantity'));
        $this->assertDatabaseHas('inventory_movements', ['type' => 'physical_count', 'reference' => 'CHECK-'.$check->id, 'quantity' => 5]);

        $this->get('/dashboard?data=real')->assertOk()->assertSee('Obat A')
            ->assertViewHas('balances', fn ($balances) => $balances->contains(fn ($balance) => $balance->location_id === $apotek->id && (float) $balance->quantity === 25.0));
        $this->get('/kedaluwarsa')->assertOk()->assertSee('BA-01')->assertSee('31/12/2027');
        $this->get('/laporan')->assertOk()->assertSee('FKT-001')->assertSee('TRF-001');
        $this->get('/stock-opname?period=2026-01')->assertOk()->assertSee('Januari 2026')->assertSee('Obat A');
        $this->get('/kartu-stok?medicine_id='.$medicine->id.'&location_id='.$apotek->id.'&start=2026-01-01&end=2026-01-01')
            ->assertOk()
            ->assertSee('TRF-001')
            ->assertSee('CHECK-'.$check->id);
    }

    public function test_transfer_rejects_insufficient_stock_without_changing_balances(): void
    {
        $petugas = User::factory()->create(['role' => Role::Pharmacy]);
        $unit = Unit::create(['name' => 'Tablet']);
        $gudang = Location::create(['code' => 'GDG', 'name' => 'Gudang']);
        $apotek = Location::create(['code' => 'APT', 'name' => 'Apotek']);
        $medicine = Medicine::create(['code' => 'OBT-A', 'name' => 'Obat A', 'dosage_form' => 'Tablet', 'unit_id' => $unit->id]);
        StockBalance::create(['medicine_id' => $medicine->id, 'location_id' => $gudang->id, 'quantity' => 5]);

        $this->actingAs($petugas)->from('/transfer')->post('/transfer', [
            'reference' => 'TRF-GAGAL', 'medicine_id' => $medicine->id,
            'source_location_id' => $gudang->id, 'destination_location_id' => $apotek->id,
            'quantity' => 10, 'occurred_at' => '2026-01-01T09:00',
        ])->assertSessionHasErrors('quantity');
        $this->assertSame(5.0, (float) $medicine->fresh()->balances()->where('location_id', $gudang->id)->value('quantity'));
        $this->assertDatabaseCount('inventory_movements', 0);
    }

    public function test_transfer_without_batch_splits_stock_by_fefo(): void
    {
        $petugas = User::factory()->create(['role' => Role::Pharmacy]);
        $unit = Unit::create(['name' => 'Tablet']);
        $gudang = Location::create(['code' => 'GDG', 'name' => 'Gudang']);
        $apotek = Location::create(['code' => 'APT', 'name' => 'Apotek']);
        $medicine = Medicine::create(['code' => 'OBT-F', 'name' => 'Obat FEFO', 'dosage_form' => 'Tablet', 'unit_id' => $unit->id]);

        $this->actingAs($petugas)->post('/obat-masuk', [
            'reference' => 'FKT-FEFO-A', 'medicine_id' => $medicine->id, 'batch_number' => 'FEFO-A',
            'expiry_date' => '2027-01-01', 'quantity' => 20, 'occurred_at' => '2026-01-01T08:00',
            'destination_location_id' => $gudang->id,
        ])->assertRedirect();
        $this->post('/obat-masuk', [
            'reference' => 'FKT-FEFO-B', 'medicine_id' => $medicine->id, 'batch_number' => 'FEFO-B',
            'expiry_date' => '2027-06-01', 'quantity' => 60, 'occurred_at' => '2026-01-01T08:10',
            'destination_location_id' => $gudang->id,
        ])->assertRedirect();

        $batchA = DB::table('batches')->where('number', 'FEFO-A')->value('id');
        $batchB = DB::table('batches')->where('number', 'FEFO-B')->value('id');

        $this->post('/transfer', [
            'reference' => 'TRF-FEFO', 'medicine_id' => $medicine->id,
            'source_location_id' => $gudang->id, 'destination_location_id' => $apotek->id,
            'quantity' => 50, 'occurred_at' => '2026-01-01T09:00',
        ])->assertRedirect();

        $this->assertDatabaseHas('inventory_movements', ['reference' => 'TRF-FEFO', 'batch_id' => $batchA, 'quantity' => 20]);
        $this->assertDatabaseHas('inventory_movements', ['reference' => 'TRF-FEFO', 'batch_id' => $batchB, 'quantity' => 30]);
        $this->assertSame(0.0, (float) BatchStockBalance::where('batch_id', $batchA)->whereBelongsTo($gudang)->value('quantity'));
        $this->assertSame(30.0, (float) BatchStockBalance::where('batch_id', $batchB)->whereBelongsTo($gudang)->value('quantity'));
        $this->assertSame(20.0, (float) BatchStockBalance::where('batch_id', $batchA)->whereBelongsTo($apotek)->value('quantity'));
        $this->assertSame(30.0, (float) BatchStockBalance::where('batch_id', $batchB)->whereBelongsTo($apotek)->value('quantity'));
    }

    public function test_supplier_return_and_special_adjustment_update_stock(): void
    {
        $petugas = User::factory()->create(['role' => Role::Pharmacy]);
        $unit = Unit::create(['name' => 'Tablet']);
        $gudang = Location::create(['code' => 'GDG', 'name' => 'Gudang']);
        $medicine = Medicine::create(['code' => 'OBT-B', 'name' => 'Obat B', 'dosage_form' => 'Tablet', 'unit_id' => $unit->id]);

        $this->actingAs($petugas)->post('/obat-masuk', [
            'reference' => 'FKT-RET', 'medicine_id' => $medicine->id, 'batch_number' => 'RB-01',
            'expiry_date' => '2027-10-10', 'quantity' => 50, 'occurred_at' => '2026-01-02T08:00',
            'destination_location_id' => $gudang->id,
        ])->assertRedirect();
        $batchId = DB::table('batches')->where('number', 'RB-01')->value('id');

        $this->post('/retur-pbf', [
            'reference' => 'RET-001',
            'medicine_id' => $medicine->id,
            'batch_id' => $batchId,
            'source_location_id' => $gudang->id,
            'quantity' => 10,
            'occurred_at' => '2026-01-02T10:00',
            'counterparty' => 'PBF Sehat',
            'reason' => 'Kemasan rusak',
        ])->assertRedirect();
        $this->assertSame(40.0, (float) StockBalance::whereBelongsTo($medicine)->whereBelongsTo($gudang)->value('quantity'));

        $this->post('/kondisi-khusus', [
            'reference' => 'ADJ-001',
            'medicine_id' => $medicine->id,
            'batch_id' => $batchId,
            'location_id' => $gudang->id,
            'direction' => 'decrease',
            'condition' => 'expired',
            'quantity' => 5,
            'occurred_at' => '2026-01-02T11:00',
        ])->assertRedirect();

        $this->assertSame(35.0, (float) StockBalance::whereBelongsTo($medicine)->whereBelongsTo($gudang)->value('quantity'));
        $this->assertSame(35.0, (float) BatchStockBalance::where('batch_id', $batchId)->whereBelongsTo($gudang)->value('quantity'));
        $this->assertDatabaseHas('inventory_movements', ['type' => 'supplier_return', 'reference' => 'RET-001', 'quantity' => 10]);
        $this->assertDatabaseHas('inventory_movements', ['type' => 'special_adjustment', 'reference' => 'ADJ-001', 'quantity' => 5]);
        $this->get('/retur-pbf')->assertOk()->assertSee('RET-001');
        $this->get('/kondisi-khusus')->assertOk()->assertSee('ADJ-001');
    }

    public function test_multi_item_receipt_can_be_saved_as_draft_then_posted(): void
    {
        $petugas = User::factory()->create(['role' => Role::Pharmacy]);
        $unit = Unit::create(['name' => 'Tablet']);
        $gudang = Location::create(['code' => 'GDG', 'name' => 'Gudang']);
        $medicineA = Medicine::create(['code' => 'OBT-C', 'name' => 'Obat C', 'dosage_form' => 'Tablet', 'unit_id' => $unit->id]);
        $medicineB = Medicine::create(['code' => 'OBT-D', 'name' => 'Obat D', 'dosage_form' => 'Tablet', 'unit_id' => $unit->id]);

        $this->actingAs($petugas)->post('/obat-masuk', [
            'action' => 'draft',
            'reference' => 'FKT-MULTI',
            'occurred_at' => '2026-01-03T08:00',
            'counterparty' => 'PBF Multi',
            'destination_location_id' => $gudang->id,
            'items' => [
                ['medicine_id' => $medicineA->id, 'batch_number' => 'MC-01', 'expiry_date' => '2027-01-01', 'input_quantity' => 1, 'input_unit_name' => 'Box', 'conversion_factor' => 12, 'quantity' => 12],
                ['medicine_id' => $medicineB->id, 'batch_number' => 'MD-01', 'expiry_date' => '2027-02-01', 'quantity' => 8],
            ],
        ])->assertRedirect();

        $receipt = DB::table('inventory_receipts')->where('reference', 'FKT-MULTI')->first();
        $this->assertSame('draft', $receipt->status);
        $this->assertDatabaseCount('inventory_movements', 0);

        $this->post('/obat-masuk/'.$receipt->id.'/post')->assertRedirect();

        $this->assertDatabaseHas('inventory_receipts', ['reference' => 'FKT-MULTI', 'status' => 'posted']);
        $this->assertDatabaseHas('inventory_receipts', ['reference' => 'FKT-MULTI', 'destination_location_id' => $gudang->id]);
        $this->assertDatabaseHas('inventory_receipt_items', ['batch_number' => 'MC-01', 'input_unit_name' => 'Box', 'conversion_factor' => 12]);
        $this->assertDatabaseHas('inventory_movements', ['reference' => 'FKT-MULTI', 'medicine_id' => $medicineA->id, 'quantity' => 12]);
        $this->assertDatabaseHas('inventory_movements', ['reference' => 'FKT-MULTI', 'medicine_id' => $medicineB->id, 'quantity' => 8]);
        $this->assertSame(12.0, (float) StockBalance::whereBelongsTo($medicineA)->whereBelongsTo($gudang)->value('quantity'));
        $this->assertSame(8.0, (float) StockBalance::whereBelongsTo($medicineB)->whereBelongsTo($gudang)->value('quantity'));
    }
}
