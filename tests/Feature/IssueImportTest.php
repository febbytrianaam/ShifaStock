<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Batch;
use App\Models\BatchStockBalance;
use App\Models\InventoryMovement;
use App\Models\IssueImport;
use App\Models\Location;
use App\Models\Medicine;
use App\Models\StockBalance;
use App\Models\Unit;
use App\Models\User;
use App\Services\InventoryStockService;
use App\Services\IssueImportService;
use App\Services\NeoklinikIssueReader;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class IssueImportTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    private Location $location;

    private Medicine $medicine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-10-24 10:00', 'Asia/Jakarta'));
        $this->staff = User::factory()->create(['role' => Role::Pharmacy, 'onboarding_seen_at' => now()]);
        $this->location = Location::create(['code' => 'APT', 'name' => 'Apotek']);
        $unit = Unit::create(['name' => 'Tablet']);
        $this->medicine = Medicine::create(['code' => 'A', 'name' => 'Obat Sintetis A', 'dosage_form' => 'Tablet', 'unit_id' => $unit->id]);
        StockBalance::create(['medicine_id' => $this->medicine->id, 'location_id' => $this->location->id, 'quantity' => 100]);
        foreach ([['EARLY', '2027-01-01', 10], ['LATE', '2028-01-01', 90]] as [$number, $expiry, $quantity]) {
            $batch = Batch::create(['medicine_id' => $this->medicine->id, 'number' => $number, 'expiry_date' => $expiry]);
            BatchStockBalance::create(['medicine_id' => $this->medicine->id, 'batch_id' => $batch->id, 'location_id' => $this->location->id, 'quantity' => $quantity]);
        }
        $this->actingAs($this->staff);
    }

    private function file(array $rows, ?callable $modify = null): UploadedFile
    {
        Date::setExcelCalendar(Date::CALENDAR_WINDOWS_1900);
        $book = new Spreadsheet;
        $sheet = $book->getActiveSheet();
        $sheet->setTitle('Sheet');
        $sheet->fromArray(NeoklinikIssueReader::HEADERS, null, 'A1');
        foreach ($rows as $index => $row) {
            $sheet->fromArray([
                Date::PHPToExcel(Carbon::parse($row['date'] ?? '2026-10-23 09:00:01', 'Asia/Jakarta')),
                null, $row['sale'] ?? 'SIM-SALE-1', 'Internal', 'Klinik Simulasi', 'Poli Simulasi',
                'DOKTER_SINTETIS_PRIVASI', 'PASIEN_SINTETIS_PRIVASI', 'Resep', $row['compound'] ?? null,
                $row['code'] ?? 'A', $row['name'] ?? 'Obat Sintetis A', $row['quantity'] ?? 15, $row['unit'] ?? 'BUTIR', null, null,
            ], null, 'A'.($index + 2));
        }
        if ($modify) {
            $modify($book);
        }
        $path = tempnam(sys_get_temp_dir(), 'issue-import-test-');
        try {
            (new Xlsx($book))->save($path);

            return UploadedFile::fake()->createWithContent('rekap-simulasi.xlsx', file_get_contents($path));
        } finally {
            $book->disconnectWorksheets();
            unlink($path);
        }
    }

    private function upload(array $rows = [[]], ?Location $location = null): IssueImport
    {
        $this->post(route('issue-imports.store'), ['file' => $this->file($rows), 'location_id' => ($location ?? $this->location)->id,
            'starts_at' => '2026-10-23T00:00', 'cutoff_confirmed' => 1])->assertSessionHasNoErrors()->assertRedirect();

        return IssueImport::latest('id')->firstOrFail();
    }

    private function map(IssueImport $document, float $factor = 1): void
    {
        $input = [];
        foreach (app(IssueImportService::class)->groups($document) as $key => $group) {
            $input[$key] = ['medicine_id' => $this->medicine->id, 'factor' => $factor, 'confirmed' => 1];
        }
        $this->post(route('issue-imports.mappings', $document), ['mappings' => $input])->assertSessionHasNoErrors();
    }

    private function confirmations(IssueImport $document): array
    {
        return ['preview_token' => app(IssueImportService::class)->preview($document->fresh())['token'],
            'delivered_confirmed' => 1, 'units_confirmed' => 1, 'fefo_confirmed' => 1, 'period_confirmed' => 1, 'identity_confirmed' => 1];
    }

    private function postImport(IssueImport $document)
    {
        return $this->post(route('issue-imports.post', $document), $this->confirmations($document));
    }

    private function stock(): float
    {
        return (float) StockBalance::where('medicine_id', $this->medicine->id)->where('location_id', $this->location->id)->value('quantity');
    }

    public function test_upload_is_private_and_preview_never_changes_stock_or_auto_approves_mapping(): void
    {
        $document = $this->upload();
        $this->assertSame(100.0, $this->stock());
        $this->assertDatabaseCount('inventory_movements', 0);
        $this->assertDatabaseCount('issue_import_mappings', 0);
        $serialized = json_encode($document->payload);
        foreach (['PASIEN_SINTETIS_PRIVASI', 'DOKTER_SINTETIS_PRIVASI', 'SIM-SALE-1'] as $private) {
            $this->assertStringNotContainsString($private, $serialized);
            $this->assertStringNotContainsString($private, DB::table('issue_imports')->value('payload'));
            $this->get(route('issue-imports.show', $document))->assertOk()->assertDontSee($private);
        }
        $this->assertStringNotContainsString('Obat Sintetis A', DB::table('issue_imports')->value('payload'));
        $this->assertSame(1, app(IssueImportService::class)->preview($document)['blocked']);
        $this->get(route('issue-imports.index'))->assertOk()->assertSee('Riwayat Impor');
    }

    public function test_import_steps_keep_matching_separate_from_final_confirmation(): void
    {
        $document = $this->upload();
        $this->get(route('issue-imports.show', $document))->assertOk()
            ->assertViewHas('step', 'match')->assertViewHas('needsMapping', 1)
            ->assertSee('data-import-mapping-form', false)->assertDontSee('data-import-post', false);
        $this->assertDatabaseCount('issue_import_mappings', 0);
        $this->map($document);
        $this->get(route('issue-imports.show', $document))->assertOk()
            ->assertViewHas('step', 'review')->assertViewHas('needsMapping', 0)
            ->assertDontSee('data-import-mapping-form', false)->assertSee('Simpan Pengeluaran &amp; Kurangi Stok', false);
        $this->assertSame(100.0, $this->stock());
        $this->assertDatabaseCount('inventory_movements', 0);
    }

    public function test_preview_document_context_and_steps_stay_in_the_side_panel_without_nested_forms(): void
    {
        $document = $this->upload();
        foreach (['match', 'review'] as $step) {
            $response = $this->get(route('issue-imports.show', [$document, 'step' => $step]))->assertOk();
            $html = new \DOMDocument;
            @$html->loadHTML($response->getContent());
            $xpath = new \DOMXPath($html);
            $this->assertSame(1, $xpath->query('//*[@data-import-info]//*[@data-import-context]')->length);
            $this->assertSame(1, $xpath->query('//*[@data-import-info]//nav[@aria-label="Langkah impor"]')->length);
            $this->assertSame(0, $xpath->query('//*[@data-import-list]//*[@data-import-context]')->length);
            $this->assertSame(0, $xpath->query('//*[@data-import-info]/ancestor::form')->length);
            $this->assertSame(1, $xpath->query('//*[@data-import-info]//form[@data-issue-import-delete]')->length);
            if ($step === 'match') {
                $this->assertSame(1, $xpath->query('//form[@id="import-mapping-form" and @data-import-mapping-form]')->length);
                $this->assertSame(1, $xpath->query('//*[@data-import-info]//button[@form="import-mapping-form"]')->length);
            }
        }
        $this->assertSame(100.0, $this->stock());
        $this->assertDatabaseCount('inventory_movements', 0);
    }

    public function test_source_columns_show_date_range_and_original_excel_rows_without_private_identifiers(): void
    {
        $document = $this->upload([
            ['sale' => 'PRIVATE-LATER', 'date' => '2026-10-23 11:12:13', 'quantity' => 7, 'compound' => 'PRIVATE-COMPOUND'],
            ['sale' => 'PRIVATE-EARLIER', 'date' => '2026-10-22 08:09:10', 'quantity' => 2],
        ]);
        foreach (['match', 'review'] as $step) {
            $response = $this->get(route('issue-imports.show', [$document, 'step' => $step]))->assertOk()
                ->assertSee('Tanggal Jual (WIB)')->assertSee('Baris Excel')->assertSee('Jumlah')
                ->assertDontSee('PRIVATE-LATER')->assertDontSee('PRIVATE-EARLIER')->assertDontSee('PRIVATE-COMPOUND')
                ->assertDontSee('PASIEN_SINTETIS_PRIVASI')->assertDontSee('DOKTER_SINTETIS_PRIVASI');
            $html = new \DOMDocument;
            @$html->loadHTML($response->getContent());
            $xpath = new \DOMXPath($html);
            $this->assertSame('1', trim($xpath->query('//tr[@data-import-match-row or @data-import-review-row]/td[@data-label="No."]')->item(0)->textContent));
            $period = $xpath->query('//tr[@data-import-match-row or @data-import-review-row]/td[@data-label="Tanggal Jual (WIB)"]')->item(0);
            $times = $xpath->query('.//time', $period);
            $this->assertCount(2, $times);
            $this->assertSame('2026-10-22T08:09:10+07:00', $times->item(0)->getAttribute('datetime'));
            $this->assertSame('2026-10-23T11:12:13+07:00', $times->item(1)->getAttribute('datetime'));
            $rows = $xpath->query('//tr[@data-import-source-row]');
            $this->assertCount(2, $rows);
            foreach ([['2', '7', 'Ya'], ['3', '2', 'Tidak']] as $index => [$number, $quantity, $compound]) {
                foreach (['Baris Excel' => $number, 'Jumlah' => $quantity, 'Racikan' => $compound, 'Satuan' => 'BUTIR', 'Kode Obat' => 'A'] as $label => $expected) {
                    $this->assertSame($expected, trim($xpath->query('.//td[@data-label="'.$label.'"]', $rows->item($index))->item(0)->textContent));
                }
            }
        }
        $this->assertSame(100.0, $this->stock());
        $this->assertDatabaseCount('inventory_movements', 0);
    }

    public function test_saved_mappings_do_not_need_reconfirmation_but_changed_units_do(): void
    {
        $first = $this->upload();
        $this->map($first);
        $document = $this->upload([['sale' => 'KNOWN'], ['sale' => 'NEW', 'code' => 'NEW-CODE']]);
        $preview = app(IssueImportService::class)->preview($document);
        $knownKey = IssueImportService::groupKey(['source_code' => 'A', 'source_unit' => 'BUTIR']);
        $newKey = IssueImportService::groupKey(['source_code' => 'NEW-CODE', 'source_unit' => 'BUTIR']);
        $this->assertFalse($preview['groups'][$knownKey]['needs_mapping']);
        $this->assertTrue($preview['groups'][$newKey]['needs_mapping']);
        $response = $this->get(route('issue-imports.show', $document))->assertOk()->assertViewHas('needsMapping', 1)
            ->assertSee('Tampilkan juga pilihan tersimpan');
        $html = new \DOMDocument;
        @$html->loadHTML($response->getContent());
        $this->assertSame(1, (new \DOMXPath($html))->query('//tr[@data-needs-mapping="0" and @hidden]')->length);
        $unit = Unit::create(['name' => 'Botol']);
        DB::table('medicines')->where('id', $this->medicine->id)->update(['unit_id' => $unit->id]);
        $this->get(route('issue-imports.show', $document))->assertOk()->assertViewHas('needsMapping', 2);
    }

    public function test_period_and_mapping_problems_have_separate_actions_and_exact_cutoff(): void
    {
        $document = $this->upload([['sale' => 'EARLY', 'date' => '2026-10-23 07:00'], ['sale' => 'LATER']]);
        $document->update(['starts_at' => '2026-10-23 08:00:00']);
        $this->get(route('issue-imports.show', [$document, 'step' => 'review']))->assertOk()
            ->assertSee('Pengeluaran lebih awal dari 23/10/2026 08:00 WIB')
            ->assertSee('Pilihan obat dan satuan perlu dikonfirmasi')
            ->assertSee('Jangan memundurkan tanggal')
            ->assertSee('Buka langkah Cocokkan obat')
            ->assertSee('data-import-problems', false)->assertDontSee('name="delivered_confirmed"', false);
        $this->assertSame(100.0, $this->stock());
    }

    public function test_mapping_guidance_explains_suggestions_confirmation_and_saved_choice_changes(): void
    {
        $document = $this->upload();
        $this->get(route('issue-imports.show', $document))->assertOk()
            ->assertSee('Saran otomatis; belum dikonfirmasi')->assertSee('data-import-confirmation-help', false)
            ->assertSee('data-import-status-help', false)->assertSee('Cara memperbaiki');
        $this->get(route('issue-imports.show', [$document, 'step' => 'review']))->assertOk()
            ->assertSee('Pilihan obat dan satuan perlu dikonfirmasi')
            ->assertSee('1 BUTIR = 1 Tablet')->assertSee('Centang Benar')
            ->assertSee('Simpan Pilihan &amp; Lanjutkan', false)
            ->assertSee('Isian otomatis baru saran, belum persetujuan')
            ->assertSee('Jika pilihan pernah disimpan')->assertSee('Stok belum berkurang');
        $this->assertDatabaseCount('issue_import_mappings', 0);
        $this->map($document);
        $this->get(route('issue-imports.show', $document))->assertOk()
            ->assertDontSee('Pilihan obat dan satuan perlu dikonfirmasi')->assertDontSee('data-import-status-help', false);
        $unit = Unit::create(['name' => 'Botol']);
        DB::table('medicines')->where('id', $this->medicine->id)->update(['unit_id' => $unit->id]);
        $this->get(route('issue-imports.show', $document))->assertOk()->assertViewHas('needsMapping', 1)
            ->assertSee('Pilihan lama perlu diperiksa ulang');
        $this->assertSame(100.0, $this->stock());
        $this->assertDatabaseCount('inventory_movements', 0);
    }

    public function test_manual_tab_can_resume_the_same_import_and_step_without_uploading_again(): void
    {
        $document = $this->upload();
        $this->get(route('issue-imports.show', $document))->assertOk()
            ->assertSessionHas('issue_import_navigation.id', $document->id)
            ->assertSessionHas('issue_import_navigation.step', 'match');
        $url = route('issue-imports.show', [$document, 'step' => 'match']);
        foreach (['preview.issues', 'issue-imports.index'] as $route) {
            $this->get(route($route))->assertOk()->assertSee('data-import-resume', false)
                ->assertSee('Lanjutkan pemeriksaan')->assertSee($url)->assertSee('Tidak perlu unggah ulang');
        }
        $this->get($url)->assertOk()->assertViewHas('document', fn ($item) => $item->id === $document->id);
        $this->map($document);
        $this->get(route('issue-imports.show', $document))->assertOk()->assertSessionHas('issue_import_navigation.step', 'review');
        $this->get(route('preview.issues'))->assertOk()->assertSee(route('issue-imports.show', [$document, 'step' => 'review']));
        $this->assertDatabaseCount('issue_imports', 1);
        $this->assertDatabaseCount('issue_import_mappings', 1);
        $this->assertSame(100.0, $this->stock());
        $this->assertDatabaseCount('inventory_movements', 0);
    }

    public function test_import_resume_ignores_expired_deleted_and_posted_previews(): void
    {
        $expired = $this->upload();
        $this->get(route('issue-imports.show', $expired))->assertOk();
        $expired->update(['created_at' => now()->subDays(8)]);
        $this->get(route('preview.issues'))->assertOk()->assertDontSee('data-import-resume', false)
            ->assertSessionMissing('issue_import_navigation');
        $deleted = $this->upload([['sale' => 'DELETED']]);
        $this->get(route('issue-imports.show', $deleted))->assertOk();
        $this->delete(route('issue-imports.destroy', $deleted))->assertRedirect()->assertSessionMissing('issue_import_navigation');
        $this->get(route('preview.issues'))->assertOk()->assertDontSee('data-import-resume', false);
        $posted = $this->upload([['sale' => 'POSTED']]);
        $this->map($posted);
        $this->get(route('issue-imports.show', $posted))->assertOk();
        $this->postImport($posted)->assertSessionHasNoErrors();
        $this->get(route('preview.issues'))->assertOk()->assertDontSee('data-import-resume', false)
            ->assertSessionMissing('issue_import_navigation');
    }

    public function test_import_resume_pointer_is_not_reused_by_other_users_or_guest(): void
    {
        $document = $this->upload();
        $this->get(route('issue-imports.show', $document))->assertOk();
        foreach ([Role::Pharmacy, Role::Guest] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]));
            $this->get(route('preview.issues'))->assertOk()->assertDontSee('data-import-resume', false);
        }
        $this->assertSame(100.0, $this->stock());
        $this->assertDatabaseCount('inventory_movements', 0);
    }

    public function test_guest_cannot_open_editing_step_even_with_explicit_step_query(): void
    {
        $document = $this->upload();
        $this->actingAs(User::factory()->create(['role' => Role::Guest]));
        $this->get(route('issue-imports.show', [$document, 'step' => 'match']))->assertOk()
            ->assertViewHas('step', 'review')->assertDontSee('data-import-mapping-form', false)->assertDontSee('data-import-post', false);
    }

    public function test_posting_splits_fefo_updates_card_and_records_actor_and_audit(): void
    {
        $document = $this->upload();
        $this->map($document);
        $this->postImport($document)->assertSessionHasNoErrors();
        $this->assertSame(85.0, $this->stock());
        $this->assertDatabaseCount('issue_import_lines', 1);
        $this->assertDatabaseCount('issue_import_line_movements', 2);
        $this->assertDatabaseHas('inventory_movements', ['quantity' => 10, 'created_by' => $this->staff->id, 'type' => 'issue']);
        $this->assertDatabaseHas('inventory_movements', ['quantity' => 5, 'created_by' => $this->staff->id]);
        $this->assertSame(85.0, (float) BatchStockBalance::sum('quantity'));
        $this->assertSame(1.0, (float) $document->fresh()->payload[0]['conversion_factor']);
        $this->assertSame('Tablet', $document->fresh()->payload[0]['target_unit']);
        $this->assertSame($this->staff->id, $document->fresh()->posted_by);
        $this->assertDatabaseHas('audit_logs', ['event' => 'inventory.issue-import.posted']);
        $this->get(route('issue-imports.show', $document))->assertOk()->assertSee('EARLY')->assertSee('LATE')->assertSee('Lihat mutasi');
        $this->get('/kartu-stok?medicine_id='.$this->medicine->id.'&location_id='.$this->location->id.'&start=2026-10-23&end=2026-10-24')->assertOk()->assertSee('OUT-IMP-');
    }

    public function test_renamed_overlapping_and_reordered_exports_skip_previously_posted_rows(): void
    {
        $first = $this->upload([['sale' => 'S1'], ['sale' => 'S2', 'quantity' => 2]]);
        $this->map($first);
        $this->postImport($first)->assertSessionHasNoErrors();
        $second = $this->upload([['sale' => 'S2', 'quantity' => 2], ['sale' => 'S3', 'quantity' => 3], ['sale' => 'S1']]);
        $this->postImport($second)->assertSessionHasNoErrors();
        $this->assertSame(80.0, $this->stock());
        $this->assertSame(1, $second->fresh()->posted_count);
        $this->assertSame(2, $second->fresh()->duplicate_count);
        $third = $this->upload([['sale' => 'S1']]);
        $this->postImport($third)->assertSessionHasNoErrors();
        $this->assertSame(80.0, $this->stock());
        $this->assertDatabaseCount('issue_import_lines', 3);
    }

    public function test_same_source_cannot_be_deducted_again_by_choosing_another_location(): void
    {
        $first = $this->upload();
        $this->map($first);
        $this->postImport($first)->assertSessionHasNoErrors();
        $other = Location::create(['code' => 'GDG', 'name' => 'Gudang']);
        $second = $this->upload([[]], $other);
        $this->postImport($second)->assertSessionHasNoErrors();
        $this->assertSame(0, $second->fresh()->posted_count);
        $this->assertSame(1, $second->fresh()->duplicate_count);
        $this->assertSame(85.0, $this->stock());
    }

    public function test_changed_source_blocks_all_new_rows_in_the_document(): void
    {
        $first = $this->upload();
        $this->map($first);
        $this->postImport($first)->assertSessionHasNoErrors();
        $second = $this->upload([['quantity' => 16], ['sale' => 'S2', 'quantity' => 1]]);
        $this->postImport($second)->assertSessionHasErrors('import');
        $this->get(route('issue-imports.show', $second))->assertSee('Transaksi pernah dicatat, tetapi isinya berubah');
        $this->assertSame(85.0, $this->stock());
        $this->assertDatabaseCount('issue_import_lines', 1);
    }

    public function test_unknown_unit_and_compounds_require_explicit_mapping_and_confirmation(): void
    {
        $document = $this->upload([['code' => 'RAC', 'unit' => '-', 'quantity' => 2, 'compound' => 'SIM-RAC-1']]);
        $this->postImport($document)->assertSessionHasErrors('import');
        $this->map($document, 3);
        $data = $this->confirmations($document);
        unset($data['units_confirmed']);
        $this->post(route('issue-imports.post', $document), $data)->assertSessionHasErrors('units_confirmed');
        $this->assertSame(100.0, $this->stock());
        $this->postImport($document)->assertSessionHasNoErrors();
        $this->assertSame(94.0, $this->stock());
    }

    public function test_aggregate_demand_in_different_units_cannot_exceed_available_stock(): void
    {
        $document = $this->upload([['sale' => 'S1', 'quantity' => 60], ['sale' => 'S2', 'quantity' => 50, 'unit' => 'KAPLET']]);
        $this->map($document);
        $this->postImport($document)->assertSessionHasErrors('import');
        $this->assertSame(100.0, $this->stock());
        $this->assertDatabaseCount('issue_import_lines', 0);
    }

    public function test_insufficient_usable_batches_and_unreconciled_totals_are_blocked(): void
    {
        DB::table('batches')->update(['expiry_date' => '2026-10-01']);
        $document = $this->upload();
        $this->map($document);
        $this->postImport($document)->assertSessionHasErrors('import');
        DB::table('batches')->update(['expiry_date' => '2028-01-01']);
        DB::table('stock_balances')->update(['quantity' => 101]);
        $this->postImport($document)->assertSessionHasErrors('import');
        $this->assertDatabaseCount('inventory_movements', 0);
    }

    public function test_changes_since_preview_require_new_confirmation(): void
    {
        $document = $this->upload();
        $this->map($document);
        $old = $this->confirmations($document);
        DB::table('stock_balances')->update(['quantity' => 99]);
        $this->post(route('issue-imports.post', $document), $old)->assertSessionHasErrors('import');
        $this->assertSame(99.0, $this->stock());
        $this->assertDatabaseCount('inventory_movements', 0);
    }

    public function test_source_before_opening_or_physical_reconciliation_is_not_deducted(): void
    {
        $document = $this->upload();
        $this->map($document);
        InventoryMovement::create(['type' => 'opening_balance', 'reference' => 'OPEN-SIM', 'medicine_id' => $this->medicine->id,
            'destination_location_id' => $this->location->id, 'quantity' => 100, 'occurred_at' => '2026-10-23 10:00', 'created_by' => $this->staff->id]);
        $this->postImport($document)->assertSessionHasErrors('import');
        DB::table('inventory_movements')->delete();
        DB::table('stock_balances')->update(['last_counted_at' => '2026-10-23 10:00']);
        $this->postImport($document)->assertSessionHasErrors('import');
        $this->assertSame(100.0, $this->stock());
    }

    public function test_start_boundary_cannot_be_changed_after_first_posting(): void
    {
        $first = $this->upload();
        $this->map($first);
        $this->postImport($first)->assertSessionHasNoErrors();
        $this->post(route('issue-imports.store'), ['file' => $this->file([['sale' => 'S2']]), 'location_id' => $this->location->id,
            'starts_at' => '2026-10-22T00:00', 'cutoff_confirmed' => 1])->assertSessionHasErrors('import');
        $this->assertDatabaseCount('issue_imports', 1);
    }

    public function test_rows_before_start_boundary_are_blocked(): void
    {
        $document = $this->upload([['date' => '2026-10-22 09:00']]);
        $this->map($document);
        $this->postImport($document)->assertSessionHasErrors('import');
        $this->assertSame(100.0, $this->stock());
    }

    public function test_transaction_rolls_back_when_second_batch_write_fails(): void
    {
        $document = $this->upload();
        $this->map($document);
        app()->instance(InventoryStockService::class, new class extends InventoryStockService
        {
            private int $writes = 0;

            public function addBatch(int $medicineId, int $batchId, int $locationId, float $quantity): BatchStockBalance
            {
                if (++$this->writes === 2) {
                    throw ValidationException::withMessages(['quantity' => 'Simulasi kegagalan batch kedua.']);
                }

                return parent::addBatch($medicineId, $batchId, $locationId, $quantity);
            }
        });
        $this->postImport($document)->assertSessionHasErrors('quantity');
        $this->assertSame(100.0, $this->stock());
        $this->assertSame(100.0, (float) BatchStockBalance::sum('quantity'));
        $this->assertDatabaseCount('inventory_movements', 0);
        $this->assertDatabaseCount('issue_import_lines', 0);
        $this->assertDatabaseCount('issue_import_policies', 0);
    }

    public function test_guest_has_read_only_history_and_cannot_upload_map_or_post(): void
    {
        $document = $this->upload();
        $this->actingAs(User::factory()->create(['role' => Role::Guest]));
        $this->get(route('issue-imports.index'))->assertOk()->assertDontSee('Unggah &amp; Periksa', false);
        $this->get(route('issue-imports.show', $document))->assertOk()->assertDontSee('Simpan Pemetaan yang Dicentang');
        $this->post(route('issue-imports.store'))->assertForbidden();
        $this->post(route('issue-imports.mappings', $document))->assertForbidden();
        $this->post(route('issue-imports.post', $document))->assertForbidden();
        $this->delete(route('issue-imports.destroy', $document))->assertForbidden();
        $this->get(route('issue-imports.index'))->assertDontSee('data-issue-import-delete', false);
        $this->get(route('issue-imports.show', $document))->assertDontSee('data-issue-import-delete', false);
    }

    public function test_inactive_location_or_medicine_and_changed_unit_are_rejected(): void
    {
        $document = $this->upload();
        $this->map($document);
        $this->medicine->update(['is_active' => false]);
        $this->postImport($document)->assertSessionHasErrors('import');
        $this->medicine->update(['is_active' => true]);
        $unit = Unit::create(['name' => 'Botol']);
        DB::table('medicines')->where('id', $this->medicine->id)->update(['unit_id' => $unit->id]);
        $this->postImport($document)->assertSessionHasErrors('import');
        $this->location->update(['is_active' => false]);
        $this->postImport($document)->assertSessionHasErrors('import');
        $this->assertSame(100.0, $this->stock());
    }

    public function test_ambiguous_source_identity_and_bad_formula_are_rejected_without_persistence(): void
    {
        foreach ([$this->file([[], []]), $this->file([[]], fn ($book) => $book->getActiveSheet()->setCellValue('M2', '=10+5'))] as $file) {
            $this->post(route('issue-imports.store'), ['file' => $file, 'location_id' => $this->location->id,
                'starts_at' => '2026-10-23T00:00', 'cutoff_confirmed' => 1])->assertSessionHasErrors('file');
        }
        $this->assertDatabaseCount('issue_imports', 0);
    }

    public function test_invalid_header_date_quantity_and_multiple_sheets_are_rejected(): void
    {
        $files = [$this->file([[]], fn ($book) => $book->getActiveSheet()->setCellValue('K1', 'Kode Lain')),
            $this->file([['quantity' => 0]]), $this->file([['quantity' => -1]]),
            $this->file([['date' => '2027-01-01']]), $this->file([[]], fn ($book) => $book->createSheet())];
        foreach ($files as $file) {
            $this->post(route('issue-imports.store'), ['file' => $file, 'location_id' => $this->location->id,
                'starts_at' => '2026-10-23T00:00', 'cutoff_confirmed' => 1])->assertSessionHasErrors('file');
        }
        $this->assertDatabaseCount('issue_imports', 0);
    }

    public function test_empty_formatted_extra_columns_and_rows_do_not_count_as_transactions(): void
    {
        $file = $this->file([[]], function ($book) {
            $book->getActiveSheet()->getStyle('Q1')->getFont()->setBold(true);
            $book->getActiveSheet()->getStyle('A5000')->getFont()->setBold(true);
        });
        $this->post(route('issue-imports.store'), ['file' => $file, 'location_id' => $this->location->id,
            'starts_at' => '2026-10-23T00:00', 'cutoff_confirmed' => 1])->assertSessionHasNoErrors();
        $this->assertSame(1, IssueImport::latest('id')->firstOrFail()->row_count);
        $this->assertSame(100.0, $this->stock());
    }

    public function test_missing_acknowledgements_are_rejected_and_draft_payload_expires(): void
    {
        $document = $this->upload();
        $this->map($document);
        $this->post(route('issue-imports.post', $document), ['preview_token' => str_repeat('0', 64)])
            ->assertSessionHasErrors(['delivered_confirmed', 'units_confirmed', 'fefo_confirmed', 'period_confirmed', 'identity_confirmed']);
        $this->travel(8)->days();
        $this->artisan('issues:purge-import-drafts')->assertSuccessful();
        $this->assertNull($document->fresh()->payload);
        $this->assertSame('expired', $document->fresh()->status);
        $this->postImport($document)->assertSessionHasErrors('import');
        $this->assertSame(100.0, $this->stock());
    }

    public function test_draft_can_be_deleted_without_changing_stock_or_saved_mapping(): void
    {
        $document = $this->upload();
        $this->map($document);
        $this->get(route('issue-imports.index'))->assertOk()->assertSee('Hapus pratinjau IMP-'.$document->id);
        $this->get(route('issue-imports.show', $document))->assertOk()->assertSee('Hapus pratinjau IMP-'.$document->id);
        $this->delete(route('issue-imports.destroy', $document))->assertSessionHasNoErrors()->assertRedirect(route('issue-imports.index'));
        $this->assertDatabaseMissing('issue_imports', ['id' => $document->id]);
        $this->assertSame(100.0, $this->stock());
        $this->assertDatabaseCount('inventory_movements', 0);
        $this->assertDatabaseCount('issue_import_mappings', 1);
        $this->assertDatabaseHas('audit_logs', ['event' => 'inventory.issue-import.deleted', 'user_id' => $this->staff->id, 'auditable_id' => $document->id]);
        $this->get(route('issue-imports.show', $document))->assertNotFound();
    }

    public function test_expired_preview_can_be_deleted_but_posting_policy_and_ledger_stay(): void
    {
        $posted = $this->upload();
        $this->map($posted);
        $this->postImport($posted)->assertSessionHasNoErrors();
        $expired = $this->upload([['sale' => 'SIM-EXPIRED']]);
        $this->travel(8)->days();
        $this->artisan('issues:purge-import-drafts')->assertSuccessful();
        $this->assertSame('expired', $expired->fresh()->status);
        $this->delete(route('issue-imports.destroy', $expired))->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('issue_imports', ['id' => $expired->id]);
        $this->assertDatabaseHas('issue_imports', ['id' => $posted->id, 'status' => 'posted']);
        $this->assertDatabaseCount('issue_import_lines', 1);
        $this->assertDatabaseCount('issue_import_policies', 1);
        $this->assertDatabaseCount('issue_import_mappings', 1);
        $this->assertSame(85.0, $this->stock());
    }

    public function test_posted_documents_and_duplicate_only_postings_cannot_be_deleted(): void
    {
        $document = $this->upload();
        $this->map($document);
        $stale = clone $document;
        $this->postImport($document)->assertSessionHasNoErrors();
        $duplicate = $this->upload();
        $this->postImport($duplicate)->assertSessionHasNoErrors();
        foreach ([$document, $duplicate] as $posted) {
            $this->get(route('issue-imports.show', $posted))->assertOk()->assertDontSee('data-issue-import-delete', false);
            $this->delete(route('issue-imports.destroy', $posted))->assertSessionHasErrors('import');
            $this->assertDatabaseHas('issue_imports', ['id' => $posted->id, 'status' => 'posted']);
        }
        try {
            app(IssueImportService::class)->delete($stale, $this->staff);
            $this->fail('A stale draft model must not bypass the posted-state lock.');
        } catch (ValidationException $error) {
            $this->assertArrayHasKey('import', $error->errors());
        }
        $this->assertDatabaseCount('issue_import_lines', 1);
        $this->assertDatabaseCount('issue_import_line_movements', 2);
        $this->assertSame(85.0, $this->stock());
    }

    public function test_deleted_draft_cannot_be_posted_using_a_stale_preview_token(): void
    {
        $document = $this->upload();
        $this->map($document);
        $payload = $this->confirmations($document);
        $this->delete(route('issue-imports.destroy', $document))->assertSessionHasNoErrors();
        $this->post(route('issue-imports.post', $document), $payload)->assertNotFound();
        $this->delete(route('issue-imports.destroy', $document))->assertNotFound();
        $this->assertDatabaseCount('issue_import_lines', 0);
        $this->assertDatabaseCount('inventory_movements', 0);
        $this->assertSame(100.0, $this->stock());
    }

    public function test_retrying_same_post_is_idempotent_and_reversal_does_not_clear_source_ledger(): void
    {
        $document = $this->upload();
        $this->map($document);
        $data = $this->confirmations($document);
        $this->post(route('issue-imports.post', $document), $data)->assertSessionHasNoErrors();
        $this->post(route('issue-imports.post', $document), $data)->assertSessionHasNoErrors();
        $this->assertSame(85.0, $this->stock());
        $movement = InventoryMovement::first();
        InventoryMovement::create(['type' => 'reversal', 'reference' => 'REV-SIM', 'reversed_movement_id' => $movement->id,
            'medicine_id' => $movement->medicine_id, 'batch_id' => $movement->batch_id, 'destination_location_id' => $this->location->id,
            'quantity' => $movement->quantity, 'occurred_at' => now(), 'created_by' => $this->staff->id]);
        $second = $this->upload();
        $this->assertSame(1, app(IssueImportService::class)->preview($second)['duplicates']);
    }
}
