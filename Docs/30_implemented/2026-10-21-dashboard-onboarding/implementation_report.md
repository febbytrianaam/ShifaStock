# Dashboard Real Dan Panduan Login Pertama

Tanggal selesai: 21 Oktober 2026 WIB.
Basis Git yang diuji: `88835eb2670cbfbf6ad6ce5ec8dcb8d2fe59c8d7` ditambah perubahan working tree pada implementasi ini. Belum ada commit/push baru atau deployment produksi.

## Yang Diimplementasikan

- Dashboard tidak lagi menyediakan sakelar atau payload demo. Angka kosong ditampilkan sesuai database, bukan diisi simulasi.
- Total kekurangan dihitung penuh dari pemeriksaan terakhir yang disetujui per lokasi; pratinjau tetap dibatasi delapan baris. Belum ada laporan dibedakan dari laporan tanpa kekurangan.
- Batch mendekati kedaluwarsa hanya dihitung jika masih mempunyai stok. Grafik tujuh hari menghitung referensi penerimaan/transfer unik agar pembagian batch FEFO tidak menggandakan jumlah dokumen.
- Panduan Driver.js menyorot area/menu dengan latar sekeliling gelap, kontrol Lewati/Kembali/Selanjutnya/Selesai, dan tombol buka ulang di topbar. Desktop dan mobile sudah diperiksa.
- Langkah berasal dari permission akun dan menu yang tersedia. Teks menu read-only tidak mengarahkan guest untuk mengirim transaksi. Panduan tidak mengubah matriks akses, FEFO, atau stok.
- `users.onboarding_seen_at` menyimpan status selesai/dilewati. Endpoint auth+active hanya memperbarui akun yang sedang login dan idempotent; pemilihan akun lain dari payload tidak digunakan. Kegagalan penyimpanan ditampilkan pada panduan.
- Dua dependency development yang memiliki advisory critical diperbarui secara kompatibel: concurrently 9.2.4 ke 9.2.5 dan shell-quote 1.9.0 ke 1.12.0. Audit npm setelah pembaruan melaporkan nol vulnerability; ini bukan audit keamanan menyeluruh aplikasi.

## Pembersihan Lokal Yang Disetujui

Pengguna menyetujui penghapusan enam master demo tanpa sumber impor dan tiga transaksi uji pada batch `test batch 1` serta `test`. Obat resmi dengan batch uji tetap dipertahankan.

| Data | Sebelum | Sesudah | Keterangan |
| --- | ---: | ---: | --- |
| Master obat | 173 | 167 | 160 dari Excel review dan 7 dari `obat.xlsx` dipertahankan |
| Mutasi uji | 3 | 0 | Stok awal, transfer, dan penerimaan yang cocok dengan manifest |
| Dokumen/item penerimaan uji | 1 / 1 | 0 / 0 | Tidak ada item lain pada faktur tersebut |
| Batch | 8 | 0 | Enam batch seed dan dua batch uji |
| Stok total / stok batch | 3 / 3 | 0 / 0 | Tidak ada stok lain pada data lokal yang diaudit |
| Draf harian / item | 1 / 171 | 0 / 0 | Belum diisi, bukan laporan yang sudah diajukan/disetujui |
| Draf opname / item | 1 / 1 | 0 / 0 | Belum diisi, hanya memuat batch uji |

Prosedur memakai `inventory:clean-demo`: default hanya pratinjau, `--apply` melakukan backup sebelum perubahan di dalam transaksi dengan mutex persediaan. Perintah hanya mengizinkan lingkungan local/testing. Riwayat lain, reversal, batch campuran, pemeriksaan berisi hitungan, atau rekonsiliasi tidak sesuai menyebabkan pembatalan. Seluruh nilai baris master yang dipertahankan dibandingkan sebelum/sesudah.

Backup JSON inventori tersimpan di `storage/app/private/backups/before-demo-cleanup-*.json`, di luar webroot dan diabaikan Git. Tabel akun/password tidak disertakan. Akun, satuan, lokasi, dan audit log tidak dihapus. Rekonsiliasi setelah pembersihan sesuai. Hasil dry-run ulang: nol kandidat tersisa, 167 master dipertahankan. Tidak ada `truncate`, `migrate:fresh`, atau migrasi produksi.

## File Dan Modul

- Dashboard: `PrototypeController`, `resources/views/dashboard.blade.php`.
- Panduan: `OnboardingController`, `App\Support\Onboarding`, migrasi `onboarding_seen_at`, model User, layout, `resources/js/onboarding.js`, dan `resources/css/onboarding.css`.
- Pembersihan: `DemoInventoryCleanup`, `routes/console.php`, dan audit baca-saja `scripts/audit-medicine-provenance.php`.
- QA: `DashboardOnboardingTest`, `DemoInventoryCleanupTest`, `scripts/test-onboarding-browser.cjs`, dan perluasan runner browser terisolasi yang sudah ada.
- Dokumentasi current/implemented dan README diperbarui. Screenshot dan hasil akademik historis tidak diganti oleh tes implementasi ini.

## Pengujian Aktual

Eksekusi lokal pada 21 Oktober 2026 WIB, Windows, PHP 8.5.9, PHPUnit 12.5.38, MySQL, Chrome headless melalui Playwright, dan Vite 7.3.7. Semua fixture pengujian terpisah dari database klinik; database MySQL uji dibuat dengan nama acak dan dihapus sesudahnya. Password fixture acak hanya melalui environment proses, tidak masuk file hasil.

| Pengujian | Hasil | Bukti lokal diabaikan Git |
| --- | --- | --- |
| `php scripts/test-inventory.php` | 81 tes, 795 assertion lulus | `storage/app/qa/inventory/phpunit-results.xml` |
| `php scripts/test-inventory-mysql.php` | 7 skenario bersaing lulus | `storage/app/qa/inventory/mysql/` |
| `php scripts/capstone-final-browser.php onboarding` | 8/8 pemeriksaan panduan lulus | `storage/app/qa/onboarding/results.json` |
| `php scripts/capstone-final-browser.php regression` | 13/13 kasus browser operasional lulus | `storage/app/qa/inventory-browser/Browser_final.json` |
| `npm run build` | Berhasil | Build lokal `public/build/`, tidak dikomit |
| `npm audit fix --ignore-scripts` | Dua pembaruan diterapkan; nol vulnerability dilaporkan | Output aktual npm, bukan sertifikasi aplikasi |

Regresi mencakup angka real/parameter demo lama, kekurangan lebih dari delapan, batch kosong, referensi FEFO terpecah, status panduan akun lain/idempotensi, permission custom, angka pengguna aktual, perlindungan impor, dry-run, riwayat campuran, draf harian berisi, dan penolakan cleanup produksi.

Browser memeriksa login pertama, overlay, maju/mundur, Lewati tersimpan lintas sesi browser, buka ulang, selesai, empat role, serta popover/tombol pada 1440 x 900 dan 390 x 844. Stok fixture tetap Gudang 50 dan Apotek 20 sesudah panduan. Regresi operasional tetap menghasilkan Gudang 50 dan Apotek 18 setelah satu pengeluaran kondisi khusus yang memang diuji.

## Verifikasi Visual

Screenshot asli `Panduan_Desktop.png`, `Panduan_Mobile.png`, dan `Dashboard_Database.png` di `storage/app/qa/onboarding/` sudah dibuka dan diperiksa: teks terbaca, highlight sesuai menu, tombol muat di layar, tema klinik tetap terang di luar panduan, dan tidak ada password pada gambar. Tidak ada manipulasi screenshot atau perubahan source fitur untuk memalsukan bukti.

## Perbaikan Posisi Pembuka Panduan (21 Oktober 2026)

Temuan pengguna berhasil direproduksi melalui Playwright sebelum perbaikan: pada desktop 1440 x 900, kotak pembuka berada pada `x=540`, `y=710.921875`, bukan di tengah. Langkah pertama menargetkan seluruh `[data-tour-main]`, sehingga Driver.js tidak menemukan ruang di sisi dashboard dan menggunakan posisi cadangan di bawah layar. Pengujian awal gagal pada assertion posisi, bukan dianggap lulus.

Perbaikan:

- Langkah sambutan tidak lagi memiliki target elemen; Driver.js memakai dialog terpusat dengan seluruh latar diredupkan.
- Langkah menu berikutnya tetap menyorot elemen yang relevan sesuai hak akses. Sidebar mobile ditutup saat pembuka dan tetap mengikuti perilaku pemulihan semula.
- Posisi diukur ulang setelah font siap. Teks deskripsi dapat digulir pada layar pendek tanpa menyembunyikan tombol navigasi atau memotong panah penunjuk.
- Regression test PHP memastikan langkah pembuka tanpa target pada keempat role. Browser memastikan pembuka terpusat, semua tombol masuk kotak/layer layar, serta navigasi maju/mundur, skip, selesai, dan buka ulang tetap berjalan.

Hasil aktual sesudah perbaikan: `php scripts/test-inventory.php` lulus **97 tes / 982 assertions**; `php scripts/capstone-final-browser.php onboarding` lulus **9/9 pemeriksaan browser**; `npm run build` berhasil. Ukuran layar yang diuji mencakup 1440 x 900, 390 x 844, 1623 x 853, 1280 x 720, 320 x 568, 844 x 390, dan resize masing-masing ukuran tambahan pada skenario terakhir. Angka pengujian awal di atas tetap dicatat sebagai riwayat, bukan hasil terbaru.

Bukti lokal diabaikan Git: `storage/app/qa/onboarding/results.json`, `Panduan_Pembuka_Desktop.png`, `Panduan_Pembuka_Mobile.png`, dan `Panduan_Pembuka_Layar_Pengguna.png`. `Kendala_Browser.png` merekam reproduksi sebelum perbaikan. Screenshot sesudah perbaikan diperiksa secara visual. Seluruh fixture memakai database MySQL simulasi bernama acak, bukan database klinik atau UAT persisten. Tidak ada perubahan pada persediaan, permission, atau status panduan akun klinik/UAT untuk memperbaiki posisi ini.

## Batasan Dan Pemulihan

- UAT langsung oleh petugas klinik belum dilakukan; hasil otomatis tidak menggantikannya.
- Dashboard lokal akan menunjukkan stok/transaksi nol sampai persediaan nyata dicatat. Jangan mengembalikan data simulasi hanya untuk mengisi grafik.
- Command cleanup adalah manifest lokal yang telah ditinjau, bukan penghapus semua obat non-Excel dan bukan prosedur rutin. Master manual baru di luar manifest tidak disentuh.
- Backup ini berisi tabel inventori, bukan full database atau backup konfigurasi. Pemulihan memerlukan pemeriksaan manual pada schema yang sama, foreign key, ID, transaksi baru, dan rekonsiliasi. Jangan menimpa semua tabel jika sudah ada transaksi operasional setelah backup.
- Migrasi panduan sudah diterapkan lokal. Lingkungan lain perlu migrasi sebelum menjalankan versi baru; cleanup data tidak dijalankan otomatis oleh migrasi atau seeder.
- Backup, kredensial, dan hasil privat QA tidak boleh dipush. Tidak ada force-push atau perubahan riwayat Git.
