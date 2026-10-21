# Dashboard Dan Panduan Awal

Permintaan pengguna: hapus data dummy, pertahankan master dari Excel resmi, dan berikan panduan login pertama dengan sorotan menu, latar gelap, Lewati, Selanjutnya, serta Kembali. Pengguna mengizinkan tiga transaksi/stok uji ikut dibersihkan.

## Rencana Yang Dijalankan

1. Audit sumber impor dan hubungan transaksi tanpa mengubah database.
2. Hapus pilihan/payload demo dashboard; gunakan perhitungan database dan kondisi kosong yang jujur.
3. Siapkan pembersihan terbatas dengan dry-run, manifest tepat, mutex, backup privat, perlindungan master impor, serta rekonsiliasi.
4. Tambahkan panduan berbasis permission dengan penyimpanan status per akun dan buka ulang dari topbar.
5. Jalankan regresi backend, konkurensi MySQL, browser desktop/mobile, dan build pada lingkungan uji terpisah.
6. Terapkan migrasi akun dan pembersihan yang disetujui pada database lokal, kemudian verifikasi hasilnya.
7. Dokumentasikan hasil aktual tanpa mengubah bukti akademik historis, melakukan commit/push, atau deployment produksi.

## Batasan

- Tidak mengubah FEFO, aturan konversi, mekanisme perhitungan stok, atau matriks RBAC.
- Tidak menghapus akun, referensi satuan/lokasi, audit log, atau master Excel.
- Tidak menyisipkan password dan backup database ke repository.
- Pembersihan lokal bukan prosedur rutin atau perintah reset semua data.
