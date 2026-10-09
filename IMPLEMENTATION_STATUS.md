# Status implementasi — 9 Oktober 2026

Acuan utama: `../AI_HANDOFF_PRD_Sistem_Persediaan_Obat_Klinik_Shifa_Medika.md`. Prototipe HTML dan foto referensi tetap dipakai sebagai acuan visual.

## Fitur yang sudah berjalan

- Laravel 13, Livewire 3, Tailwind CSS 4/Vite, MySQL.
- Login memakai username. Akun lokal: `admin`, `petugas`, dan `kepala`; password sementara `123`.
- Pembatasan akses berdasarkan peran Administrator Sistem, Petugas Farmasi, dan Kepala Apoteker.
- Master obat tersimpan di database, lengkap dengan pencarian, paginasi, status aktif, satuan, dan penandaan data impor yang belum diverifikasi.
- Penerimaan obat menambah stok Gudang dan mencatat mutasi inventori.
- Transfer Gudang–Apotek mengurangi lokasi asal, menambah lokasi tujuan, menolak stok yang tidak cukup, dan mencatat mutasi secara atomik.
- Pemeriksaan Harian mendukung draf, pengiriman, persetujuan atau pengembalian oleh Kepala Apoteker, pencarian, paginasi, cetak, dan salin ringkasan.
- Persetujuan Pemeriksaan Harian merekonsiliasi saldo lokasi dengan jumlah fisik dan mencatat selisih sebagai mutasi audit.
- Stock Opname Periodik mengambil laporan Pemeriksaan Harian yang telah disetujui pada bulan terpilih. Tidak ada input ganda untuk aktivitas hitung fisik yang sama.
- Dashboard, Master Obat, Monitoring Kedaluwarsa, Kekurangan & Permintaan, Stock Opname, dan Laporan membaca sumber stok yang sama.
- Kepala tabel Pemeriksaan Harian tetap terlihat saat tabel digulir ke bawah.
- Rute dan komponen pratinjau dummy lama telah dihapus.

## Sumber data stok

`stock_balances` menyimpan saldo per obat dan lokasi. `inventory_movements` menyimpan jejak penerimaan, transfer, dan penyesuaian hasil hitung fisik.

Alurnya:

1. Penerimaan obat masuk ke Gudang.
2. Transfer memindahkan saldo dari Gudang ke Apotek atau antar lokasi.
3. Petugas menghitung stok fisik dalam Pemeriksaan Harian.
4. Setelah Kepala Apoteker menyetujui laporan, hasil fisik menjadi saldo lokasi terbaru dan selisihnya tercatat dalam mutasi.
5. Semua ringkasan dan laporan membaca saldo serta mutasi yang sama.

Karena aplikasi belum menerima transaksi pemakaian pasien, pengurangan obat untuk pelayanan tidak dapat diketahui saat kejadian. Saldo direkonsiliasi melalui Pemeriksaan Harian yang disetujui. Keputusan ini mempertahankan alur sesuai kondisi klinik saat ini tanpa membuat transaksi keluar rekaan.

## Data obat impor

Daftar dari `../obat.xlsx` telah dimasukkan sebagai kandidat master obat. Baris impor tetap tidak aktif atau belum terverifikasi sampai satuan dan informasi operasionalnya dilengkapi. Data ini tidak otomatis membuat saldo. Saldo awal muncul dari penerimaan obat atau hasil Pemeriksaan Harian yang disetujui.

## Catatan desain data

- Saldo saat ini merupakan agregat per obat dan lokasi.
- Batch dan kedaluwarsa dicatat pada penerimaan. Monitoring Kedaluwarsa menampilkan batch penerimaan, sedangkan kolom stoknya diberi label sebagai total obat karena saldo belum dihitung per batch.
- Minimum stok tetap memakai parameter obat/lokasi jika tersedia. Jumlah permintaan pada pemeriksaan dapat diisi petugas sesuai kebutuhan.
- Nomor batch sementara unik pada pasangan obat dan nomor batch.

## Ruang lingkup yang masih terbuka

- Pengelolaan pengguna dan izin melalui UI.
- Transaksi pemakaian pasien atau integrasi dengan sistem pelayanan/resep.
- Saldo dan FEFO per batch, retur, pembatalan/reversal, dan audit aktivitas lengkap.
- Editor konversi kemasan serta kebijakan minimum stok formal per lokasi.
- Pemesanan langsung ke supplier; laporan permintaan saat ini adalah bahan rekap, bukan purchase order otomatis.

## Verifikasi

- PHPUnit: 17 test, 142 assertions, seluruhnya lulus.
- Cakupan alur utama: penerimaan 100 unit, transfer 30 unit, rekonsiliasi hitung fisik 25 unit, serta penolakan transfer bila stok tidak cukup.
- Vite production build berhasil.
- Browser smoke test memeriksa halaman operasional berbasis data database dan memastikan header tabel Pemeriksaan Harian memakai posisi sticky.

## Catatan lingkungan

Database aplikasi lokal memakai MySQL `shifa_medika`. PHPUnit memakai SQLite `:memory:` yang terisolasi. Uji ini belum mencakup simulasi konkurensi MySQL.

Folder induk mengandung tanda kurung siku. Operasi build dan test dapat memerlukan eksekusi di luar sandbox Codex karena pembatasan `realpath` pada Windows.
