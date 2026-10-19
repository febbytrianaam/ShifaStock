# Screenshot Implementasi ShifaStock

Dokumentasi Laporan Kemajuan Capstone Universitas Terbuka (STSI4440).

Paket ini merekam kondisi pengambilan awal 18 Oktober 2026, termasuk bukti 58 tes. Perbaikan label batch yang ditemukan kemudian dan hasil terbaru 70 tes dicatat terpisah pada [pengujian final](../Capstone_Testing_Final/Ringkasan_Pengujian.md). Jangan menganggap angka pengujian atau status temuan pada paket historis ini sebagai hasil terbaru.

- Commit aplikasi: `edf1faa8e51f97927b1f78337c69b230b58be2d6`.
- Waktu mulai pengambilan: 2026-10-18 22:38:31 +07:00 (Asia/Jakarta).
- Resolusi setiap PNG: **1440 x 900**, skala perangkat 1, tanpa penggabungan atau perubahan isi screenshot.
- **Semua akun, supplier, batch, jumlah stok, dan transaksi adalah simulasi**, dibuat di database MySQL terpisah.
- Nama/identitas visual Klinik Shifa Medika merupakan identitas aplikasi; tidak ada data pasien atau kredensial akun nyata.
- Mode Data Real berarti data dari database aplikasi simulasi, **bukan data klinik**.
- Gambar 01-18 dan 02b diambil dari aplikasi melalui HTTP lokal dengan Playwright, tanpa intersepsi response atau manipulasi isi halaman. Gambar 19 adalah laporan dokumentasi HTML terpisah yang merangkum hasil tes aktual, bukan fitur aplikasi.
- Form yang belum disimpan/dikirim disebutkan secara eksplisit; keberadaan form bukan bukti semua kasus validasi atau UAT telah lulus.
- Password acak hanya diteruskan melalui memori/environment proses; tidak disimpan dalam repository, screenshot, atau metadata.

## Daftar Gambar

| No. | Modul / File | Deskripsi | Role | Status |
| --- | --- | --- | --- | --- |
| 1 | [Login](Gambar_01_Login.png) | Form login asli; kolom password kosong. | Tanpa login | berhasil |
| 2 | [Pengelolaan Pengguna dan Hak Akses](Gambar_02_Pengguna_dan_Hak_Akses.png) | Daftar empat akun simulasi dengan role berbeda; tab Hak Akses tersedia. | Administrator | berhasil |
| 02b | [Matriks Hak Akses (pendukung)](Gambar_02b_Matriks_Hak_Akses.png) | Tab matriks RBAC asli; tidak mengubah konfigurasi hak akses. | Administrator | berhasil |
| 3 | [Dashboard - Mode Data Real](Gambar_03_Dashboard_Data_Real.png) | Mode Data Real membaca database simulasi, bukan database klinik. | Petugas Farmasi | berhasil |
| 4 | [Daftar Master Obat](Gambar_04_Master_Obat.png) | Enam obat simulasi dengan satuan, verifikasi, dan status aktif. | Kepala Apoteker | berhasil |
| 5 | [Form Tambah/Edit Obat](Gambar_05_Form_Edit_Obat.png) | Form Edit Obat diisi dari master simulasi; tidak menyimpan perubahan. | Kepala Apoteker | berhasil |
| 6 | [Form Penerimaan Obat](Gambar_06_Form_Penerimaan_Obat.png) | Edit draf penerimaan asli berisi dua item dan konversi Box; draf belum menambah stok. | Petugas Farmasi | berhasil |
| 7 | [Daftar/Detail Penerimaan](Gambar_07_Riwayat_Penerimaan.png) | Riwayat tiga dokumen terposting dan satu draf simulasi. | Petugas Farmasi | berhasil |
| 8 | [Form Transfer Gudang-Apotek](Gambar_08_Transfer_Gudang_Apotek.png) | Isian transfer 20 tablet dengan FEFO otomatis; belum dikirim. | Petugas Farmasi | berhasil |
| 9 | [Form Pengeluaran Obat](Gambar_09_Pengeluaran_Obat.png) | Pengeluaran simulasi dari Apotek tanpa data pasien; isian belum dikirim. | Petugas Farmasi | berhasil |
| 10 | [Form Stok Awal](Gambar_10_Stok_Awal.png) | Role Kepala Apoteker sesuai izin default. Contoh stok awal belum dikirim. | Kepala Apoteker | berhasil |
| 11 | [Kartu Stok dan Riwayat Mutasi](Gambar_11_Kartu_Stok_dan_Mutasi.png) | Kartu Paracetamol di Gudang; stok awal, penerimaan, transfer dan retur berasal dari transaksi simulasi. | Petugas Farmasi | berhasil |
| 12 | [Form Retur PBF](Gambar_12_Retur_PBF.png) | Contoh retur batch dari Gudang dengan alasan dan supplier simulasi; belum dikirim. | Petugas Farmasi | berhasil |
| 13 | [Form Kondisi Khusus](Gambar_13_Kondisi_Khusus.png) | Contoh pengurangan batch rusak di Gudang dengan label lokasi konsisten; belum dikirim. Lihat kendala label Apotek pada catatan terpisah. | Petugas Farmasi | berhasil |
| 14 | [Pemeriksaan Stok Harian](Gambar_14_Pemeriksaan_Stok_Harian.png) | Draf pemeriksaan terisi enam obat sesuai stok database simulasi. | Petugas Farmasi | berhasil |
| 15 | [Review Pemeriksaan Harian](Gambar_15_Review_Pemeriksaan_Harian.png) | Laporan terkirim; perbandingan stok dan tindakan persetujuan/koreksi. Tidak mengklaim telah disetujui. | Kepala Apoteker | berhasil |
| 16 | [Sesi Stock Opname Bulanan](Gambar_16_Sesi_Stock_Opname_Bulanan.png) | Sesi bulanan Gudang dibuat dari snapshot aktual database simulasi; belum final. | Petugas Farmasi | berhasil |
| 17 | [Detail Penghitungan Stock Opname](Gambar_17_Detail_Penghitungan_Stock_Opname.png) | Detail snapshot dan hitungan fisik yang telah disimpan; tombol finalisasi tersedia tetapi tidak dijalankan. | Kepala Apoteker | berhasil |
| 18 | [Laporan Persediaan](Gambar_18_Laporan_Persediaan.png) | Ringkasan persediaan dan transaksi dari database simulasi. | Kepala Apoteker | berhasil |
| 19 | [Bukti Hasil Pengujian Otomatis](Gambar_19_Hasil_Pengujian.png) | Tabel ringkasan dari JUnit dan keluaran MySQL aktual; bukan halaman fitur aplikasi. Keluaran mentah tetap disertakan. | Tidak berlaku (laporan dokumentasi hasil tes) | berhasil |

Commit, tanggal per gambar, URL relatif, dan SHA-256 setiap PNG dicatat di [manifest.json](manifest.json). Gambar 02b adalah pendukung tambahan untuk memperlihatkan matriks permission, terpisah dari daftar pengguna.

## Bukti Pengujian

Gambar 19 diambil dengan Playwright dari [laporan tabel pengujian](Bukti_Pengujian/Laporan_Pengujian.html), bukan tampilan fitur ShifaStock. Laporan mengelompokkan 58 kasus uji dari JUnit, tujuh skenario MySQL, build frontend, dan rekonsiliasi stok. Rincian setiap kasus dapat dibuka di bagian bawah laporan HTML.

Angka dan status dihitung dari [JUnit XML](Bukti_Pengujian/phpunit-results.xml) dan keluaran perintah aktual beserta [kode exit](Bukti_Pengujian/hasil-eksekusi.json), bukan angka historis README. [Ringkasan terstruktur](Bukti_Pengujian/Ringkasan_Tabel.json), semua keluaran mentah, dan [screenshot keluaran sebelumnya](Bukti_Pengujian/Screenshot_Keluaran_Asli.png) dipertahankan. Perubahan format tabel tidak menjalankan ulang tes; waktu pengujian tetap mengikuti eksekusi asli, sedangkan waktu pengambilan gambar baru dicatat dalam manifest.

Untuk memperbarui format dari bukti yang sama: jalankan `php scripts/capstone-test-report.php`, lalu `node scripts/capstone-test-report.cjs` dan `node scripts/verify-capstone.cjs` dengan Playwright tersedia. Tidak memerlukan server aplikasi atau akses database.

## Kendala Dan Batasan

- Tidak ada kegagalan pengambilan pada rangkaian terakhir.

- Temuan UI label batch lintas lokasi pada Kondisi Khusus dan kendala shutdown server Windows dijelaskan di [Kendala dan verifikasi](Kendala_dan_Verifikasi.md). Bukti UI asli ada di folder Bukti_Kendala. Keberhasilan gambar utama tidak meniadakan temuan tersebut.

Pengujian otomatis dan tangkapan ini tidak menggantikan UAT petugas, audit keamanan menyeluruh, atau pembuktian seluruh kondisi produksi. Source fitur aplikasi tidak diubah untuk keperluan dokumentasi.

## Pengambilan Ulang

Jalankan `php scripts/capstone-capture.php` setelah dependency PHP, frontend, MySQL, dan Playwright tersedia. Runner memerlukan izin membuat/menghapus database uji; server dibatasi ke 127.0.0.1. Gunakan resolusi modul Node.js yang memuat Playwright. Pada Windows/sandbox, penghentian proses server dapat membutuhkan izin tambahan; lihat catatan kendala. Pada pengambilan yang diserahkan, server sudah dihentikan dan database simulasi sudah dihapus.

## Hasil Verifikasi Akhir

- **19 dari 19 gambar utama berhasil**, dengan satu gambar matriks hak akses tambahan (02b).
- Satu screenshot diagnostik terpisah memperlihatkan kendala label batch Apotek; gambar ini tidak diperlakukan sebagai bukti fitur berhasil untuk kasus tersebut.
- Seluruh 20 PNG utama/pendukung diperiksa dimensinya, isi nonkosong, dan SHA-256-nya; hasilnya ada di [Verifikasi_Teknis.json](Verifikasi_Teknis.json).
- Seluruh gambar ditinjau secara visual melalui contact sheet internal dan pemeriksaan gambar asli pada bagian padat. Contact sheet tidak disertakan sebagai pengganti screenshot asli.
- Tidak tampak password, token, hash password, identitas pasien, atau pengguna klinik asli. Username/email yang terlihat adalah akun simulasi. Teks alamat/brand klinik merupakan identitas aplikasi yang memang ditampilkan oleh halaman login.
- Sebagian daftar panjang hanya memperlihatkan area viewport; tabel/dialog menggunakan layout dan scroll asli aplikasi. Teks input yang lebih panjang dari kolom dapat terpotong sesuai perilaku UI, tanpa pengubahan CSS.
- **Hasil pengujian aktual:** 58 tes, 610 assertion, 0 failure/error/skipped; tujuh skenario konkurensi MySQL lulus; build berhasil; rekonsiliasi 12 pasangan obat/lokasi simulasi tanpa selisih.
- Pada pengambilan awal ini tidak ada source fitur yang diubah; perbaikan fitur dilakukan pada tahap pengujian final yang ditautkan di atas. Status commit/publikasi mengikuti riwayat Git, bukan status saat bukti diambil.
