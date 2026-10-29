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

Gunakan `php scripts/uat.php migrate` untuk migrasi tambahan ke database UAT yang sudah ada. Runner memeriksa konfigurasi/nama database terlebih dahulu, tanpa seed atau reset hasil petugas. Jangan gunakan artisan dengan `.env` utama untuk memperbarui UAT. Migrasi cakupan pemeriksaan 21 Oktober dan pencatatan draf bersama 22 Oktober 2026 sudah diterapkan ke UAT workspace ini; database klinik tidak dimigrasikan.

Pada Pemeriksaan Stok > Harian, pilih **Semua obat** atau **Obat tertentu**. Pilihan kedua menyediakan pencarian dan centang obat. Daftar awal bisa diisi bertahap: kosongkan jumlah yang belum diperiksa, dan isi 0 hanya jika sudah dihitung dan benar-benar kosong. **Simpan Draf** menyimpan isian yang belum lengkap. Saat mengirim sebagian, konfirmasi menampilkan jumlah yang sudah/belum diperiksa dan menyediakan **Kirim N obat**, **Simpan Draf**, serta **Kembali**. Persetujuan hanya menyesuaikan stok obat yang benar-benar sudah diperiksa; obat lain tidak dianggap kosong. Satu laporan per tanggal/lokasi tetap berlaku; tombol mulai membuka laporan yang sudah ada. Ini tetap hitung fisik, bukan pengajuan pembelian terpisah tanpa perubahan stok.

Penjelasan **Memahami angka pemeriksaan stok** tersedia sebelum form mulai pemeriksaan. Stok awal adalah posisi persediaan saat mulai memakai sistem, bukan isian ulang harian. Stok sistem berasal dari transaksi yang dicatat; jumlah fisik berasal dari hitungan langsung pada lokasi pemeriksaan. Jika sistem 10 tablet tetapi fisiknya 2, jumlah fisik diisi 2 dan setelah persetujuan stok menjadi 2, bukan 8. Obat terpakai tidak otomatis mengurangi sistem tanpa pencatatan pengeluaran. Pada halaman hitung, buka **Stok sistem dan jumlah fisik** untuk membaca pengingat tanpa meninggalkan isian.

Judul kolom **Obat**, **Stok sistem**, **Jumlah fisik / satuan**, dan **Status** memiliki ikon bantuan. Arahkan kursor atau fokuskan judul dengan keyboard untuk membaca artinya; klik mempertahankan penjelasan, dan Escape atau klik di luar menutupnya. Pada HP, tombol bantuan kolom tersedia di dalam **Stok sistem dan jumlah fisik**. Kolom tinjauan apoteker juga menjelaskan hasil tersimpan dan perubahan jika disetujui; perubahan tersebut bukan jumlah pemakaian pasien. Membuka bantuan tidak menyimpan atau mengubah hitungan.

Draf dapat dilanjutkan bersama oleh petugas dan kepala apoteker yang memiliki izin operasional. Buka laporan yang sama dari daftar; nama **Dibuat oleh** tidak berubah meskipun pengguna lain menyimpan pekerjaan. **Dikerjakan terakhir** menunjukkan penyimpan terakhir, **Rincian** menunjukkan pembaru terakhir per obat, dan tab **Aktivitas** mencatat pembuat, penyimpanan, penambahan, pengiriman, serta tinjauan beserta nama dan waktunya. Riwayat sebelum fitur ini tersedia ditandai **Belum tercatat**, bukan diisi ulang dengan perkiraan. Pembatalan draf tetap hanya untuk pembuat.

Jika pengguna lain sudah menyimpan sejak halaman dibuka, sistem menolak isian versi lama agar tidak menimpa pekerjaan terbaru. Isian yang belum tersimpan tetap ditampilkan; gunakan **Buka versi terbaru di tab baru**, bandingkan dengan isian Anda, lalu lanjutkan dari versi terbaru. Jangan langsung memuat ulang tab lama sebelum memeriksa isian yang masih diperlukan. Tidak ada penggabungan atau penyimpanan otomatis.

Jika tabel tidak memiliki kolom input, baca penjelasan **Mode lihat saja**. Guest atau akun tanpa izin operasional tetap hanya baca. Laporan terkirim harus dikembalikan oleh apoteker melalui **Kembalikan untuk Koreksi** sebelum petugas atau apoteker mengisi lagi. Laporan yang sudah disetujui tetap terkunci. Jangan mereset data atau mengubah matriks hak akses untuk membuka laporan yang sudah terkunci.

Tab **Daftar Obat** menampilkan obat, stok sistem saat halaman dibuka, jumlah fisik, dan satuan. Daftar awal menampilkan 10 obat per halaman; pencarian, filter, dan pergantian halaman tidak menghapus isian. Progres menghitung semua obat dalam laporan, bukan hanya halaman yang terlihat. Status **Belum disimpan** berarti perlu menekan **Simpan Draf** sebelum keluar. Membuka tab lain tidak otomatis menyimpan.

Buka **Rincian** pada obat untuk mengisi hasil per batch, menandai stok menipis, menentukan jumlah diminta, atau memberi catatan. Jika hitungan batch sudah diisi, jumlah fisik dijumlahkan otomatis; ubah angkanya melalui Rincian. Saat ada isian bermasalah, tombol perbaikan pada peringatan akan membuka halaman dan rincian yang sesuai. Pada HP kecil, ikon **+** membuka Tambah Obat dan isian jumlah/satuan ditampilkan satu baris penuh.

Kepala apoteker meninjau laporan terkirim melalui tab **Tinjauan Apoteker** sebelum menyetujui atau mengembalikan untuk koreksi. Tab **Ringkasan** berisi rekap terakhir yang disimpan untuk disalin ke WAG, bukan angka yang belum disimpan. Tabel mengikuti lebar area halaman di desktop dan berubah susunan sesuai ruang yang tersedia pada tablet/HP; margin halaman tidak dipersempit untuk mengatasi monitor besar.

Untuk menambah obat yang tertinggal, buka draf yang sedang dikerjakan lalu tekan **Tambah Obat**, cari dan centang obat, kemudian **Tambahkan ke Pemeriksaan**. Isian yang sedang dikerjakan ikut disimpan sebagai draf, termasuk rincian batch dan catatan; tidak perlu membatalkan laporan. Obat baru belum dianggap kosong sampai jumlahnya benar-benar diisi. X, Batal, atau Escape menutup pilihan tanpa mengubah isian. Setelah laporan dikirim/disetujui, penambahan terkunci. Laporan terkirim yang dikembalikan apoteker untuk koreksi dapat diperbarui lagi oleh petugas atau apoteker berizin operasional.

**Satuan Hitung** berbentuk dropdown dan otomatis mengikuti satuan stok di Data Obat, bukan satuan pembelian box/strip. Jika master belum memiliki satuan, pilih referensi yang sesuai dan lengkapi master lewat apoteker sebelum mencatat hasil lebih dari 0. Jika satuan draf lama berbeda, periksa kembali jumlahnya lalu pilih satuan stok; sistem tidak otomatis mengonversi angka lama.

**Cetak / Simpan PDF** membuka laporan pemeriksaan khusus A4 portrait di tab baru, bukan mencetak halaman aplikasi. Pada pratinjau, tekan tombol cetak lalu pilih printer atau Save as PDF. Laporan memakai data terakhir yang disimpan, mencakup seluruh obat dalam laporan meskipun tabel layar sedang difilter/dibagi halaman. Bila masih ada perubahan, sistem mengingatkan untuk Simpan Draf dahulu. Draf tetap berlabel belum disetujui; identitas klinik, status, serta penanda simulasi UAT ikut tercetak.

Widget **Kosong / Menipis** dan **Perlu Ditindaklanjuti** di dashboard memakai stok transaksi dan batas minimum yang diatur per obat/lokasi, bukan menunggu pemeriksaan harian disetujui. Klik item untuk membuka Monitoring pada obat dan lokasi tersebut. Angka dihitung per obat/lokasi, sehingga obat yang sama di dua lokasi dapat dihitung dua kali. Obat tanpa batas minimum belum termasuk penilaian ini. Tabel stok dashboard menampilkan maksimal 12 baris positif terbaru; pencarian hanya mencakup baris tersebut. Status Tersedia berarti jumlah lebih dari nol, bukan otomatis di atas minimum.

## Akun Simulasi

### Pencatatan Obat Keluar

Pengeluaran untuk pelayanan atau pemakaian internal dicatat lewat **Pengeluaran Obat**, bukan menunggu pemeriksaan stok. ShifaStock tidak terhubung ke Neoklinik, sehingga catatan di sana tidak otomatis mengurangi stok ShifaStock. Untuk pengeluaran dari Apotek, lokasi sumbernya Apotek; jumlah adalah yang keluar, bukan sisanya. Contoh stok 20, keluar 3: jumlah diisi 3 dan setelah berhasil disimpan stok menjadi 17.

Referensi transaksi otomatis, sedangkan referensi sumber opsional dapat berisi nomor resep/rekap tanpa identitas pasien. Batch kosong memakai FEFO yang masih layak. Penyimpanan berhasil langsung mengurangi stok sumber/batch dan mencatat mutasi kartu stok, tanpa persetujuan pemeriksaan harian. Jangan mengulangi pengeluaran yang sama, termasuk yang sudah tercakup dalam penyesuaian pemeriksaan. Transfer antar lokasi dan retur supplier tetap melalui menu masing-masing. Panduan ini juga tersedia di atas form Pengeluaran Obat.

### Jika Login Menampilkan 419

419 berarti token keamanan formulir tidak cocok dengan sesi yang diterima server, bukan pesan bahwa password salah. Halaman yang lama terbuka, logout/login di tab lain, sesi kedaluwarsa, atau cookie yang tidak terbawa dapat menjadi penyebab; penyebab kasus tertentu perlu diperiksa, bukan langsung disimpulkan.

1. Buka ulang `https://shifastock.nexspace.my.id/login` melalui alamat/tab baru, lalu isi ulang login. Jangan sekadar refresh halaman 419 karena dapat mengirim formulir lama kembali.
2. Gunakan domain HTTPS yang sama secara konsisten, tanpa bergantian dengan alamat HTTP lokal saat menguji sesi. Port 4002 tetap merupakan backend lokal untuk domain tersebut.
3. Jika halaman baru masih 419, bandingkan dengan jendela Incognito. Catat alamat, waktu, browser/perangkat, serta apakah error langsung muncul atau sesudah halaman lama terbuka, lalu laporkan ke pengelola. Jangan mengirim password, cookie, atau token ke dokumentasi publik.
4. Untuk transaksi selain login, periksa riwayat sebelum mengulang agar tidak membuat transaksi ganda.

Pada pemeriksaan 22 Oktober 2026, formulir baru di domain HTTPS berhasil melewati token CSRF dan menampilkan validasi kredensial untuk akun uji yang tidak ada. Ini bukan bukti login akun valid ataupun penyelesaian penyebab pada sesi pengguna. Tidak ada penghapusan sesi massal, penurunan keamanan cookie, atau pengecualian CSRF.

### Daftar Akun

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

## Impor Pengeluaran Excel Neoklinik

Gunakan **Pengeluaran Obat > Impor & Riwayat Excel**. Form manual tetap tersedia; jangan catat manual transaksi yang sama dengan yang akan diimpor.

1. Pilih ekspor `.xlsx` asli dengan satu sheet dan 16 kolom, maksimal 4 MB/2.000 baris. Untuk UAT, gunakan workbook sintetis atau sumber yang sudah dianonimkan; jangan mengunggah identitas pasien ke lingkungan demo publik.
2. Pilih lokasi stok asal dan **Mulai pencatatan (WIB)**. Ini bukan waktu unggah: pilih awal periode pengeluaran yang belum diperhitungkan dalam posisi stok ShifaStock.
3. Contoh: hitung fisik Apotek pukul 08.00 menghasilkan sisa 100 tablet. Jangan impor pengeluaran sebelum hitungan tersebut karena sudah tercakup dalam sisa 100; impor hanya pengeluaran sesudahnya. Posting pertama menetapkan batas lokasi, dan file periode berikutnya tetap dapat diimpor.
4. Tekan **Baca File & Lanjutkan**. Langkah **Cocokkan obat** hanya meminta pilihan baru atau yang sudah tidak valid. Periksa produk, kekuatan, dan satuan. Contoh **1 BUTIR pada Excel berisi: 1 Tablet** berarti setiap satu butir akan mengurangi satu tablet, bukan satu box. Kode cocok hanya saran, belum persetujuan.
5. Centang **Benar** untuk pilihan yang sudah diperiksa, lalu **Simpan Pilihan & Lanjutkan**. Pilihan tersimpan dapat dibuka melalui **Tampilkan juga pilihan tersimpan**; jika semuanya masih valid, unggahan berikutnya langsung menuju pemeriksaan. **Tandai pilihan yang tampil dan sudah saya periksa** bersifat opsional dan tidak mencentang pilihan kosong atau yang tersembunyi. Mengubah obat/isi per satuan membatalkan centang baris tersebut agar diperiksa lagi.
6. Pada **Periksa & simpan**, periksa ringkasan dan jumlah stok yang akan keluar; buka rincian bila perlu. Peringatan dikelompokkan menurut penyebab, dengan jumlah transaksi dan petunjuk perbaikannya. Filter **Perlu diperiksa** hanya mengatur tampilan, bukan melewatkan transaksi bermasalah. Data berubah, stok/batch tidak cukup, satuan/master bermasalah, atau transaksi sebelum stok awal/hitung fisik tetap memblokir penyimpanan.
7. Setelah masalah selesai, isi konfirmasi obat diserahkan, arti jumlah racikan, lokasi/FEFO, periode/WIB, dan identitas sumber. Tekan **Simpan Pengeluaran & Kurangi Stok**, lalu konfirmasi SweetAlert. Baru langkah ini membuat mutasi/kartu stok dan audit; unggah serta menyimpan pilihan obat belum mengubah stok.
8. Duplikat tidak dikurangi lagi, termasuk ekspor overlap atau lokasi berbeda. Koreksi/retur/pembatalan sumber tidak diterapkan otomatis. Gunakan peninjauan dan pembalikan terotorisasi, bukan mengubah nomor jual agar bisa diimpor lagi.

Pada lokasi yang sudah memiliki batas mulai pencatatan, pengaturan tanggal disembunyikan dalam bagian yang dapat dibuka; tanggal tetap terlihat pada ringkasannya dan tidak diganti otomatis. Jangan memundurkan batas hanya untuk meloloskan file lama. Riwayat berada di bagian **Riwayat impor** yang dapat dibuka, sehingga tidak memenuhi form awal. Pada HP, baris pencocokan dan pemeriksaan tersusun vertikal.

Pada desktop lebar, daftar obat berada di kiri, sedangkan petunjuk, ringkasan, dan tombol lanjut/simpan berada di panel kanan. Gulir daftar obat untuk memeriksa baris berikutnya; tombol pencocokan tetap di panel, bukan di ujung daftar. Pada layar lebih kecil, panel berpindah ke bawah daftar agar isian tidak terlalu sempit. Menggulir atau mengganti susunan layar tidak menyimpan pilihan dan tidak mengurangi stok.

Daftar pencocokan/pemeriksaan langsung dimulai di bawah tab menu. Nomor IMP, lokasi, langkah proses, dan pesan status berada di panel informasi; buka **Detail file** untuk melihat periode, jumlah penjualan, dan baris racikan. Buka **Petunjuk pencocokan** bila membutuhkan penjelasan obat/satuan. Kedua bagian tersebut hanya membuka informasi, bukan menyimpan atau menyetujui pilihan.

**No.** adalah nomor urut ringkasan obat, bukan nomor penjualan. **Tanggal Jual (WIB)** berasal dari transaksi Excel; jika satu obat memiliki beberapa waktu, kolom menampilkan waktu pertama sampai terakhir. Buka **Rincian N transaksi dari Excel** di bawah obat untuk melihat **Baris Excel**, waktu lengkap, kode/nama, jumlah asli, satuan, status racikan, dan hasil pemeriksaan per transaksi. Baris Excel mengikuti posisi pada file asli (baris 1 adalah judul); urutan rinciannya mengikuti file, bukan urutan tanggal. Nama pasien/dokter dan identitas penjualan/racikan asli tetap tidak ditampilkan.

Ketik kode atau nama pada **Cari obat pada Excel** untuk menemukan obat tanpa menggulir seluruh daftar. Pencarian tidak menghapus centang, obat pilihan, atau angka yang sudah diisi; mengosongkan pencarian menampilkan daftar lagi sesuai pengaturan pilihan tersimpan. Tombol tandai bersama hanya mencentang obat yang sedang tampil dan memiliki pilihan/isi satuan valid. Saat menyimpan, seluruh pilihan yang sudah dicentang ikut disimpan, termasuk yang sedang tidak terlihat karena pencarian. Pencarian, membuka rincian, dan menyimpan pencocokan tetap belum mengurangi stok.

**Pilihan obat dan satuan perlu dikonfirmasi** tidak berarti obat pasti salah. Sistem belum memiliki persetujuan yang masih valid untuk obat/satuan tersebut. Pada **Cocokkan obat**, pilih obat sesuai nama, bentuk dan dosis, periksa isi per satuan, centang **Benar**, lalu **Simpan Pilihan & Lanjutkan**. **Saran otomatis; belum dikonfirmasi** berarti dropdown yang sudah terisi tetap perlu diperiksa dan disimpan. **Pilihan lama perlu diperiksa ulang** berarti data obat/satuannya perlu ditinjau kembali sebelum menyetujui ulang. Buka **Cara memperbaiki** pada status rincian untuk membaca tindakan yang diperlukan tanpa meninggalkan halaman. Selama belum lolos pemeriksaan, stok belum dikurangi.

**Tidak perlu unggah ulang setelah membuka Input Manual.** Selama sesi pengguna yang sama, tab **Impor & Riwayat Excel** dari Input Manual kembali ke dokumen dan langkah yang terakhir dibuka. Tombol **Lanjutkan pemeriksaan** juga tersedia pada Input Manual dan halaman unggah jika ada pratinjau aktif. File yang sudah dibaca serta pilihan yang sudah disimpan tetap tersedia; tidak ada pengurangan stok karena pindah tab.

Isian/centang yang belum disimpan bukan simpan otomatis. Jika peringatan **Ada isian yang belum disimpan** muncul, pilih **Tetap di halaman**, simpan pilihan, baru pindah. **Keluar tanpa menyimpan** tetap membuang isian lokal yang belum disimpan, tetapi tidak menghapus dokumen Excel. Tombol lanjut tidak muncul untuk dokumen terposting, dihapus, atau kedaluwarsa. Setelah sesi berakhir, buka dokumen draf melalui Riwayat Impor; batas kedaluwarsa tujuh hari tetap berlaku.

File asli tidak diarsipkan. Kolom pasien/dokter dibuang; data persediaan yang diperlukan untuk pratinjau dan jejak konversi disimpan terenkripsi. Pratinjau yang tidak diposting kedaluwarsa setelah tujuh hari; operator scheduler menjalankan `php artisan issues:purge-import-drafts` setiap hari. Riwayat posting dan identitas pencegah duplikasi tidak ikut dihapus.

**Hapus pratinjau:** ikon tempat sampah tersedia di kolom Aksi pada riwayat dan di bagian atas detail untuk draf/pratinjau kedaluwarsa. Konfirmasi SweetAlert menyebut nomor IMP; Batal mempertahankan pratinjau. Hapus tidak mengubah stok, pemetaan tersimpan, atau batas mulai pencatatan dan dicatat di audit. Hanya pengguna dengan izin operasikan persediaan yang dapat menghapus; Guest tidak bisa. Dokumen terposting, termasuk posting yang seluruhnya duplikat, tidak dapat dihapus karena riwayat dan pengaman duplikasi harus dipertahankan. Tidak ada pemulihan pratinjau yang dihapus; unggah ulang file jika masih diperlukan.

Migration tabel impor diterapkan melalui `php scripts/uat.php migrate`, bukan seed/reset database. Jika menu tampil 500 karena tabel `issue_imports` belum ada, jalankan migrasi additive pada environment yang benar, lalu refresh. Jangan menggunakan `migrate:fresh` pada UAT persisten atau klinik.

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
