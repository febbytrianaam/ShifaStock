# Gap Analysis PRD ShifaStock

Tanggal audit: 13 Oktober 2026  
Acuan: PRD ShifaStock v0.1 draft review, 12 Oktober 2026  
Basis audit: source code lokal pada `C:\laragon\www\ShifaStock`

## Ringkasan Eksekutif

Implementasi saat ini sudah kuat untuk fondasi P0: autentikasi, RBAC matrix, audit log admin, master obat, penerimaan, transfer, saldo per lokasi, batch/expiry dasar, pemeriksaan harian, monitoring, dan laporan ringkas.

Gap terbesar terhadap PRD ada pada empat area:

1. Retur PBF belum dibuat.
2. Kartu stok khusus belum dibuat sebagai menu mandiri.
3. Kondisi khusus/penyesuaian insiden belum dibuat.
4. Saldo dan FEFO belum dikelola per batch; stok saat ini masih agregat per obat dan lokasi.

Selain itu, penerimaan saat ini langsung mem-posting saldo, belum ada draf penerimaan/fisik-terima terpisah dari posting final sebagaimana disebut PRD.

## Status Per Fitur PRD

| ID | Fitur PRD | Status | Temuan Implementasi | Gap Utama |
| --- | --- | --- | --- | --- |
| F-01 | Pengguna dan hak akses | Selesai sebagian besar | Login username, akun aktif/nonaktif, CRUD pengguna, RBAC matrix, route `can:*`, audit log admin sudah ada. | Audit belum mencatat seluruh aksi stok; nonaktif user masih pakai confirm browser, bukan SweetAlert. |
| F-02 | Master obat dan referensi | Selesai sebagian | Master obat Livewire sudah ada, import riset, status verifikasi/aktif, satuan, kemasan, audit master obat, lokasi/unit. | Referensi PBF/supplier belum jadi entitas terpisah; pengelolaan parameter minimum stok dan referensi teknis belum lengkap lewat UI. |
| F-03 | Penerimaan dari PBF | Sebagian | Penerimaan satu baris per submit, batch, expiry, faktur/reference, supplier text, petugas, saldo Gudang bertambah, mutasi tercatat. | Belum multi-baris dalam satu dokumen penerimaan; belum ada status draft vs posted; belum membedakan waktu terima fisik dan waktu posting; belum ada guard eksplisit posting dua kali selain unique reference. |
| F-04 | Transfer Gudang ke Apotek | Sebagian | Transfer atomik, cek stok sumber via lock, saldo sumber berkurang dan tujuan bertambah, mutasi tercatat, batch opsional. | Saldo tidak per batch, sehingga pilihan batch belum benar-benar mengurangi stok batch tertentu; belum ada FEFO/rekomendasi batch; belum ada pembatasan khusus Gudang ke Apotek saja. |
| F-05 | Retur ke PBF | Belum dibuat | Tidak ada route/controller/model/view untuk retur. | Perlu modul retur, status retur sebelum/sesudah penerimaan, alasan, PBF, batch, jumlah, dan pencegahan pengurangan ganda. |
| F-06 | Monitoring dan FEFO | Sebagian | Dashboard, kekurangan/menipis, expiry 3 bulan, lokasi stok pada monitoring kedaluwarsa, highlight perhatian khusus sudah ada. | FEFO belum dibuat; parameter expiry belum configurable; minimum stok belum dikelola UI; dashboard demo/real ada, tetapi sumber/keterkinian data belum eksplisit di setiap ringkasan. |
| F-07 | Kartu stok | Sebagian | `inventory_movements` menyimpan mutasi penerimaan, transfer, physical count dan laporan menampilkan 50 pergerakan terbaru. | Belum ada menu kartu stok khusus dengan filter obat/lokasi/periode dan saldo berjalan; route legacy `/kartu-stok` justru 404. |
| F-08 | Stock opname | Sebagian | Pemeriksaan harian draft-submit-review sudah ada; stock opname periodik menampilkan approved checks per periode; finalisasi oleh kepala apoteker mengoreksi saldo agregat lokasi. | Belum ada modul stock opname bulanan mandiri dengan stok tercatat, stok fisik, selisih, alasan per periode sebagai entitas terpisah; koreksi batch dari opname belum ada. |
| F-09 | Kondisi khusus dan penyesuaian | Belum dibuat | Tidak ada modul insiden obat hilang/terjatuh/rusak/tidak diambil/expired sebagai workflow tersendiri. | Perlu pencatatan kejadian, status penyelesaian, batch opsional, dan posting penyesuaian satu kali. |
| F-10 | Laporan persediaan | Sebagian | Laporan stok saat ini, 50 mutasi terbaru, dan pemeriksaan disetujui sudah ada. | Belum ada rekap bulanan formal, ekspor, filter periode lengkap, laporan retur/kondisi khusus, laporan kartu stok, serta disclaimer sumber data Neoklinik. |

## Status Aturan Bisnis Kritis

| Aturan PRD | Status | Catatan |
| --- | --- | --- |
| Saldo per lokasi, obat, dan batch yang diketahui | Sebagian | Saldo saat ini per obat dan lokasi. Batch dicatat pada mutasi, tetapi saldo batch belum ada. |
| Stok normal tidak boleh negatif | Selesai untuk transfer | `InventoryStockService::add()` menolak saldo negatif dengan lock row. |
| Mutasi penting atomik | Sebagian | Penerimaan, transfer, dan approval pemeriksaan memakai DB transaction. Belum ada idempotency/posting state selain unique reference. |
| Obat kedaluwarsa tidak tersedia untuk penggunaan | Belum penuh | Monitoring menampilkan expired, tetapi transfer/pemilihan batch belum menolak batch expired. |
| FEFO sebagai rekomendasi, bukan bukti keluar fisik | Belum dibuat | Belum ada rekomendasi FEFO. |
| Stock opname existing total obat/lokasi, bukan per batch | Sebagian | Implementasi koreksi agregat per obat/lokasi sesuai batasan, namun belum ada penjelasan formal di semua laporan. |
| Kondisi khusus maksimal satu penyesuaian final | Belum dibuat | Modul kondisi khusus belum ada. |
| Obat berhistori tidak dihapus | Sebagian | UI cenderung nonaktifkan obat, foreign key restrict menjaga histori. Perlu cek semua aksi delete. |
| Tidak duplikasi transaksi pasien / data pasien | Selesai sementara | Tidak ada modul pasien/resep. Perlu disclaimer bahwa saldo Apotek belum sinkron Neoklinik. |

## Keputusan Terbuka PRD

| ID | Keputusan Terbuka | Status Implementasi |
| --- | --- | --- |
| D-01 | Dampak transaksi pasien dari Neoklinik | Belum diputuskan/diimplementasi. Sistem belum sinkron transaksi pasien. |
| D-02 | Migrasi/inisialisasi saldo awal | Sebagian melalui penerimaan/pemeriksaan; belum ada workflow import saldo awal formal. |
| D-03 | Koreksi batch dari opname agregat | Belum ada. |
| D-04 | Batas minimum stok per obat/lokasi | Struktur `stock_parameters` ada, UI konfigurasi belum lengkap. |
| D-05 | Periode peringatan expiry | Hardcoded sekitar 3 bulan, belum configurable. |
| D-06 | Format ekspor laporan | Belum ada ekspor. |
| D-07 | Struktur data final/ERD | Struktur database berjalan ada, ERD final belum disusun di repo. |
| D-08 | Deployment, backup, keamanan teknis | Belum terdokumentasi lengkap. |

## Prioritas Rekomendasi

### P0 - Tutup Gap Fondasi

1. Tambah kartu stok mandiri dengan filter obat, lokasi, batch, periode, dan saldo berjalan.
2. Tambah status posting untuk penerimaan agar mendukung draft vs posted dan mencegah double posting secara eksplisit.
3. Buat saldo batch atau tabel detail batch balance jika transfer batch dan FEFO mau akurat.
4. Tambah validasi batch expired agar tidak dapat dipilih untuk transfer/pemakaian.

### P1 - Pengendalian Operasional

1. Modul retur PBF.
2. Modul kondisi khusus dan penyesuaian final satu kali.
3. UI konfigurasi minimum stok dan parameter expiry.
4. FEFO recommendation untuk batch layak pakai.
5. Laporan bulanan dengan filter dan ekspor.

### P2 - Integrasi dan Dokumentasi

1. Disclaimer sumber/keterkinian data di dashboard/laporan.
2. Desain keputusan integrasi Neoklinik atau workflow rekonsiliasi.
3. ERD final dan dokumen deployment/backup.
4. Audit log untuk aksi stok penting, bukan hanya admin/user/permission.

## Catatan Risiko

- Saldo stok batch belum ada, sehingga fitur yang menampilkan batch harus dianggap informasi batch penerimaan, bukan bukti saldo batch aktual.
- Penerimaan langsung menambah saldo; jika proses klinik butuh validasi terpisah antara terima fisik dan posting, model saat ini perlu diubah.
- Dashboard mode demo aman untuk presentasi, tetapi harus jelas ketika user sedang membaca data demo vs data real.
- Laporan belum boleh diklaim sudah mencerminkan transaksi pasien dari Neoklinik.

