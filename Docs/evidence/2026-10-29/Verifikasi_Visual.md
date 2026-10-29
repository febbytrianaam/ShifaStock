# Verifikasi Visual dan Keamanan Evidence

Tanggal: **29 Oktober 2026**, setelah pengambilan final pukul 14.16 WIB. Baseline: `05ba910ce8804ffc9e049a17e96295bb59714cb9`.

Metode: seluruh **15 PNG** dibuka dan diperiksa secara visual oleh agent; dimensi PNG, hash SHA-256, dan sampel piksel diperiksa dengan skrip. Ini bukan UAT manual oleh petugas klinik dan bukan pemeriksaan otomatis berbasis OCR.

| Gambar | Hasil pemeriksaan visual |
| --- | --- |
| SS-01 transfer | Dua item terisi, nama obat, kuantitas, lokasi, dan perkiraan FEFO terbaca. Form sebelum posting. |
| SS-01 hasil transfer | Diambil ulang agar kedua baris hasil transaksi, referensi sama, batch, dan kuantitas terlihat; bukan hanya notifikasi sukses. |
| SS-02 proses petugas | Dua hasil hitung tersimpan dan satu kosong; peringatan pemeriksaan sebagian terbaca. |
| SS-02 pemeriksaan bersama | Pembuat Petugas Simulasi dan pelaksana terakhir Apoteker Simulasi terlihat; tiga angka tersimpan, masih berstatus Draf. |
| SS-02 aktivitas | Tiga aktivitas terlihat utuh dengan pelaku dan waktu; proses petugas dan apoteker dapat dibedakan. |
| SS-03 unggah | Halaman unggah dan panduan terlihat; belum memilih file. Tidak diklaim sebagai hasil posting. |
| SS-03 validasi | Pemetaan belum dikonfirmasi; indikator perlu diperbaiki merupakan kondisi uji yang disengaja. Tidak terdapat exception aplikasi. |
| SS-03 hasil impor | Dua transaksi sumber dan tiga baris alokasi batch terlihat; tidak ada identitas pasien atau dokter. |
| SS-04 batch | Nomor batch, ED, dua lokasi, stok per lokasi, jumlah batch, dan status terlihat. Satu baris mengelompokkan beberapa lokasi. |
| SS-05 retur | Form terisi dengan keterangan simulasi pendek. Label batch dibatasi lebar field bawaan, tetapi nama batch/lokasi dan keterangan stok tersedia terbaca; tidak mengubah CSS aplikasi. |
| SS-05 kondisi khusus | Lokasi Apotek, batch, jumlah dua tablet, dan alasan simulasi terlihat sebelum posting. |
| SS-05 hasil kondisi | Notifikasi berhasil dan satu riwayat pengurangan dua tablet terlihat. |
| SS-06 batch sesuai lokasi | Lokasi Apotek konsisten dengan label batch dan sisa 40 tablet; tidak salah menampilkan Gudang. |
| TEST-01 PHPUnit | Seluruh tabel 12 kelompok, jumlah 213 tes / 2.347 assertions, status, sumber, dan batas SQLite terbaca dalam 900 piksel. |
| TEST-02 Playwright | Seluruh 16 baris, status, sumber hasil aktual, dan batas cakupan impor terbaca dalam 900 piksel. |

## Pemeriksaan Umum

- 15/15 PNG berukuran **1440 x 900** dan memiliki konten nonkosong berdasarkan pemeriksaan sampel warna/piksel.
- Tidak terlihat loading, overlay panduan yang menghalangi, halaman 403/419/500, atau exception backend.
- Tidak ada pengubahan DOM, CSS, watermark, penggabungan gambar, atau penyuntingan piksel screenshot fitur. Interaksi hanya berupa penggunaan form dan scroll melalui browser.
- Dua gambar hasil pengujian berasal dari laporan HTML tersendiri yang disusun dari XML/JSON aktual, dan diberi label sebagai laporan dokumentasi, bukan fitur aplikasi.
- Seluruh nama pengguna dan transaksi berasal dari fixture simulasi; tidak menampilkan password, cookie, token, identitas pasien/dokter, atau data klinik asli.
- Screenshot form sebelum posting dibedakan secara eksplisit dari screenshot hasil transaksi di indeks/caption.
- Source aplikasi tetap sama dengan baseline; perubahan lama di worktree yang sudah ada sebelum tugas ini tidak dibatalkan atau dimasukkan ke pekerjaan ini.
- `Run.json` mencatat hash gambar lama tetap sama dan database sementara dibersihkan. Pemeriksaan read-only tambahan pada `Cleanup_Verification.json` mendapati **0** database sementara dengan pola runner evidence/impor.
- Paket gambar bertanggal berisi salinan PNG **byte-identik** dengan sumber evidence, bukan hasil pemrosesan ulang gambar.

Hash dan pemeriksaan piksel masing-masing file tersedia pada [manifest.json](manifest.json). Batas pengujian dan kendala runner dicatat di [README.md](README.md).
