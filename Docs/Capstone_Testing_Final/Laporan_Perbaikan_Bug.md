# Laporan Perbaikan Bug

- Commit dasar: `edf1faa8e51f97927b1f78337c69b230b58be2d6`.
- Yang diuji: commit dasar **ditambah perubahan working tree yang belum di-commit**, bukan commit bersih. SHA-256 source dicatat di [metadata](Bukti_Pengujian/Run_20261018_232819/metadata.json).
- Waktu eksekusi otomatis: 2026-10-18T23:28:19+07:00 sampai 2026-10-18T23:31:40+07:00.
- Waktu browser: 18/10/2026 23:39:29 WIB sampai 18/10/2026 23:40:19 WIB.
- Environment: Windows, PHP 8.5.9, Laravel 13.35.0; PHPUnit pada SQLite `:memory:`; konkurensi dan browser pada database MySQL acak terpisah; browser Chrome/Playwright, viewport 1440 x 900.
- Seluruh data adalah **simulasi**, bukan data operasional klinik. Tidak ada deployment atau push remote.

## F.3: Temuan Dan Penyebab

Kondisi Khusus menampilkan label Gudang ketika lokasi Apotek dipilih dan satu batch tersedia di dua lokasi. Query backend memang memuat saldo kedua lokasi agar form dapat berganti lokasi tanpa reload. Setiap opsi memiliki `batch_id` sama, tetapi metadata dependency dan label lokasinya berbeda.

Komponen searchable select sudah memfilter daftar menu menurut obat/lokasi. Kesalahannya terjadi setelah klik: penetapan `select.value = option.value` memilih opsi native pertama dengan ID yang sama, bukan objek opsi yang diklik. Masalah serupa ada pada pencocokan teks saat submit. Saat prefill atau lokasi berubah, kode lama menghapus pilihan yang tidak cocok tanpa mencari opsi saudara untuk lokasi baru.

[Bukti sebelum perbaikan](Bukti_Pengujian/Bug_Sebelum_Perbaikan.png) menunjukkan Lokasi Apotek, label batch Gudang 50 Tablet, dan konteks stok Apotek 20 Tablet. Status Lulus pada kasus reproduksi di [log baseline](Bukti_Pengujian/Browser_baseline.json) berarti bug berhasil direproduksi, **bukan fitur dinyatakan benar**.

## Solusi Dan Batas Perubahan

- `resources/js/app.js`: klik opsi dan pencocokan saat submit memakai `selectedIndex` opsi yang benar, bukan pencarian berdasarkan value yang ambigu.
- Pergantian dependency mencari opsi dengan ID sama yang cocok dengan lokasi/obat/arah saat ini; jika tidak ada, pilihan dikosongkan.
- `aria-selected` mengikuti identitas opsi terpilih. Payload tetap mengirim ID batch asli.
- Tidak mengubah query controller, service stok, FEFO, rumus kuantitas, route, permission, atau kebijakan lokasi.

## Regression Test

| Metode | Kasus | Status / Bukti |
| --- | --- | --- |
| PHPUnit HTTP + database | Batch yang sama ada di Gudang dan Apotek; penyesuaian Apotek hanya mengurangi batch Apotek dan ledger sesuai lokasi. | Lulus; `test_shared_batch_adjustment_changes_only_selected_location` di [JUnit](Bukti_Pengujian/Run_20261018_232819/phpunit-results.xml) |
| PHPUnit HTTP + database | Batch yang tidak tersedia pada lokasi terpilih ditolak tanpa perubahan mutasi. | Lulus; `test_batch_unavailable_at_selected_location_is_rejected` di [JUnit](Bukti_Pengujian/Run_20261018_232819/phpunit-results.xml) |
| Playwright | Pilihan batch, pergantian lokasi, prefill, validasi, pergantian obat, dan posting; regresi dropdown transfer/pengeluaran/retur. | Lihat status tiap kasus di [log browser](Bukti_Pengujian/Browser_final.json) |
| Backend pascabrowser | Gudang tetap 50, Apotek 20 menjadi 18; saldo total/batch/ledger cocok. | [Rekonsiliasi aktual](Bukti_Pengujian/Browser_Rekonsiliasi.json) |

## File Yang Berubah

- Source aplikasi: `resources/js/app.js`.
- Tes: `tests/Feature/CapstoneFunctionalTest.php` (sepuluh kelompok fungsional dan dua regresi backend).
- Runner: `scripts/capstone-final-tests.php`, `scripts/capstone-final-browser.php`, `scripts/capstone-final-browser.cjs`, `scripts/capstone-final-docs.php`, `scripts/capstone-final-verify.cjs`.
- Dokumentasi dan bukti: `Docs/Capstone_Testing_Final/`. Tambahan screenshot terdahulu tetap terpisah.

## Kendala Otomasi

Percobaan awal selector Playwright juga mencocokkan option native yang tersembunyi sehingga timeout. Locator diperjelas ke menu searchable select, tanpa mengubah halaman atau isi screenshot. [Log kendala awal](Bukti_Pengujian/Browser_Kendala_Selector.json) dipertahankan. Percobaan browser berikutnya mencari meta CSRF yang tidak ada pada layout; selector disesuaikan ke input token form logout asli. [Log kendala CSRF](Bukti_Pengujian/Browser_Kendala_CSRF.json) dipertahankan dan tidak digabungkan sebagai hasil final. Keduanya kendala script, bukan kegagalan produk. Browser diulang lengkap setelah perbaikan selector; source aplikasi/tes PHPUnit tidak berubah. SHA-256 script versi akhir dicatat di Verifikasi_Akhir.json, sementara metadata eksekusi PHPUnit menyimpan snapshot script pada waktu run tersebut.
