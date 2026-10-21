# Demo Dan UAT Lokal

UAT memakai database MySQL **`shifastock_uat`**, bukan database operasional klinik. Tidak memerlukan hosting publik; aplikasi dapat dicoba di laptop ini saat kunjungan klinik.

## Membuka Aplikasi

```bash
php scripts/uat.php serve
```

Buka **http://127.0.0.1:8010** pada komputer yang menjalankan server tersebut. Pastikan ada penanda **UAT / Simulasi**. Alamat loopback ini tidak dapat dibuka dari komputer lain. Jangan memakai URL aplikasi utama untuk transaksi percobaan.

Setup awal pada workspace ini sudah selesai. Untuk instalasi lokal lain yang memiliki master Excel resmi dan akses provisioning MySQL:

```bash
php scripts/uat.php setup
```

Setup menolak menimpa database/manifest UAT yang sudah ada. Menjalankan server kembali tidak mereset transaksi atau hasil UAT. Jangan mengganti `.env` utama, mengimpor dump klinik, atau menjalankan `migrate:fresh` untuk mengulang demo.

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

- 167 master obat Excel, satuan, lokasi, dan kemasan disalin sebagai referensi awal. Status dan satuan asli master tidak dipaksa berubah.
- Akun, sesi, audit, dan transaksi lama klinik tidak disalin. Metadata master `imported_by` dan `verified_by` dikosongkan karena akun sumber tidak dibawa.
- Stok dan transaksi contoh baru diberi referensi `UAT-*`: dua batch FEFO, contoh mendekati ED/kedaluwarsa, penerimaan terposting dan draf, transfer, parameter minimum, draf pemeriksaan serta draf opname.
- Angka stok tersebut sepenuhnya simulasi, bukan hitungan fisik klinik. Jangan memindahkan hasil stok UAT menjadi stok operasional.
- Kartu stok cetak UAT diberi penanda simulasi; unduhan laporan CSV memiliki awalan nama `uat-` tanpa mengubah susunan kolomnya.
- Ini salinan awal, bukan sinkronisasi dua arah. Perubahan master/parameter/transaksi UAT tidak dikirim kembali ke database klinik.
- Koneksi MySQL UAT memakai pengguna khusus yang hanya memiliki grant pada database UAT, tanpa `GRANT OPTION`. Akses baca ke database klinik sudah diuji ditolak.
- APP_KEY, cookie sesi, cache, compiled view, log, serta disk file UAT dipisahkan. Debug dimatikan. Guard startup menolak nama database/pengguna MySQL, sesi/cache, atau konfigurasi lain yang tidak aman.

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

Bukti lokal tanpa password:

- `storage/app/qa/uat-isolation.json`
- `storage/app/qa/uat-browser/results.json` dan PNG desktop/mobile
- `storage/app/qa/inventory/phpunit-results.xml`

Pengujian otomatis bukan UAT petugas. Catat hasil percobaan langsung dari klinik, terutama satuan/kemasan, kecocokan alur kerja, dan pemahaman tindakan yang mengubah stok.
