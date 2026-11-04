<?php

namespace App\Http\Controllers;

use App\Models\Medicine;
use App\Models\Unit;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\NamedRange;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class MedicineExportController extends Controller
{
    /**
     * Download a read-only copy of the current master data as a working template.
     * The template is intentionally not an importer: changes remain subject to
     * pharmacist verification before they are applied in ShifaStock.
     */
    public function download()
    {
        $medicines = Medicine::query()
            ->with(['unit', 'packages'])
            ->orderBy('code')
            ->get();

        $units = Unit::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->pluck('name')
            ->values();

        $filename = (app()->environment('uat') ? 'uat-' : '')
            .'template-master-obat-'.now('Asia/Jakarta')->format('Ymd-Hi').'.xlsx';

        return response()->streamDownload(function () use ($medicines, $units): void {
            $spreadsheet = new Spreadsheet;

            try {
                $spreadsheet->getProperties()
                    ->setCreator('ShifaStock')
                    ->setTitle('Template Master Obat')
                    ->setDescription('Template pengisian dan koreksi master obat ShifaStock.');

                $this->buildGuideSheet($spreadsheet->getActiveSheet());
                $referenceSheet = $spreadsheet->createSheet();
                $this->buildReferenceSheet($spreadsheet, $referenceSheet, $units);
                $this->buildMasterSheet($spreadsheet->createSheet(), $medicines, $units);

                (new Xlsx($spreadsheet))->save('php://output');
            } finally {
                $spreadsheet->disconnectWorksheets();
            }
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'no-store, private',
        ]);
    }

    private function buildGuideSheet(Worksheet $sheet): void
    {
        $sheet->setTitle('Petunjuk');
        $sheet->mergeCells('A1:F1');
        $sheet->setCellValue('A1', 'TEMPLATE PENGISIAN MASTER OBAT');
        $sheet->getStyle('A1:F1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 16, 'color' => ['argb' => 'FFFFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF3277B2']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(28);

        $source = app()->environment('uat')
            ? 'Sumber data: UAT / simulasi ShifaStock.'
            : 'Sumber data: database ShifaStock yang sedang aktif.';
        $sheet->mergeCells('A3:F3');
        $sheet->setCellValue('A3', $source.' Diekspor pada '.now('Asia/Jakarta')->format('d/m/Y H:i').' WIB.');
        $sheet->getStyle('A3:F3')->getFont()->setItalic(true)->setSize(10);

        $instructions = [
            ['1', 'Buka sheet “Master Obat”. Isi atau koreksi hanya kolom kuning.'],
            ['2', 'Kode Klinik adalah identitas obat. Jangan mengubah kode tersebut.'],
            ['3', 'Pilih Satuan Stok dari daftar. Isi per Kemasan adalah jumlah satuan stok dalam satu kemasan pembelian.'],
            ['4', 'Gunakan Status Verifikasi “Perlu Revisi” bila nama, satuan, atau kemasan masih meragukan.'],
            ['5', 'Simpan file lalu serahkan kepada kepala apoteker/admin untuk ditinjau. File ini belum mengubah data ShifaStock secara otomatis.'],
        ];

        $sheet->fromArray([['Langkah', 'Petunjuk']], null, 'A5');
        $sheet->fromArray($instructions, null, 'A6');
        $sheet->getStyle('A5:B5')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF5B9BD5']],
        ]);
        $sheet->getStyle('A5:B10')->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB('FFD9E2F3');
        $sheet->getStyle('B6:B10')->getAlignment()->setWrapText(true);
        $sheet->getColumnDimension('A')->setWidth(12);
        $sheet->getColumnDimension('B')->setWidth(112);
        foreach (range('C', 'F') as $column) {
            $sheet->getColumnDimension($column)->setWidth(4);
        }
        foreach (range(6, 10) as $row) {
            $sheet->getRowDimension($row)->setRowHeight(32);
        }
        $sheet->freezePane('A5');
    }

    /** @param Collection<int, Medicine> $medicines */
    private function buildMasterSheet(Worksheet $sheet, $medicines, $units): void
    {
        $sheet->setTitle('Master Obat');
        $sheet->mergeCells('A1:M1');
        $sheet->setCellValue('A1', 'DAFTAR MASTER OBAT UNTUK DITINJAU PETUGAS');
        $sheet->mergeCells('A2:M2');
        $sheet->setCellValue('A2', 'Kolom berwarna kuning dapat diisi atau dikoreksi. Kode, status aktif, dan data sumber tidak diubah oleh file ini.');
        $sheet->getStyle('A1:M1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 14, 'color' => ['argb' => 'FFFFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF3277B2']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $sheet->getStyle('A2:M2')->getFont()->setItalic(true)->setSize(10);
        $sheet->getStyle('A2:M2')->getAlignment()->setWrapText(true);
        $sheet->getRowDimension(1)->setRowHeight(25);
        $sheet->getRowDimension(2)->setRowHeight(24);

        $headers = [
            'No.', 'Kode Klinik', 'Nama Asli Klinik', 'Nama Final', 'Kandungan / Keterangan',
            'Kekuatan', 'Bentuk Sediaan', 'Satuan Stok', 'Kemasan Pembelian',
            'Isi per Kemasan', 'Status Verifikasi', 'Catatan Petugas', 'Status Aktif',
        ];
        $sheet->fromArray([$headers], null, 'A6');
        $sheet->getStyle('A6:M6')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF5B9BD5']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
        ]);
        $sheet->getRowDimension(6)->setRowHeight(35);

        $row = 7;
        foreach ($medicines as $index => $medicine) {
            $package = $medicine->packages->sortBy('id')->first();
            $this->writeTextRow($sheet, $row, [
                'A' => (string) ($index + 1),
                'B' => $medicine->code,
                'C' => $medicine->original_name ?: $medicine->name,
                'D' => $medicine->name,
                'E' => $medicine->generic_name,
                'F' => $medicine->strength,
                'G' => $medicine->dosage_form,
                'H' => $medicine->unit?->name,
                'I' => $package?->name ?: $medicine->purchase_unit_name,
                'J' => $package?->conversion_factor ?: $medicine->conversion_candidate,
                'K' => $this->verificationLabel($medicine->verification_status),
                'L' => $medicine->verification_note,
                'M' => $medicine->is_active ? 'Aktif' : 'Nonaktif',
            ]);
            $sheet->getRowDimension($row)->setRowHeight(28);
            $row++;
        }

        $lastRow = max(7, $row - 1);
        $sheet->getStyle("A6:M{$lastRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB('FFD9E2F3');
        $sheet->getStyle("A7:B{$lastRow}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFF1F5F9');
        $sheet->getStyle("C7:L{$lastRow}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFFFF2CC');
        $sheet->getStyle("M7:M{$lastRow}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFF1F5F9');
        $sheet->getStyle("C7:L{$lastRow}")->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP);
        $sheet->getStyle("A7:A{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("J7:J{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sheet->setAutoFilter("A6:M{$lastRow}");
        $sheet->freezePane('C7');

        foreach ([
            'A' => 7, 'B' => 16, 'C' => 28, 'D' => 28, 'E' => 28, 'F' => 16, 'G' => 18,
            'H' => 16, 'I' => 20, 'J' => 18, 'K' => 19, 'L' => 32, 'M' => 14,
        ] as $column => $width) {
            $sheet->getColumnDimension($column)->setWidth($width);
        }

        $unitValidation = (new DataValidation)
            ->setType(DataValidation::TYPE_LIST)
            ->setErrorStyle(DataValidation::STYLE_STOP)
            ->setAllowBlank(true)
            ->setShowInputMessage(true)
            ->setShowErrorMessage(true)
            ->setPromptTitle('Pilih satuan stok')
            ->setPrompt('Pilih satuan yang dipakai untuk menghitung stok obat.')
            ->setErrorTitle('Satuan tidak tersedia')
            ->setError('Pilih satuan dari daftar yang disediakan.')
            ->setFormula1('DaftarSatuanStok');
        $sheet->setDataValidation("H7:H{$lastRow}", $unitValidation);

        $statusValidation = (new DataValidation)
            ->setType(DataValidation::TYPE_LIST)
            ->setErrorStyle(DataValidation::STYLE_STOP)
            ->setAllowBlank(false)
            ->setShowInputMessage(true)
            ->setShowErrorMessage(true)
            ->setPromptTitle('Status verifikasi')
            ->setPrompt('Tandai Perlu Revisi bila data belum pasti.')
            ->setErrorTitle('Status tidak tersedia')
            ->setError('Pilih salah satu status pada daftar.')
            ->setFormula1('DaftarStatusVerifikasi');
        $sheet->setDataValidation("K7:K{$lastRow}", $statusValidation);
    }

    /** @param Collection<int, string> $units */
    private function buildReferenceSheet(Spreadsheet $spreadsheet, Worksheet $sheet, $units): void
    {
        $sheet->setTitle('Referensi');
        $sheet->setCellValue('A1', 'Satuan Stok');
        foreach ($units->values() as $index => $unit) {
            $sheet->setCellValueExplicit('A'.($index + 2), $unit, DataType::TYPE_STRING);
        }
        $sheet->setCellValue('C1', 'Status Verifikasi');
        foreach (['Belum Diverifikasi', 'Perlu Revisi', 'Terverifikasi'] as $index => $status) {
            $sheet->setCellValueExplicit('C'.($index + 2), $status, DataType::TYPE_STRING);
        }
        $spreadsheet->addNamedRange(new NamedRange('DaftarSatuanStok', $sheet, '$A$2:$A$'.max(2, $units->count() + 1)));
        $spreadsheet->addNamedRange(new NamedRange('DaftarStatusVerifikasi', $sheet, '$C$2:$C$4'));
        $sheet->setSheetState(Worksheet::SHEETSTATE_HIDDEN);
    }

    /** @param array<string, mixed> $values */
    private function writeTextRow(Worksheet $sheet, int $row, array $values): void
    {
        foreach ($values as $column => $value) {
            $cell = "{$column}{$row}";
            if ($column === 'J' && $value !== null && $value !== '') {
                $sheet->setCellValue($cell, (float) $value);

                continue;
            }

            $sheet->setCellValueExplicit($cell, (string) ($value ?? ''), DataType::TYPE_STRING);
        }
    }

    private function verificationLabel(?string $status): string
    {
        return match ($status) {
            'terverifikasi' => 'Terverifikasi',
            'perlu_revisi' => 'Perlu Revisi',
            default => 'Belum Diverifikasi',
        };
    }
}
