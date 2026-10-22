# Demo Dan UAT Lokal

UAT memakai database MySQL **`shifastock_uat`**, bukan database operasional klinik. Pada workspace ini, sesuai permintaan pengguna, domain **https://shifastock.nexspace.my.id** diteruskan ke server UAT pada **127.0.0.1:4002**. Ini lingkungan simulasi, bukan deployment operasional klinik.

## Membuka Aplikasi

```bash
php scripts/uat.php serve
```

Buka **https://shifastock.nexspace.my.id** dan pastikan ada penanda **UAT / Simulasi**. Proxy domain harus tetap mengarah ke **127.0.0.1:4002**. Konfigurasi privat workspace menyimpan `listen_port=4002`, URL publik HTTPS, dan secure session cookie. Instalasi baru tanpa pengaturan port tetap memakai default 8010.

Jalankan ulang melalui **`php scripts/uat.php serve`**, bukan `php artisan serve --port 4002`. Runner membaca konfigurasi UAT privat; perintah artisan biasa masih membaca `.env` utama milik lingkungan klinik. `.env` utama sengaja tidak ditimpa, dan database klinik tidak diubah atau dihapus. Server loopback 8010 yang sudah berjalan sebelumnya masih memakai database UAT yang sama, tetapi domain HTTPS adalah alamat utama untuk pengujian ini.

Setup awal pada workspace ini sudah selesai. Untuk instalasi lokal lain yang memiliki master Excel resmi dan akses provisioning MySQL:

```bash
php scripts/uat.php setup
```

Setup menolak menimpa database/manifest UAT yang sudah ada. Menjalankan server kembali tidak mereset transaksi atau hasil UAT. Jangan mengganti `.env` utama, mengimpor dump klinik, atau menjalankan `migrate:fresh` untuk mengulang demo.

## Pembaruan Skema Dan Pemeriksaan Harian

Gunakan `php scripts/uat.php migrate` untuk migrasi tambahan ke database UAT yang sudah ada. Runner memeriksa konfigurasi/nama database terlebih dahulu, tanpa seed atau reset hasil petugas. Jangan gunakan artisan dengan `.env` utama untuk memperbarui UAT. Migrasi cakupan pemeriksaan 21 Oktober 2026 sudah diterapkan ke UAT workspace ini; database klinik tidak dimigrasikan.

Pada Pemeriksaan Stok > Harian, pilih **Semua obat** atau **Obat tertentu**. Pilihan kedua menyediakan pencarian dan centang obat. Daftar awal bisa diisi bertahap: kosongkan jumlah yang belum diperiksa, dan isi 0 hanya jika sudah dihitung dan benar-benar kosong. **Simpan Draf** menyimpan isian yang belum lengkap. Saat mengirim sebagian, konfirmasi menampilkan jumlah yang sudah/belum diperiksa dan menyediakan **Kirim N obat**, **Simpan Draf**, serta **Kembali**. Persetujuan hanya menyesuaikan stok obat yang benar-benar sudah diperiksa; obat lain tidak dianggap kosong. Satu laporan per tanggal/lokasi tetap berlaku; tombol mulai membuka laporan yang sudah ada. Ini tetap hitung fisik, bukan pengajuan pembelian terpisah tanpa perubahan stok.

Jika tabel tidak memiliki kolom input, baca penjelasan **Mode lihat saja**. Draf hanya dapat diisi akun pembuatnya; akun petugas lain maupun Guest tidak mengambil alih draf. Laporan terkirim harus dikembalikan oleh apoteker melalui **Kembalikan untuk Koreksi** sebelum pemiliknya mengisi lagi. Laporan yang sudah disetujui tetap terkunci. Jangan mengubah hak akses, menghapus laporan, atau mereset data untuk mengatasi perbedaan akun pembuat.

Untuk menambah obat yang tertinggal, buka draf milik Anda lalu tekan **Tambah Obat**, cari dan centang obat, kemudian **Tambahkan ke Pemeriksaan**. Isian yang sedang dikerjakan ikut disimpan sebagai draf, termasuk rincian batch dan catatan; tidak perlu membatalkan laporan. Obat baru belum dianggap kosong sampai jumlahnya benar-benar diisi. X, Batal, atau Escape menutup pilihan tanpa mengubah isian. Setelah laporan dikirim/disetujui, penambahan terkunci. Laporan terkirim yang dikembalikan apoteker untuk koreksi dapat diperbarui lagi oleh pemiliknya.

**Satuan Hitung** berbentuk dropdown dan otomatis mengikuti satuan stok di Data Obat, bukan satuan pembelian box/strip. Jika master belum memiliki satuan, pilih referensi yang sesuai dan lengkapi master lewat apoteker sebelum mencatat hasil lebih dari 0. Jika satuan draf lama berbeda, periksa kembali jumlahnya lalu pilih satuan stok; sistem tidak otomatis mengonversi angka lama.

**Cetak / Simpan PDF** membuka laporan pemeriksaan khusus A4 portrait di tab baru, bukan mencetak halaman aplikasi. Pada pratinjau, tekan tombol cetak lalu pilih printer atau Save as PDF. Laporan memakai data terakhir yang disimpan, mencakup seluruh obat dalam laporan meskipun tabel layar sedang difilter/dibagi halaman. Bila masih ada perubahan, sistem mengingatkan untuk Simpan Draf dahulu. Draf tetap berlabel belum disetujui; identitas klinik, status, serta penanda simulasi UAT ikut tercetak.

Widget **Kosong / Menipis** dan **Perlu Ditindaklanjuti** di dashboard memakai stok transaksi dan batas minimum yang diatur per obat/lokasi, bukan menunggu pemeriksaan harian disetujui. Klik item untuk membuka Monitoring pada obat dan lokasi tersebut. Angka dihitung per obat/lokasi, sehingga obat yang sama di dua lokasi dapat dihitung dua kali. Obat tanpa batas minimum belum termasuk penilaian ini. Tabel stok dashboard menampilkan maksimal 12 baris positif terbaru; pencarian hanya mencakup baris tersebut. Status Tersedia berarti jumlah lebih dari nol, bukan otomatis di atas minimum.

## Akun Simulasi

| Username | Role | Pemakaian |
| --- | --- | --- |
| `petugas_uat` | Petugas Farmasi | Uji penerimaan, transfer, pengeluaran, dan pemeriksaan harian |
| `apoteker_uat` | Kepala Apoteker | Uji master obat, stok awal, parameter, review dan finalisasi |
| `guest` | Guest | Lihat semua menu persediaan gabungan petugas dan apoteker, tanpa perubahan data atau menu superadmin |
| `admin_uat` | Administrator | Pengelola internal UAT, bukan akun pengamat klinik |

Setup membuat password berbeda secara acak. Pengelola dapat menggantinya untuk kebutuhan UAT lokal; jangan gunakan password simulasi untuk akun operasional. Kredensial privat disimpan di `storage/app/private/uat/accounts.json`, di luar webroot dan diabaikan Git. Jangan memasukkannya ke README publik, screenshot, atau laporan akademik. Perubahan password melalui admin UAT tidak memperbarui file tersebut otomatis.

Akun UAT bukan akun petugas asli dan tidak tersambung ke akun klinik. Panduan login pertama masih tersedia pada keempat akun tetap; pemeriksaan browser menggunakan akun QA sementara yang sudah dibersihkan.

## Data Dan Batas Pemisahan

Pada 21 Oktober 2026, pengguna meminta UAT dimulai kosong. Transaksi simulasi, batch, stok, parameter, pemeriksaan, opname, dan audit lama dibersihkan setelah backup privat, dengan empat akun, 167 master obat, satuan, lokasi, hak akses, serta konfigurasi tetap utuh. Dashboard dimulai dari nol setelah pembersihan; tester sejak itu sudah mengisi transaksi baru. Jangan menghapus atau memuat ulang fixture untuk mengembalikan keadaan kosong. Data klinik sudah diverifikasi tidak berubah.

- 167 master obat Excel, satuan, lokasi, dan kemasan disalin sebagai referensi awal. Status dan satuan asli master tidak dipaksa berubah.
- Akun, sesi, audit, dan transaksi lama klinik tidak disalin. Metadata master `imported_by` dan `verified_by` dikosongkan karena akun sumber tidak dibawa.
- Saat setup awal, stok dan transaksi contoh memakai referensi `UAT-*`. Fixture tersebut sudah dibersihkan sesuai permintaan di atas; tidak dimuat ulang otomatis saat server dijalankan.
- Angka stok tersebut sepenuhnya simulasi, bukan hitungan fisik klinik. Jangan memindahkan hasil stok UAT menjadi stok operasional.
- Kartu stok cetak UAT diberi penanda simulasi; unduhan laporan CSV memiliki awalan nama `uat-` tanpa mengubah susunan kolomnya.
- Ini salinan awal, bukan sinkronisasi dua arah. Perubahan master/parameter/transaksi UAT tidak dikirim kembali ke database klinik.
- Koneksi MySQL UAT memakai pengguna khusus yang hanya memiliki grant pada database UAT, tanpa `GRANT OPTION`. Akses baca ke database klinik sudah diuji ditolak.
- APP_KEY, cookie sesi, cache, compiled view, log, serta disk file UAT dipisahkan. Debug dimatikan. Guard startup menolak nama database/pengguna MySQL, sesi/cache, atau konfigurasi lain yang tidak aman.
- Untuk URL HTTPS, guard juga mewajibkan secure session cookie. Header protokol/IP proxy hanya dipercaya dari loopback pada UAT; forwarded host/port tidak dipakai untuk mengganti tujuan tautan. Login domain, cookie aman, tautan HTTPS, dashboard kosong, akses obat, penolakan admin Guest, serta logout sudah diuji lewat browser nyata.

## Hak Akses Guest

Guest dapat membuka Stok Awal dan Parameter Monitoring, selain seluruh menu operasional petugas/apoteker. Parameter dan hasil penghitungan ditampilkan sebagai teks, bukan isian yang dapat disimpan.

Permission lihat parameter dipisahkan dari permission kelola. Guest tidak dapat membuat, mengedit, menghapus, memposting, menyetujui, memfinalisasi, melakukan reversal, atau mengakses pengguna/RBAC/audit/referensi/pengaturan superadmin. Batas ini dikunci di backend dan matriks, termasuk ketika nilai izin Guest di database keliru diaktifkan. Pencarian, filter, cetak, dan laporan tetap dapat digunakan.

Read-only berlaku untuk data bisnis. Login/logout, pencatatan audit keamanan, dan status panduan akun tetap menggunakan penyimpanan aplikasi seperti biasa.

## Verifikasi Dan Bukti

```bash
php scripts/uat.php status
php scripts/uat.php verify
php scripts/uat.php browser-check
```

`status` tidak menampilkan password. `verify` membaca inventori klinik melalui konfigurasi lokal, membandingkannya dengan fingerprint saat setup, memeriksa grant UAT, dan membuktikan akun MySQL UAT tidak dapat membaca master klinik. Perubahan inventori klinik yang sah setelah setup juga akan membuat fingerprint berbeda; hasil berbeda perlu ditinjau, bukan di-reset otomatis.

`browser-check` membutuhkan server UAT yang sudah berjalan dan Playwright yang tersedia melalui resolusi modul Node. Runner memakai akun QA sementara, menguji menu dan penolakan HTTP nyata, lalu menghapus akun/sesi/audit milik QA saja. Inventori UAT dan akun tetap tidak direset.

Runner `browser-check` saat ini mengharapkan fixture transaksi setup awal. Setelah pembersihan, jangan menjalankannya dengan menganggap seluruh kasus dapat lulus pada database kosong, dan jangan mengisi ulang fixture tanpa persetujuan. Hasil 25 pemeriksaan sebelumnya adalah riwayat saat fixture masih tersedia. Untuk regresi transaksi gunakan database terisolasi melalui `php scripts/test-inventory.php` atau `php scripts/capstone-final-browser.php regression`, bukan UAT persisten yang sedang dikosongkan.

Bukti lokal tanpa password:

- `storage/app/qa/uat-isolation.json`
- `storage/app/qa/uat-browser/results.json` dan PNG desktop/mobile
- `storage/app/qa/inventory/phpunit-results.xml`

Pengujian otomatis bukan UAT petugas. Catat hasil percobaan langsung dari klinik, terutama satuan/kemasan, kecocokan alur kerja, dan pemahaman tindakan yang mengubah stok.
