# AI HANDOFF — PRD Sistem Informasi Manajemen Persediaan Obat Klinik Shifa Medika

## 1. Tujuan Handoff

Dokumen ini disiapkan agar AI/agent berikutnya dapat melanjutkan analisis, desain, dokumentasi, maupun implementasi **Sistem Informasi Manajemen Persediaan Obat Klinik Shifa Medika** tanpa mengulang konteks dari awal.

Basis utama handoff ini adalah **PRD v0.1** yang disusun dari **Draft Kebutuhan Sistem Persediaan Obat Klinik v0.2**.

> Jangan mengubah kebutuhan yang berstatus **usulan** atau **perlu konfirmasi** menjadi keputusan final tanpa dasar tambahan.

---

## 2. Konteks Dokumen

Dokumen utama Capstone Project berada dalam satu Google Docs dengan urutan tab:

1. Dokumen Analisis Klinik Shifa Medika
2. Draft Kebutuhan Sistem Persediaan Obat Klinik
3. PRD Sistem Persediaan Obat Klinik
4. Tugas I - Proposal Capstone Project

### Aturan penting

- **Dokumen Analisis Klinik Shifa Medika** adalah sumber kondisi existing dari narasumber.
- Jangan mengubah fakta existing di tab tersebut hanya agar sesuai dengan desain sistem.
- **Draft Kebutuhan Sistem** menerjemahkan existing menjadi kebutuhan dan rancangan awal.
- **PRD** adalah acuan produk untuk desain, backlog, ERD, SDD, UI, dan implementasi.
- Jika ada konflik, bedakan mana fakta existing, mana usulan rancangan, dan mana keputusan teknis.

---

## 3. Ringkasan Produk

Sistem dirancang untuk membantu Klinik Shifa Medika mencatat, memantau, dan menelusuri persediaan obat pada dua lokasi utama:

- **Gudang**
- **Apotek**

Fokus utama sistem:

- stok per obat
- stok per batch
- stok per lokasi
- tanggal kedaluwarsa
- penerimaan obat
- transfer gudang ke apotek
- pengeluaran obat
- monitoring stok
- FEFO
- kartu stok
- stock opname
- penyesuaian stok
- kondisi khusus
- laporan persediaan
- role dan hak akses

Sistem **tidak dimaksudkan menggantikan seluruh Neoklinik**.

---

## 4. Existing yang Menjadi Dasar

Kondisi existing yang sudah dipakai sebagai dasar:

- Sekitar **152 item obat aktif**.
- Satu obat dapat memiliki beberapa **batch** dan **expiry**.
- Penerimaan obat saat ini berasal dari **satu PBF/supplier**.
- Stok gudang dan apotek harus dipisahkan.
- Transfer gudang ke apotek saat ini menggunakan kartu stok.
- Batch yang dipindahkan pada transfer existing belum tercatat dengan baik.
- Pengecekan stok dilakukan **setiap malam**.
- Stock opname dilakukan **setiap akhir bulan**.
- Warning expiry dibutuhkan untuk rentang **3 bulan**.
- Koreksi stock opname difinalisasi oleh **Kepala Apoteker**.
- Laporan bulanan ditujukan kepada **Kepala Klinik**.
- Berdasarkan klarifikasi narasumber, **Kepala Apoteker = Apoteker Penanggung Jawab**.

---

## 5. Scope Sistem

### In Scope

- akun pengguna
- role dan hak akses
- status akun
- master obat
- satuan
- kemasan
- faktor konversi
- lokasi penyimpanan
- data referensi teknis
- penerimaan obat
- batch
- expiry
- stok gudang
- stok apotek
- transfer gudang ke apotek
- pengeluaran persediaan
- monitoring stok minimum
- monitoring expiry
- FEFO
- kartu stok
- histori mutasi
- stock opname
- penyesuaian stok
- expired
- rusak
- hilang
- retur
- laporan persediaan
- audit aktivitas penting

### Out of Scope

- rekam medis pasien
- diagnosis
- pelayanan medis
- pembayaran pasien
- modul keuangan
- akuntansi pengadaan
- seluruh layanan Klinik Shifa Medika
- penggantian penuh Neoklinik
- master supplier kompleks

Karena saat ini hanya ada **satu PBF/supplier**, identitas supplier diperlakukan sebagai **referensi sumber penerimaan**, bukan modul master supplier terpisah.

---

## 6. Actor / Role Sistem

### Administrator Sistem

Role teknis aplikasi, bukan jabatan existing farmasi.

Tanggung jawab:

- kelola pengguna
- kelola role
- kelola hak akses
- kelola status akun
- kelola data referensi teknis

Bukan pelaksana utama transaksi persediaan.

Tidak diusulkan untuk:

- transaksi penerimaan
- transfer
- pengeluaran
- stock opname
- finalisasi koreksi
- laporan operasional
- master obat
- parameter monitoring

---

### Petugas Farmasi

Tanggung jawab operasional:

- melihat dashboard
- melihat stok
- melihat kartu stok
- mencatat penerimaan
- mencatat transfer gudang ke apotek
- mencatat pengeluaran
- mengisi hasil stock opname
- membentuk dan meninjau laporan

Batasan:

- tidak memfinalisasi koreksi stock opname
- hak pembatalan transaksi final / posting retur masih **belum final**

---

### Kepala Apoteker

Kepala Apoteker adalah pihak yang sama dengan Apoteker Penanggung Jawab.

Memiliki fungsi operasional yang relevan dan kewenangan tambahan:

- memelihara master obat
- mengatur parameter monitoring
- mengisi hasil stock opname
- memfinalisasi koreksi stock opname
- membentuk dan meninjau laporan

Hak pembatalan transaksi final / posting retur masih perlu ditetapkan.

---

### Kepala Klinik

Saat ini hanya sebagai **penerima laporan**.

Belum ada keputusan bahwa Kepala Klinik memiliki akun sistem.

> Jangan otomatis menjadikan Kepala Klinik sebagai actor sistem.

---

## 7. Functional Requirements

### F-01 — Akun dan Akses

Sistem harus menyediakan:

- identitas pengguna
- role
- hak akses
- status akun
- pengelolaan akun oleh Administrator Sistem

Aturan:

- akun individu
- hak akses mengikuti role
- akun dapat aktif/nonaktif
- izin harus divalidasi di server

---

### F-02 — Master Persediaan

Sistem harus menyediakan:

- master obat
- satuan
- konversi
- lokasi penyimpanan
- data referensi sistem

Master obat minimal:

- kode obat
- nama obat
- bentuk sediaan
- satuan dasar
- status aktif
- kemasan
- faktor konversi

Obat yang sudah memiliki histori transaksi tidak dihapus, tetapi dinonaktifkan.

Tidak perlu modul master supplier terpisah pada scope saat ini.

---

### F-03 — Penerimaan Obat

Data minimum:

- identitas PBF/supplier
- nomor faktur/referensi
- waktu penerimaan
- lokasi tujuan
- obat
- nomor batch
- expiry
- jumlah
- satuan input
- faktor konversi

Alur target:

`periksa barang/dokumen → input penerimaan → validasi batch/expiry/konversi → draft → posting`

Aturan:

- draft tidak mengubah stok
- posting menambah stok lokasi tujuan
- posting menghasilkan mutasi/kartu stok

---

### F-04 — Transfer Gudang ke Apotek

Data minimum:

- lokasi sumber
- lokasi tujuan
- obat
- batch
- jumlah
- waktu perpindahan

Aturan:

- batch harus sesuai barang fisik
- gudang berkurang
- apotek bertambah
- total stok keseluruhan tidak berubah
- transaksi harus atomic
- jika salah satu sisi gagal, kedua saldo tidak berubah

---

### F-05 — Pengeluaran Persediaan

Data minimum:

- referensi pengeluaran
- waktu
- lokasi
- obat
- batch
- jumlah

Aturan:

- tolak jika jumlah lebih besar dari stok tersedia
- tolak jika batch tidak layak
- jika memakai lebih dari satu batch, jumlah tiap batch dicatat terpisah
- tidak membangun master pasien/rekam medis
- referensi dapat mengacu ke transaksi sumber yang disepakati

---

### F-06 — Monitoring Stok dan FEFO

Dashboard minimal menampilkan:

- stok gudang
- stok apotek
- stok menipis
- batch mendekati expiry
- batch expired
- informasi yang dapat ditelusuri ke obat/lokasi/batch/kartu stok

Stok minimum:

- diusulkan per obat/lokasi
- nilai belum tersedia
- jika belum diatur, tampilkan `belum diatur`

Expiry:

- warning existing = **3 bulan**
- interpretasi expiry bulan/tahun masih perlu dikonfirmasi

FEFO:

- rekomendasikan batch layak dengan expiry paling dekat
- tidak menggantikan pemeriksaan batch fisik

---

### F-07 — Kartu Stok

Kartu stok minimal berisi:

- waktu transaksi
- jenis transaksi
- referensi transaksi
- jumlah masuk
- jumlah keluar
- saldo
- pelaku
- obat
- batch
- lokasi

Kartu stok harus dapat digunakan untuk menelusuri sumber perubahan saldo.

---

### F-08 — Stock Opname

Stock opname dilakukan bulanan.

Alur target:

`buat sesi → simpan saldo acuan → input jumlah fisik → hitung selisih → periksa penyebab → finalisasi Kepala Apoteker → catat mutasi penyesuaian`

Rumus:

`selisih = jumlah fisik − jumlah catatan pada waktu acuan`

Aturan:

- selisih positif menambah saldo setelah finalisasi
- selisih negatif mengurangi saldo setelah finalisasi
- pengecekan stok malam berbeda dari stock opname bulanan
- pencatatan checklist stok malam di sistem belum diputuskan

---

### F-09 — Penyesuaian dan Kondisi Khusus

Jenis kejadian yang perlu dapat ditelusuri:

- koreksi stock opname
- expired
- rusak
- hilang
- retur

Data minimum:

- obat
- batch
- lokasi
- jumlah
- alasan
- referensi pemeriksaan/retur

Aturan:

- kondisi khusus dibedakan dari pengeluaran pasien
- expired yang sudah mengurangi stok tidak boleh dikurangi lagi ketika dimusnahkan atau diangkut sebagai limbah

Masih belum final:

- pembatalan transaksi
- obat tidak diambil pasien
- pengembalian
- retur
- kewenangan masing-masing proses

---

### F-10 — Laporan

Sistem menyediakan laporan per periode.

Laporan harus dapat merekonsiliasi:

- saldo awal
- mutasi masuk
- mutasi keluar
- transfer
- koreksi/penyesuaian
- saldo akhir

Laporan bulanan existing kepada Kepala Klinik memuat:

- obat masuk
- obat keluar
- obat expired
- sisa stok gudang
- sisa stok apotek

Format final mengikuti contoh klinik setelah diterima tim.

---

## 8. User Stories Inti

- **US-01** — Sebagai Administrator Sistem, saya ingin mengelola akun dan hak akses agar setiap pengguna hanya dapat menjalankan fungsi sesuai role.
- **US-02** — Sebagai Kepala Apoteker, saya ingin memelihara master obat dan parameter monitoring agar informasi persediaan dapat digunakan dengan aturan yang sesuai.
- **US-03** — Sebagai Petugas Farmasi, saya ingin mencatat penerimaan berdasarkan batch dan expiry agar stok bertambah pada batch yang benar.
- **US-04** — Sebagai Petugas Farmasi, saya ingin memindahkan stok dari gudang ke apotek agar stok kedua lokasi tetap terpisah dan total persediaan tetap konsisten.
- **US-05** — Sebagai Petugas Farmasi, saya ingin mencatat pengeluaran berdasarkan batch agar riwayat stok dapat ditelusuri.
- **US-06** — Sebagai Petugas Farmasi atau Kepala Apoteker, saya ingin melihat stok menipis dan expiry agar kondisi persediaan dapat diketahui tanpa pemeriksaan manual seluruh obat.
- **US-07** — Sebagai Petugas Farmasi, saya ingin melihat kartu stok agar dapat menelusuri penyebab perubahan saldo.
- **US-08** — Sebagai Petugas Farmasi, saya ingin memasukkan hasil stock opname agar sistem dapat menghitung selisih antara stok fisik dan catatan.
- **US-09** — Sebagai Kepala Apoteker, saya ingin memfinalisasi koreksi stock opname agar perubahan saldo akibat selisih memiliki kewenangan yang jelas.
- **US-10** — Sebagai Petugas Farmasi atau Kepala Apoteker, saya ingin membuat laporan periodik agar kondisi persediaan dapat direkap dan disampaikan kepada Kepala Klinik.

---

## 9. Alur End-to-End

### Penerimaan

`PBF → pemeriksaan barang/dokumen → input penerimaan → batch/expiry/konversi → draft → posting → stok bertambah → kartu stok`

### Transfer

`pilih stok gudang → pilih batch fisik → isi jumlah → validasi saldo → posting → gudang berkurang → apotek bertambah → kartu stok`

### Pengeluaran

`input/terima referensi → pilih lokasi → pilih batch aktual → validasi stok & kelayakan → posting → stok berkurang → kartu stok`

### Monitoring

`mutasi stok → saldo per obat/batch/lokasi → evaluasi minimum & expiry → dashboard → detail stok/kartu stok`

### Stock Opname

`buat sesi → saldo acuan → input fisik → hitung selisih → telaah → finalisasi Kepala Apoteker → penyesuaian → saldo baru + audit`

### Laporan

`saldo awal + mutasi periode + penyesuaian → saldo akhir → rekap bulanan → Kepala Klinik`

---

## 10. Business Rules

- **B-01** — Saldo per obat/batch/lokasi = saldo awal + mutasi masuk − mutasi keluar.
- **B-02** — Kuantitas transaksi harus positif dalam satuan sah. Jumlah input dan faktor konversi historis disimpan.
- **B-03** — Draft tidak mengubah saldo. Posting mengubah saldo dan kartu stok dalam satu transaksi utuh.
- **B-04** — Stok sumber harus cukup saat posting. Akses bersamaan tidak boleh menghasilkan saldo negatif.
- **B-05** — Pengiriman ulang permintaan yang sama tidak boleh menggandakan transaksi.
- **B-06** — Transaksi final tidak dihapus/ditimpa. Pembalikan terhubung ke transaksi asal dan mempertahankan audit.
- **B-07** — Nomor batch tidak unik secara global. Kombinasi obat/batch dengan expiry yang bertentangan harus diperiksa.
- **B-08** — Hak akses harus divalidasi pada server.

---

## 11. Model Data Konseptual

Objek utama yang perlu dimodelkan:

### Obat / Kemasan

- kode
- nama
- bentuk
- satuan dasar
- status
- kemasan
- faktor konversi

### Lokasi / Sumber Penerimaan

- identitas lokasi
- nama lokasi
- status
- identitas PBF/supplier sebagai referensi sumber penerimaan

### Batch

- obat
- nomor batch
- expiry
- ketelitian tanggal expiry yang tersedia

### Dokumen Transaksi

- nomor
- jenis
- referensi sumber
- waktu kejadian
- waktu input
- waktu posting
- status
- pelaku

### Detail / Mutasi

- obat/batch
- lokasi sumber
- lokasi tujuan
- jumlah input
- faktor konversi
- jumlah masuk
- jumlah keluar

### Opname / Penyesuaian

- sesi/cut-off
- saldo acuan
- jumlah fisik
- selisih
- alasan
- pemfinalisasi

### Monitoring / Akun

- minimum per obat/lokasi
- horizon expiry
- identitas akun
- role
- status
- izin

### Audit

- tindakan
- objek terkait
- pelaku
- waktu
- perubahan relevan

Catatan:

- kategori, racikan, harga beli, harga jual, dan nilai transaksi dari existing **tidak otomatis menjadi requirement sistem baru**
- harga/nilai transaksi tidak boleh memperluas scope menjadi modul keuangan tanpa keputusan baru

---

## 12. Hubungan dengan Neoklinik

Belum diasumsikan ada integrasi otomatis.

Setiap jenis transaksi harus ditentukan berasal dari:

1. input langsung pada sistem baru
2. impor dari ekspor resmi
3. integrasi yang tersedia dan diizinkan

Jika memakai impor:

- wajib ada identitas transaksi stabil
- harus ada pencegahan duplikasi
- input manual + impor untuk kejadian yang sama tidak boleh berjalan tanpa kontrol

Jika data tidak real-time:

- tampilkan waktu pembaruan terakhir
- jangan menyebut dashboard real-time jika data obat keluar belum masuk

---

## 13. Saldo Awal dan Migrasi

Tidak adanya kebutuhan migrasi Excel **bukan berarti saldo awal = 0**.

Saldo awal harus:

- dicatat sebagai kejadian pembukaan
- dapat ditelusuri
- memiliki sumber yang disepakati
- idealnya per lokasi dan batch

Sumber saldo awal masih perlu dikonfirmasi.

---

## 14. Acceptance Criteria Awal

- **AC-01** — Obat yang sama pada dua batch berbeda menghasilkan saldo batch terpisah.
- **AC-02** — Transfer 20 dari gudang 50 ke apotek 10 menghasilkan gudang 30, apotek 30, total 60.
- **AC-03** — Jika satu bagian transfer gagal, tidak boleh ada perubahan saldo sebagian.
- **AC-04** — FEFO mengikuti expiry terdekat dan batch fisik tetap dicatat.
- **AC-05** — Akses bersamaan / retry tidak boleh menghasilkan saldo negatif atau mutasi ganda.
- **AC-06** — Petugas Farmasi tidak dapat memfinalisasi koreksi stock opname tanpa kewenangan Kepala Apoteker.
- **AC-07** — Laporan setelah transfer/koreksi tetap konsisten dengan kartu stok.

---

## 15. Open Questions

### Q-01 — Sumber data obat keluar

Belum final:

- input manual
- impor
- integrasi

Dibutuhkan contoh data obat keluar.

### Q-02 — Stock opname per batch atau total obat

Belum final:

- hitung fisik per batch
- atau hanya total per obat
- aturan transaksi selama opname

### Q-03 — Stok minimum dan expiry

Belum diketahui:

- nilai minimum per obat/lokasi
- interpretasi expiry yang hanya bulan/tahun

### Q-04 — Pembatalan / Pengembalian / Retur

Masih perlu ditegaskan:

- alur
- kondisi obat
- perubahan stok
- kewenangan role

### Q-05 — Lokasi awal penerimaan

Perlu konfirmasi:

- apakah semua penerimaan selalu masuk gudang
- apakah ada pemakaian/transfer sebelum input malam

### Q-06 — Saldo awal dan data contoh

Masih perlu:

- mekanisme saldo awal
- master obat
- transaksi
- laporan
- data batch/expired
- dokumen pendukung

---

## 16. Keputusan Implementasi Awal

### Stack

Implementasi awal ditetapkan menggunakan:

- **Laravel 13**
- **Livewire 3**
- **Tailwind CSS**
- **MySQL**

Arsitektur:

- **Laravel monolith**
- tidak perlu SPA terpisah
- prototype HTML/JavaScript/Tailwind lama hanya menjadi referensi visual/alur UI

Starter kit auth dan package role/permission belum dikunci.

---

## 17. Prinsip Implementasi Persediaan

### Jangan edit saldo langsung

Perubahan stok harus berasal dari transaksi/mutasi.

Contoh tipe mutasi:

```text
RECEIPT
TRANSFER_OUT
TRANSFER_IN
ISSUE
ADJUSTMENT
EXPIRED
```

Saldo mengikuti:

```text
saldo per obat/batch/lokasi
= saldo awal
+ mutasi masuk
- mutasi keluar
```

### Atomic Transaction

Transfer gudang → apotek harus atomic:

```text
kurangi gudang
+
tambah apotek
=
satu database transaction
```

Jika salah satu gagal, rollback seluruh perubahan.

### Audit

Pertahankan:

- pelaku
- waktu kejadian
- waktu input
- waktu posting
- referensi transaksi
- transaksi asal jika reversal
- perubahan penting

---

## 18. Arah Arsitektur Laravel

Struktur modul yang direkomendasikan:

```text
Laravel Application
├── Authentication
├── Role & Permission
│   ├── Administrator Sistem
│   ├── Petugas Farmasi
│   └── Kepala Apoteker
│
├── Master
│   ├── Obat
│   ├── Satuan / Kemasan
│   ├── Lokasi
│   └── Data Referensi
│
├── Persediaan
│   ├── Penerimaan
│   ├── Transfer Gudang → Apotek
│   ├── Pengeluaran
│   ├── Batch & Expiry
│   └── Kartu Stok
│
├── Monitoring
│   ├── Dashboard
│   ├── Stok Minimum
│   ├── Expiry
│   └── FEFO
│
├── Stock Opname
│   ├── Sesi
│   ├── Input Fisik
│   ├── Selisih
│   └── Finalisasi Koreksi
│
├── Penyesuaian
│   ├── Expired
│   ├── Rusak
│   ├── Hilang
│   └── Retur
│
└── Laporan
```

---

## 19. Prioritas MVP

Urutan implementasi yang direkomendasikan:

1. Authentication
2. Role & Permission
3. Master Obat
4. Satuan / Kemasan
5. Lokasi
6. Penerimaan
7. Batch & Expiry
8. Transfer Gudang → Apotek
9. Pengeluaran
10. Kartu Stok / Stock Movements
11. Dashboard & Monitoring
12. Stock Opname
13. Finalisasi Koreksi
14. Laporan
15. Kondisi khusus / retur setelah Q-04 lebih jelas

---

## 20. Use Case

Use case diagram **tidak perlu diubah hanya karena stack menggunakan Laravel**.

Use case berubah hanya jika ada perubahan:

- actor
- kewenangan role
- kebutuhan bisnis
- scope fungsional F-01 sampai F-10

Actor saat ini:

- Administrator Sistem
- Petugas Farmasi
- Kepala Apoteker

Kepala Klinik **belum menjadi actor sistem**.

Catatan use case yang masih perlu diperhatikan saat diagram difinalkan:

- Administrator Sistem sebaiknya fokus ke akun/hak akses/data referensi teknis.
- Master obat dan parameter monitoring lebih cocok ke Kepala Apoteker.
- Petugas Farmasi dapat membuat/meninjau laporan.
- Hak pembatalan/pengembalian/retur masih belum final.

---

## 21. Hal yang Jangan Diasumsikan AI Berikutnya

Jangan mengasumsikan:

- Kepala Klinik punya akun.
- PBF adalah actor sistem.
- pasien adalah actor sistem.
- Neoklinik sudah terintegrasi otomatis.
- stok minimum sudah tersedia.
- stock opname pasti per batch.
- pembatalan dan retur sudah final.
- semua penerimaan pasti masuk gudang.
- saldo awal = 0.
- harga beli/jual otomatis masuk scope.
- supplier membutuhkan CRUD penuh.
- checklist stok malam wajib ada di aplikasi.
- FEFO membuktikan batch fisik yang benar-benar dipakai.

---

## 22. Next Recommended Deliverables

Setelah handoff ini, pekerjaan yang paling logis:

1. finalisasi Use Case Diagram
2. buat ERD konseptual
3. turunkan ke logical database design
4. buat SDD / arsitektur aplikasi
5. buat backlog implementasi Laravel
6. buat migration schema
7. buat model & relationships
8. implement auth + role
9. implement stock movement engine
10. implement transaksi utama
11. implement monitoring
12. implement stock opname
13. implement laporan

---

## 23. Traceability

```text
F-01 → Akun, role, hak akses
F-02 → Master obat, satuan/konversi, lokasi, data referensi
F-03 → Penerimaan
F-04 → Transfer gudang–apotek
F-05 → Pengeluaran
F-06 → Monitoring stok, expiry, FEFO
F-07 → Kartu stok
F-08 → Stock opname
F-09 → Koreksi dan kondisi khusus
F-10 → Laporan
```

---

## 24. Instruksi untuk AI/Agent Berikutnya

Jika melanjutkan pekerjaan:

1. Gunakan PRD sebagai sumber requirement utama.
2. Bedakan fakta existing, usulan desain, dan keputusan implementasi.
3. Jangan menutup Q-01 sampai Q-06 tanpa data baru.
4. Jangan menambahkan scope klinis/keuangan.
5. Prioritaskan integritas stok, audit trail, dan traceability.
6. Untuk desain database, gunakan prinsip mutasi stok daripada edit saldo langsung.
7. Untuk transfer dan posting stok, gunakan database transaction.
8. Pertahankan role dan kewenangan sesuai PRD.
9. Jika membuat diagram atau dokumentasi baru, pastikan dapat ditelusuri kembali ke F-01 sampai F-10.
10. Jika menemukan ketidakkonsistenan, tandai sebagai open issue dan jangan diam-diam memilih salah satu asumsi.

---

**Status handoff:** siap digunakan untuk melanjutkan ERD, SDD, backlog, dan implementasi Laravel.
