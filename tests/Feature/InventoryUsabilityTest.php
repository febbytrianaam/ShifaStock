<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Location;
use App\Models\Medicine;
use App\Models\StockBalance;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryUsabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_supplier_invoice_is_separate_and_same_unit_always_has_factor_one(): void
    {
        $user = User::factory()->create(['role' => Role::Pharmacy]);
        $unit = Unit::create(['name' => 'Tablet']);
        $location = Location::create(['code' => 'GDG', 'name' => 'Gudang', 'is_active' => true]);
        $medicine = Medicine::create(['code' => 'UX-1', 'name' => 'Obat UX', 'dosage_form' => 'Tablet', 'unit_id' => $unit->id, 'is_active' => true]);

        $this->actingAs($user)->post(route('receipts.store'), [
            'reference' => 'FKT-UX-1', 'supplier_invoice_number' => 'PBF-ABC-99', 'action' => 'post',
            'occurred_at' => '2026-10-15T09:00', 'destination_location_id' => $location->id,
            'items' => [['medicine_id' => $medicine->id, 'batch_number' => 'UX-BATCH', 'expiry_date' => '2028-01-01',
                'input_quantity' => 2, 'input_unit_name' => 'Tablet', 'conversion_factor' => '1.0006', 'quantity' => 999]],
        ])->assertRedirect(route('preview.receipts', ['tab' => 'riwayat']))->assertSessionHasNoErrors();

        $this->assertDatabaseHas('inventory_receipts', ['reference' => 'FKT-UX-1', 'supplier_invoice_number' => 'PBF-ABC-99']);
        $this->assertDatabaseHas('inventory_receipt_items', ['medicine_id' => $medicine->id, 'quantity' => 2, 'conversion_factor' => 1]);
        $this->assertDatabaseHas('stock_balances', ['medicine_id' => $medicine->id, 'quantity' => 2]);
    }

    public function test_damaged_stock_cannot_increase_stock_even_if_request_is_modified(): void
    {
        $user = User::factory()->create(['role' => Role::Pharmacy]);
        $unit = Unit::create(['name' => 'Tablet']);
        $location = Location::create(['code' => 'GDG', 'name' => 'Gudang', 'is_active' => true]);
        $medicine = Medicine::create(['code' => 'UX-2', 'name' => 'Obat UX', 'dosage_form' => 'Tablet', 'unit_id' => $unit->id, 'is_active' => true]);
        StockBalance::create(['medicine_id' => $medicine->id, 'location_id' => $location->id, 'quantity' => 10]);
        $batch = \App\Models\Batch::create(['medicine_id' => $medicine->id, 'number' => 'UX-2-BATCH', 'expiry_date' => '2028-01-01']);
        \App\Models\BatchStockBalance::create(['medicine_id' => $medicine->id, 'batch_id' => $batch->id, 'location_id' => $location->id, 'quantity' => 10]);

        $this->actingAs($user)->post(route('special-adjustments.store'), [
            'reference' => 'KHS-UX-1', 'medicine_id' => $medicine->id, 'location_id' => $location->id, 'batch_id' => $batch->id,
            'condition' => 'rusak', 'direction' => 'increase', 'quantity' => 2, 'occurred_at' => '2026-10-15T09:00', 'notes' => 'Rusak saat pemeriksaan',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('stock_balances', ['medicine_id' => $medicine->id, 'quantity' => 8]);
    }
}
