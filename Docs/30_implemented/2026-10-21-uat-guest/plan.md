# Pemisahan UAT Dan Guest Gabungan

Permintaan: siapkan database UAT untuk demo ke klinik menggunakan akun simulasi. Guest harus melihat gabungan menu petugas dan kepala apoteker, tetapi hanya baca dan tanpa menu superadmin.

## Rencana Yang Dijalankan

1. Periksa permission, route, tampilan read-only, dan cara bootstrap lingkungan yang tersedia.
2. Pisahkan permission lihat/kelola parameter; aktifkan menu Stok Awal untuk Guest dan kunci semua izin tulis/administrasinya di backend serta matriks.
3. Pisahkan database MySQL, akun database, kredensial simulasi, sesi/cache, log, file, dan compiled view UAT tanpa mengganti `.env` utama.
4. Salin master Excel sebagai referensi tanpa akun atau transaksi klinik; buat fixture persediaan melalui backend yang sudah ada.
5. Verifikasi inventori klinik tetap sama, grant MySQL terbatas, dan akses UAT ke master klinik ditolak.
6. Jalankan tes backend, MySQL paralel, browser langsung, build, dan inspeksi screenshot; dokumentasikan hasil serta cara menjalankan UAT lokal.

Tidak ada hosting publik, deployment produksi, reset UAT otomatis, commit, atau push yang diminta.
