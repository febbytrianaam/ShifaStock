# ShifaStock - Monitoring Obat Klinik Shifa Medika

ShifaStock adalah aplikasi monitoring persediaan obat untuk Klinik Shifa Medika. Aplikasi ini membantu pengelolaan master obat, penerimaan obat, transfer stok antar lokasi, pemeriksaan stok harian, monitoring kedaluwarsa, laporan persediaan, serta pengelolaan pengguna dan hak akses berbasis role.

## ⚙️ Stack

- Laravel 13
- Livewire 3
- Tailwind CSS 4
- Vite
- MySQL
- Chart.js
- SweetAlert2
- PHP 8.3+

## ✨ Fitur Utama

- Login berbasis username dan password
- Dashboard dengan mode `Demo` dan `Data real`
- Master data obat dengan status verifikasi dan status aktif
- Import/preview data riset master obat
- Penerimaan obat ke gudang
- Transfer stok gudang ke apotek
- Pemeriksaan stok harian
- Cancel draft pemeriksaan dengan SweetAlert
- Review/finalisasi pemeriksaan oleh kepala apoteker
- Stock opname periodik
- Monitoring obat kosong dan menipis
- Monitoring kedaluwarsa beserta lokasi stok
- Laporan persediaan dan pergerakan stok
- CRUD pengguna
- Matrix RBAC/hak akses per role
- Audit log aktivitas admin
- Role `guest` untuk view-only demo

## 👤 Role Default

| Role | Username Demo | Keterangan |
| --- | --- | --- |
| Administrator | `admin` | CRUD pengguna, hak akses, audit log |
| Petugas Farmasi | `petugas` | Operasional stok |
| Kepala Apoteker | `kepala` | Master obat dan finalisasi pemeriksaan |
| Guest | `guest` | View-only |

Akun demo dibuat melalui `LocalDemoSeeder` dan hanya untuk environment lokal. Password demo tidak ditampilkan di dokumentasi; set sendiri melalui `LOCAL_DEMO_PASSWORD` di `.env` atau input tersembunyi saat seeder dijalankan.

## 🚀 Instalasi Lokal

Clone repository:

```bash
git clone https://github.com/febbytrianaam/ShifaStock.git
cd ShifaStock
```

Install dependency PHP dan JavaScript:

```bash
composer install
npm install
```

Siapkan file environment:

```bash
cp .env.example .env
php artisan key:generate
```

Atur koneksi database di `.env`, contoh untuk Laragon/MySQL lokal:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=shifa_medika
DB_USERNAME=root
DB_PASSWORD=
```

Untuk membuat akun demo lokal, tambahkan password demo di `.env`:

```env
LOCAL_DEMO_PASSWORD=
```

Isi nilainya secara lokal saja dan jangan commit file `.env`.

Jalankan migration dan seeder:

```bash
php artisan migrate --seed
php artisan db:seed --class=LocalDemoSeeder
```

Build asset frontend:

```bash
npm run build
```

Jalankan aplikasi:

```bash
php artisan serve --host=127.0.0.1 --port=8000
```

Buka aplikasi di:

```text
http://127.0.0.1:8000
```

## Development

Untuk menjalankan Vite saat pengembangan:

```bash
npm run dev
```

Di terminal lain:

```bash
php artisan serve
```

## 🔐 RBAC dan Hak Akses

Hak akses dikelola dari menu:

```text
Administrasi > Pengguna > Hak Akses
```

Matrix permission dapat diatur per role. Beberapa permission administrator dikunci sebagai wajib agar akun admin tidak kehilangan akses ke manajemen pengguna dan hak akses.

Permission utama meliputi:

- `view-dashboard`
- `view-medicines`
- `view-receipts`
- `view-transfers`
- `view-nightly-checks`
- `view-opname`
- `view-nightly-history`
- `view-shortages`
- `view-expiry`
- `view-reports`
- `operate-inventory`
- `manage-medicines`
- `finalize-opname`
- `manage-users`
- `manage-permissions`
- `view-audit-logs`

## Audit Log

Audit log dapat diakses dari:

```text
Administrasi > Audit Log
```

Aktivitas yang dicatat:

- Login
- Logout
- Membuat pengguna
- Mengubah pengguna
- Menonaktifkan pengguna
- Mengubah matrix hak akses

Log menyimpan pelaku, role, IP address, user agent, waktu, event, deskripsi, dan metadata perubahan.

## 📦 Data Obat dan Operasional

Master obat menggunakan data yang sudah terhubung ke backend database. Menu operasional seperti penerimaan obat, transfer stok, monitoring kedaluwarsa, laporan, dan pemeriksaan harian membaca data dari database.

Dashboard memiliki toggle:

- `Demo`: data contoh untuk presentasi
- `Data real`: data dari database operasional

## Testing

Jalankan test:

```bash
php artisan test
```

Catatan: jika test menggunakan SQLite in-memory, pastikan ekstensi `pdo_sqlite` aktif di PHP lokal.

## Build Produksi

Sebelum deploy/push rilis:

```bash
composer install --no-dev --optimize-autoloader
npm install
npm run build
php artisan migrate --force
php artisan optimize
```

## Struktur Penting

```text
app/
  Http/Controllers/
  Livewire/
  Models/
  Support/
database/
  migrations/
  seeders/
  data/
resources/
  views/
  css/
  js/
routes/
  web.php
```

## Catatan Keamanan

- Jangan commit file `.env`.
- Ganti password demo sebelum digunakan di luar lokal.
- Pastikan permission role sudah dicek dari menu Hak Akses.
- Audit log sebaiknya tidak dihapus sembarangan karena berisi riwayat aktivitas admin.

## Lisensi

Project ini dibuat untuk kebutuhan internal Klinik Shifa Medika.
