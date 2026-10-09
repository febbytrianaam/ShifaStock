<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Location;
use App\Models\Medicine;
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
        $petugas = User::factory()->create(['role' => Role::Pharmacy]);
        $kepala = User::factory()->create(['role' => Role::Pharmacist]);
        $unit = Unit::create(['name' => 'Tablet']);
        $gudang = Location::create(['code' => 'GDG', 'name' => 'Gudang']);
        $apotek = Location::create(['code' => 'APT', 'name' => 'Apotek']);
        $medicine = Medicine::create(['code' => 'OBT-A', 'name' => 'Obat A', 'dosage_form' => 'Tablet', 'unit_id' => $unit->id]);

        $this->actingAs($petugas)->post('/obat-masuk', [
            'reference' => 'FKT-001', 'medicine_id' => $medicine->id, 'batch_number' => 'BA-01',
            'expiry_date' => '2027-12-31', 'quantity' => 100, 'occurred_at' => '2026-01-01T08:00',
            'counterparty' => 'PBF Sehat',
        ])->assertRedirect();
        $this->assertSame(100.0, (float) StockBalance::whereBelongsTo($medicine)->whereBelongsTo($gudang)->value('quantity'));

        $this->post('/transfer', [
            'reference' => 'TRF-001', 'medicine_id' => $medicine->id,
            'source_location_id' => $gudang->id, 'destination_location_id' => $apotek->id,
            'quantity' => 30, 'occurred_at' => '2026-01-01T09:00',
        ])->assertRedirect();
        $this->assertSame(70.0, (float) StockBalance::whereBelongsTo($medicine)->whereBelongsTo($gudang)->value('quantity'));
        $this->assertSame(30.0, (float) StockBalance::whereBelongsTo($medicine)->whereBelongsTo($apotek)->value('quantity'));

        $this->post('/pengecekan-malam', ['date' => '2026-01-01', 'location_id' => $apotek->id]);
        $check = DB::table('nightly_checks')->first();
        $item = DB::table('nightly_check_items')->first();
        $this->post('/pengecekan-malam/'.$check->id, ['action' => 'submit', 'items' => [
            $item->id => ['quantity' => 25, 'unit' => 'Tablet', 'is_low' => 1, 'requested_quantity' => 20],
        ]]);
        $this->actingAs($kepala)->post('/pengecekan-malam/'.$check->id.'/review', ['action' => 'approve'])->assertRedirect();
        $this->assertSame(25.0, (float) StockBalance::whereBelongsTo($medicine)->whereBelongsTo($apotek)->value('quantity'));
        $this->assertDatabaseHas('inventory_movements', ['type' => 'physical_count', 'reference' => 'CHECK-'.$check->id, 'quantity' => 5]);

        $this->get('/dashboard')->assertOk()->assertSee('Obat A')->assertSee('25 Tablet');
        $this->get('/kedaluwarsa')->assertOk()->assertSee('BA-01')->assertSee('31/12/2027');
        $this->get('/laporan')->assertOk()->assertSee('FKT-001')->assertSee('TRF-001');
        $this->get('/stock-opname?period=2026-01')->assertOk()->assertSee('Januari 2026')->assertSee('Obat A');
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
}
