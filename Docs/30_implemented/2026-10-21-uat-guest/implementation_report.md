# Database UAT Dan Guest Read-Only

Tanggal: 21 Oktober 2026 WIB. Basis Git: `88835eb2670cbfbf6ad6ce5ec8dcb8d2fe59c8d7` ditambah perubahan working tree, termasuk dashboard/panduan yang dikerjakan sebelumnya. Belum commit/push atau deployment produksi.

## Implementasi

- Database persisten `shifastock_uat` dan pengguna MySQL acak khusus, dengan grant hanya pada schema UAT dan tanpa grant option. `.env` utama tidak diubah.
- Bootstrap UAT terpisah dengan guard fail-closed, APP_KEY/cookie sendiri, sesi/cache database UAT, private view/file directory, log terpisah, queue sync, dan debug mati.
- Setup menolak database/manifest yang sudah ada; seeder menolak menimpa akun/transaksi UAT. Tidak ada reset otomatis atau perintah drop database UAT.
- Salinan awal 167 master obat Excel beserta satuan/lokasi/kemasan, tanpa akun, transaksi, sesi, atau audit klinik. Referensi ke akun import/verifikasi di master dikosongkan. Data UAT tidak disinkronkan kembali ke klinik.
- Empat akun simulasi dengan password acak berbeda; password dan koneksi hanya disimpan privat lokal, tidak dicetak atau dimasukkan dokumentasi publik.
- Fixture melalui controller/service persediaan yang sudah ada: enam stok awal, satu penerimaan terposting, satu penerimaan draf, satu transfer FEFO, dua parameter minimum, satu pemeriksaan draf, dan satu opname draf. Total delapan mutasi pada lima pasangan obat/lokasi; rekonsiliasi sesuai.
- Penanda UAT/Simulasi pada login dan halaman aplikasi. Server hanya listen pada loopback `127.0.0.1:8010`, bukan hosting publik atau akses LAN.
- Cetak kartu stok UAT memiliki penanda simulasi; nama unduhan laporan CSV diawali `uat-`. Kolom CSV dan perhitungan persediaan tidak berubah.

## Hak Akses Guest

Guest mendapatkan seluruh menu persediaan, termasuk Stok Awal dan Parameter Monitoring, tanpa hak tulis atau administrasi. Izin lihat parameter dipisahkan dari kelolanya; migrasi mempertahankan akses lihat pengelola lama dan mengatur Guest sesuai kebijakan yang diminta.

Penolakan Guest diterapkan sebelum membaca izin database, diproyeksikan pada matriks, serta dipaksakan saat menyimpan/reset matriks. Izin tulis, finalisasi, reversal, pengguna, RBAC, audit, referensi, dan pengaturan superadmin tidak dapat diaktifkan untuk Guest. Role lain tidak diberi batas keras yang sama.

Tampilan parameter dan detail opname read-only tidak menyediakan input/form tulis. Detail harian non-editable tidak membungkus pencarian dalam form POST. Dashboard tidak menampilkan ringkasan administrator ketika akses persediaan Guest dinonaktifkan. Panduan awal menyesuaikan menu dan deskripsi baca-saja.

## Verifikasi Aktual

Windows lokal, PHP 8.5.9, PHPUnit 12.5.38, MySQL, Chrome headless/Playwright dan Vite 7.3.7.

| Pemeriksaan | Hasil aktual |
| --- | --- |
| PHPUnit terisolasi | 90 tes / 931 assertion lulus; SQLite memory, bukan database klinik |
| Konkurensi MySQL | 7 skenario lulus pada database acak yang dibersihkan setelah tes |
| Browser UAT langsung | 25/25 kelompok pemeriksaan lulus, 1440 x 900 dan 390 x 844 |
| Hak tulis Guest | 21 permintaan POST/PUT/DELETE ditolak 403 dengan CSRF valid |
| Superadmin Guest | Empat menu administrasi ditolak 403, sidebar tidak menampilkannya |
| Rekonsiliasi UAT | 5 pasangan obat/lokasi, nol mismatch dan nol masalah identitas batch |
| Isolasi sumber | Fingerprint inventori klinik sebelum/sesudah setup dan QA tetap sama |
| Hak MySQL UAT | Grant hanya UAT; query baca master klinik ditolak oleh MySQL |
| Frontend build | Berhasil |

Pengujian Guest juga mengaktifkan izin terlarang secara sengaja pada database tes PHPUnit dan memastikan akses tetap ditolak, termasuk Livewire. Browser UAT menggunakan akun QA sementara, bukan empat akun yang diserahkan untuk demo. Setelah tes, akun/sesi/audit milik QA dihapus; inventori UAT tidak berubah, dan akun tetap masih memiliki panduan login pertama.

Helper browser awal perlu diperbaiki karena selalu menunggu panduan pada login Guest kedua, padahal status Lewati sudah tersimpan. Setelah pengujian cetak ditambahkan, helper CSRF juga diarahkan kembali ke halaman aplikasi karena halaman cetak memang tidak memiliki form token. Filter kartu stok memakai parameter `start`/`end` sesuai route, dengan assertion referensi transaksi nyata. Eksekusi ulang lengkap menghasilkan 25/25 lulus. Perbaikan helper tidak memalsukan tampilan atau hasil backend.

Bukti privat: `storage/app/qa/inventory/phpunit-results.xml`, `storage/app/qa/inventory/mysql/`, `storage/app/qa/uat-isolation.json`, serta `storage/app/qa/uat-browser/results.json` dan PNG. Screenshot desktop parameter serta mobile sidebar sudah dibuka dan diperiksa; tidak ada password atau akun petugas klinik asli.

## File Utama

- `scripts/uat.php`, `scripts/test-uat-browser.cjs`, `database/seeders/UatSeeder.php`
- `app/Support/UatEnvironment.php`, `AppServiceProvider`, konfigurasi filesystem/log
- `Permissions`, migrasi ekspansi Guest, route parameter, matriks admin dan preview akses
- Layout, login, komponen penanda UAT, parameter monitoring, opname, pemeriksaan harian, dashboard, dan Onboarding
- `GuestRoleTest`, `UatEnvironmentTest`
- [Panduan UAT](../../00_current/uat_guide.md)

## Batasan Dan Pemulihan

- UAT langsung petugas klinik belum dilakukan. Lulus otomatis bukan persetujuan operasional atau validasi satuan/kemasan oleh apoteker.
- UAT lokal dapat dipakai di laptop saat kunjungan. Komputer lain tidak dapat menggunakan alamat loopback ini; akses LAN/hosting belum dikonfigurasi.
- Password awal ada di file privat lokal, bukan repository. Reset melalui admin UAT akan membuat password awal di file itu tidak berlaku.
- Hasil UAT dipertahankan ketika server dimulai ulang. Jika kelak perlu reset, backup dan minta persetujuan eksplisit; jangan drop/truncate atau menjalankan seeder paksa.
- Setup yang tidak selesai ditandai `ready=false` dan tidak boleh dilayani. Periksa penyebab/otorisasi provisioning; jangan menyamarkan kegagalan dengan memakai database klinik.
- Untuk menghentikan UAT, hentikan proses server yang menjalankan `scripts/uat.php serve`; tidak perlu mengubah `.env` utama. Rollback source/migrasi perlu ditinjau terpisah dan tidak otomatis menghapus schema atau pengguna MySQL UAT.
