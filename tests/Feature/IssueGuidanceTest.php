<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IssueGuidanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_operator_sees_explanation_before_form_and_accessible_field_hints_without_writes(): void
    {
        $this->actingAs(User::factory()->create(['role' => Role::Pharmacy]));
        $response = $this->get(route('preview.issues'))->assertOk()
            ->assertSee('ShifaStock tidak terhubung ke Neoklinik;')
            ->assertSee('bukan sisa stok.')
            ->assertSee('Pengeluaran tidak menunggu persetujuan pemeriksaan harian.')
            ->assertSee('stok Apotek menjadi 17 tablet.')
            ->assertSee('Hindari pencatatan ganda.')
            ->assertSee('tanpa identitas pasien.')
            ->assertDontSee('Catat obat keluar dari persediaan berdasarkan referensi layanan, tindakan, atau catatan internal klinik.');
        $content = $response->getContent();
        $this->assertLessThan(strpos($content, 'data-issue-form'), strpos($content, 'data-issue-guide'));
        $document = new \DOMDocument;
        $document->loadHTML($content, LIBXML_NOERROR | LIBXML_NOWARNING);
        foreach (['issue-quantity', 'issue-source-reference'] as $field) {
            $input = $document->getElementById($field);
            $this->assertInstanceOf(\DOMElement::class, $input);
            $hint = $document->getElementById($input->getAttribute('aria-describedby'));
            $this->assertInstanceOf(\DOMElement::class, $hint);
            $this->assertNotEmpty(trim($hint->textContent));
        }
        $this->assertDatabaseCount('inventory_movements', 0);
        $this->assertDatabaseCount('stock_balances', 0);
    }

    public function test_guest_can_read_explanation_but_cannot_record_an_issue(): void
    {
        $this->actingAs(User::factory()->create(['role' => Role::Guest]));
        $this->get(route('preview.issues'))->assertOk()->assertSee('data-issue-guide', false)
            ->assertDontSee('data-issue-form', false)->assertDontSee('Simpan &amp; Kurangi Stok', false);
        $this->post(route('issues.store'), [])->assertForbidden();
        $this->assertDatabaseCount('inventory_movements', 0);
        $this->assertDatabaseCount('stock_balances', 0);
    }
}
