<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SidebarNavigationTest extends TestCase
{
    use RefreshDatabase;

    private function menu(string $html): array
    {
        preg_match('/<aside id="sidebar".*?<\/aside>/s', $html, $sidebar);
        preg_match_all('/<p class="[^"]*" data-sidebar-text>([^<]+)<\/p>|data-tour-menu="([^"]+)"/', $sidebar[0], $matches, PREG_SET_ORDER);

        return array_map(fn ($match) => html_entity_decode($match[1] ?: $match[2]), $matches);
    }

    public function test_staff_menu_follows_daily_work_order_and_keeps_the_account_in_the_top_bar(): void
    {
        $this->actingAs(User::factory()->create(['role' => Role::Pharmacy, 'name' => 'Petugas Simulasi']));
        $response = $this->get(route('preview.transfers'))->assertOk();
        $this->assertSame(['dashboard', 'KERJA HARIAN', 'preview.nightly', 'shortages.index', 'TRANSAKSI', 'preview.receipts', 'preview.transfers', 'preview.issues',
            'preview.returns', 'special-adjustments.index', 'DATA & LAPORAN', 'medicines.index', 'preview.reports'], $this->menu($response->getContent()));
        $response->assertSee('<h1 class="truncate text-sm font-semibold text-slate-900 sm:text-base">Transfer Stok</h1>', false)
            ->assertSee('data-sidebar-collapse', false)->assertSee('data-sidebar-close', false)
            ->assertSee('aria-current="page"', false)->assertSee('Petugas Farmasi')->assertSee('>PS<', false);
        preg_match('/<aside id="sidebar".*?<\/aside>/s', $response->getContent(), $sidebar);
        $this->assertStringNotContainsString('Keluar', $sidebar[0]);
        $this->assertStringContainsString('Keluar', explode('</aside>', $response->getContent())[1]);
    }

    public function test_pharmacist_gets_setup_pages_and_admin_only_gets_administration(): void
    {
        $this->actingAs(User::factory()->create(['role' => Role::Pharmacist]));
        $this->assertSame(['medicines.index', 'opening-balances.index', 'monitoring-parameters.index', 'preview.reports'],
            array_slice($this->menu($this->get(route('dashboard'))->getContent()), -4));
        $this->actingAs(User::factory()->create(['role' => Role::Administrator]));
        $menu = $this->menu($this->get(route('dashboard'))->getContent());
        $this->assertSame('dashboard', $menu[0]);
        $this->assertSame('ADMINISTRASI', $menu[1]);
        $this->assertNotContains('KERJA HARIAN', $menu);
    }
}
