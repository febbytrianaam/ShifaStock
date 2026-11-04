<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Medicine;
use App\Models\MedicinePackage;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Tests\TestCase;

class MedicineExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_pharmacy_can_download_a_safe_editable_master_medicine_template(): void
    {
        $pharmacy = User::factory()->create(['role' => Role::Pharmacy]);
        $tablet = Unit::create(['name' => 'Tablet']);
        Unit::create(['name' => 'Botol']);
        $medicine = Medicine::create([
            'code' => '=OBT-001',
            'original_name' => '=PARACETAMOL KLINIK',
            'name' => '=PARACETAMOL 500 MG',
            'generic_name' => 'Paracetamol',
            'strength' => '500 mg',
            'dosage_form' => 'Tablet',
            'unit_id' => $tablet->id,
            'verification_status' => 'perlu_revisi',
            'verification_note' => 'Pastikan isi kemasan.',
            'is_active' => true,
        ]);
        MedicinePackage::create(['medicine_id' => $medicine->id, 'name' => 'Box', 'conversion_factor' => 100]);

        $response = $this->actingAs($pharmacy)->get(route('medicines.export-template'));

        $response->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
            ->assertHeader('cache-control', 'no-store, private');
        $this->assertMatchesRegularExpression('/attachment; filename=?template-master-obat-\d{8}-\d{4}\.xlsx/', (string) $response->headers->get('content-disposition'));

        $path = tempnam(sys_get_temp_dir(), 'shifastock-master-obat-');
        file_put_contents($path, $response->streamedContent());

        try {
            $workbook = IOFactory::load($path);
            $master = $workbook->getSheetByName('Master Obat');

            $this->assertNotNull($master);
            $this->assertSame('Petunjuk', $workbook->getSheet(0)->getTitle());
            $this->assertSame('Kode Klinik', $master->getCell('B6')->getValue());
            $this->assertSame('=OBT-001', $master->getCell('B7')->getValue());
            $this->assertSame(DataType::TYPE_STRING, $master->getCell('B7')->getDataType());
            $this->assertSame('Box', $master->getCell('I7')->getValue());
            $this->assertSame(100.0, (float) $master->getCell('J7')->getValue());
            $this->assertSame('Perlu Revisi', $master->getCell('K7')->getValue());
            $this->assertSame('DaftarSatuanStok', $master->getDataValidation('H7')->getFormula1());
            $this->assertSame('DaftarStatusVerifikasi', $master->getDataValidation('K7')->getFormula1());
            $this->assertNotNull($workbook->getDefinedName('DaftarSatuanStok'));
            $this->assertNotNull($workbook->getDefinedName('DaftarStatusVerifikasi'));
            $this->assertSame(Worksheet::SHEETSTATE_HIDDEN, $workbook->getSheetByName('Referensi')->getSheetState());
            $workbook->disconnectWorksheets();
        } finally {
            @unlink($path);
        }
    }

    public function test_the_export_uses_the_same_view_permission_as_the_medicine_page(): void
    {
        $pharmacy = User::factory()->create(['role' => Role::Pharmacy]);
        $administrator = User::factory()->create(['role' => Role::Administrator]);

        $this->actingAs($pharmacy)->get('/obat')->assertOk()->assertSee('Unduh Template Excel');
        $this->get(route('medicines.export-template'))->assertOk();
        $this->actingAs($administrator)->get(route('medicines.export-template'))->assertForbidden();
    }
}
