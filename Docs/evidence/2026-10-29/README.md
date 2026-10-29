# Evidence Implementasi dan Pengujian ShifaStock

Dokumentasi tambahan untuk Capstone Project Universitas Terbuka, Tugas II, subtab **03. Dokumentasi Implementasi dan Pengujian Sistem**.

**Paket gambar satu folder sesuai permintaan:** [Docs/Capstone_Screenshots/2026-10-29](../../Capstone_Screenshots/2026-10-29/README.md). Seluruh 15 PNG berada langsung di folder tersebut, dengan nama Gambar 22-29 dan pendamping a/b. Folder evidence ini menyimpan sumber, log, dan rincian teknis.

## Identitas dan Keamanan

- Baseline yang diperiksa: `05ba910ce8804ffc9e049a17e96295bb59714cb9` (`05ba910`).
- Tanggal pengambilan: **29 Oktober 2026**, zona waktu Asia/Jakarta (WIB).
- Lingkungan: Windows lokal, PHP **8.5.9**, Laravel **13.35.0**, Google Chrome headless melalui Playwright.
- Seluruh PNG beresolusi **1440 x 900**, device scale factor 1; bukan gambar hasil penyuntingan atau mockup fitur.
- Data seluruhnya **simulasi**. Akun Petugas Simulasi dan Apoteker Simulasi dibuat khusus dokumentasi, dengan password acak yang hanya diteruskan melalui environment proses.
- PHPUnit menggunakan SQLite `:memory:`. Browser dan pemeriksaan konkurensi menggunakan database MySQL sementara yang terpisah dari database klinik/UAT; database sementara dibersihkan setelah selesai.
- Tidak memakai workbook operasional Neoklinik, data pasien, atau dokter asli. Workbook pengujian dibuat oleh runner; penanda identitas sintetisnya tidak muncul pada screenshot.
- Tidak mengubah fitur, UI, aturan FEFO, hak akses, atau struktur database aplikasi. Saat pengambilan evidence tidak ada commit, push, deployment, atau perubahan dokumen Google Docs. Publikasi dokumentasi kemudian diizinkan melalui permintaan terpisah pengguna; commit publikasi bukan baseline pengujian.
- Gambar lama tetap dipertahankan: **22 file PNG** untuk nomor Gambar 01-21, termasuk tambahan Gambar 02b. Hash file lama diperiksa sebelum dan sesudah pengambilan.

## Indeks Gambar Utama

Nomor 22-29 merupakan **usulan** kelanjutan 21 nomor gambar sebelumnya. Semua baris di bawah berstatus **berhasil**; waktu per file dan SHA-256 tercantum pada [manifest.json](manifest.json).

| Usulan nomor | ID / modul | File | Role | Isi dan status yang benar-benar terlihat |
| --- | --- | --- | --- | --- |
| Gambar 22 | SS-01 / Transfer multi-item | [Form transfer](screenshots/SS-01-transfer-multi-item.png) | Petugas Simulasi | Dua obat, jumlah 12 dan 8 tablet, tujuan Apotek, perkiraan alokasi FEFO. Form diambil **sebelum** disimpan; hasil posting ada pada gambar pendamping. |
| Gambar 23 | SS-02 / Pemeriksaan harian bersama | [Draf bersama](screenshots/SS-02-pemeriksaan-harian.png) | Apoteker Simulasi | Draf dibuat petugas dan dilanjutkan apoteker. Tiga hasil hitung tersimpan; pembuat dan pelaksana terakhir terlihat. Status masih **Draf**, bukan disetujui. |
| Gambar 24 | SS-03 / Impor Excel Neoklinik | [Hasil impor](screenshots/SS-03-import-neoklinik.png) | Petugas Simulasi | Dua transaksi sumber berhasil diposting; transaksi pertama dialokasikan ke dua batch FEFO. Referensi dan tautan kartu stok terlihat. |
| Gambar 25 | SS-04 / Stok per batch | [Monitoring batch](screenshots/SS-04-stok-per-batch.png) | Petugas Simulasi | Dua batch, ED, jumlah per lokasi Gudang/Apotek, total batch, dan indikator mendekati ED. |
| Gambar 26 | SS-05 / Retur dan kondisi khusus | [Form obat rusak](screenshots/SS-05-retur-kondisi-obat.png) | Petugas Simulasi | Dua tablet rusak di Apotek, batch dan keterangan simulasi; form **sebelum posting**. Form retur serta hasil kondisi khusus tersedia sebagai pendamping. |
| Gambar 27 | SS-06 / Dropdown batch sesuai lokasi | [Batch Apotek](screenshots/SS-06-dropdown-batch.png) | Petugas Simulasi | Lokasi Apotek dan label batch Apotek, stok batch 40 tablet. Diverifikasi setelah berpindah dari Gudang pada nomor batch yang sama. |
| Gambar 28 | TEST-01 / PHPUnit | [Tabel PHPUnit](testing/TEST-01-phpunit.png) | Runner pengujian | Ringkasan aktual 213 tes dan 2.347 assertions; rincian dikelompokkan dari JUnit tanpa penghitungan ganda. |
| Gambar 29 | TEST-02 / Playwright | [Tabel Playwright](testing/TEST-02-playwright.png) | Petugas Simulasi / Guest Simulasi melalui runner | Enam belas kelompok skenario browser **khusus impor** lulus, bukan klaim 16 skenario untuk seluruh modul. |

## Gambar Pendamping

Tujuh gambar berikut melengkapi bukti proses/hasil yang tidak cukup dijelaskan satu gambar utama. Seluruhnya **berhasil**, tanggal dan baseline sama dengan gambar utama.

| Usulan nomor | File | Role | Caption dan penjelasan |
| --- | --- | --- | --- |
| Gambar 22a | [Hasil transfer](supporting/SS-01-hasil-transfer.png) | Petugas Simulasi | **Hasil Posting Transfer Dua Item Obat dalam Satu Referensi.** Riwayat menunjukkan dua obat dengan referensi yang sama, perpindahan Gudang ke Apotek, jumlah, dan batch hasil alokasi FEFO. |
| Gambar 23a | [Proses petugas](supporting/SS-02-proses-petugas.png) | Petugas Simulasi | **Penyimpanan Sebagian Hasil Pemeriksaan oleh Petugas Farmasi.** Dua obat telah diperiksa dan satu belum diperiksa; isian kosong tidak diperlakukan sebagai stok habis. |
| Gambar 23b | [Aktivitas bersama](supporting/SS-02-aktivitas-bersama.png) | Apoteker Simulasi | **Jejak Aktivitas Kolaborasi Pemeriksaan Stok Harian.** Petugas membuat laporan dan mengisi dua obat; apoteker kemudian melengkapi satu obat pada draf yang sama. |
| Gambar 24a | [Halaman unggah](supporting/SS-03-form-impor.png) | Petugas Simulasi | **Halaman Unggah File Pengeluaran Obat dari Excel Neoklinik.** Menampilkan pemilihan file, lokasi, batas mulai pencatatan, dan penjelasan pencegahan pencatatan ganda. File belum dipilih pada gambar ini. |
| Gambar 24b | [Validasi pencocokan](supporting/SS-03-validasi-pencocokan.png) | Petugas Simulasi | **Validasi Pencocokan Obat dan Satuan Sebelum Posting Impor.** Pemetaan belum dikonfirmasi sehingga dua transaksi belum siap dan belum mengurangi stok; ini kondisi validasi yang disengaja, bukan kegagalan modul. |
| Gambar 26a | [Form retur](supporting/SS-05-retur-pbf.png) | Petugas Simulasi | **Pencatatan Retur Obat kepada PBF Berdasarkan Batch dan Lokasi.** Form sebelum posting retur tiga tablet dari Gudang ke PBF Simulasi; pengurangan stok kemudian diverifikasi melalui backend. |
| Gambar 26b | [Hasil kondisi khusus](supporting/SS-05-hasil-kondisi-khusus.png) | Petugas Simulasi | **Hasil Posting Pengurangan Stok Akibat Kondisi Khusus Obat Rusak.** Notifikasi berhasil dan riwayat memperlihatkan pengurangan dua tablet pada batch di Apotek. |

## Caption Gambar Utama

**Gambar 22. Tampilan Transfer Obat Multi-Item pada Sistem ShifaStock.**
Menunjukkan pengisian dua item obat dalam satu transaksi dari Gudang ke Apotek, dengan pemilihan batch otomatis berdasarkan kedaluwarsa terdekat.

**Gambar 23. Pencatatan Pemeriksaan Stok Harian secara Kolaboratif.**
Menunjukkan hasil hitung yang disimpan pada draf buatan petugas dan dilanjutkan apoteker, dengan identitas pembuat serta pelaksana terakhir tetap tercatat.

**Gambar 24. Hasil Impor Pengeluaran Obat dari Excel Neoklinik.**
Menunjukkan posting dua transaksi sumber menggunakan file simulasi, alokasi pengeluaran per batch FEFO, serta akses riwayat mutasi melalui kartu stok.

**Gambar 25. Monitoring Persediaan Obat Berdasarkan Batch dan Lokasi Penyimpanan.**
Menunjukkan nomor batch, tanggal kedaluwarsa, stok Gudang dan Apotek, serta status prioritas penggunaan setelah transaksi simulasi diposting.

**Gambar 26. Form Pencatatan Kondisi Khusus Obat Rusak.**
Menunjukkan data obat, lokasi Apotek, batch, jumlah rusak, dan keterangan sebelum transaksi pengurangan stok disimpan.

**Gambar 27. Verifikasi Pemilihan Batch Sesuai Lokasi Stok.**
Menunjukkan batch dengan label Apotek dan jumlah tersedia yang sesuai, walaupun nomor batch yang sama juga memiliki stok di Gudang.

**Gambar 28. Ringkasan Hasil Eksekusi Pengujian Otomatis PHPUnit.**
Menunjukkan 213 tes dan 2.347 assertions yang lulus pada eksekusi ulang tanggal 29 Oktober 2026, disusun sebagai tabel dari artefak JUnit aktual.

**Gambar 29. Ringkasan Hasil Pengujian Browser Impor Excel dengan Playwright.**
Menunjukkan 16 kelompok skenario impor yang lulus, mencakup pencocokan, posting FEFO, perlindungan duplikasi, validasi, responsivitas, dan akses guest.

## Hasil Pengujian Aktual

| Jenis verifikasi | Hasil | Sumber |
| --- | --- | --- |
| PHPUnit / SQLite terisolasi | **213 tes, 2.347 assertions, 0 gagal, 0 error, 0 dilewati**; waktu JUnit 80,68 detik | [JUnit XML](testing/phpunit-results.xml), [log PHPUnit](testing/PHPUnit.log) |
| Playwright impor / MySQL terisolasi | **16/16 kelompok lulus**, 0 error JavaScript yang dicatat runner | [Hasil browser](testing/Playwright.json), [log browser](testing/Playwright.log) |
| Verifikasi MySQL impor | **Lulus**: total stok dan jumlah stok batch sama-sama 80; tiga baris sumber unik; mencakup posting bersamaan dan retry tanpa duplikasi | [Hasil MySQL](testing/MySQL.json) |
| Browser alur fitur tambahan | **5/5 kelompok lulus**: SS-01, SS-02, SS-04, SS-05, SS-06 | [Hasil pengambilan fitur](testing/Feature_Capture.json), [log fitur](testing/Feature_Capture.log) |
| Rekonsiliasi fixture setelah transaksi | **0 ketidaksesuaian stok, 0 masalah identitas batch**; Paracetamol Gudang 185 / Apotek 80, Cetirizine Gudang 72 / Apotek 28 | [Rekonsiliasi](testing/Capture_Reconciliation.json) |
| Frontend build | **Berhasil** | [Log build](testing/Build.log) |
| Ukuran dan isi gambar | **15/15 PNG** berukuran 1440 x 900, tidak kosong; hash dan pemeriksaan sampel piksel tercatat | [Manifest](manifest.json), [verifikasi visual](Verifikasi_Visual.md) |
| UAT manual oleh petugas klinik | **Tidak dilakukan pada pekerjaan ini** | Bukan klaim kelulusan UAT klinik |

Hasil tes dieksekusi ulang dalam sesi ini, mulai pukul **14.03 WIB**. Pengujian browser impor selesai sekitar **14.07 WIB**. Pengambilan gambar fitur diulang setelah koreksi runner dan pembingkaian gambar; PHPUnit dan 16 kelompok impor tidak dihitung sebagai eksekusi berulang pada pengambilan ulang tersebut. Waktu terperinci tersedia di `Run.json`, `Attempt_01_Run.json`, `Playwright.json`, dan `manifest.json`.

Tabel PNG pengujian adalah **laporan dokumentasi** yang dirender dari XML/JSON aktual, bukan fitur pelaporan baru dalam aplikasi. HTML asal tersedia pada `testing/TEST-01-phpunit.html` dan `testing/TEST-02-playwright.html`.

## Catatan dan Batas Verifikasi

1. Semua enam kategori fitur yang diminta berhasil didokumentasikan. Tidak ada fitur aplikasi yang diubah untuk menghasilkan gambar.
2. Pemeriksaan bersama berhenti pada draf tersimpan. Screenshot tidak membuktikan persetujuan apoteker atau finalisasi stock opname; keduanya bukan alur screenshot tambahan yang dipilih di sini.
3. Kondisi khusus yang diposting pada sesi browser ini adalah **obat rusak**, bukan contoh posting obat kedaluwarsa. Monitoring memperlihatkan batch mendekati ED dan batch aman, bukan batch yang sudah kedaluwarsa.
4. Stok per batch ditampilkan aplikasi sebagai **satu baris per batch**, dengan beberapa lokasi dalam baris yang sama. Dua batch di dua lokasi berarti dua baris, bukan empat baris transaksi.
5. Impor merupakan pembacaan file Excel, bukan integrasi API atau sinkronisasi langsung dengan Neoklinik. Tidak memakai file klinik asli untuk evidence.
6. Percobaan awal pengambilan gambar gagal karena runner belum melewati panduan login pertama, belum menekan konfirmasi SweetAlert transfer, dan salah mengasumsikan jumlah baris monitoring batch. Catatan aktualnya dipertahankan di `testing/Attempt_0*_*.json`; itu kendala runner, bukan bug fitur baru. Hasil akhir terdapat pada `Run.json` dan `Feature_Capture.json`.
7. Penghentian server sementara di Windows sempat terkendala wrapper proses. Server khusus evidence dihentikan melalui port loopback milik runner; tidak menghentikan server UAT/klinik. Runner dokumentasi sudah menggunakan identifikasi listener sementara untuk pembersihan berikutnya.
8. Label batch pada form retur mengikuti lebar input aplikasi; detail stok lengkap tetap terbaca pada keterangan stok tersedia. Tidak memperlebar field atau mengubah CSS aplikasi demi screenshot.

## Reproduksi

Skrip dokumentasi berada pada `reproduce/`. Memerlukan runtime PHP proyek, MySQL lokal dengan izin membuat database sementara, Node.js, paket Playwright yang tersedia pada `NODE_PATH`, serta Chrome pada lokasi yang tercantum dalam runner.

```powershell
php Docs/evidence/2026-10-29/reproduce/collect.php
node Docs/evidence/2026-10-29/reproduce/reports.cjs
php Docs/evidence/2026-10-29/reproduce/verify-cleanup.php
node Docs/evidence/2026-10-29/reproduce/pack.cjs
```

Jalankan dari root repository dengan baseline yang sama. Perintah pertama melakukan pengujian baru dan membuat fixture terisolasi; perintah kedua membuat tabel hasil serta manifest dari artefak eksekusi. Opsi internal `--capture-only` hanya untuk pengambilan ulang gambar dengan bukti tes yang sudah diverifikasi pada baseline/sesi yang sama. Tidak ada password yang perlu ditulis ke skrip atau dokumentasi.
