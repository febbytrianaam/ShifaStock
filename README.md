# ShifaStock

**Pengelolaan persediaan obat Klinik Shifa Medika.**

ShifaStock membantu pencatatan obat masuk, transfer gudang ke apotek, pengeluaran, pemeriksaan fisik, dan tindak lanjut stok. Persediaan dapat ditelusuri per obat, batch, tanggal kedaluwarsa, dan lokasi melalui kartu stok serta laporan.

Aplikasi berfokus pada pengelolaan obat, bukan pelayanan pasien atau integrasi Neoklinik. Dashboard dan transaksi operasional menggunakan database aplikasi, tanpa mode data dummy. Panduan sorotan menu muncul pada login pertama, mengikuti hak akses, dan dapat dibuka ulang dari tombol tanda tanya di topbar.

## ✨ Fitur Utama

| Area | Fitur |
| --- | --- |
| Master obat | Pencarian, verifikasi, status aktif, preview/import referensi, satuan stok, dan konversi kemasan beli per obat |
| Penerimaan | Input banyak baris, pilihan obat searchable, nomor faktur supplier, konversi satuan, edit/batalkan draf, posting ke lokasi tujuan, dan peringatan faktur ganda |
| Pergerakan stok | Stok awal per batch, transfer banyak obat dalam satu referensi dengan penyimpanan atomik, pengeluaran, retur PBF, serta kondisi khusus dengan batch wajib |
| Pemilihan batch | Mengikuti obat dan lokasi; FEFO otomatis untuk transfer/pengeluaran tanpa pilihan batch eksplisit, dengan pengecualian batch kedaluwarsa |
| Pemeriksaan harian | Daftar responsif dengan pencarian/pagination dan progres, rincian batch/permintaan/catatan, tambah obat ke draf, serta kirim sebagian dengan konfirmasi; tinjauan apoteker hanya menyesuaikan obat yang sudah diperiksa |
| Opname periodik | Snapshot stok, isian fisik, finalisasi, dan Mulai Hitung Ulang dengan konfirmasi serta pencatatan isian lama di audit log |
| Monitoring | Stok kosong/menipis, parameter minimum stok, kedaluwarsa beserta lokasi, dan pintasan tindak lanjut |
| Pelaporan | Kartu stok per obat/lokasi/batch/periode, cetak dengan identitas klinik, laporan persediaan, dan riwayat pemeriksaan |
| Administrasi | Pengguna, matriks RBAC, referensi lokasi/satuan, pengaturan sistem, audit log, dan ekspor audit |

Pencarian obat dan batch terhubung ke backend. Validasi dan konfirmasi tindakan penting menggunakan SweetAlert.

## Role Dan Hak Akses

| Role | Username Demo Lokal | Tanggung Jawab Default |
| --- | --- | --- |
| Administrator | `admin` | Pengguna, hak akses, referensi sistem, pengaturan, dan audit log |
| Petugas Farmasi | `petugas` | Pencatatan dan pemantauan stok operasional |
| Kepala Apoteker | `kepala` | Master obat, review/finalisasi pemeriksaan, dan pengawasan persediaan |
| Guest | `guest` | Seluruh menu persediaan gabungan petugas/apoteker, hanya baca, tanpa menu superadmin |

Hak akses diatur melalui **Administrasi > Pengguna > Hak Akses**. Matriks mengatur menu serta tindakan backend; beberapa izin inti administrator dikunci untuk mencegah kehilangan akses administrasi.

Pengelolaan password dilakukan melalui pengguna yang berwenang mengelola akun. Daftar password akun tidak dipublikasikan di README, halaman login, atau output seeder. Kolom password pada form login tersembunyi secara default dan memiliki ikon mata untuk menampilkan atau menyembunyikan isian sendiri.

Halaman 403, 404, dan 500 menggunakan identitas klinik dan petunjuk pemulihan berbahasa Indonesia. Halaman 500 mengingatkan pengguna memeriksa riwayat sebelum mengulang transaksi. Detail teknis error tidak ditampilkan saat `APP_DEBUG=false`.

## Pengamanan Stok

- Stok total, stok batch, dan mutasi diperbarui dalam transaksi database. Perubahan yang gagal dibatalkan bersama.
- Transaksi stok memakai pengunci database bersama sebelum membaca/mengubah stok, termasuk pemilihan FEFO dan snapshot.
- Referensi transaksi diperiksa ulang setelah penguncian. Reversal dan persetujuan berulang tidak boleh menerapkan perubahan dua kali.
- Kondisi khusus wajib menyebut batch; jumlah stok mengikuti presisi database empat desimal.
- Pemeriksaan menolak snapshot yang berubah, termasuk transaksi dengan hasil akhir stok tetap sama. Draf opname yang tidak sesuai dapat dimulai ulang melalui **Mulai Hitung Ulang**.
- Satuan stok obat tidak dapat diganti/dikosongkan setelah memiliki stok, draf terkait, atau riwayat. Nama referensi satuan yang sudah dipakai juga dilindungi; kemasan pembelian tetap dapat diatur.
- Pembatalan mutasi final memakai reversal berizin dan beralasan, bukan menghapus riwayat.

Peringatan faktur supplier ganda tetap memerlukan pemeriksaan petugas. Dua referensi berbeda dengan faktur fisik yang sama tidak otomatis dianggap satu transaksi. Sistem tidak menebak batch atau mengubah data historis untuk menutupi selisih stok.

## Stack Dan Prasyarat

- Laravel 13, Livewire 3, dan MySQL.
- Tailwind CSS 4, Vite 7, Chart.js, dan SweetAlert2.
- Composer serta Node.js yang memenuhi persyaratan Vite pada `package-lock.json`.
- **PHP 8.4.1 atau lebih baru untuk dependency yang terkunci saat ini.** Walaupun `composer.json` mencantumkan `^8.3`, sejumlah dependency di `composer.lock` membutuhkan versi lebih tinggi. Pengujian lokal terakhir menggunakan PHP 8.5.9.
- Ekstensi PHP yang dibutuhkan Composer, termasuk `pdo_mysql`; pengujian terisolasi juga membutuhkan `pdo_sqlite`.

## 🚀 Instalasi Lokal

```bash
git clone https://github.com/febbytrianaam/ShifaStock.git
cd ShifaStock
composer install
npm ci
```

Salin `.env.example` menjadi `.env`. Untuk PowerShell:

```powershell
Copy-Item .env.example .env
```

Untuk Bash, gunakan `cp .env.example .env`. Buat database MySQL kosong, lalu sesuaikan `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, dan `DB_PASSWORD` di `.env`.

```bash
php artisan key:generate
php artisan migrate --seed
```

Seeder default mengisi referensi satuan serta lokasi Gudang dan Apotek. Akun demo lokal dibuat terpisah:

```bash
php artisan db:seed --class=LocalDemoSeeder
```

`LocalDemoSeeder` hanya berjalan pada `APP_ENV=local` dan meminta password melalui input tersembunyi. Alternatifnya, atur `LOCAL_DEMO_PASSWORD` di `.env` lokal. Menjalankannya lagi memperbarui akun demo, termasuk passwordnya; jangan gunakan untuk provisioning produksi.

```bash
npm run build
php artisan serve --host=127.0.0.1 --port=8000
```

Buka [ShifaStock lokal](http://127.0.0.1:8000). Untuk pengembangan frontend, jalankan `npm run dev` di terminal terpisah dari server PHP. Laragon juga dapat digunakan dengan document root mengarah ke direktori `public/`.

Master obat dapat dimasukkan melalui menu Data Obat, termasuk preview/import referensi yang disediakan. Seed referensi tidak berarti stok fisik sudah tersedia; catat stok awal atau penerimaan sesuai batch dan lokasi sebenarnya.

## Pengujian

**Antarmuka pemeriksaan harian, 22 Oktober 2026:** daftar ringkas dengan Rincian, progres dan status simpan, tab tinjauan/ringkasan, serta layout desktop/tablet/HP. Tabel mengikuti lebar halaman tanpa batas 1360 px; pengujian UI mencakup Full HD, QHD, dan 4K. Pengujian ulang sebelum publikasi lulus **157 tes / 1621 assertions** dan **35 kasus browser** (12 UI, 7 kirim sebagian, 16 operasional), beserta build frontend. Tidak ada perubahan aturan stok atau hak akses. Hasil aktual tiap tahap dan batasan pengujian tercatat pada [laporan implementasi](Docs/30_implemented/2026-10-22-daily-check-workspace/implementation_report.md).

**Pemeriksaan sebagian, 22 Oktober 2026:** **157 tes / 1621 assertions**, **7 tes browser pemeriksaan sebagian**, **15 regresi harian**, dan **16 regresi operasional** lulus. Obat yang belum diperiksa tetap kosong pada isian, tidak dianggap stok 0, dan tidak disesuaikan saat persetujuan. Peringatan dikelompokkan dan mode lihat saja menjelaskan akun pembuat/status laporan. Semua transaksi uji memakai database sementara. [Laporan implementasi](Docs/30_implemented/2026-10-22-partial-daily-submission/implementation_report.md).

**Audit widget dashboard, 22 Oktober 2026:** seluruh widget cocok dengan query pembanding read-only pada database UAT. Pengujian fokus **25 tes / 254 assertions** lulus. Tabel stok dashboard adalah pratinjau maksimal 12 baris; pencariannya bukan pencarian seluruh inventori. [Rincian audit](Docs/30_implemented/2026-10-22-dashboard-stock-monitoring/implementation_report.md#follow-up-audit-of-other-widgets).

**Monitoring stok dashboard, 22 Oktober 2026:** pengujian ulang **147 tes / 1533 assertions** dan **16 pemeriksaan browser** lulus. Widget kosong/menipis kini memakai stok transaksi dan batas minimum yang sama dengan halaman Monitoring, tanpa menunggu pemeriksaan harian disetujui. Pengujian transaksi memakai database simulasi terpisah. [Laporan implementasi](Docs/30_implemented/2026-10-22-dashboard-stock-monitoring/implementation_report.md).

**Laporan cetak pemeriksaan, 22 Oktober 2026:** pengujian tahap ini **140 tes / 1450 assertions** dan **15 pemeriksaan browser harian** lulus. PDF **A4 portrait** diuji langsung: laporan singkat satu halaman, laporan 70 obat delapan halaman, seluruh baris serta judul tabel terverifikasi dan semua hasil render diperiksa. Pengujian memakai database simulasi terpisah. [Laporan implementasi](Docs/30_implemented/2026-10-21-daily-check-print-report/implementation_report.md).

**Tambah obat pada draf, 21 Oktober 2026:** pengujian tahap ini **133 tes / 1392 assertions** dan **12 pemeriksaan browser harian** lulus, termasuk penambahan sambil mempertahankan jumlah, satuan, rincian batch, permintaan, dan catatan. Build frontend, kompilasi Blade, serta isolasi UAT diverifikasi. [Laporan](Docs/30_implemented/2026-10-21-add-medicines-to-draft/implementation_report.md).

**Pemeriksaan obat tertentu dan dropdown satuan, 21 Oktober 2026:** suite terbaru 124 tes / 1306 assertions lulus. Verifikasi fitur mencakup 8 pemeriksaan browser harian, 13 regresi browser operasional, dan 9 skenario MySQL bersamaan. Build frontend, kompilasi Blade, dan verifikasi pemisahan UAT berhasil. Semua fixture stok memakai database sementara. [Laporan implementasi](Docs/30_implemented/2026-10-21-selected-daily-check/implementation_report.md).

**Transfer banyak obat, 21 Oktober 2026:** 112 tes / 1189 assertions, 9 skenario MySQL bersamaan, 9 pemeriksaan browser transfer, dan 13 regresi browser operasional lulus. Build frontend serta kompilasi Blade berhasil. Semua transaksi pengujian memakai database sementara, bukan database klinik atau UAT persisten.

**Pembaruan domain UAT 21 Oktober 2026:** 100 tes / 1001 assertion lulus. Delapan pemeriksaan browser lewat domain HTTPS juga lulus, termasuk login Guest, dashboard transaksi kosong, pembatasan admin, dan logout. Database klinik tetap tidak berubah.

**Eksekusi antarmuka sebelumnya, 21 Oktober 2026:** 97 tes / 986 assertion lulus pada SQLite terisolasi. Pengujian browser tambahan lulus untuk posisi panduan (9 pemeriksaan), halaman error (7 pemeriksaan), dan tombol mata login (3 pemeriksaan), serta build frontend berhasil. Hasil tiap tahap dan lokasi bukti lokal dicatat pada [strategi pengujian](Docs/00_current/testing_strategy.md). Pengujian ini tidak menggantikan UAT manual petugas.

**Eksekusi UAT/Guest 21 Oktober 2026:** 90 tes / 931 assertion, 7 skenario MySQL paralel, 25 pemeriksaan browser UAT, dan build lulus. Database serta pengguna MySQL UAT sudah diverifikasi terpisah dari inventori klinik. [Laporan UAT dan Guest](Docs/30_implemented/2026-10-21-uat-guest/implementation_report.md) memuat hasil browser terbaru dan batasannya.

**Eksekusi dashboard/panduan sebelumnya pada 21 Oktober 2026:** 81 tes / 795 assertion, 7 skenario MySQL, 13 kasus browser operasional, 8 pemeriksaan panduan, dan build frontend lulus pada database uji terpisah. [Laporan dashboard dan panduan](Docs/30_implemented/2026-10-21-dashboard-onboarding/implementation_report.md) mencatat cakupan serta batasannya; UAT petugas tetap belum dilakukan.

### Regresi Terisolasi

```bash
php scripts/test-inventory.php
```

Runner memakai SQLite `:memory:`, melewati cache konfigurasi lokal, dan tidak memakai database klinik. Jika ekstensi tersedia tetapi belum aktif, runner mencoba memuat `pdo_sqlite` untuk proses pengujian saja. Laporan JUnit tersimpan di `storage/app/qa/inventory/phpunit-results.xml` dan diabaikan Git.

Untuk regresi browser antarmuka pemeriksaan harian, jalankan `php scripts/capstone-final-browser.php nightly-ui` setelah build frontend. Runner memerlukan Playwright/Chrome dan izin membuat/menghapus database MySQL sementara. Data simulasi 167 obat, akun, dan password dibuat untuk proses pengujian; bukan akun klinik/UAT persisten. Hasil JSON dan screenshot lokal disimpan di `storage/app/qa/nightly-workspace-browser/` dan diabaikan Git.

Untuk komputer lambat, timeout dapat dinaikkan. Contoh PowerShell:

```powershell
$env:INVENTORY_TEST_TIMEOUT = '900'
php scripts/test-inventory.php
```

### Transaksi Bersamaan Di MySQL

```bash
php scripts/test-inventory-mysql.php
```

Runner membuat database sementara bernama acak `shifastock_qa_*`, menjalankan migrasi dan worker terpisah di sana, lalu menghapus database uji tersebut. Akun MySQL perlu izin membuat/menghapus database. Migrasi dan penulisan pengujian tidak dilakukan pada database klinik.

Tujuh skenario mencakup pengeluaran FEFO bersamaan, reversal berulang, finalisasi opname berulang, persetujuan harian berulang, referensi ganda, perubahan satuan saat penerimaan pertama, dan dua penerimaan ke stok yang belum mempunyai baris persediaan.

### Audit Stok Baca-Saja

```bash
php artisan inventory:reconcile
php artisan inventory:reconcile --json
```

Perintah ini membaca **database yang sedang dikonfigurasi aplikasi**, membandingkan stok total, jumlah stok batch, dan mutasi bersih, tanpa mengubah stok. Exit `0` berarti tidak ditemukan selisih; exit `1` berarti ada selisih yang perlu diperiksa. Database kosong bukan bukti bahwa data operasional sudah benar.

### Antarmuka Dan Build

```bash
npm run build
php artisan view:cache
php scripts/verify-inventory-usability.php --fixtures
```

Dengan Playwright yang tersedia melalui resolusi modul Node.js:

```bash
node tests/browser-daily-batches.cjs
node tests/browser-opname-recount.cjs
```

Tes browser tersebut memakai fixture lokal dan mencegat permintaan tulis agar tidak mengubah stok klinik. Petunjuk lengkap ada di [strategi pengujian](Docs/00_current/testing_strategy.md).

**Hasil eksekusi final 18 Oktober 2026:** 70 tes / 733 assertion lulus, tujuh skenario MySQL bersamaan lulus, 13 kasus browser Playwright pada HTTP lokal lulus, dan build frontend berhasil. [Tabel fungsional dan bukti aktual](Docs/Capstone_Testing_Final/Hasil_Pengujian_Fungsional.md) membedakan pengujian otomatis, browser, dan UAT manual yang belum dilakukan. [Paket screenshot awal](Docs/Capstone_Screenshots/README.md) tetap memuat hasil historis 58 tes / 610 assertion. Pembaruan dokumentasi 19 Oktober tidak dianggap eksekusi ulang. Cakupan ini bukan jaminan seluruh jadwal transaksi, beban produksi, atau keamanan aplikasi telah diuji.

## Demo Dan UAT Lokal

Lingkungan UAT terpisah memakai database `shifastock_uat`, pengguna MySQL dengan grant khusus UAT, akun simulasi, serta sesi/cache sendiri. Data stok dan transaksi UAT tidak masuk ke database klinik. Pada workspace demo, [domain UAT](https://shifastock.nexspace.my.id) diteruskan ke loopback port `4002`; restart melalui runner UAT, bukan artisan biasa. Guest dapat melihat seluruh menu persediaan, termasuk Stok Awal dan Parameter Monitoring, tetapi izin tulis dan administrasinya dikunci di backend.

```bash
php scripts/uat.php setup
php scripts/uat.php serve
```

`setup` hanya untuk penyiapan awal dan menolak menimpa UAT yang sudah ada. Instalasi baru memakai `http://127.0.0.1:8010` secara default; workspace demo ini memakai domain HTTPS dan port backend `4002` sesuai konfigurasi privat. Transaksi contoh workspace sudah dikosongkan atas permintaan pengguna, sedangkan akun dan master obat dipertahankan. Username: `petugas_uat`, `apoteker_uat`, dan `guest`. Akun `admin_uat` khusus pengelola internal. Password akun UAT tidak ditampilkan di README; kredensial disimpan privat lokal dan diabaikan Git.

Lihat [panduan UAT](Docs/00_current/uat_guide.md) untuk lokasi kredensial privat, verifikasi isolasi, pengujian browser, dan batasan akses lokal. Jangan mengganti `.env` utama atau mereset data klinik untuk demo.

## Deployment Dan Pembaruan

1. Backup database serta konfigurasi sebelum memperbarui aplikasi.
2. Hentikan sementara penulisan selama deployment. Arahkan web server hanya ke `public/` dan siapkan konfigurasi produksi, termasuk `APP_DEBUG=false`.
3. Pasang dependency dan jalankan migrasi **sebelum** menerima transaksi pada versi baru:

```bash
composer install --no-dev --optimize-autoloader
npm ci
npm run build
php artisan migrate --force
php artisan optimize
php artisan inventory:reconcile
```

Migrasi terbaru menambahkan snapshot opname dan pengunci transaksi stok; jangan menghapus baris pengunci secara manual. Draf opname lama tanpa snapshot harus memakai **Mulai Hitung Ulang** sebelum finalisasi. Selisih historis perlu ditinjau, bukan diperbaiki dengan menebak batch atau mereset database.

Jangan menjalankan `migrate:fresh` pada data klinik atau seeder demo di produksi. Lihat [deployment, backup, dan restore](Docs/00_current/deployment_backup.md) untuk checklist lebih lanjut.

## Dokumentasi

| Dokumen | Isi |
| --- | --- |
| [Indeks dokumentasi](Docs/README.md) | Panduan membaca dokumentasi proyek |
| [Kondisi terkini](Docs/00_current/current_state.md) | Implementasi yang tersedia dan batasannya |
| [Arsitektur](Docs/00_current/architecture.md) | Struktur aplikasi dan alur data |
| [Riwayat implementasi](Docs/30_implemented/README.md) | Plan serta laporan pekerjaan yang selesai |
| [Strategi pengujian](Docs/00_current/testing_strategy.md) | Runner, fixture, dan cakupan tes |
| [Keamanan](Docs/00_current/security.md) | Pedoman akses dan keamanan |

## 🔐 Catatan Keamanan

- Jangan commit `.env`, kredensial database, password, atau hasil pengujian yang mengandung data internal.
- Akun demo hanya untuk lokal; konfigurasi pengguna produksi dikelola terpisah.
- Tinjau matriks hak akses sebelum digunakan petugas. Panduan menu bukan pengganti pembatasan akses backend.
- Pertahankan audit log dan riwayat mutasi sesuai kebijakan klinik. Log mencatat aktivitas akun, administrasi, dan tindakan persediaan yang telah diinstrumentasi.
- Penguncian aplikasi tidak melindungi perubahan melalui SQL langsung atau akses administrator database. Jangan menggunakannya untuk mengganti satuan atau mengoreksi stok tanpa prosedur terverifikasi.

## Penggunaan

Proyek ini dikembangkan untuk kebutuhan internal Klinik Shifa Medika. Metadata lisensi package tercantum di `composer.json`.
