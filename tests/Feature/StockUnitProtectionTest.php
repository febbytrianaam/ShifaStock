<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Livewire\MedicineIndex;
use App\Models\Batch;
use App\Models\BatchStockBalance;
use App\Models\Medicine;
use App\Models\StockBalance;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class StockUnitProtectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_used_medicine_unit_is_locked_even_after_stock_reaches_zero(): void
    {
        $user = User::factory()->create(['role' => Role::Pharmacist]);
        $unit = Unit::create(['name' => 'Tablet']);
        $newUnit = Unit::create(['name' => 'Botol']);
        $location = \App\Models\Location::create(['code' => 'GDG', 'name' => 'Gudang']);
        $medicine = Medicine::create(['code' => 'UNIT-LOCK', 'name' => 'Obat', 'unit_id' => $unit->id, 'dosage_form' => 'Tablet']);
        StockBalance::create(['medicine_id' => $medicine->id, 'location_id' => $location->id, 'quantity' => 0]);

        Livewire::actingAs($user)->test(MedicineIndex::class)->call('edit', $medicine->id)
            ->set('unit_id', (string) $newUnit->id)->call('save')->assertHasErrors('unit_id');
        Livewire::actingAs($user)->test(MedicineIndex::class)->call('edit', $medicine->id)
            ->set('unit_id', '')->call('save')->assertHasErrors('unit_id');
        $this->assertSame($unit->id, $medicine->fresh()->unit_id);
        Livewire::actingAs($user)->test(MedicineIndex::class)->call('edit', $medicine->id)
            ->set('name', 'Nama dikoreksi')->set('package_name', 'Box')->set('package_conversion', '20')
            ->call('save')->assertHasNoErrors();
        $this->assertSame('Nama dikoreksi', $medicine->fresh()->name);
        $this->assertSame(20.0, (float) $medicine->packages()->sole()->conversion_factor);
    }

    public function test_unused_medicine_can_change_unit_but_reference_rename_cannot_relabel_used_stock(): void
    {
        $unit = Unit::create(['name' => 'Tablet']);
        $newUnit = Unit::create(['name' => 'Botol']);
        $medicine = Medicine::create(['code' => 'UNIT-FREE', 'name' => 'Obat', 'unit_id' => $unit->id, 'dosage_form' => 'Tablet']);
        $medicine->update(['unit_id' => $newUnit->id]);
        $this->assertSame($newUnit->id, $medicine->fresh()->unit_id);
        $location = \App\Models\Location::create(['code' => 'GDG', 'name' => 'Gudang']);
        $batch = Batch::create(['medicine_id' => $medicine->id, 'number' => 'UNIT-BATCH']);
        BatchStockBalance::create(['medicine_id' => $medicine->id, 'batch_id' => $batch->id, 'location_id' => $location->id, 'quantity' => 1]);
        $admin = User::factory()->create(['role' => Role::Administrator]);
        $this->actingAs($admin)->put(route('admin.references.units.update', $newUnit), ['name' => 'Strip', 'is_active' => 1])
            ->assertSessionHasErrors('name');
        $this->assertSame('Botol', $newUnit->fresh()->name);
        $this->put(route('admin.references.units.update', $unit), ['name' => 'Tablet baru', 'is_active' => 1])
            ->assertSessionHasNoErrors();
        $this->assertSame('Tablet baru', $unit->fresh()->name);
    }
}
