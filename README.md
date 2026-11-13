# ShifaStock

**Pengelolaan persediaan obat Klinik Shifa Medika.**

ShifaStock membantu pencatatan obat masuk, transfer gudang ke apotek, pengeluaran, pemeriksaan fisik, dan tindak lanjut stok. Persediaan dapat ditelusuri per obat, batch, tanggal kedaluwarsa, dan lokasi melalui kartu stok serta laporan.

Aplikasi berfokus pada pengelolaan obat, bukan pelayanan pasien atau sinkronisasi otomatis Neoklinik. Rekap pengeluaran Neoklinik dapat diimpor melalui Excel dengan pemeriksaan dan konfirmasi petugas. Dashboard dan transaksi operasional membaca database aplikasi, tanpa mode data dummy.

## Status Proyek

Per 11 November 2026, versi UAT terakhir adalah tag `uat-2026-11-10`.

- Fitur inti sudah lengkap dan lulus pengujian otomatis, termasuk black box 134/134. Rinciannya ada di [Pengujian](#pengujian).
- **Belum siap production.** Yang masih perlu dilakukan:
  - UAT manual oleh petugas farmasi dan apoteker klinik, termasuk impor Neoklinik nyata;
  - keputusan kepala apoteker tentang tiga aturan alur stok: batas waktu impor Neoklinik terhadap pemeriksaan harian, pengeluaran manual bertanggal mundur, dan transaksi di lokasi yang sedang opname;
  - penentuan server production, HTTPS, dan scheduler;
  - uji backup dan restore di database terpisah.

## ✨ Fitur Utama

| Area | Fitur |
| --- | --- |
| Dashboard | Muat satu layar 1920×1080 dan menyegarkan diri tiap menit. Kartu stok kosong, menipis, kedaluwarsa, dan kesehatan stok dengan tren 7 hari; daftar tindak lanjut bertab; grafik aktivitas; alur pemeriksaan hari ini; radar kedaluwarsa termasuk batch yang sudah lewat; serta kondisi per lokasi |
| Monitoring Stok | Satu baris per obat dengan semua lokasi berdampingan, kartu KPI yang sekaligus menjadi filter, serta tab stok minimum, kedaluwarsa, dan hasil pemeriksaan terakhir. Kekurangan diisi dari lokasi penerimaan hanya bila stoknya di atas minimum lokasi itu sendiri; sisanya menjadi estimasi pesanan PBF. Rencana transfer membuka form transfer yang sudah terisi, dan daftar pesanan/permintaan dapat disalin |
| Monitoring Kedaluwarsa | Tab yang sama dengan Monitoring Stok ditambah tampilan Semua batch, kartu KPI, pencarian, pilihan lokasi, dan rentang tanggal |
| Master obat | Pencarian, verifikasi, status aktif, preview/import referensi, satuan stok, konversi kemasan beli per obat, dan unduhan template Excel untuk review master |
| Penerimaan | Input banyak baris, pilihan obat searchable, nomor faktur supplier, konversi satuan, edit/batalkan draf, posting ke lokasi tujuan, dan peringatan faktur ganda |
| Pergerakan stok | Stok awal per batch, transfer banyak obat dalam satu referensi dengan penyimpanan atomik, pengeluaran, retur PBF, serta kondisi khusus dengan batch wajib |
| Impor pengeluaran | Excel Neoklinik, pencocokan obat/satuan, rincian transaksi, pencarian, pratinjau dan posting atomik, pencegahan impor ganda, hapus pratinjau, serta lanjutkan pemeriksaan setelah pindah tab |
| Pemilihan batch | Mengikuti obat dan lokasi; FEFO otomatis untuk transfer/pengeluaran tanpa pilihan batch eksplisit, dengan pengecualian batch kedaluwarsa |
| Pemeriksaan harian | Draf bersama petugas/apoteker dengan identitas pembuat, pelaksana per obat, riwayat aktivitas, dan perlindungan versi lama; daftar responsif, rincian batch, tambah obat, dan kirim sebagian; tinjauan hanya menyesuaikan obat yang diperiksa; cetak laporan A4 |
| Opname periodik | Snapshot stok, isian fisik, finalisasi, dan Mulai Hitung Ulang dengan konfirmasi serta pencatatan isian lama di audit log |
| Pelaporan | Kartu stok per obat/lokasi/batch/periode, cetak dengan identitas klinik, laporan persediaan termasuk stok per lokasi, dan riwayat pemeriksaan |
| Administrasi | Pengguna, matriks RBAC, referensi lokasi/satuan, pengaturan sistem, audit log dengan filter tanggal, dan ekspor audit |

Template Excel master obat hanya untuk review; isinya tidak diimpor kembali ke aplikasi.

## Tampilan Dan Navigasi

- Sidebar mengikuti alur kerja harian. Menu yang tampil mengikuti hak akses masing-masing akun.

  | Kelompok | Menu |
  | --- | --- |
  | Kerja Harian | Pemeriksaan Stok, Stock Opname Periodik, Riwayat Pemeriksaan, Monitoring Stok, Monitoring Kedaluwarsa |
  | Transaksi | Penerimaan Obat, Transfer Stok, Pengeluaran & Impor, Retur PBF, Kondisi Khusus |
  | Data & Laporan | Data Obat, Stok Awal, Parameter Monitoring, Laporan, Kartu Stok |
  | Administrasi | Pengguna, Audit Log, Referensi Sistem, Pengaturan Sistem |

- Jumlah yang perlu ditindaklanjuti tampil di samping menu. Sidebar dapat diciutkan menjadi deretan ikon (diingat per browser) dan menjadi drawer di HP.
- Semua menu, halaman login, dan halaman error memakai tema yang sama dengan Dashboard. Target desain 1920×1080; tampilan juga diperiksa pada laptop 1280–1536 px dan HP. Form penerimaan dan transfer banyak baris masih perlu digeser ke samping di HP.
- Bahasa default aplikasi adalah `id`, sehingga pesan validasi, nama kolom, dan paginasi berbahasa Indonesia.
- Font Plus Jakarta Sans dibundel bersama aplikasi, tanpa permintaan ke Google Fonts.
- Panduan sorotan menu muncul pada login pertama, mengikuti hak akses, dan dapat dibuka ulang dari tombol tanda tanya di topbar.
- Pencarian obat dan batch terhubung ke backend. Validasi dan konfirmasi tindakan penting menggunakan SweetAlert.

Panduan di Pemeriksaan Stok menjelaskan stok awal, stok sistem, jumlah fisik, dan kapan persetujuan menyesuaikan stok. Judul kolom memiliki bantuan yang dapat dibuka lewat hover, keyboard, atau ketukan pada HP. Pengeluaran Obat menjelaskan pencatatan manual tanpa sinkronisasi Neoklinik, jumlah keluar versus sisa stok, FEFO, serta pencegahan pencatatan ganda.

Impor Excel memakai tiga langkah: pilih file, cocokkan obat, lalu periksa dan simpan. Unggah dan pencocokan belum mengurangi stok. Pilihan otomatis tetap memerlukan konfirmasi; tanggal mulai pencatatan mencegah pengurangan ulang transaksi yang sudah tercakup stok awal/hitung fisik. File asli, identitas pasien/dokter, dan nomor penjualan mentah tidak diarsipkan. Pratinjau dapat dilanjutkan selama belum dihapus atau kedaluwarsa; isian yang belum disimpan tidak disimpan otomatis. [Panduan impor](Docs/00_current/uat_guide.md#impor-pengeluaran-excel-neoklinik).

## Role Dan Hak Akses

| Role | Tanggung Jawab Default |
| --- | --- |
| Administrator | Pengguna, hak akses, referensi sistem, pengaturan, dan audit log |
| Petugas Farmasi | Pencatatan dan pemantauan stok operasional |
| Kepala Apoteker | Master obat, review/finalisasi pemeriksaan, dan pengawasan persediaan |
| Guest | Seluruh menu persediaan gabungan petugas/apoteker, hanya baca, tanpa menu superadmin |

Hak akses diatur melalui **Administrasi > Pengguna > Hak Akses**. Matriks mengatur menu serta tindakan backend; beberapa izin inti administrator dikunci untuk mencegah kehilangan akses administrasi. Bila izin gagal dibaca dari database, akses ditolak.

Pengguna non-Administrator yang diberi izin mengelola akun tidak dapat memberikan role Administrator, mengubah atau menonaktifkan Administrator, maupun mereset password akun yang sudah ada. Penggantian password mengakhiri sesi lama akun tersebut.

Username, password, dan data akun lain tidak dicantumkan di README, halaman login, atau output seeder. Kolom password pada form login tersembunyi secara default dan memiliki ikon mata untuk menampilkan atau menyembunyikan isian sendiri.

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

- Laravel 13, Livewire 3, dan MySQL. Dashboard dan Monitoring Stok adalah komponen Livewire.
- Tailwind CSS 4, Vite 7, SweetAlert2, driver.js untuk panduan menu, dan PhpSpreadsheet untuk impor/ekspor Excel. Grafik dashboard dirender sebagai SVG dari server.
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

### Hasil Terakhir

Pengujian otomatis terakhir dijalankan pada 9–10 November 2026. Semua pengujian tulis memakai database sementara yang langsung dihapus; data klinik dan UAT tidak disentuh.

| Pengujian | Hasil |
| --- | --- |
| PHPUnit | **284 tes / 3089 assertions** lulus pada `07b4231` |
| Browser per fase | Regresi 18, panduan 14, workspace pemeriksaan 20, kirim sebagian 7, pemeriksaan obat tertentu 15, transfer 9, satu hari klinik 13, dan impor pengeluaran 21 cek lulus pada `307e746`. Semua fase lulus lagi pada `07b4231` |
| Transaksi bersamaan MySQL | 11 skenario lulus |
| Black box | 134 kasus di 22 modul, dijalankan otomatis lewat Chrome. Putaran awal pada `307e746`: 132 Berhasil, 2 Gagal. Setelah perbaikan di `07b4231`: **134/134 Berhasil**, rekonsiliasi stok 0 selisih |
| Build | `npm run build` dan kompilasi view Blade berhasil |

Dua kegagalan black box putaran awal adalah Audit Log yang menolak rentang tanggal terbalik tanpa pesan, dan reversal di Kartu Stok yang tidak menampilkan pesan sukses. Putaran itu juga mencatat pesan validasi umum yang masih berbahasa Inggris. Ketiganya diperbaiki di `07b4231`. [Cara kerja dan hasil black box](Docs/00_current/testing_strategy.md#black-box-run---10-november-2026).

Belum dilakukan: UAT manual oleh staf klinik, uji di HP fisik, uji beban, dan uji restore backup. Pengujian otomatis tidak menggantikan UAT manual.

Hasil sebelumnya tercatat di [strategi pengujian](Docs/00_current/testing_strategy.md) dan [riwayat implementasi](Docs/30_implemented/README.md). Bukti Capstone tersedia pada [paket screenshot 29 Oktober](Docs/Capstone_Screenshots/2026-10-29/README.md) dan [tabel pengujian fungsional 18 Oktober](Docs/Capstone_Testing_Final/Hasil_Pengujian_Fungsional.md).

### Regresi Terisolasi

```bash
php scripts/test-inventory.php
```

Runner memakai SQLite `:memory:`, melewati cache konfigurasi lokal, dan tidak memakai database klinik. Jika ekstensi tersedia tetapi belum aktif, runner mencoba memuat `pdo_sqlite` untuk proses pengujian saja. Laporan JUnit tersimpan di `storage/app/qa/inventory/phpunit-results.xml` dan diabaikan Git.

Untuk komputer lambat, timeout dapat dinaikkan. Contoh PowerShell:

```powershell
$env:INVENTORY_TEST_TIMEOUT = '900'
php scripts/test-inventory.php
```

### Pengujian Browser

```bash
php scripts/capstone-final-browser.php
php scripts/capstone-final-browser.php daily-cycle
php scripts/test-issue-import.php
```

Tanpa argumen, `capstone-final-browser.php` menjalankan regresi operasional. Fase lainnya adalah `onboarding`, `nightly-ui`, `partial-nightly`, `selected-nightly`, `transfer`, dan `daily-cycle`. `test-issue-import.php` menguji impor pengeluaran Excel.

Kebutuhan:

- build frontend sudah dijalankan;
- Playwright dapat ditemukan lewat resolusi modul Node.js, misalnya melalui `NODE_PATH`;
- Chrome terpasang di `C:/Program Files/Google/Chrome/Application/chrome.exe`;
- akun MySQL di `.env` boleh membuat dan menghapus database.

Setiap fase membuat database sementara bernama acak lalu menghapusnya. Data simulasi, akun, dan password dibuat hanya untuk proses itu, bukan akun klinik/UAT. Hasil JSON dan screenshot disimpan di bawah `storage/app/qa/` dan diabaikan Git.

Fase `daily-cycle` menguji satu hari klinik dari awal sampai akhir lewat antarmuka: stok awal → penerimaan → transfer → retur → pengeluaran → hitung harian dan persetujuan → impor Neoklinik → opname → kartu stok dan reversal. Langkah berjudul "Perilaku saat ini" mencatat tiga aturan alur stok yang masih menunggu keputusan apoteker sebagai `OBSERVASI`.

### Transaksi Bersamaan Di MySQL

```bash
php scripts/test-inventory-mysql.php
```

Runner membuat database sementara bernama acak `shifastock_qa_*`, menjalankan migrasi dan worker terpisah di sana, lalu menghapus database uji tersebut. Akun MySQL perlu izin membuat/menghapus database. Migrasi dan penulisan pengujian tidak dilakukan pada database klinik.

Skenario mencakup pengeluaran FEFO bersamaan, transfer banyak obat, referensi ganda, reversal berulang, pembuatan dan finalisasi opname, penyimpanan dan persetujuan harian, perubahan satuan saat penerimaan pertama, serta dua penerimaan ke stok yang belum mempunyai baris persediaan.

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

## Demo Dan UAT Lokal

Lingkungan UAT terpisah memakai database `shifastock_uat`, pengguna MySQL dengan grant khusus UAT, akun simulasi, serta sesi/cache sendiri. Data stok dan transaksi UAT tidak masuk ke database klinik. Guest dapat melihat seluruh menu persediaan, termasuk Stok Awal dan Parameter Monitoring, tetapi izin tulis dan administrasinya dikunci di backend.

Untuk instalasi baru:

```bash
php scripts/uat.php setup
php scripts/uat.php serve
```

`setup` hanya untuk penyiapan awal dan menolak menimpa UAT yang sudah ada. Instalasi baru memakai `http://127.0.0.1:8010` secara default.

Pada workspace demo, UAT dilayani dari checkout terpisah yang dikunci ke tag Git `uat-*`, saat ini `uat-2026-11-10`. Perubahan yang belum di-commit di checkout utama tidak terlihat oleh tester, dan versi yang diuji selalu dapat ditunjuk lewat tag. Merilis versi baru berarti membuat tag, memindahkan checkout UAT ke tag itu, membangun ulang aset, dan menjalankan migrasi UAT bila ada. Langkah lengkap dan cara rollback ada di [panduan UAT](Docs/00_current/uat_guide.md#versi-uat-terkunci).

Alamat UAT, akun, dan password disimpan privat dan diabaikan Git; jangan mencantumkannya di README.

Tester mungkin sudah memasukkan transaksi, jadi jangan mereset, seed ulang, atau menjalankan `migrate:fresh` pada UAT. Jangan mengganti `.env` utama atau mereset data klinik untuk demo. Lihat [panduan UAT](Docs/00_current/uat_guide.md) untuk verifikasi isolasi dan batasan akses lokal.

## Deployment Dan Pembaruan

Server production belum ditentukan. Isian host, HTTPS, backup, dan scheduler di [deployment, backup, dan restore](Docs/00_current/deployment_backup.md) masih perlu dilengkapi bersama pemilik server.

1. Backup database serta konfigurasi sebelum memperbarui aplikasi.
2. Hentikan sementara penulisan selama deployment. Arahkan web server hanya ke `public/` dan siapkan konfigurasi produksi, termasuk `APP_ENV=production` dan `APP_DEBUG=false`.
3. Pasang dependency dan jalankan migrasi **sebelum** menerima transaksi pada versi baru:

```bash
composer install --no-dev --optimize-autoloader
npm ci
npm run build
php artisan migrate --force
php artisan optimize
php artisan inventory:reconcile
```

4. Pasang cron yang menjalankan `php artisan schedule:run` setiap menit. Scheduler menghapus pratinjau impor pengeluaran yang sudah kedaluwarsa setiap hari.

Skema mencakup snapshot opname dan pengunci transaksi stok; jangan menghapus baris pengunci secara manual. Draf opname lama tanpa snapshot harus memakai **Mulai Hitung Ulang** sebelum finalisasi. Selisih historis perlu ditinjau, bukan diperbaiki dengan menebak batch atau mereset database.

Jangan menjalankan `migrate:fresh` pada data klinik atau seeder demo di produksi.

## Dokumentasi

| Dokumen | Isi |
| --- | --- |
| [Indeks dokumentasi](Docs/README.md) | Panduan membaca dokumentasi proyek |
| [Kondisi terkini](Docs/00_current/current_state.md) | Implementasi yang tersedia dan batasannya |
| [Arsitektur](Docs/00_current/architecture.md) | Struktur aplikasi dan alur data |
| [Riwayat implementasi](Docs/30_implemented/README.md) | Plan serta laporan pekerjaan yang selesai |
| [Strategi pengujian](Docs/00_current/testing_strategy.md) | Runner, fixture, cakupan tes, dan hasil tiap putaran |
| [Panduan UAT](Docs/00_current/uat_guide.md) | Versi UAT terkunci, akun simulasi, dan batas pemisahan data |
| [Deployment dan backup](Docs/00_current/deployment_backup.md) | Checklist production, backup, dan restore |
| [Keamanan](Docs/00_current/security.md) | Pedoman akses dan keamanan |
| [Privasi dan retensi](Docs/00_current/privacy_and_retention.md) | Data yang disimpan dan asumsi retensinya |

## 🔐 Catatan Keamanan

- Jangan commit `.env`, kredensial database, password, dump SQL, atau hasil pengujian yang mengandung data internal.
- Akun demo hanya untuk lokal; konfigurasi pengguna produksi dikelola terpisah. Jangan memakai ulang password demo di production.
- Tinjau matriks hak akses sebelum digunakan petugas. Panduan menu bukan pengganti pembatasan akses backend.
- Pertahankan audit log dan riwayat mutasi sesuai kebijakan klinik. Log mencatat aktivitas akun, administrasi, dan tindakan persediaan yang telah diinstrumentasi, termasuk alamat IP dan perangkat; kebijakan retensinya perlu disetujui klinik.
- Penguncian aplikasi tidak melindungi perubahan melalui SQL langsung atau akses administrator database. Jangan menggunakannya untuk mengganti satuan atau mengoreksi stok tanpa prosedur terverifikasi.

## Penggunaan

Proyek ini dikembangkan untuk kebutuhan internal Klinik Shifa Medika. Metadata lisensi package tercantum di `composer.json`.
