# Hasil Pengujian Fungsional

- Commit dasar: `edf1faa8e51f97927b1f78337c69b230b58be2d6`.
- Yang diuji: commit dasar **ditambah perubahan working tree yang belum di-commit**, bukan commit bersih. SHA-256 source dicatat di [metadata](Bukti_Pengujian/Run_20261018_232819/metadata.json).
- Waktu eksekusi otomatis: 2026-10-18T23:28:19+07:00 sampai 2026-10-18T23:31:40+07:00.
- Waktu browser: 18/10/2026 23:39:29 WIB sampai 18/10/2026 23:40:19 WIB.
- Environment: Windows, PHP 8.5.9, Laravel 13.35.0; PHPUnit pada SQLite `:memory:`; konkurensi dan browser pada database MySQL acak terpisah; browser Chrome/Playwright, viewport 1440 x 900.
- Seluruh data adalah **simulasi**, bukan data operasional klinik. Tidak ada deployment atau push remote.

## Tabel 2: Skenario Fungsional

Status hanya mengikuti kasus uji yang benar-benar dieksekusi. Bukti utama adalah JUnit, bukan sekadar screenshot halaman.

| No. | Modul / Skenario | Metode | Hasil yang Diharapkan | Hasil Aktual | Status | Bukti |
| --- | --- | --- | --- | --- | --- | --- |
| 1 | Autentikasi / Login valid dan tidak valid | Otomatis PHPUnit HTTP | Kredensial salah ditolak tanpa sesi; akun aktif dengan kredensial benar masuk dashboard. | Login salah menghasilkan validasi username dan tetap guest; login benar mengautentikasi akun petugas. | **Lulus** | [JUnit](Bukti_Pengujian/Run_20261018_232819/phpunit-results.xml), `test_authentication_valid_and_invalid_credentials` |
| 2 | Hak akses / Pembatasan berdasarkan permission | Otomatis PHPUnit HTTP | Guest hanya melihat; penulisan, pengelolaan pengguna, dan edit master oleh petugas ditolak. | Guest GET Kondisi Khusus 200 tanpa form; POST kondisi khusus dan GET pengguna 403; edit master via Livewire oleh petugas 403; tidak ada mutasi baru. | **Lulus** | [JUnit](Bukti_Pengujian/Run_20261018_232819/phpunit-results.xml), `test_permissions_restrict_guest_write_and_staff_master_edit` |
| 3 | Master obat / Tambah dan edit obat | Otomatis PHPUnit + Livewire | Kepala Apoteker dapat membuat obat dan menyimpan revisi pada record yang sama. | FINAL-NEW dibuat lalu namanya direvisi melalui komponen Livewire; jumlah master tetap dua (satu fixture dan satu obat baru). | **Lulus** | [JUnit](Bukti_Pengujian/Run_20261018_232819/phpunit-results.xml), `test_master_medicine_create_and_edit` |
| 4 | Penerimaan / Simpan draf dan posting | Otomatis PHPUnit HTTP | Draf tidak mengubah stok; posting menambah stok sekali; posting ulang ditolak. | Draf menghasilkan nol mutasi/nol stok; posting menghasilkan dua mutasi dan stok 50; posting ulang HTTP 422 tanpa mutasi tambahan. | **Lulus** | [JUnit](Bukti_Pengujian/Run_20261018_232819/phpunit-results.xml), `test_receipt_draft_then_post_adds_stock_once` |
| 5 | Transfer / Perpindahan stok dan FEFO | Otomatis PHPUnit HTTP | Transfer 25 mengambil batch kedaluwarsa terdekat dahulu, lalu batch berikutnya. | SIM-EARLY dialokasikan 20 dan SIM-LATE 5; stok Gudang/Apotek masing-masing 25; rekonsiliasi cocok. | **Lulus** | [JUnit](Bukti_Pengujian/Run_20261018_232819/phpunit-results.xml), `test_transfer_allocates_earliest_expiry_first_across_batches` |
| 6 | Pengeluaran / Pengurangan stok dan pencatatan mutasi | Otomatis PHPUnit HTTP | Pengeluaran 22 dari Apotek 25 menyisakan 3 dan tercatat dalam ledger. | Stok Apotek 3; jumlah mutasi SIM-OUT 22; stok total, batch, dan ledger tetap cocok. | **Lulus** | [JUnit](Bukti_Pengujian/Run_20261018_232819/phpunit-results.xml), `test_issue_decreases_stock_and_records_movement` |
| 7 | Kartu stok / Kesesuaian riwayat transaksi | Otomatis PHPUnit HTTP | Kartu Gudang menampilkan penerimaan dan transfer sesuai periode serta stok berjalan. | Empat baris mutasi memuat SIM-FKT/SIM-TRF; stok berjalan terakhir 25; cetak kartu HTTP 200. | **Lulus** | [JUnit](Bukti_Pengujian/Run_20261018_232819/phpunit-results.xml), `test_stock_card_history_matches_posted_transactions` |
| 8 | Pemeriksaan harian / Penghitungan dan review | Otomatis PHPUnit HTTP | Pengiriman hitung fisik tidak langsung mengubah stok; persetujuan apoteker menerapkan hitungan per batch. | Hitungan 48 (18+30) terkirim dengan stok tetap 50; setelah review status approved dan stok 48, rekonsiliasi cocok. | **Lulus** | [JUnit](Bukti_Pengujian/Run_20261018_232819/phpunit-results.xml), `test_daily_count_submission_and_pharmacist_review` |
| 9 | Stock opname / Penghitungan, hitung ulang, dan finalisasi | Otomatis PHPUnit HTTP | Hitung ulang mereset isian snapshot; isian baru dapat difinalisasi; sesi final tidak dapat dihitung ulang. | Isian fisik dihapus saat recount; pengisian ulang berhasil; status finalized; recount sesudah final HTTP 422; rekonsiliasi cocok. | **Lulus** | [JUnit](Bukti_Pengujian/Run_20261018_232819/phpunit-results.xml), `test_opname_count_recount_and_finalize` |
| 10 | Monitoring / Indikator minimum dan kedaluwarsa | Otomatis PHPUnit HTTP | Stok 50 di bawah minimum 60 tampil sesuai lokasi; kategori expired/near/safe dan filter mengikuti tanggal. | Parameter Gudang muncul satu baris, Apotek kosong; expired/near/safe diuji 1/0/1 lalu 0/1/1; filter expired memilih batch yang tepat. | **Lulus** | [JUnit](Bukti_Pengujian/Run_20261018_232819/phpunit-results.xml), `test_monitoring_minimum_and_expiry_indicators_with_location_filter` |

Ringkasan sepuluh kelompok: 10 Lulus; 0 Gagal; 0 Belum Diuji.

## Pengujian Browser Otomatis

Browser merupakan pengujian Playwright terhadap aplikasi HTTP lokal dan backend MySQL simulasi, bukan UAT manual. Tiga menu serupa hanya diuji dropdown-nya, bukan posting transaksinya melalui browser.

| No. | Skenario | Hasil Aktual / Status | Bukti |
| --- | --- | --- | --- |
| 1 | Login tidak valid ditolak | Lulus | [Screenshot](Bukti_Pengujian/Browser_01_Login_Ditolak.png) |
| 2 | Login valid petugas | Lulus | [Log kasus](Bukti_Pengujian/Browser_final.json) |
| 3 | Batch Apotek dipilih dengan label dan native option sesuai lokasi | Lulus | [Screenshot](Bukti_Pengujian/Browser_02_Batch_Apotek.png) |
| 4 | Pergantian lokasi memilih opsi batch yang sesuai | Lulus | [Log kasus](Bukti_Pengujian/Browser_final.json) |
| 5 | Prefill batch Gudang dan Apotek dari tautan monitoring | Lulus | [Screenshot](Bukti_Pengujian/Browser_03_Prefill_Apotek.png) |
| 6 | Validasi SweetAlert tidak mengembalikan label ke Gudang | Lulus | [Screenshot](Bukti_Pengujian/Browser_04_Validasi_Form.png) |
| 7 | Mengubah obat membersihkan batch yang tidak cocok | Lulus | [Log kasus](Bukti_Pengujian/Browser_final.json) |
| 8 | Posting kondisi khusus mengurangi stok Apotek saja | Lulus | [Screenshot](Bukti_Pengujian/Browser_05_Posting_Apotek.png) |
| 9 | Regresi dropdown lintas lokasi pada /transfer | Lulus | [Log kasus](Bukti_Pengujian/Browser_final.json) |
| 10 | Regresi dropdown lintas lokasi pada /obat-keluar | Lulus | [Log kasus](Bukti_Pengujian/Browser_final.json) |
| 11 | Regresi dropdown lintas lokasi pada /retur-pbf | Lulus | [Log kasus](Bukti_Pengujian/Browser_final.json) |
| 12 | Monitoring menampilkan lokasi dan indikator minimum serta kedaluwarsa | Lulus | [Screenshot](Bukti_Pengujian/Browser_06_Monitoring.png) |
| 13 | Guest dapat melihat tetapi tidak dapat mengirim transaksi atau membuka pengguna | Lulus | [Screenshot](Bukti_Pengujian/Browser_07_Akses_Ditolak.png) |

## Pengujian Manual

**Belum Diuji:** UAT oleh petugas/apoteker nyata, kenyamanan penggunaan, cetak fisik printer klinik, serta seluruh variasi perangkat dan browser. Tidak ada klaim pengujian manual telah lulus.

Data simulasi menguji alur terbatas, bukan seluruh kemungkinan transaksi atau sertifikasi keamanan. Tes otomatis tidak menggantikan penerimaan pengguna.
