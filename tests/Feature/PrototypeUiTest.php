<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PrototypeUiTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_dummy_routes_are_not_exposed(): void
    {
        $this->actingAs(User::factory()->create(['role' => Role::Pharmacy]));
        $this->get('/obat-keluar')->assertNotFound();
        $this->get('/kartu-stok')->assertNotFound();
        $this->get('/preview-dokumen/permintaan-persediaan')->assertNotFound();
    }

    public function test_live_opname_uses_approved_checks_without_dummy_rows(): void
    {
        $this->actingAs(User::factory()->create(['role' => Role::Pharmacy]));
        $this->get('/stock-opname?period=2026-10')->assertOk()->assertSee('Oktober 2026')->assertSee('Belum ada pemeriksaan');
        $this->get('/stock-opname?period=2025-01')->assertOk()->assertSee('Belum ada pemeriksaan');
        $this->getJson('/stock-opname?period=invalid')->assertUnprocessable();
    }

    public function test_pharmacy_can_view_operational_pages_without_creating_inventory_records(): void
    {
        $this->actingAs(User::factory()->create(['role' => Role::Pharmacy]));
        $this->get('/dashboard')->assertOk()->assertSee('stockChart')->assertSee('Perlu Ditindaklanjuti')
            ->assertSee('Pemeriksaan Harian')->assertSee('Kekurangan &amp; Permintaan', false)
            ->assertDontSee('Obat Keluar')->assertDontSee('Kartu Stok');
        foreach (['obat-masuk', 'transfer', 'stock-opname', 'kedaluwarsa', 'laporan'] as $path) {
            $this->get('/'.$path)->assertOk()->assertDontSee('PRATINJAU UI');
        }
        $this->assertDatabaseCount('medicines', 0);
        $this->assertDatabaseCount('batches', 0);
    }

    public function test_preview_pages_require_an_active_operational_account(): void
    {
        $this->get('/obat-masuk')->assertRedirect('/login');
        $this->actingAs(User::factory()->create(['role' => Role::Administrator]))->get('/obat-masuk')->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => Role::Pharmacy, 'is_active' => false]))->get('/obat-masuk')->assertRedirect('/login');
    }
}
