# Ringkasan Pengujian

- Commit dasar: `edf1faa8e51f97927b1f78337c69b230b58be2d6`.
- Yang diuji: commit dasar **ditambah perubahan working tree yang belum di-commit**, bukan commit bersih. SHA-256 source dicatat di [metadata](Bukti_Pengujian/Run_20261018_232819/metadata.json).
- Waktu eksekusi otomatis: 2026-10-18T23:28:19+07:00 sampai 2026-10-18T23:31:40+07:00.
- Waktu browser: 18/10/2026 23:39:29 WIB sampai 18/10/2026 23:40:19 WIB.
- Environment: Windows, PHP 8.5.9, Laravel 13.35.0; PHPUnit pada SQLite `:memory:`; konkurensi dan browser pada database MySQL acak terpisah; browser Chrome/Playwright, viewport 1440 x 900.
- Seluruh data adalah **simulasi**, bukan data operasional klinik. Tidak ada deployment atau push remote.

## F.2: Implementasi Dan Environment

Perbaikan dibatasi pada identitas opsi dropdown batch. Sepuluh kelompok fungsional diuji melalui HTTP kernel/Livewire dengan SQLite in-memory; transaksi bersamaan diuji pada MySQL sementara; bug UI diuji melalui browser sungguhan terhadap HTTP lokal. Source aplikasi yang berubah hanya `resources/js/app.js`.

## Hasil Eksekusi Terbaru

| Pengujian | Hasil Aktual | Bukti |
| --- | --- | --- |
| PHPUnit | 70 tes, 733 assertion; 0 failure, 0 error, 0 skipped; exit 0 | [Log](Bukti_Pengujian/Run_20261018_232819/01_PHPUnit_SQLite.txt), [JUnit](Bukti_Pengujian/Run_20261018_232819/phpunit-results.xml) |
| Konkurensi MySQL | 7 skenario PASS; exit 0 | [Log](Bukti_Pengujian/Run_20261018_232819/02_Konkurensi_MySQL.txt) |
| Frontend Vite | Build exit 0 | [Log](Bukti_Pengujian/Run_20261018_232819/03_Frontend_Build.txt) |
| Browser Playwright final | 13 Lulus, 0 Gagal dari 13 kasus | [Log](Bukti_Pengujian/Browser_final.json) |
| Rekonsiliasi setelah browser | [Hasil aktual](Bukti_Pengujian/Browser_Rekonsiliasi.json), bukan angka historis | [JSON](Bukti_Pengujian/Browser_Rekonsiliasi.json) |
| UAT manual petugas | Belum Diuji | Tidak ada klaim penerimaan pengguna |

### Skenario MySQL Yang Dieksekusi

- PASS concurrent FEFO issues: one accepted, no overselling.
- PASS concurrent reversal: applied once.
- PASS concurrent opname finalization: applied once.
- PASS concurrent daily approval and MySQL JSON snapshot round-trip.
- PASS concurrent duplicate reference: posted once.
- PASS unit edit versus first receipt: serialized, no historical relabeling.
- PASS concurrent first receipts: one balance row, both additions retained.

## F.4: Evaluasi Dan Batas Pembuktian

Angka di atas berasal dari eksekusi terbaru yang lognya disertakan, bukan hasil 58 tes pada dokumentasi sebelumnya. Sepuluh kelompok skenario dan hasil aktualnya tersedia di [tabel fungsional](Hasil_Pengujian_Fungsional.md). Bukti penyebab, perubahan, dan pengujian ulang tersedia di [laporan bug](Laporan_Perbaikan_Bug.md).

Tidak seluruh sepuluh alur dijalankan melalui browser: master, draf, FEFO, kartu stok, review harian, dan finalisasi/recount dibuktikan terutama melalui PHPUnit HTTP/Livewire. Playwright fokus pada label lintas lokasi, validasi, posting kondisi khusus, monitoring dan akses guest. UAT manual, seluruh kasus produksi, lintas perangkat, serta audit keamanan menyeluruh belum dinyatakan selesai.

Tidak ada data pasien, kredensial, token sesi, atau isi konfigurasi rahasia yang disertakan. Screenshot asli 1440 x 900, tanpa manipulasi DOM/response untuk mengubah hasil. Database uji dibuat terpisah dan dihapus setelah selesai; server browser dibatasi ke loopback dan dihentikan oleh runner. Lihat [metadata browser](Bukti_Pengujian/Browser_final.json) dan keluaran cleanup pada log.

### Verifikasi Bukti

[Verifikasi akhir](Bukti_Pengujian/Verifikasi_Akhir.json) mencatat dimensi, isi nonkosong, hash PNG, konsistensi source aplikasi/tes, tautan lokal, dan pemeriksaan pola rahasia. Gambar akses ditolak adalah HTTP 403 yang memang diharapkan, bukan screenshot keberhasilan fitur. Screenshot SweetAlert diambil setelah animasi selesai; seluruh gambar ditinjau visual untuk keterbacaan dan data sensitif. Peninjauan gambar oleh asisten bukan UAT manual petugas.

## Pengambilan Ulang

1. Jalankan `php scripts/capstone-final-tests.php` untuk PHPUnit, konkurensi MySQL, dan build.
2. Pastikan Playwright tersedia pada resolusi modul Node, lalu jalankan `php scripts/capstone-final-browser.php final`.
3. Jalankan `php scripts/capstone-final-docs.php` untuk memperbarui tabel dari bukti terbaru, lalu `node scripts/capstone-final-verify.cjs` untuk memeriksa bukti dan metadata.

Mode `baseline` hanya untuk mereproduksi bug pada source sebelum perbaikan, bukan untuk menyatakan aplikasi lulus. Jangan menjalankannya pada source sesudah perbaikan lalu mengharapkan bug tetap muncul. Tidak ada langkah deployment atau push otomatis.
