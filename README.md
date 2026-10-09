# Klinik Shifa Medika — Persediaan Obat

Fondasi Laravel monolith berdasarkan AI handoff PRD. Lihat [IMPLEMENTATION_STATUS.md](IMPLEMENTATION_STATUS.md) untuk cakupan dan batas implementasi.

Stack: Laravel 13, Livewire 3, Tailwind CSS 4/Vite, MySQL. PHP minimal 8.3.

## Jalankan aplikasi lokal

Dari direktori `shifa-medika`, pastikan MySQL Laragon aktif:

```powershell
php artisan serve --host=127.0.0.1 --port=8000
```

Buka http://127.0.0.1:8000. Asset sudah dibangun; Vite tidak perlu aktif.

Akun demo lokal dengan password `123` ada di `LOCAL_DEMO_ACCOUNTS.txt` (diabaikan Git). Seeder demo hanya berjalan pada environment local dan memperbarui tiga akun demo ke username `admin`, `petugas`, `kepala` dengan password `123`:

```powershell
php artisan db:seed --class=LocalDemoSeeder
```

Buat akun individu dengan prompt password tersembunyi:

```powershell
php artisan shifa:create-user nama --email=nama@example.com --name="Nama Pengguna" --role=kepala_apoteker
```

Role tersedia: `administrator`, `petugas_farmasi`, `kepala_apoteker`. Tidak ada registrasi publik.

## Setup komputer lain

```powershell
composer install
Copy-Item .env.example .env
php artisan key:generate
npm install
npm run build
```

Buat database MySQL `shifa_medika`, atur kredensial `.env`, lalu:

```powershell
php artisan migrate --seed
php artisan shifa:create-user admin --email=admin@example.com --name="Administrator" --role=administrator
```

Seeder dasar hanya membuat satuan referensi dan lokasi, tanpa obat/stok/saldo awal rekaan.

## Pengembangan dan pemeriksaan

```powershell
npm run dev
php artisan test
php vendor/bin/pint --test
```

PHPUnit memakai SQLite in-memory, terpisah dari MySQL aplikasi. Pengujian posting dan concurrency MySQL belum tersedia karena mesin mutasi belum dibuat.

Konfigurasi MySQL root tanpa password adalah konfigurasi Laragon lokal pada mesin ini. Sesuaikan pada lingkungan lain. Akun demo dan konfigurasi lokal tidak ditujukan untuk produksi.

## Data dummy untuk demonstrasi UI

```powershell
php artisan db:seed --class=PrototypeSeeder
```

Enam obat dan batch contoh ditambahkan secara idempotent pada environment local. Tampilan saldo, minimum, grafik, dan transaksi menggunakan fixture simulasi. Belum terhubung ke mesin persediaan.
