# Kendala Dan Verifikasi Pengambilan

Catatan ini adalah riwayat pengambilan awal. Bug label batch lintas lokasi di bawah sudah ditindaklanjuti melalui [perbaikan dan regression test final](../Capstone_Testing_Final/Laporan_Perbaikan_Bug.md), dengan [hasil aktual terpisah](../Capstone_Testing_Final/Ringkasan_Pengujian.md). Bukti awal tetap dipertahankan tanpa memodifikasi gambar.

## Pengambilan Awal

- Validasi nama pengguna pada script Playwright terlalu ketat karena username dan email berada pada satu elemen. Selector diperbaiki pada script dokumentasi.
- Role combobox cocok dengan elemen select asli dan input pencari sekaligus. Script diperjelas untuk memilih input pencari yang benar-benar dipakai pengguna.
- Halaman fitur tetap merespons. Kegagalan di atas merupakan kendala otomasi, bukan bukti kegagalan modul aplikasi.
- Penghentian server melalui wrapper artisan tertahan pada Windows. Hanya proses server capstone yang sudah diverifikasi berdasarkan command line/port uji yang dihentikan; runner kemudian menghapus database simulasinya. Pengambilan ulang menggunakan server PHP lokal secara langsung dengan router resmi Laravel, tanpa mengubah source fitur.

Pengambilan ulang dilakukan pada database simulasi baru. Status dan file yang diserahkan mengikuti manifest pengambilan terakhir, bukan hasil parsial pengambilan awal.

## Batas Pembuktian

### Temuan UI Aktual: Label Batch Kondisi Khusus

Pada pemeriksaan visual, form Kondisi Khusus dengan lokasi Apotek dapat menampilkan label batch Gudang ketika nomor batch yang sama tersedia di kedua lokasi. Konteks stok di bawah form mengikuti Apotek, tetapi teks pilihan batch dapat tidak konsisten. Bukti screenshot asli disimpan di [Bukti_Kendala/Kondisi_Khusus_Label_Batch_Apotek.png](Bukti_Kendala/Kondisi_Khusus_Label_Batch_Apotek.png).

Temuan ini bukan kegagalan locator: ketidaksesuaian tampak pada UI asli. Tidak dilakukan perubahan source fitur atau manipulasi HTML. Gambar 13 utama menggunakan contoh lokasi Gudang dengan label yang konsisten, melalui pilihan UI biasa. Gambar itu hanya membuktikan tampilan form untuk kasus tersebut; tidak membuktikan seluruh kombinasi lokasi/batch atau keberhasilan posting. Pengujian ulang dan perbaikan label lintas lokasi perlu menjadi tindak lanjut terpisah.

### Lingkungan Eksekusi

Pembatasan proses pada Windows/sandbox juga menahan penghentian otomatis server PHP langsung. Setelah pengambilan selesai, proses server uji dikenali dari command line dan port khususnya, lalu dihentikan secara terbatas. Runner kemudian melanjutkan cleanup database simulasi. Tidak ada layanan klinik yang dihentikan. Ini dicatat sebagai kendala lingkungan otomasi, bukan kendala fitur aplikasi.

Screenshot form yang terisi tetapi belum dikirim membuktikan tampilan input, bukan keberhasilan posting transaksi tersebut. Riwayat dan stok yang ditampilkan berasal dari transaksi simulasi yang diproses controller/service aplikasi. Pengujian otomatis dijalankan terpisah dengan hasil aktual dan tidak menggunakan database klinik.

Tidak ada pengubahan HTML, penggabungan screenshot, penghilangan error melalui manipulasi halaman, atau perubahan source fitur untuk pengambilan gambar.

## Status Akhir

### Penyajian Tabel Hasil Pengujian

Gambar 19 diperbarui menjadi screenshot laporan HTML dokumentasi terpisah agar mudah dibaca, bukan penambahan halaman fitur aplikasi. Ringkasan dihitung dari JUnit XML dan log eksekusi aktual yang telah disimpan. Tidak ada pengujian baru yang diklaim dalam perubahan format ini. Keluaran mentah dan screenshot teks sebelumnya dipertahankan di Bukti_Pengujian; waktu tes dan waktu screenshot dibedakan dalam metadata. Playwright memeriksa kesesuaian total tabel, keberadaan 58 rincian kasus, dan keterlihatan catatan batasan pada resolusi 1440 x 900.

Pengambilan terakhir menggunakan commit aplikasi `edf1faa8e51f97927b1f78337c69b230b58be2d6` pada 18 Oktober 2026 (Asia/Jakarta). Seluruh 19 gambar utama dan satu pendukung berhasil ditangkap, sementara temuan UI di atas tetap terbuka. Server dokumentasi telah dihentikan dan database simulasinya dihapus setelah eksekusi; tidak ada perubahan terhadap database klinik.
