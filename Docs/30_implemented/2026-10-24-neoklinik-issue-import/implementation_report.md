# Implementasi Impor Pengeluaran Excel Neoklinik

Tanggal: 24 Oktober 2026, Asia/Jakarta.
Versi: working tree pada commit dasar `2d2e8ef0bfded0f39c164e8ed7fb4c109a909459`, belum di-commit/push.
Status: implementasi dan regresi lokal/UAT selesai; bukan deployment produksi atau persetujuan penggunaan data klinik.

## Alur yang Tersedia

**Pengeluaran Obat > Impor & Riwayat Excel** menyediakan upload, pratinjau, persetujuan pemetaan, posting, dan riwayat. Input manual tetap tersedia. Ini impor berbasis file, bukan sinkronisasi/API Neoklinik.

1. Unggah `.xlsx`, pilih lokasi asal, dan tentukan **Mulai pencatatan (WIB)**. File belum mengubah stok.
2. Periksa obat dan satuan sumber. Kode cocok hanya menjadi saran sampai pemetaan dipilih, dicentang, dan disimpan. Isi per satuan adalah jumlah satuan stok untuk satu satuan sumber, bukan asumsi universal isi box/strip.
3. Pratinjau diringkas per kode obat/satuan, dengan rincian baris/waktu sumber yang bisa dibuka. Status membedakan siap, sudah diimpor, data berubah, pemetaan bermasalah, periode salah, dan stok/batch tidak mencukupi.
4. Sebelum posting, konfirmasi penyerahan obat, produk/satuan/racikan, lokasi/FEFO, periode yang belum dicatat/WIB, serta kestabilan identitas sumber.
5. Seluruh baris baru harus lolos; posting berjalan atomik. Duplikat dilaporkan dan dilewati. Hasil menampilkan petugas, mutasi, batch FEFO, snapshot konversi asli, dan tautan kartu stok.

## Backend dan Pengaman

- Parser `NeoklinikIssueReader` memakai PhpSpreadsheet 5.10.0. Header 16 kolom diperiksa; batas unggahan 4 MB dan 2.000 transaksi tidak kosong. Kolom kosong/format tambahan di luar P dan baris kosong berformat tidak dihitung sebagai transaksi; area pemindaian dibatasi 10.000 baris. Error sheet, kolom, area, dan jumlah transaksi dipisahkan.
- ZIP dibatasi jumlah entry dan total ukuran terurai; objek tertanam, macro, drawing, dan relasi eksternal ditolak. Formula pada kolom yang digunakan tidak dievaluasi dan ditolak. Tanggal Excel diparse dengan zona WIB yang dikonfirmasi operator; tanggal masa depan, jumlah nonpositif, atau presisi sumber di luar empat desimal ditolak.
- Kunci sumber menggunakan namespace Neoklinik klinik, nomor penjualan, kode obat yang dinormalisasi, dan kelompok racikan. Nomor baris, nama file, kuantitas, pilihan lokasi, dan referensi OUT tidak menjadi identitas. Baris ambigu dengan kunci sama dalam satu file ditolak.
- Fingerprint mendeteksi perubahan waktu/jumlah/satuan/kategori sumber. Perubahan diblokir untuk review, bukan dianggap pengeluaran baru. Source key unik di database tetap disimpan meskipun mutasi kemudian dibalik.
- `InventoryTransaction` menjadi pengunci bersama dan batas transaksi stok. Preview token diverifikasi kembali di bawah lock, lalu stok total/batch, master/unit, lokasi aktif, kecukupan FEFO, dan rekonsiliasi diperiksa ulang. Unique constraint dan transaksi yang sama melindungi ledger impor/mutasi.
- Satu baris sumber dapat menghasilkan beberapa mutasi batch. Semua menggunakan `InventoryStockService`, tanpa perubahan aturan FEFO atau pengaman stok negatif yang sudah berjalan.
- Batas mulai pencatatan lokasi ditetapkan pada posting pertama yang mengandung transaksi baru dan tidak dapat diganti lewat upload berikutnya. Transaksi lebih lama daripada stok awal, penyesuaian opname, atau hitung fisik terakhir obat/lokasi juga diblokir untuk mencegah pengurangan dua kali.
- Preview yang tidak diposting kedaluwarsa setelah tujuh hari. Command `issues:purge-import-drafts` dijadwalkan harian, menghapus payload draf kedaluwarsa saja; ledger/snapshot transaksi terposting tetap ada.

## Privasi dan RBAC

- Raw workbook dan nama file pengguna tidak diarsipkan. Kolom dokter/pasien tidak disalin ke payload, pratinjau, log, atau audit. Nomor penjualan/racikan hanya dipakai untuk menghasilkan hash identitas; nilai mentah dan Medication/Request/Dispense Id tidak disimpan.
- Data persediaan tersanitasi disimpan melalui encrypted cast: draf untuk pratinjau, dan snapshot baris baru saat posting untuk jejak kode/satuan sumber, faktor, unit/obat tujuan, dan jumlah aktual. Nilai konversi historis tidak mengikuti perubahan mapping berikutnya.
- Exception parser tidak dilaporkan beserta raw cell. Error menjelaskan baris/struktur tanpa menyalin identitas klinis.
- File referensi asli yang mengandung data pasien dikecualikan secara spesifik lewat `.gitignore`; tidak dipindahkan, diubah, atau dimasukkan ke fixture.
- GET membutuhkan `view-issues`; upload/mapping/posting juga membutuhkan `operate-inventory`. Guest hanya melihat riwayat/pratinjau tersanitasi dan ditolak pada ketiga endpoint tulis. Tidak ada perubahan matriks atau grant role.

## Perbaikan Selama Verifikasi

| Temuan | Perbaikan dan verifikasi |
| --- | --- |
| Menu impor UAT sempat 500 | Log menunjukkan tabel `shifastock_uat.issue_imports` belum ada. Migrasi additive dijalankan lewat `php scripts/uat.php migrate`; request halaman UAT setelahnya HTTP 200, formulir dan riwayat tersedia. Verifikasi melalui transaksi read-only, tanpa posting atau perubahan stok UAT. |
| Pesan struktur Excel terlalu umum/ketat | Validasi sheet/kolom/area dipisahkan; baris kosong dan format tambahan tidak dihitung sebagai transaksi. Regression test format Q1 dan A5000 lulus. File referensi asli dibaca read-only: satu sheet, A:P, 333 baris termasuk header, 332 transaksi. File yang dipilih pengguna pada browser tidak disalin untuk dibandingkan; ini bukan klaim bahwa semua varian ekspornya identik. |
| Penjelasan batas waktu membingungkan | Label menjadi Mulai pencatatan (WIB), dibedakan dari tanggal unggah dan diberi contoh hitung fisik 100 tablet. Batas yang sudah tersimpan menjelaskan bahwa file periode berikutnya tetap boleh diimpor. |
| Luapan 13 px pada tablet | Elemen label absolut di tabel scroll keluar dari containing block. Wrapper tabel dibuat positioned; konfirmasi diberi span yang dapat menyusut. Dropdown memakai pola fixed-position yang sudah digunakan penerimaan/transfer agar tidak terpotong. Empat viewport lulus tanpa page overflow. |
| Preview ratusan baris terlalu padat | Ringkasan per obat/satuan dengan rincian transaksi yang dapat dibuka; tabel panjang menggunakan scroll lokal, bukan memperlebar halaman. |

Klarifikasi operator: batas yang dicoba adalah 23 Oktober 2026 pukul 08.00 WIB. Pemeriksaan read-only file referensi menemukan 35 dari 332 baris sebelum jam tersebut dan 297 pada/sesudahnya. Khusus PARA, tiga dari 34 baris berada sebelum 08.00; baris lainnya masih memerlukan pemetaan. Ini menjelaskan dua kategori peringatan pada ringkasan obat yang sama, bukan kegagalan parsing. Jangan memundurkan batas hanya agar lolos apabila pengeluaran lama sudah tercakup dalam stok awal/hitung fisik.

## Hasil Eksekusi Aktual

Semua fixture otomatis sintetis dan akun/password pengujian dibuat acak. Database MySQL pengujian menggunakan nama acak `shifastock_issue_qa_*`, berbeda dari klinik/UAT, dan dibersihkan runner setelah selesai. Tidak ada posting file klinik oleh agen.

| Eksekusi | Hasil |
| --- | --- |
| PHPUnit khusus `IssueImportTest` | 20 tes, 202 assertions lulus |
| Seluruh PHPUnit | 199 tes, 2.087 assertions; nol failure/error/skipped |
| Browser Playwright | 8 kelompok skenario lulus; nol page error |
| Viewport | 1440x900, 2560x1440, 768x1024, 390x844; upload dan preview tanpa overflow halaman. Tabel pada layar sempit tetap dapat digeser secara lokal. |
| MySQL posting/FEFO/overlap | Stok total dan total batch konsisten |
| Dua worker impor bersamaan | Satu diposting, satu menolak preview basi; retry mengidentifikasi duplikat, bukan memotong ulang |
| Hasil akhir fixture MySQL | Stok awal 100, dua transaksi browser 15+2, satu transaksi bersamaan 3; stok total/batch 80 dan tepat tiga source line unik |
| Frontend build | `npm run build` lulus |
| UAT halaman impor | HTTP 200, form upload dan riwayat terdeteksi melalui request authenticated read-only |
| File referensi asli | 332 transaksi terbaca tanpa posting; SHA-256 tetap `2556748b164623c5bf5cb504461c59f83a51c65424271512f35cc9f7bca1d06b` |

Bukti lokal (diabaikan Git):

- `storage/app/qa/neoklinik-import-analysis/Full_PHPUnit.xml`
- `storage/app/qa/neoklinik-import-analysis/Import_PHPUnit.xml`
- `storage/app/qa/issue-imports/9b4c8545c39938e2/Playwright.json`
- `storage/app/qa/issue-imports/9b4c8545c39938e2/MySQL.json`
- PNG upload/preview desktop, QHD, tablet, HP, posting, dan penolakan perubahan di folder run tersebut.

Run terakhir, bukan screenshot kegagalan awal, dipakai sebagai bukti akhir. Pengujian otomatis bukan pengesahan arti data Neoklinik atau UAT langsung petugas klinik.

### Reproduksi

```bash
php artisan test --filter=IssueImportTest
php artisan test
npm run build
php scripts/test-issue-import.php
```

Runner terakhir memerlukan MySQL lokal dengan izin membuat database QA sementara, Node yang dapat menemukan Playwright (misalnya melalui `NODE_PATH` runtime pengujian), dan Chrome pada path yang ditetapkan runner browser. Tidak ada password tersimpan pada skrip atau dokumen hasil.

## Follow-Up: Hapus Pratinjau Impor

Permintaan lanjutan 24 Oktober 2026 menambahkan ikon Trash2 pada riwayat dan detail untuk status draf/kedaluwarsa yang belum diposting. SweetAlert mendukung konfirmasi maupun Batal, termasuk pengaktifan tombol dengan keyboard. Hapus menghilangkan dokumen/payload pratinjau dan merekam aktor pada `inventory.issue-import.deleted`; tidak menghapus pemetaan bersama, batas lokasi, stok, mutasi, atau ledger sumber.

Endpoint DELETE mengikuti `view-issues` dan `operate-inventory`. Di bawah pengunci `InventoryTransaction`, service membaca ulang dokumen dengan row lock dan menolak status terposting, metadata posting, ataupun source line terkait. Ini juga melindungi model draf basi ketika proses posting sudah menang lebih dahulu. Sebaliknya, posting melalui URL/token draf yang sudah dihapus mendapat 404. Posting seluruhnya duplikat tetap dilindungi, meski tidak membuat mutasi baru.

Tidak ada migrasi, pembersihan data UAT/klinik, perubahan hak akses, atau pembalikan stok dalam follow-up ini. Penghapusan otomatis hanya diuji pada fixture sintetis di database pengujian terpisah.

Eksekusi aktual setelah penambahan: 24 tes khusus impor / 266 assertions dan seluruh suite 203 tes / 2.151 assertions lulus; frontend build, Pint, dan whitespace check lulus. Sepuluh kelompok Playwright lulus, termasuk SweetAlert Batal/konfirmasi, pengaktifan hapus dengan keyboard, penghapusan dari riwayat pada HP, tidak adanya tombol pada dokumen terposting/Guest, serta regresi posting/FEFO/overlap. Fixture MySQL setelah penghapusan dan uji dua worker tetap memiliki stok total/batch 80 dengan tiga source line unik.

Bukti lokal: `storage/app/qa/neoklinik-import-analysis/Import_Delete_PHPUnit.xml`, `Full_PHPUnit_After_Delete.xml`, dan `storage/app/qa/issue-imports/0812145b763e8fdf/` (`Playwright.json`, `MySQL.json`, `Hapus_Pratinjau_Berhasil.png`, serta `Hapus_Pratinjau_HP.png`). Angka delapan kelompok pada verifikasi awal di atas dipertahankan sebagai riwayat, bukan hasil browser terbaru.

## Batasan Operasional yang Masih Memerlukan Konfirmasi

- Ekspor tidak membuktikan status final penyerahan, pembatalan, retur, ataupun batch/lokasi fisik. Konfirmasi operator wajib; aplikasi tidak menebak atau membalik transaksi hanya karena hilang dari ekspor baru.
- Identitas komposit unik pada contoh yang dianalisis, tetapi belum menjadi kontrak sumber yang terverifikasi lintas semua jenis ekspor. Jika ada baris ambigu, importer menolak; jangan mengubah nomor jual agar lolos.
- Kuantitas racikan harus benar-benar total bahan terpakai. Satuan `-`, SACARIN, konflik satuan, dan master yang belum lengkap harus ditinjau. Tidak ada penambahan/perbaikan master otomatis atau substitusi berdasarkan nama.
- PARA tidak boleh otomatis dipetakan ke PARACETAMOL SYR/Botol. Pemetaan tablet harus diverifikasi produk/kekuatannya oleh klinik.
- Impor belum bisa mendeteksi otomatis transaksi yang sebelumnya diketik manual tanpa identitas sumber yang sama. Operator wajib memastikan kejadian yang sama belum dicatat manual atau masuk penyesuaian fisik.
- FEFO memakai stok layak pakai saat posting, bukan rekonstruksi batch historis Neoklinik. Jangan memundurkan batas atau membuat stok fiktif untuk meloloskan file lama.
- Tidak ada UI koreksi masal impor, reset batas lokasi, auto-sync, ataupun deployment produksi. Koreksi terposting memerlukan review dan alur pembalikan yang sudah terotorisasi.
