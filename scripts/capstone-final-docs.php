<?php

// Build documentation from the latest recorded run, not historical README totals.
$root = dirname(__DIR__);
$output = $root.'/Docs/Capstone_Testing_Final';
$proof = $output.'/Bukti_Pengujian';
$meta = json_decode(file_get_contents($proof.'/Eksekusi_Terbaru.json'), true, flags: JSON_THROW_ON_ERROR);
$browser = json_decode(file_get_contents($proof.'/Browser_final.json'), true, flags: JSON_THROW_ON_ERROR);
$xml = simplexml_load_file($proof.'/'.$meta['run_directory'].'/phpunit-results.xml', SimpleXMLElement::class, LIBXML_NONET);
if (! $xml) throw new RuntimeException('Bukti JUnit tidak tersedia.');
$total = $xml->testsuite[0];
$cases = [];
foreach ($xml->xpath('//testcase') as $case) {
    $cases[(string) $case['class'].'::'.(string) $case['name']] = $case;
}
$class = 'Tests\\Feature\\CapstoneFunctionalTest::';
$status = function (string $name) use ($cases, $class): string {
    $case = $cases[$class.$name] ?? null;
    return $case === null ? 'Belum Diuji' : (isset($case->failure) || isset($case->error) ? 'Gagal' : (isset($case->skipped) ? 'Belum Diuji' : 'Lulus'));
};
$scenarios = [
    ['Autentikasi', 'Login valid dan tidak valid', 'Kredensial salah ditolak tanpa sesi; akun aktif dengan kredensial benar masuk dashboard.', 'Login salah menghasilkan validasi username dan tetap guest; login benar mengautentikasi akun petugas.', 'authentication_valid_and_invalid_credentials'],
    ['Hak akses', 'Pembatasan berdasarkan permission', 'Guest hanya melihat; penulisan, pengelolaan pengguna, dan edit master oleh petugas ditolak.', 'Guest GET Kondisi Khusus 200 tanpa form; POST kondisi khusus dan GET pengguna 403; edit master via Livewire oleh petugas 403; tidak ada mutasi baru.', 'permissions_restrict_guest_write_and_staff_master_edit'],
    ['Master obat', 'Tambah dan edit obat', 'Kepala Apoteker dapat membuat obat dan menyimpan revisi pada record yang sama.', 'FINAL-NEW dibuat lalu namanya direvisi melalui komponen Livewire; jumlah master tetap dua (satu fixture dan satu obat baru).', 'master_medicine_create_and_edit'],
    ['Penerimaan', 'Simpan draf dan posting', 'Draf tidak mengubah stok; posting menambah stok sekali; posting ulang ditolak.', 'Draf menghasilkan nol mutasi/nol stok; posting menghasilkan dua mutasi dan stok 50; posting ulang HTTP 422 tanpa mutasi tambahan.', 'receipt_draft_then_post_adds_stock_once'],
    ['Transfer', 'Perpindahan stok dan FEFO', 'Transfer 25 mengambil batch kedaluwarsa terdekat dahulu, lalu batch berikutnya.', 'SIM-EARLY dialokasikan 20 dan SIM-LATE 5; stok Gudang/Apotek masing-masing 25; rekonsiliasi cocok.', 'transfer_allocates_earliest_expiry_first_across_batches'],
    ['Pengeluaran', 'Pengurangan stok dan pencatatan mutasi', 'Pengeluaran 22 dari Apotek 25 menyisakan 3 dan tercatat dalam ledger.', 'Stok Apotek 3; jumlah mutasi SIM-OUT 22; stok total, batch, dan ledger tetap cocok.', 'issue_decreases_stock_and_records_movement'],
    ['Kartu stok', 'Kesesuaian riwayat transaksi', 'Kartu Gudang menampilkan penerimaan dan transfer sesuai periode serta stok berjalan.', 'Empat baris mutasi memuat SIM-FKT/SIM-TRF; stok berjalan terakhir 25; cetak kartu HTTP 200.', 'stock_card_history_matches_posted_transactions'],
    ['Pemeriksaan harian', 'Penghitungan dan review', 'Pengiriman hitung fisik tidak langsung mengubah stok; persetujuan apoteker menerapkan hitungan per batch.', 'Hitungan 48 (18+30) terkirim dengan stok tetap 50; setelah review status approved dan stok 48, rekonsiliasi cocok.', 'daily_count_submission_and_pharmacist_review'],
    ['Stock opname', 'Penghitungan, hitung ulang, dan finalisasi', 'Hitung ulang mereset isian snapshot; isian baru dapat difinalisasi; sesi final tidak dapat dihitung ulang.', 'Isian fisik dihapus saat recount; pengisian ulang berhasil; status finalized; recount sesudah final HTTP 422; rekonsiliasi cocok.', 'opname_count_recount_and_finalize'],
    ['Monitoring', 'Indikator minimum dan kedaluwarsa', 'Stok 50 di bawah minimum 60 tampil sesuai lokasi; kategori expired/near/safe dan filter mengikuti tanggal.', 'Parameter Gudang muncul satu baris, Apotek kosong; expired/near/safe diuji 1/0/1 lalu 0/1/1; filter expired memilih batch yang tepat.', 'monitoring_minimum_and_expiry_indicators_with_location_filter'],
];
$runLink = 'Bukti_Pengujian/'.$meta['run_directory'];
$wib = fn (string $date) => (new DateTimeImmutable($date))->setTimezone(new DateTimeZone('Asia/Jakarta'))->format('d/m/Y H:i:s').' WIB';
$context = "- Commit dasar: `{$meta['commit']}`.\n- Yang diuji: commit dasar **ditambah perubahan working tree yang belum di-commit**, bukan commit bersih. SHA-256 source dicatat di [metadata]($runLink/metadata.json).\n- Waktu eksekusi otomatis: {$meta['started_at']} sampai {$meta['finished_at']}.\n- Waktu browser: {$browser['started_at']} sampai {$browser['finished_at']} (akhiran Z berarti UTC; WIB = UTC+7).\n- Environment: {$meta['environment']['os']}, PHP {$meta['environment']['php']}, Laravel {$meta['environment']['laravel']}; PHPUnit pada SQLite `:memory:`; konkurensi dan browser pada database MySQL acak terpisah; browser Chrome/Playwright, viewport 1440 x 900.\n- Seluruh data adalah **simulasi**, bukan data operasional klinik. Tidak ada deployment atau push remote.\n";
$context = str_replace("{$browser['started_at']} sampai {$browser['finished_at']} (akhiran Z berarti UTC; WIB = UTC+7)",
    $wib($browser['started_at']).' sampai '.$wib($browser['finished_at']), $context);
$lines = ["# Hasil Pengujian Fungsional", '', $context, '## Tabel 2: Skenario Fungsional', '',
    'Status hanya mengikuti kasus uji yang benar-benar dieksekusi. Bukti utama adalah JUnit, bukan sekadar screenshot halaman.', '',
    '| No. | Modul / Skenario | Metode | Hasil yang Diharapkan | Hasil Aktual | Status | Bukti |',
    '| --- | --- | --- | --- | --- | --- | --- |'];
$counts = ['Lulus' => 0, 'Gagal' => 0, 'Belum Diuji' => 0];
foreach ($scenarios as $index => [$module, $scenario, $expected, $actual, $method]) {
    $result = $status('test_'.$method);
    $counts[$result]++;
    $actual = $result === 'Lulus' ? $actual : 'Lihat hasil eksekusi kasus terkait pada JUnit; hasil yang diharapkan belum terbukti.';
    $lines[] = '| '.($index + 1).' | '.$module.' / '.$scenario.' | Otomatis PHPUnit'.($module === 'Master obat' ? ' + Livewire' : ' HTTP').' | '.$expected.' | '.$actual.' | **'.$result.'** | [JUnit]('.$runLink.'/phpunit-results.xml), `test_'.$method.'` |';
}
$lines = array_merge($lines, ['', "Ringkasan sepuluh kelompok: {$counts['Lulus']} Lulus; {$counts['Gagal']} Gagal; {$counts['Belum Diuji']} Belum Diuji.", '',
    '## Pengujian Browser Otomatis', '', 'Browser merupakan pengujian Playwright terhadap aplikasi HTTP lokal dan backend MySQL simulasi, bukan UAT manual. Tiga menu serupa hanya diuji dropdown-nya, bukan posting transaksinya melalui browser.', '',
    '| No. | Skenario | Hasil Aktual / Status | Bukti |', '| --- | --- | --- | --- |']);
foreach ($browser['results'] as $index => $row) {
    $evidence = isset($row['evidence']) ? '[Screenshot](Bukti_Pengujian/'.$row['evidence'].')' : '[Log kasus](Bukti_Pengujian/Browser_final.json)';
    $lines[] = '| '.($index + 1).' | '.$row['name'].' | '.$row['status'].' | '.$evidence.' |';
}
$lines = array_merge($lines, ['', '## Pengujian Manual', '',
    '**Belum Diuji:** UAT oleh petugas/apoteker nyata, kenyamanan penggunaan, cetak fisik printer klinik, serta seluruh variasi perangkat dan browser. Tidak ada klaim pengujian manual telah lulus.', '',
    'Data simulasi menguji alur terbatas, bukan seluruh kemungkinan transaksi atau sertifikasi keamanan. Tes otomatis tidak menggantikan penerimaan pengguna.', '']);
file_put_contents($output.'/Hasil_Pengujian_Fungsional.md', implode("\n", $lines));
$regressionA = $status('test_shared_batch_adjustment_changes_only_selected_location');
$regressionB = $status('test_batch_unavailable_at_selected_location_is_rejected');
$bug = "# Laporan Perbaikan Bug\n\n".$context."\n## F.3: Temuan Dan Penyebab\n\n".
    "Kondisi Khusus menampilkan label Gudang ketika lokasi Apotek dipilih dan satu batch tersedia di dua lokasi. Query backend memang memuat saldo kedua lokasi agar form dapat berganti lokasi tanpa reload. Setiap opsi memiliki `batch_id` sama, tetapi metadata dependency dan label lokasinya berbeda.\n\n".
    "Komponen searchable select sudah memfilter daftar menu menurut obat/lokasi. Kesalahannya terjadi setelah klik: penetapan `select.value = option.value` memilih opsi native pertama dengan ID yang sama, bukan objek opsi yang diklik. Masalah serupa ada pada pencocokan teks saat submit. Saat prefill atau lokasi berubah, kode lama menghapus pilihan yang tidak cocok tanpa mencari opsi saudara untuk lokasi baru.\n\n".
    "[Bukti sebelum perbaikan](Bukti_Pengujian/Bug_Sebelum_Perbaikan.png) menunjukkan Lokasi Apotek, label batch Gudang 50 Tablet, dan konteks stok Apotek 20 Tablet. Status Lulus pada kasus reproduksi di [log baseline](Bukti_Pengujian/Browser_baseline.json) berarti bug berhasil direproduksi, **bukan fitur dinyatakan benar**.\n\n".
    "## Solusi Dan Batas Perubahan\n\n".
    "- `resources/js/app.js`: klik opsi dan pencocokan saat submit memakai `selectedIndex` opsi yang benar, bukan pencarian berdasarkan value yang ambigu.\n".
    "- Pergantian dependency mencari opsi dengan ID sama yang cocok dengan lokasi/obat/arah saat ini; jika tidak ada, pilihan dikosongkan.\n".
    "- `aria-selected` mengikuti identitas opsi terpilih. Payload tetap mengirim ID batch asli.\n".
    "- Tidak mengubah query controller, service stok, FEFO, rumus kuantitas, route, permission, atau kebijakan lokasi.\n\n".
    "## Regression Test\n\n".
    "| Metode | Kasus | Status / Bukti |\n| --- | --- | --- |\n".
    "| PHPUnit HTTP + database | Batch yang sama ada di Gudang dan Apotek; penyesuaian Apotek hanya mengurangi batch Apotek dan ledger sesuai lokasi. | $regressionA; `test_shared_batch_adjustment_changes_only_selected_location` di [JUnit]($runLink/phpunit-results.xml) |\n".
    "| PHPUnit HTTP + database | Batch yang tidak tersedia pada lokasi terpilih ditolak tanpa perubahan mutasi. | $regressionB; `test_batch_unavailable_at_selected_location_is_rejected` di [JUnit]($runLink/phpunit-results.xml) |\n".
    "| Playwright | Pilihan batch, pergantian lokasi, prefill, validasi, pergantian obat, dan posting; regresi dropdown transfer/pengeluaran/retur. | Lihat status tiap kasus di [log browser](Bukti_Pengujian/Browser_final.json) |\n".
    "| Backend pascabrowser | Gudang tetap 50, Apotek 20 menjadi 18; saldo total/batch/ledger cocok. | [Rekonsiliasi aktual](Bukti_Pengujian/Browser_Rekonsiliasi.json) |\n\n".
    "## File Yang Berubah\n\n".
    "- Source aplikasi: `resources/js/app.js`.\n- Tes: `tests/Feature/CapstoneFunctionalTest.php` (sepuluh kelompok fungsional dan dua regresi backend).\n".
    "- Runner: `scripts/capstone-final-tests.php`, `scripts/capstone-final-browser.php`, `scripts/capstone-final-browser.cjs`, `scripts/capstone-final-docs.php`, `scripts/capstone-final-verify.cjs`.\n".
    "- Dokumentasi dan bukti: `Docs/Capstone_Testing_Final/`. Tambahan screenshot terdahulu tetap terpisah.\n\n".
    "## Kendala Otomasi\n\nPercobaan awal selector Playwright juga mencocokkan option native yang tersembunyi sehingga timeout. Locator diperjelas ke menu searchable select, tanpa mengubah halaman atau isi screenshot. [Log kendala awal](Bukti_Pengujian/Browser_Kendala_Selector.json) dipertahankan. Percobaan browser berikutnya mencari meta CSRF yang tidak ada pada layout; selector disesuaikan ke input token form logout asli. [Log kendala CSRF](Bukti_Pengujian/Browser_Kendala_CSRF.json) dipertahankan dan tidak digabungkan sebagai hasil final. Keduanya kendala script, bukan kegagalan produk. Browser diulang lengkap setelah perbaikan selector; source aplikasi/tes PHPUnit tidak berubah. SHA-256 script versi akhir dicatat di Verifikasi_Akhir.json, sementara metadata eksekusi PHPUnit menyimpan snapshot script pada waktu run tersebut.\n";
file_put_contents($output.'/Laporan_Perbaikan_Bug.md', $bug);
$commands = array_column($meta['commands'], null, 'name');
$mysql = file_get_contents($proof.'/'.$meta['run_directory'].'/02_Konkurensi_MySQL.txt');
preg_match_all('/^PASS .+$/m', $mysql, $mysqlCases);
$browserPassed = count(array_filter($browser['results'], fn ($row) => $row['status'] === 'Lulus'));
$browserFailed = count(array_filter($browser['results'], fn ($row) => $row['status'] === 'Gagal'));
$summary = "# Ringkasan Pengujian\n\n".$context."\n## F.2: Implementasi Dan Environment\n\n".
    "Perbaikan dibatasi pada identitas opsi dropdown batch. Sepuluh kelompok fungsional diuji melalui HTTP kernel/Livewire dengan SQLite in-memory; transaksi bersamaan diuji pada MySQL sementara; bug UI diuji melalui browser sungguhan terhadap HTTP lokal. Source aplikasi yang berubah hanya `resources/js/app.js`.\n\n".
    "## Hasil Eksekusi Terbaru\n\n| Pengujian | Hasil Aktual | Bukti |\n| --- | --- | --- |\n".
    "| PHPUnit | {$total['tests']} tes, {$total['assertions']} assertion; {$total['failures']} failure, {$total['errors']} error, {$total['skipped']} skipped; exit {$commands['01_PHPUnit_SQLite']['exit_code']} | [Log]($runLink/01_PHPUnit_SQLite.txt), [JUnit]($runLink/phpunit-results.xml) |\n".
    '| Konkurensi MySQL | '.count($mysqlCases[0])." skenario PASS; exit {$commands['02_Konkurensi_MySQL']['exit_code']} | [Log]($runLink/02_Konkurensi_MySQL.txt) |\n".
    "| Frontend Vite | Build exit {$commands['03_Frontend_Build']['exit_code']} | [Log]($runLink/03_Frontend_Build.txt) |\n".
    "| Browser Playwright final | $browserPassed Lulus, $browserFailed Gagal dari ".count($browser['results'])." kasus | [Log](Bukti_Pengujian/Browser_final.json) |\n".
    "| Rekonsiliasi setelah browser | [Hasil aktual](Bukti_Pengujian/Browser_Rekonsiliasi.json), bukan angka historis | [JSON](Bukti_Pengujian/Browser_Rekonsiliasi.json) |\n".
    "| UAT manual petugas | Belum Diuji | Tidak ada klaim penerimaan pengguna |\n\n".
    "### Skenario MySQL Yang Dieksekusi\n\n".implode("\n", array_map(fn ($s) => '- '.trim($s), $mysqlCases[0]))."\n\n".
    "## F.4: Evaluasi Dan Batas Pembuktian\n\n".
    "Angka di atas berasal dari eksekusi terbaru yang lognya disertakan, bukan hasil 58 tes pada dokumentasi sebelumnya. Sepuluh kelompok skenario dan hasil aktualnya tersedia di [tabel fungsional](Hasil_Pengujian_Fungsional.md). Bukti penyebab, perubahan, dan pengujian ulang tersedia di [laporan bug](Laporan_Perbaikan_Bug.md).\n\n".
    "Tidak seluruh sepuluh alur dijalankan melalui browser: master, draf, FEFO, kartu stok, review harian, dan finalisasi/recount dibuktikan terutama melalui PHPUnit HTTP/Livewire. Playwright fokus pada label lintas lokasi, validasi, posting kondisi khusus, monitoring dan akses guest. UAT manual, seluruh kasus produksi, lintas perangkat, serta audit keamanan menyeluruh belum dinyatakan selesai.\n\n".
    "Tidak ada data pasien, kredensial, token sesi, atau isi konfigurasi rahasia yang disertakan. Screenshot asli 1440 x 900, tanpa manipulasi DOM/response untuk mengubah hasil. Database uji dibuat terpisah dan dihapus setelah selesai; server browser dibatasi ke loopback dan dihentikan oleh runner. Lihat [metadata browser](Bukti_Pengujian/Browser_final.json) dan keluaran cleanup pada log.\n\n".
    "### Verifikasi Bukti\n\n[Verifikasi akhir](Bukti_Pengujian/Verifikasi_Akhir.json) mencatat dimensi, isi nonkosong, hash PNG, konsistensi source aplikasi/tes, tautan lokal, dan pemeriksaan pola rahasia. Gambar akses ditolak adalah HTTP 403 yang memang diharapkan, bukan screenshot keberhasilan fitur. Screenshot SweetAlert diambil setelah animasi selesai; seluruh gambar ditinjau visual untuk keterbacaan dan data sensitif. Peninjauan gambar oleh asisten bukan UAT manual petugas.\n\n".
    "## Pengambilan Ulang\n\n1. Jalankan `php scripts/capstone-final-tests.php` untuk PHPUnit, konkurensi MySQL, dan build.\n".
    "2. Pastikan Playwright tersedia pada resolusi modul Node, lalu jalankan `php scripts/capstone-final-browser.php final`.\n".
    "3. Jalankan `php scripts/capstone-final-docs.php` untuk memperbarui tabel dari bukti terbaru, lalu `node scripts/capstone-final-verify.cjs` untuk memeriksa bukti dan metadata.\n\n".
    "Mode `baseline` hanya untuk mereproduksi bug pada source sebelum perbaikan, bukan untuk menyatakan aplikasi lulus. Jangan menjalankannya pada source sesudah perbaikan lalu mengharapkan bug tetap muncul. Tidak ada langkah deployment atau push otomatis.\n";
file_put_contents($output.'/Ringkasan_Pengujian.md', $summary);
echo 'Dokumentasi dibuat dari JUnit aktual dan '.count($browser['results']).' hasil browser.'.PHP_EOL;
