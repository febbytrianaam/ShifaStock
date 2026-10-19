<?php

// Format recorded evidence only; never invent results or rerun against clinic data.
$directory = dirname(__DIR__).'/Docs/Capstone_Screenshots/Bukti_Pengujian';
$execution = json_decode(file_get_contents($directory.'/hasil-eksekusi.json'), true, flags: JSON_THROW_ON_ERROR);
$xml = simplexml_load_file($directory.'/phpunit-results.xml', SimpleXMLElement::class, LIBXML_NONET);
if ($xml === false) throw new RuntimeException('Laporan JUnit tidak dapat dibaca.');
$suites = $xml->xpath('//testsuite[@file]');
$groups = [
    'Akses dan pengelolaan pengguna' => ['AdminUserManagementTest', 'FoundationTest', 'GuestRoleTest'],
    'Penerimaan obat dan draf' => ['ReceiptWorkflowTest'],
    'Mutasi dan rekonsiliasi stok' => ['InventoryFlowTest', 'InventoryIntegrationTest', 'InventoryReconciliationTest', 'InventoryUsabilityTest'],
    'Pemeriksaan stok harian' => ['DailyBatchCountTest', 'NightlyCheckTest', 'NightlyDraftCancelTest'],
    'Stock opname bulanan' => ['OpnameAuditTest'],
    'Perlindungan satuan dan snapshot' => ['StockUnitProtectionTest', 'StockSnapshotTest'],
    'Antarmuka, format angka, dan dasar' => ['PrototypeUiTest', 'QuantityTest', 'ExampleTest'],
    'Skenario fungsional capstone' => ['CapstoneFunctionalTest'],
];
$rows = [];
foreach ($groups as $label => $classes) $rows[$label] = ['tests' => 0, 'assertions' => 0, 'failures' => 0, 'errors' => 0, 'skipped' => 0];
$details = [];
foreach ($suites as $suite) {
    $name = (string) $suite['name'];
    $short = substr($name, strrpos($name, '\\') + 1);
    $matched = false;
    foreach ($groups as $label => $classes) if (in_array($short, $classes, true)) {
        foreach (array_keys($rows[$label]) as $key) $rows[$label][$key] += (int) $suite[$key];
        $matched = true;
        break;
    }
    if (! $matched) throw new RuntimeException('Test suite belum dipetakan: '.$short);
    foreach ($suite->testcase as $case) $details[] = ['suite' => $name, 'name' => (string) $case['name'],
        'assertions' => (int) $case['assertions'], 'status' => isset($case->failure) || isset($case->error) ? 'Gagal' : (isset($case->skipped) ? 'Dilewati' : 'Lulus')];
}
$total = array_fill_keys(['tests', 'assertions', 'failures', 'errors', 'skipped'], 0);
foreach ($rows as $row) foreach ($total as $key => $value) $total[$key] += $row[$key];
$root = $xml->testsuite[0];
foreach ($total as $key => $value) if ($value !== (int) $root[$key]) throw new RuntimeException('Total tabel berbeda dari JUnit: '.$key);
$commands = array_column($execution['commands'], null, 'file');
$mysql = file_get_contents($directory.'/02_Konkruensi_MySQL.txt');
$cases = [
    'concurrent FEFO issues' => ['Pengeluaran bersamaan (FEFO)', 'Stok tidak terjual berlebih'],
    'concurrent reversal:' => ['Reversal berulang', 'Pembalikan diterapkan sekali'],
    'concurrent opname finalization' => ['Finalisasi opname bersamaan', 'Penyesuaian diterapkan sekali'],
    'concurrent daily approval' => ['Persetujuan harian bersamaan', 'Persetujuan diterapkan sekali'],
    'concurrent duplicate reference' => ['Referensi transaksi ganda', 'Transaksi diposting sekali'],
    'unit edit versus first receipt' => ['Ubah satuan saat penerimaan', 'Satuan historis terlindungi'],
    'concurrent first receipts' => ['Dua penerimaan pertama', 'Kedua penambahan tercatat'],
];
$raceRows = [];
foreach ($cases as $needle => [$label, $expected]) $raceRows[] = ['label' => $label, 'expected' => $expected,
    'passed' => $commands['02_Konkruensi_MySQL.txt']['exit_code'] === 0 && str_contains($mysql, 'PASS '.$needle)];
$reconciliation = file_get_contents($directory.'/04_Rekonsiliasi_Simulasi.txt');
preg_match('/(\d+) pasangan obat\/lokasi diperiksa/', $reconciliation, $pairs);
$e = fn ($value) => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$badge = fn (string $status) => '<span class="status '.($status === 'Lulus' ? 'pass' : 'attention').'">'.$e($status).'</span>';
$suiteStatus = fn ($row) => $row['failures'] || $row['errors'] ? 'Gagal' : ($row['skipped'] ? 'Dilewati' : 'Lulus');
$date = (new DateTimeImmutable($execution['started_at']))->setTimezone(new DateTimeZone('Asia/Jakarta'))->format('d/m/Y H:i').' WIB';
$racePass = count(array_filter($raceRows, fn ($row) => $row['passed']));
ob_start();
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Hasil Pengujian ShifaStock - Dokumentasi Capstone</title>
<style>
*{box-sizing:border-box}body{margin:0;background:#f6f8fb;color:#23313d;font:14px/1.45 Arial,sans-serif;letter-spacing:0}main{max-width:1360px;margin:0 auto;padding:28px 24px}header{display:flex;justify-content:space-between;gap:24px;align-items:flex-start;border-bottom:1px solid #dbe2e8;padding-bottom:18px}h1{margin:4px 0 5px;font-size:26px}h2{font-size:16px;margin:0 0 4px}.eyebrow{font-size:12px;color:#216597;font-weight:700}p{margin:0;color:#596a79}.metadata{text-align:right;font-size:12px;line-height:1.8}.metrics{display:grid;grid-template-columns:repeat(4,1fr);border-bottom:1px solid #dbe2e8;margin-bottom:22px}.metric{padding:18px 0}.metric b{font-size:28px;display:block;color:#17694c}.metric span{font-size:12px;color:#596a79}.columns{display:grid;grid-template-columns:1.15fr 1fr;gap:24px}section{min-width:0}.caption{font-size:12px;margin-bottom:12px}table{border-collapse:collapse;width:100%;background:white;font-size:12px}th{background:#edf2f6;color:#435666;text-align:left;font-size:11px;font-weight:700}th,td{padding:10px 12px;border-bottom:1px solid #e4e9ee;vertical-align:middle}td:first-child{font-weight:600}.number{text-align:right;font-variant-numeric:tabular-nums}.status{display:inline-block;font-size:11px;font-weight:700;padding:3px 8px;border-radius:4px}.pass{background:#e6f4ed;color:#17694c}.attention{background:#fff0e5;color:#9b4511}tfoot td{background:#f1f5f7;font-weight:700}.validation{margin-top:24px}.validation table{margin-top:10px}.source-note{margin-top:16px;padding-top:14px;border-top:1px solid #dbe2e8;font-size:12px;color:#596a79}.source-note strong{color:#854c13}a{color:#216597}details{margin-top:22px;font-size:12px}summary{cursor:pointer;font-weight:700;color:#216597}details table{margin-top:12px}.details-name{font-family:monospace;font-weight:400;overflow-wrap:anywhere}@media(max-width:850px){.columns{grid-template-columns:1fr}.metrics{grid-template-columns:repeat(2,1fr)}header{display:block}.metadata{text-align:left;margin-top:12px}.table-scroll{overflow:auto}}@media print{body{background:white}main{padding:0}details{display:none}}
@media(min-width:851px){main{padding:20px 24px}header{padding-bottom:14px}.metric{padding:12px 0}.metrics{margin-bottom:16px}th,td{padding:8px 10px}.validation{margin-top:18px}.source-note{margin-top:12px;padding-top:10px}}
<?php if ($rows['Skenario fungsional capstone']['tests'] > 0): ?>
@media(min-width:851px){main{padding-top:16px}header{padding-bottom:12px}.metric{padding:10px 0}.metrics{margin-bottom:14px}th,td{padding:6px 10px}.validation{margin-top:14px}}
<?php endif ?>
</style>
</head>
<body><main>
<header><div><div class="eyebrow">SHIFASTOCK / DOKUMENTASI CAPSTONE STSI4440</div><h1>Hasil Pengujian Otomatis</h1><p>Ringkasan dari hasil eksekusi aktual, menggunakan data simulasi dan database terpisah.</p></div>
<div class="metadata">Waktu pengujian: <?= $e($date) ?><br>Commit aplikasi: <code title="<?= $e($execution['commit']) ?>"><?= $e(substr($execution['commit'], 0, 12)) ?></code><br>Laporan dokumentasi, bukan halaman fitur aplikasi</div></header>
<div class="metrics"><div class="metric"><b><?= $total['tests'] ?></b><span>Kasus uji otomatis</span></div><div class="metric"><b><?= $total['assertions'] ?></b><span>Pemeriksaan / assertion</span></div><div class="metric"><b><?= $total['failures'] + $total['errors'] ?></b><span>Kegagalan dan error</span></div><div class="metric"><b><?= $racePass ?> / <?= count($raceRows) ?></b><span>Skenario bersamaan MySQL lulus</span></div></div>
<div class="columns"><section><h2>Fungsi dan Keamanan Alur</h2><p class="caption">Dikelompokkan dari JUnit; satu kasus uji dapat memeriksa beberapa hal.</p><div class="table-scroll"><table><thead><tr><th>Area pengujian</th><th class="number">Kasus</th><th class="number">Assertion</th><th>Hasil</th></tr></thead><tbody>
<?php foreach ($rows as $label => $row): if ($row['tests'] === 0) continue; ?><tr><td><?= $e($label) ?></td><td class="number"><?= $row['tests'] ?></td><td class="number"><?= $row['assertions'] ?></td><td><?= $badge($suiteStatus($row)) ?></td></tr><?php endforeach ?>
</tbody><tfoot><tr><td>Total</td><td class="number"><?= $total['tests'] ?></td><td class="number"><?= $total['assertions'] ?></td><td><?= $total['skipped'] ?> dilewati</td></tr></tfoot></table></div></section>
<section><h2>Transaksi Bersamaan di MySQL</h2><p class="caption">Dua proses terpisah menguji persaingan akses pada database sementara.</p><table><thead><tr><th>Skenario</th><th>Hasil yang diperiksa</th><th>Status</th></tr></thead><tbody>
<?php foreach ($raceRows as $row): ?><tr><td><?= $e($row['label']) ?></td><td><?= $e($row['expected']) ?></td><td><?= $badge($row['passed'] ? 'Lulus' : 'Belum terbukti') ?></td></tr><?php endforeach ?>
</tbody></table></section></div>
<section class="validation"><h2>Pemeriksaan Pendukung</h2><table><thead><tr><th>Pemeriksaan</th><th>Hasil aktual</th><th>Sumber</th><th>Status</th></tr></thead><tbody>
<tr><td>Build frontend</td><td>Exit code <?= (int) $commands['03_Build_Frontend.txt']['exit_code'] ?></td><td><a href="03_Build_Frontend.txt">Keluaran build</a></td><td><?= $badge($commands['03_Build_Frontend.txt']['exit_code'] === 0 ? 'Lulus' : 'Gagal') ?></td></tr>
<tr><td>Rekonsiliasi stok simulasi</td><td><?= $e($pairs[1] ?? 'Jumlah tidak terbaca') ?> pasangan obat/lokasi; <?= str_contains($reconciliation, 'Tidak ditemukan selisih.') ? 'tidak ditemukan selisih' : 'lihat keluaran asli' ?></td><td><a href="04_Rekonsiliasi_Simulasi.txt">Keluaran rekonsiliasi</a></td><td><?= $badge($commands['04_Rekonsiliasi_Simulasi.txt']['exit_code'] === 0 ? 'Lulus' : 'Gagal') ?></td></tr>
</tbody></table></section>
<div class="source-note">Sumber asli: <a href="phpunit-results.xml">JUnit XML</a> · <a href="01_Regresi_SQLite.txt">Regresi SQLite</a> · <a href="02_Konkruensi_MySQL.txt">Konkurensi MySQL</a> · <a href="hasil-eksekusi.json">Kode exit dan metadata</a>.<br><strong>Batasan:</strong> tes otomatis bukan UAT atau audit keamanan menyeluruh. Temuan UI label batch lintas lokasi tetap terbuka; lihat <a href="../Kendala_dan_Verifikasi.md">catatan kendala</a>. Tidak ada data klinik atau password dalam laporan ini.</div>
<details><summary>Rincian <?= count($details) ?> kasus uji dari JUnit</summary><table><thead><tr><th>Kelompok teknis</th><th>Kasus uji</th><th class="number">Assertion</th><th>Hasil</th></tr></thead><tbody><?php foreach ($details as $detail): ?><tr><td class="details-name"><?= $e($detail['suite']) ?></td><td class="details-name"><?= $e($detail['name']) ?></td><td class="number"><?= $detail['assertions'] ?></td><td><?= $badge($detail['status']) ?></td></tr><?php endforeach ?></tbody></table></details>
</main></body></html>
<?php
file_put_contents($directory.'/Laporan_Pengujian.html', ob_get_clean());
file_put_contents($directory.'/Ringkasan_Tabel.json', json_encode(['commit' => $execution['commit'], 'test_started_at' => $execution['started_at'],
    'source' => 'phpunit-results.xml', 'totals' => $total, 'groups' => $rows, 'mysql_scenarios' => $raceRows], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
echo 'Laporan tabel dibuat dari '.$total['tests'].' kasus / '.$total['assertions'].' assertion; '.$racePass.' skenario MySQL lulus.'.PHP_EOL;
