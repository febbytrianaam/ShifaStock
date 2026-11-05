<?php

// Writes a synthetic Neoklinik sales export for browser tests. Rows arrive as JSON on stdin
// ({sold_at: "Y-m-d H:i:s" WIB, sale, code, name, quantity, unit}); the .xlsx path is the first argument.
use App\Services\NeoklinikIssueReader;
use Carbon\CarbonImmutable;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

require __DIR__.'/../vendor/autoload.php';

$path = $argv[1] ?? throw new InvalidArgumentException('Path file .xlsx wajib diisi.');
$rows = json_decode(stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR);
$book = new Spreadsheet;
$sheet = $book->getActiveSheet();
$sheet->setTitle('Sheet');
$sheet->fromArray(NeoklinikIssueReader::HEADERS, null, 'A1');
foreach ($rows as $index => $row) {
    $soldAt = CarbonImmutable::parse($row['sold_at'], 'Asia/Jakarta');
    $sheet->fromArray([Date::PHPToExcel($soldAt), null, $row['sale'], 'Internal', 'Klinik Simulasi', 'Poli Simulasi',
        'DOKTER_SINTETIS_PRIVASI', 'PASIEN_SINTETIS_PRIVASI', 'Resep', null, $row['code'], $row['name'], $row['quantity'], $row['unit'], null, null], null, 'A'.($index + 2));
}
(new Xlsx($book))->save($path);
$book->disconnectWorksheets();
