<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use Throwable;
use ZipArchive;

class NeoklinikIssueReader
{
    public const MAX_ROWS = 2000;

    public const MAX_SCAN_ROWS = 10000;

    public const HEADERS = ['Tgl. Jual', 'Medication Id', 'No. Jual', 'Jns. Jual', 'Nama Klinik',
        'Nama Poli', 'Nama Dokter', 'Nama Pasien', 'Resep / Non Resep', 'Id Racikan',
        'Id Obat', 'Nama Obat', 'Kuantitas', 'Satuan', 'MedicationRequest Id', 'MedicationDispense Id'];

    public static function normalize(string $value): string
    {
        return mb_strtoupper(preg_replace('/\s+/u', ' ', trim($value)));
    }

    public function read(string $path): array
    {
        $this->checkArchive($path);
        $book = null;
        try {
            $reader = new Xlsx;
            $info = $reader->listWorksheetInfo($path);
            if (count($info) !== 1) {
                $this->fail('File terbaca memiliki '.count($info).' sheet (termasuk sheet tersembunyi). Gunakan workbook dengan satu sheet transaksi.');
            }
            if ($info[0]['totalColumns'] < 16) {
                $this->fail('File terbaca hanya memiliki '.$info[0]['totalColumns'].' kolom. Ekspor Neoklinik memerlukan 16 kolom dari Tgl. Jual sampai MedicationDispense Id.');
            }
            if ($info[0]['totalRows'] > self::MAX_SCAN_ROWS + 1) {
                $this->fail('Area sheet terbaca sampai baris '.$info[0]['totalRows'].', termasuk kemungkinan sel kosong berformat. Bersihkan baris kosong di bawah data atau ekspor ulang; maksimal 2.000 transaksi dalam area 10.000 baris.');
            }
            $reader->setReadFilter(new class implements IReadFilter
            {
                public function readCell(string $columnAddress, int $row, string $worksheetName = ''): bool
                {
                    return ($row === 1 && strlen($columnAddress) === 1 && $columnAddress <= 'P') || ($row > 1 && $row <= NeoklinikIssueReader::MAX_SCAN_ROWS + 1 && in_array($columnAddress, ['A', 'C', 'D', 'I', 'J', 'K', 'L', 'M', 'N'], true));
                }
            });
            $reader->setReadEmptyCells(false);
            $book = $reader->load($path);
            $sheet = $book->getSheet(0);
            foreach (self::HEADERS as $index => $header) {
                if (trim((string) $sheet->getCell([$index + 1, 1])->getValue()) !== $header) {
                    $this->fail('Header Excel tidak sesuai ekspor Neoklinik. Gunakan file asli tanpa mengubah urutan atau nama kolom.');
                }
            }
            $rows = $seen = [];
            for ($number = 2; $number <= $info[0]['totalRows']; $number++) {
                $values = [];
                foreach (['A', 'C', 'D', 'I', 'J', 'K', 'L', 'M', 'N'] as $column) {
                    if (! $sheet->cellExists($column.$number)) {
                        $values[$column] = null;

                        continue;
                    }
                    $cell = $sheet->getCell($column.$number);
                    if ($cell->getDataType() === DataType::TYPE_FORMULA) {
                        $this->fail("Baris {$number}: formula tidak diperbolehkan. Gunakan hasil ekspor, bukan rumus Excel.");
                    }
                    $values[$column] = $cell->getValue();
                }
                if (count(array_filter($values, fn ($value) => $value !== null && $value !== '')) === 0) {
                    continue;
                }
                if (count($rows) >= self::MAX_ROWS) {
                    $this->fail('Ditemukan lebih dari 2.000 baris transaksi berisi data. Perkecil periode ekspor; baris kosong tidak dihitung.');
                }
                foreach (['C' => 150, 'K' => 100, 'L' => 200, 'N' => 50] as $column => $length) {
                    if (! is_scalar($values[$column]) || trim((string) $values[$column]) === '' || mb_strlen((string) $values[$column]) > $length) {
                        $this->fail("Baris {$number}: nomor jual, kode obat, nama obat, dan satuan harus terisi dengan panjang yang wajar.");
                    }
                }
                if (! is_numeric($values['A']) || ! is_numeric($values['M']) || ! is_finite((float) $values['M']) ||
                    (float) $values['M'] <= 0 || (float) $values['M'] > 999999999999.9999 ||
                    abs(round((float) $values['M'], 4) - (float) $values['M']) > 0.0000001) {
                    $this->fail("Baris {$number}: tanggal harus berupa tanggal Excel dan kuantitas harus positif dengan maksimal empat desimal.");
                }
                if ($values['J'] !== null && (! is_scalar($values['J']) || mb_strlen((string) $values['J']) > 150)) {
                    $this->fail("Baris {$number}: identitas racikan tidak valid.");
                }
                $date = CarbonImmutable::instance(Date::excelToDateTimeObject((float) $values['A'], 'Asia/Jakarta'));
                if ($date->year < 2000 || $date->greaterThan(now('Asia/Jakarta')->addMinutes(5))) {
                    $this->fail("Baris {$number}: tanggal transaksi tidak wajar atau berada di masa depan. Periksa periode ekspor.");
                }
                $code = self::normalize((string) $values['K']);
                $unit = self::normalize((string) $values['N']);
                // Stable across file names, row order, mapping, quantity changes, and chosen locations.
                $key = hash('sha256', json_encode(['neoklinik:clinic:v1', self::normalize((string) $values['C']), $code,
                    self::normalize((string) ($values['J'] ?? ''))], JSON_THROW_ON_ERROR));
                if (isset($seen[$key])) {
                    $this->fail("Baris {$number} dan {$seen[$key]} memiliki identitas penjualan-obat-racikan yang sama. Ekspor ini ambigu; jangan digabung atau diposting otomatis.");
                }
                $seen[$key] = $number;
                $quantity = number_format((float) $values['M'], 4, '.', '');
                $occurred = $date->format('Y-m-d H:i:s.u');
                $rows[] = ['source_key' => $key, 'sale_key' => hash('sha256', self::normalize((string) $values['C'])),
                    'fingerprint' => hash('sha256', json_encode([$occurred, $quantity, $unit,
                        self::normalize((string) ($values['D'] ?? '')), self::normalize((string) ($values['I'] ?? ''))], JSON_THROW_ON_ERROR)),
                    'source_row' => $number, 'source_code' => $code, 'source_name' => trim((string) $values['L']),
                    'source_unit' => $unit, 'source_quantity' => $quantity, 'occurred_at' => $occurred,
                    'compound' => trim((string) ($values['J'] ?? '')) !== ''];
            }
            if (! $rows) {
                $this->fail('File tidak berisi transaksi. Ekspor ulang dengan periode yang memiliki pengeluaran.');
            }
            usort($rows, fn ($a, $b) => [$a['occurred_at'], $a['source_key']] <=> [$b['occurred_at'], $b['source_key']]);

            return $rows;
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable) {
            // Parser exceptions may include source cells; never log their raw contents.
            $this->fail('File Excel tidak dapat dibaca. Gunakan ekspor .xlsx Neoklinik yang tidak rusak atau diproteksi.');
        } finally {
            $book?->disconnectWorksheets();
        }
    }

    private function checkArchive(string $path): void
    {
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            $this->fail('File harus berupa workbook .xlsx yang valid.');
        }
        try {
            $size = 0;
            if ($zip->numFiles > 1500) {
                $this->fail('Struktur file terlalu besar. Gunakan ekspor transaksi sederhana.');
            }
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $entry = $zip->statIndex($i);
                $size += $entry['size'];
                if ($size > 32 * 1024 * 1024 || preg_match('~(?:vbaProject|externalLinks/|embeddings/|drawings/)~i', $entry['name'])) {
                    $this->fail('File terlalu besar atau mengandung objek/tautan eksternal. Gunakan ekspor transaksi tanpa objek tambahan.');
                }
                if (str_ends_with($entry['name'], '.rels') && preg_match('/TargetMode\s*=\s*["\']External["\']/i', $zip->getFromIndex($i))) {
                    $this->fail('Tautan eksternal tidak diperbolehkan pada file impor.');
                }
            }
        } finally {
            $zip->close();
        }
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['file' => $message]);
    }
}
