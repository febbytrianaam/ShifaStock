# Audit Menyeluruh dan Perbaikan Bug ShifaStock

Tanggal: **3 November 2026**, zona waktu Asia/Jakarta. Basis commit: `dd720d5d75001e804309a6374bc3dbbd727f87c2` **ditambah perubahan working tree**, bukan hasil pengujian commit bersih. Perubahan belum di-commit, di-push, atau di-deploy.

## Ringkasan

Audit memperbaiki perlindungan hak akses, sesi setelah reset password, hitungan stok lama, opname tanpa selisih, batas waktu/ketelitian impor Excel, formulir pencocokan besar, monitoring kedaluwarsa, validasi input, dan keamanan teks CSV. Hasil akhir aktual: **260 PHPUnit tests / 2.843 assertions**, **90 kelompok pengujian browser**, dan **11 kelompok concurrency MySQL**, seluruhnya lulus. Build frontend dan audit dependensi juga lulus.

Database klinik/UAT tidak di-reset, dimigrasi, diimpor, atau dibersihkan. `.env` aktif tidak diubah. Tidak ada penghapusan akun, obat, transaksi, stok, atau sesi operasional oleh proses audit. Database sementara yang dibuat runner untuk pengujian dibersihkan oleh runner masing-masing. Recount melalui fitur tetap mengosongkan isian hitung saat pengguna memintanya, dengan nilai sebelumnya di audit log dan identitas baris dipertahankan.

## Temuan dan perbaikan

| Area | Masalah | Perbaikan dan bukti |
| --- | --- | --- |
| Delegasi kelola pengguna | Izin `manage-users` dapat dipakai untuk mencoba membuat/mempromosikan Administrator atau mengubah akun Administrator | Delegasi tetap dapat mengelola akun operasional, tetapi tidak dapat menetapkan role Administrator, mengedit/menonaktifkan Administrator, atau mereset password akun yang sudah ada. Form mengikuti pembatasan backend. `AuthorizationAuditTest`, `AdminUserManagementTest`. |
| Reset password | Sesi yang sudah login belum diperiksa terhadap password terbaru | Aktifkan Laravel AuthenticateSession pada web middleware, rotasi remember token saat password berubah. Sesi lama ditolak pada request berikutnya; reset password sendiri oleh Administrator mempertahankan sesi perangkat saat ini. Pengujian HTTP mencakup kedua kondisi. Tidak menghapus tabel sesi. |
| Permission saat gangguan database | Kegagalan lookup dapat memulihkan izin default yang sebelumnya dicabut | Lookup yang gagal atau tabel permission tidak tersedia menolak izin operasional. Izin administrasi yang memang dikunci tetap mengikuti aturan lama. Baris permission yang belum diatur tetap memakai default provisioning lama; tidak mengubah matrix tersimpan. |
| Tab hak akses | Pengguna dengan `manage-permissions` tanpa `manage-users` tidak dapat membuka matrix | Controller memeriksa izin per tab; sidebar menampilkan Hak Akses untuk pengguna tersebut. Daftar pengguna, mutasi akun, dan audit log tetap membutuhkan izinnya sendiri. |
| Input matrix | Struktur role/permission yang tidak sesuai dapat menghasilkan error server | Validasi setiap role sebagai array dan setiap nilai permission sebagai boolean sebelum sinkronisasi. Input rusak ditolak 422 tanpa mengubah izin. |
| Pemeriksaan harian | `observed_revision` yang tidak dikirim dapat melewati pemeriksaan stok berubah | Revisi stok wajib saat mengirim laporan. Revisi kosong/lama ditolak tanpa menyimpan perubahan; revisi nol sah jika belum ada transaksi. Simpan draf tetap boleh tanpa revisi stok. `DailyRevisionAuditTest`, browser pemeriksaan sebagian/terpilih. |
| Opname tanpa selisih | Finalisasi hitungan yang sama tidak meninggalkan penanda mutasi, sehingga persetujuan laporan lama dapat menimpa hasil tersebut | Catat jejak nol yang terhubung obat, batch, lokasi, dan pelaksana; perbarui `last_counted_at`. Jejak ini tidak menambah/mengurangi stok. Laporan lama ditolak oleh pengaman snapshot yang sudah ada. |
| Formulir opname lama | Form lain dapat menimpa hasil hitung baru atau memulai recount dengan keadaan lama | Versi formulir bertanda HMAC mencakup sesi dan item yang dirender. Save/finalize/recount memeriksanya di dalam transaksi terkunci. Versi hilang, palsu, sesi lain, atau lama ditolak. Nilai lama tidak otomatis ditampilkan sebagai nilai terbaru sesudah konflik. |
| Referensi/sesi opname | Referensi hanya melihat mutasi final; beberapa draft dapat bertabrakan. Pembuatan sesi bersamaan untuk lokasi/periode sama dapat berakhir error database | Nomor juga memperhitungkan referensi sesi yang sudah dipesan. Pemeriksaan lokasi/periode diulang di dalam lock persediaan sehingga permintaan kedua ditolak dengan pesan validasi. Regression HTTP dan concurrency MySQL. |
| Batch stok nol dan recount | Batch habis tidak masuk daftar hitung; recount sebelumnya mengganti baris item | Batch nol tetap dapat dihitung dan dikoreksi. Recount memperbarui baris yang sama dan menyimpan hitungan sebelumnya di audit log. FEFO tidak berubah. |
| Melengkapi satuan awal | Obat tanpa satuan terhalang oleh baris draft pemeriksaan yang sama sekali belum dihitung | Pengisian satuan pertama boleh jika hanya ada draft kosong tanpa jumlah, snapshot, rincian batch bermakna, atau satuan. Hitungan nol yang tercatat, laporan terkirim, dan seluruh riwayat stok tetap mengunci satuan. Penggantian satuan stok yang sudah digunakan tetap dilarang. |
| Waktu impor Excel | Pecahan detik serial Excel dapat berada beberapa mikrodetik setelah batas hitung fisik | Normalisasi ke detik terdekat untuk perbandingan batas waktu, fingerprint baru, dan penyimpanan. Batas hitung fisik tetap ditegakkan. Ekspor persis sama dengan fingerprint lama tetap dikenali tanpa menulis ulang ledger. |
| Ketelitian jumlah impor | Toleransi tetap dapat menerima jumlah lebih dari empat desimal yang berubah setelah konversi besar | Toleransi hanya untuk noise representasi floating-point relatif. Jumlah dengan ketelitian tambahan ditolak sebelum pembulatan/konversi. Nilai empat desimal dan noise seperti `0.1 + 0.2` tetap diterima. |
| Pencocokan impor besar | Ratusan baris form dapat terpotong oleh `max_input_vars`; baris yang belum disetujui dapat menghalangi pilihan yang benar | Kirim hanya pilihan yang dicentang melalui satu payload JSON berbatas ukuran dan jumlah. Backend memvalidasi seluruh pilihan tersebut sebelum menulis. Baris tidak dicentang tidak menghalangi penyimpanan. Browser nyata membuktikan **502 pilihan** tersimpan lengkap, tanpa posting stok. |
| Monitoring kedaluwarsa | Batch kosong ikut dihitung, total/lokasi yang tampil tidak mengikuti filter, dan badge memakai pendekatan 31 hari per bulan | Query dan eager loading memakai saldo positif di lokasi yang dipilih. Badge, filter, dan hitungan memakai satu batas kalender bulan yang sama. `AuditRegressionTest`. |
| Input transaksi/filter | Array pada ID atau baris scalar dapat mencapai query/normalisasi dan menghasilkan 500 | Validasi ID scalar integer dan bentuk baris sebelum query/perhitungan pada endpoint terkait. Monitoring minimum memvalidasi semua identitas obat/lokasi sebelum melakukan penulisan. |
| Ekspor CSV | Teks nama/kode/lokasi/satuan yang diawali karakter formula dapat dibaca sebagai formula spreadsheet | Awali teks berisiko dengan apostrof saat ekspor. Kolom kuantitas tetap numerik dan data master tidak diubah. |
| Dependensi | Registry audit melaporkan satu advisory Symfony Mime dan satu advisory follow-redirects | Patch `symfony/mime` **8.1.7 → 8.1.10**, `follow-redirects` **1.16.1 → 1.16.2**. Composer/npm audit ulang: tidak ada advisory yang terlapor. Tidak menyatakan pernah terjadi kebocoran. |

## Validasi aktual

Runtime lokal: PHP **8.5.9**, Laravel **13.35.0**, Chrome melalui Playwright. PHPUnit memakai SQLite `:memory:`. Pengujian MySQL/browser memakai nama database acak dengan guard environment dan data simulasi. Tidak mengakses domain klinik untuk melakukan transaksi.

| Pemeriksaan | Hasil aktual | Sumber bukti lokal |
| --- | --- | --- |
| Suite awal sebelum audit | 214 tests / 2.352 assertions lulus | Eksekusi runner pada awal audit; angka historis baseline, bukan hasil akhir |
| Reproduksi monitoring/validasi | 12 tests, 9 gagal; tiga filter tanggal sudah lulus sebelum perubahan | `storage/app/qa/inventory/audit-before-2026-11-03.xml` |
| Regresi monitoring/validasi sesudah perbaikan | 12 tests / 32 assertions lulus | `storage/app/qa/inventory/audit-after-2026-11-03.xml` |
| PHPUnit akhir | **260 tests / 2.843 assertions, 0 gagal/error/skipped** | `php scripts/test-inventory.php`; `storage/app/qa/inventory/phpunit-results.xml` |
| MySQL concurrency | **11 kelompok lulus**: FEFO, reversal, finalisasi opname, pembuatan sesi sama, draft bersama, approval harian, referensi duplikat, satuan vs penerimaan pertama, dua penerimaan pertama, rollback transfer multi-item, referensi transfer multi-item | `php scripts/test-inventory-mysql.php`; `storage/app/qa/inventory/mysql-audit-2026-11-03.log` |
| Browser operasional | **17/17 lulus** | `php scripts/capstone-final-browser.php regression`; `storage/app/qa/inventory-browser/Browser_final.json` |
| Browser impor + MySQL impor | **17/17 lulus**, termasuk 502 pilihan; posting/duplikat/race mempertahankan stok dan total batch **80**, tiga transaksi sumber unik | `php scripts/test-issue-import.php`; `storage/app/qa/issue-imports/58756c5cd4c0aacd/{Playwright,MySQL}.json` |
| Browser onboarding | **14/14 lulus**, termasuk simulasi kegagalan jaringan/status dan retry | `php scripts/capstone-final-browser.php onboarding`; `storage/app/qa/onboarding/results.json` |
| Browser workspace pemeriksaan | **20/20 lulus**, 167 obat, responsif, kolaborasi dan konflik dua akun | `php scripts/capstone-final-browser.php nightly-ui`; `storage/app/qa/nightly-workspace-browser/results.json` |
| Browser pemeriksaan sebagian | **7/7 lulus**, termasuk submit dan approval dengan pengaman revisi | `php scripts/capstone-final-browser.php partial-nightly`; `storage/app/qa/partial-nightly-browser/results.json` |
| Browser obat terpilih | **15/15 lulus**, termasuk tambah obat, submit/approval, dan PDF A4 70 baris | `php scripts/capstone-final-browser.php selected-nightly`; `storage/app/qa/selected-nightly-browser/results.json` |
| Frontend | `npm run build` lulus | Build aktual sesi audit |
| Dependensi/konfigurasi paket | Composer validate lulus; Composer audit 0 advisory; npm audit 0 vulnerability | Lockfile setelah patch dan audit registry aktual |
| Pemeriksaan statis | Sintaks PHP berkas berubah, JS yang diubah, dan `git diff --check` lulus | Eksekusi lokal; tidak menjalankan formatter seluruh repository |

Ringkasan terstruktur dengan nama skenario/status dan hash sumber tersedia di [verification.json](verification.json). Log lengkap dan gambar QA berada di `storage/app/qa/` yang diabaikan Git; dokumen ini tidak menyalin kredensial, hash password akun, APP_KEY, maupun workbook klinik. Berkas screenshot/dokumentasi Capstone lama yang sudah berubah sebelum audit tidak ditimpa oleh fase runner yang dipilih.

## Berkas utama

- `app/Http/Controllers/{Admin/UserController,InventoryController,IssueImportController,NightlyCheckController}.php`
- `app/Services/{OpnameSnapshotService,StockUnitGuard,NeoklinikIssueReader,IssueImportService}.php`
- `app/Support/Permissions.php`, `bootstrap/app.php`, `routes/web.php`
- Form pengguna, matrix/tab pengguna, layout navigasi, form opname, monitoring kedaluwarsa, dan pencocokan impor di `resources/views/`.
- `resources/js/issue-imports.js`, `composer.lock`, `package-lock.json`.
- Regresi baru: `AuditRegressionTest`, `AuthorizationAuditTest`, `DailyRevisionAuditTest`, `OpnameAuditRegressionTest`; perluasan `IssueImportTest` dan `StockUnitProtectionTest`.
- Pengujian alur lama sekarang mengambil hidden revision/version dari halaman yang benar-benar dirender, bukan menyuntikkan token diam-diam pada semua request. Runner MySQL memakai versi snapshot yang sama untuk kedua worker yang bersaing.

## Batas dan tindak lanjut

1. **Bukan UAT manual atau sertifikasi data klinik.** Hasil ini membuktikan skenario otomatis yang dijalankan, bukan jaminan tidak ada bug lain.
2. **VDI/domain belum diperbarui oleh audit ini.** Perubahan kode/aset/rute lokal perlu dipublikasikan dan diverifikasi pada lingkungan tujuan setelah instruksi berikutnya. Tidak mengubah DB target atau menonaktifkan tanda UAT otomatis.
3. Halaman pemeriksaan/opname yang sudah terbuka sebelum pembaruan harus dimuat ulang sebelum dikirim agar memiliki revisi baru. Pesan validasi memberi arahan; data tidak dipaksa masuk.
4. Fingerprint lama hanya ditoleransi untuk ekspor ulang yang persis cocok. Perubahan serial waktu mentah pada ledger lama dapat tetap diblokir untuk tinjauan; ledger lama tidak ditulis ulang atau dianggap duplikat secara longgar.
5. Sesi login standar memiliki penanda hash password dari SessionGuard. Sesi nonstandar/legacy tanpa penanda akan diberi penanda saat request pertamanya; tidak dilakukan penghapusan sesi massal.
6. Belum dilakukan pengujian beban banyak pengguna, penetration test independen, restore backup, atau migrasi operasional. Periode ekspor, kecocokan produk, satuan, dan keputusan klinik tetap memerlukan verifikasi petugas.
7. Perubahan onboarding dan file `.env.uat` privat yang sudah ada merupakan pekerjaan sebelumnya. `.env.uat` tetap diabaikan Git, tidak menggantikan `.env` aktif; perbaikan domain terdahulu yang dilaporkan pengguna tetap tercatat sebagai selesai setelah hard refresh, bukan hasil deployment audit ini.

Referensi advisory: [Symfony CVE-2026-45070](https://symfony.com/cve-2026-45070), [follow-redirects GHSA-mrwp-f3q8-3g9p](https://github.com/advisories/GHSA-mrwp-f3q8-3g9p). Hasil registry bersih berlaku pada waktu pengujian, bukan jaminan bahwa tidak akan ada advisory baru.
