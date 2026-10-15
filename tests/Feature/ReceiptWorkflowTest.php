<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Batch;
use App\Models\BatchStockBalance;
use App\Models\InventoryReceipt;
use App\Models\Location;
use App\Models\Medicine;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReceiptWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function receiptData(): array
    {
        $this->actingAs(User::factory()->create(['role' => Role::Pharmacy]));
        $unit = Unit::create(['name' => 'Tablet']);
        $location = Location::create(['code' => 'GDG', 'name' => 'Gudang', 'is_active' => true]);
        $medicine = Medicine::create(['code' => 'DRAFT-1', 'name' => 'Obat Draf', 'unit_id' => $unit->id, 'dosage_form' => 'Tablet', 'is_active' => true]);

        return [
            'reference' => 'FKT-DRAFT-1', 'action' => 'draft', 'counterparty' => 'PBF Sehat',
            'supplier_invoice_number' => 'INV-99', 'destination_location_id' => $location->id, 'occurred_at' => '2026-10-15T09:00',
            'items' => [['medicine_id' => $medicine->id, 'batch_number' => 'BATCH-DRAFT', 'expiry_date' => '2028-01-01',
                'input_quantity' => 2, 'input_unit_name' => 'Tablet', 'conversion_factor' => 1]],
        ];
    }

    public function test_draft_can_be_reopened_edited_and_posted_once(): void
    {
        $data = $this->receiptData();
        $this->post(route('receipts.store'), $data)->assertSessionHasNoErrors();
        $receipt = InventoryReceipt::firstOrFail();
        $this->assertDatabaseCount('inventory_movements', 0);
        $this->get(route('receipts.edit', $receipt))->assertOk()->assertSee('Edit Draf')->assertSee('2028-01-01');

        $data['items'][0]['input_quantity'] = 6;
        $data['items'][0]['batch_number'] = 'BATCH-REVISED';
        $this->put(route('receipts.update', $receipt), $data)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('inventory_receipts', 1);
        $this->assertDatabaseCount('inventory_receipt_items', 1);
        $this->assertDatabaseHas('inventory_receipt_items', ['quantity' => 6, 'batch_number' => 'BATCH-REVISED']);
        $this->assertDatabaseCount('inventory_movements', 0);
        $this->assertDatabaseCount('stock_balances', 0);

        $data['action'] = 'post';
        $this->put(route('receipts.update', $receipt), $data)->assertSessionHasNoErrors();
        $this->assertDatabaseHas('stock_balances', ['medicine_id' => $data['items'][0]['medicine_id'], 'quantity' => 6]);
        $this->put(route('receipts.update', $receipt), $data)->assertUnprocessable();
        $this->get(route('receipts.edit', $receipt))->assertUnprocessable();
        $this->post(route('receipts.post', $receipt))->assertUnprocessable();
        $this->delete(route('receipts.destroy', $receipt))->assertUnprocessable();
        $this->assertDatabaseCount('inventory_movements', 1);
    }

    public function test_duplicate_invoice_needs_confirmation_and_excludes_the_current_draft(): void
    {
        $data = $this->receiptData();
        $this->post(route('receipts.store'), $data)->assertSessionHasNoErrors();
        $receipt = InventoryReceipt::firstOrFail();
        $this->put(route('receipts.update', $receipt), $data)->assertSessionHasNoErrors();
        $this->getJson(route('receipts.check-invoice', ['counterparty' => ' pbf sehat ', 'supplier_invoice_number' => ' inv-99 ']))
            ->assertOk()->assertJsonCount(1, 'matches');
        $this->getJson(route('receipts.check-invoice', ['counterparty' => 'PBF Sehat', 'supplier_invoice_number' => 'INV-99', 'receipt_id' => $receipt->id]))
            ->assertOk()->assertJsonCount(0, 'matches');

        $data['reference'] = 'FKT-DRAFT-2';
        $data['counterparty'] = ' pbf sehat ';
        $data['supplier_invoice_number'] = ' inv-99 ';
        $data['action'] = 'post';
        $this->post(route('receipts.store'), $data)->assertSessionHasErrors('duplicate_invoice');
        $this->assertDatabaseCount('inventory_receipts', 1);
        $this->assertDatabaseCount('inventory_movements', 0);
        $data['duplicate_invoice_confirmed'] = 1;
        $this->post(route('receipts.store'), $data)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('inventory_receipts', 2);
        $this->assertDatabaseCount('inventory_movements', 1);

        $this->post(route('receipts.post', $receipt))->assertRedirect(route('receipts.edit', $receipt))->assertSessionHasErrors('duplicate_invoice');
        $this->assertDatabaseCount('inventory_movements', 1);
    }

    public function test_item_validation_keeps_original_row_numbers_and_database_unchanged(): void
    {
        $data = $this->receiptData();
        $item = $data['items'][0];
        $item['batch_number'] = '';
        $data['items'] = [0 => [], 2 => $item];
        $response = $this->followingRedirects()->from(route('preview.receipts'))->post(route('receipts.store'), $data);
        $this->assertDatabaseCount('inventory_receipts', 0);
        $response->assertOk()->assertSee('data-receipt-field-error', false)->assertSee('Baris 3: isi nomor batch.');
    }

    public function test_monitoring_links_prefill_forms_and_are_not_shown_to_guest(): void
    {
        $data = $this->receiptData();
        $medicineId = $data['items'][0]['medicine_id'];
        $locationId = $data['destination_location_id'];
        $batch = Batch::create(['medicine_id' => $medicineId, 'number' => 'EXPIRED-LINK', 'expiry_date' => '2025-01-01']);
        BatchStockBalance::create(['medicine_id' => $medicineId, 'batch_id' => $batch->id, 'location_id' => $locationId, 'quantity' => 8]);
        $params = ['medicine_id' => $medicineId, 'batch_id' => $batch->id, 'source_location_id' => $locationId];
        $this->get(route('preview.expiry'))->assertOk()->assertSee(route('preview.returns', $params));
        $this->get(route('preview.returns', $params))->assertOk()->assertViewHas('prefill', fn ($prefill) => (int) $prefill['batch_id'] === $batch->id);
        $this->get(route('preview.transfers', ['medicine_id' => $medicineId, 'destination_location_id' => $locationId]))
            ->assertOk()->assertViewHas('prefill', fn ($prefill) => (int) $prefill['destination_location_id'] === $locationId);
        $this->assertDatabaseCount('inventory_movements', 0);

        $receipt = InventoryReceipt::create(['reference' => 'GUARD-DRAFT', 'status' => 'draft', 'received_at' => now(), 'created_by' => auth()->id()]);
        $this->actingAs(User::factory()->create(['role' => Role::Guest]));
        $this->get(route('preview.expiry'))->assertOk()->assertDontSee('Retur dari Gudang');
        $this->get(route('receipts.edit', $receipt))->assertForbidden();
        $this->put(route('receipts.update', $receipt), $data)->assertForbidden();
        $this->getJson(route('receipts.check-invoice', ['counterparty' => 'PBF Sehat']))->assertForbidden();
    }
}
