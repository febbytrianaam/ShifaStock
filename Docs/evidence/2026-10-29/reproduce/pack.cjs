const fs = require('fs');
const path = require('path');
const crypto = require('crypto');
const assert = require('assert/strict');
const source = path.dirname(__dirname);
const destination = path.resolve(source, '../../Capstone_Screenshots/2026-10-29');
const manifest = JSON.parse(fs.readFileSync(path.join(source,'manifest.json'),'utf8'));
const run = JSON.parse(fs.readFileSync(path.join(source,'testing/Run.json'),'utf8'));
assert.equal(run.exit_code,0);
assert.equal(manifest.images.length,15);
const entries = [
    ['SS-01','22','Transfer_Multi_Item','Tampilan Transfer Obat Multi-Item pada Sistem ShifaStock',true],
    ['SS-01-result','22a','Hasil_Transfer','Hasil Posting Transfer Dua Item Obat dalam Satu Referensi',false],
    ['SS-02','23','Pemeriksaan_Bersama','Pencatatan Pemeriksaan Stok Harian secara Kolaboratif',true],
    ['SS-02-process','23a','Proses_Petugas','Penyimpanan Sebagian Hasil Pemeriksaan oleh Petugas Farmasi',false],
    ['SS-02-activity','23b','Aktivitas_Bersama','Jejak Aktivitas Kolaborasi Pemeriksaan Stok Harian',false],
    ['SS-03','24','Hasil_Impor_Excel','Hasil Impor Pengeluaran Obat dari Excel Neoklinik',true],
    ['SS-03-upload','24a','Form_Impor_Excel','Halaman Unggah File Pengeluaran Obat dari Excel Neoklinik',false],
    ['SS-03-validation','24b','Validasi_Impor','Validasi Pencocokan Obat dan Satuan Sebelum Posting Impor',false],
    ['SS-04','25','Monitoring_Batch','Monitoring Persediaan Obat Berdasarkan Batch dan Lokasi Penyimpanan',true],
    ['SS-05','26','Form_Kondisi_Khusus','Form Pencatatan Kondisi Khusus Obat Rusak',true],
    ['SS-05-return','26a','Form_Retur_PBF','Pencatatan Retur Obat kepada PBF Berdasarkan Batch dan Lokasi',false],
    ['SS-05-result','26b','Hasil_Kondisi_Khusus','Hasil Posting Pengurangan Stok Akibat Kondisi Khusus Obat Rusak',false],
    ['SS-06','27','Batch_Sesuai_Lokasi','Verifikasi Pemilihan Batch Sesuai Lokasi Stok',true],
    ['TEST-01-phpunit','28','Hasil_PHPUnit','Ringkasan Hasil Eksekusi Pengujian Otomatis PHPUnit',true],
    ['TEST-02-playwright','29','Hasil_Playwright','Ringkasan Hasil Pengujian Browser Impor Excel dengan Playwright',true],
];
const hash = data => crypto.createHash('sha256').update(data).digest('hex');
const legacyDirectory=path.dirname(destination);
const legacy = fs.readdirSync(legacyDirectory).filter(name=>/^Gambar_.*\.png$/i.test(name))
    .map(name=>[name,hash(fs.readFileSync(path.join(legacyDirectory,name)))]);
fs.mkdirSync(destination,{recursive:true});
const packed=[];
for(const [id,number,slug,title,primary] of entries){
    const item=manifest.images.find(image=>image.id===id);
    assert.ok(item,id);
    const data=fs.readFileSync(path.join(source,item.file));
    assert.equal(hash(data),item.sha256);
    const file=`Gambar_${number}_${slug}.png`;
    const target=path.join(destination,file);
    if(fs.existsSync(target)) assert.equal(hash(fs.readFileSync(target)),item.sha256,'Do not replace a different existing image');
    else fs.copyFileSync(path.join(source,item.file),target,fs.constants.COPYFILE_EXCL);
    assert.equal(hash(fs.readFileSync(target)),item.sha256);
    packed.push({...item,source_file:item.file,file,number,caption:`Gambar ${number}. ${title}.`,primary});
}
for(const [name,digest] of legacy) assert.equal(hash(fs.readFileSync(path.join(legacyDirectory,name))),digest);
const testCaptions={
    'TEST-01-phpunit':'Hasil aktual eksekusi ulang: 213 tes, 2.347 assertions, tanpa kegagalan/error. Tabel laporan dibuat dari JUnit; pengujian menggunakan SQLite sementara.',
    'TEST-02-playwright':'Hasil aktual eksekusi ulang: 16/16 kelompok browser khusus impor lulus. Tabel dibuat dari hasil Playwright pada MySQL terisolasi; bukan klaim UAT manual klinik.'
};
const technical='../../evidence/2026-10-29/README.md';
const markdown=`# Screenshot ShifaStock - 29 Oktober 2026

Seluruh **15 gambar berada langsung dalam folder ini**: 8 gambar utama dan 7 pendamping. Resolusi 1440 x 900; data simulasi, bukan stok operasional klinik. Tidak ada password, token, maupun identitas pasien/dokter asli.

Baseline: \`${manifest.commit}\`. Pengambilan: **29 Oktober 2026 (WIB)**. Semua gambar berstatus **berhasil**. Nomor 22-29 beserta akhiran a/b merupakan usulan kelanjutan nomor 01-21; gambar lama tidak diganti.

## Indeks

| Nomor | Gambar | Kelompok | Role | Status |
| --- | --- | --- | --- | --- |
${packed.map(item=>`| ${item.number} | [${item.caption.replace(/^Gambar [^.]+\. /,'')} ](${item.file}) | ${item.primary?'Utama':'Pendamping'} | ${item.role} | Berhasil |`).join('\n')}

## Caption untuk Laporan

${packed.map(item=>`**${item.caption}**\n\n${item.description || testCaptions[item.id]}\n`).join('\n')}

## Hasil dan Batas Verifikasi

- PHPUnit: **213 tes / 2.347 assertions lulus**, 0 gagal, 0 error, 0 dilewati.
- Browser impor: **16/16 kelompok lulus**; browser alur fitur tambahan: **5/5 kelompok lulus**.
- Verifikasi MySQL dan rekonsiliasi stok lulus; build frontend berhasil.
- Pemeriksaan bersama masih berupa **draf tersimpan**, bukan hasil persetujuan atau finalisasi opname.
- Gambar form transfer, retur, dan kondisi khusus diambil sebelum posting; hasil transaksi dibedakan pada caption gambar pendamping.
- Tabel hasil tes adalah laporan dari artefak eksekusi aktual, bukan halaman fitur aplikasi yang baru dibuat.
- Nomor batch sama di Gudang dan Apotek diuji dengan berpindah lokasi; label terpilih konsisten dengan Apotek dan sisa batch 40 tablet.
- Pengambilan evidence tidak melakukan UAT manual oleh petugas klinik atau deployment. Commit/push dokumentasi kemudian diizinkan melalui permintaan terpisah; commit publikasi bukan baseline aplikasi yang diuji.
- Salinan gambar dalam folder ini byte-identik dengan sumber; SHA-256, waktu, role, dan asal file tersedia pada [manifest.json](manifest.json).

[Dokumentasi teknis lengkap, log aktual, kendala runner, dan batas pengujian](${technical}).
`;
fs.writeFileSync(path.join(destination,'README.md'),markdown);
fs.writeFileSync(path.join(destination,'manifest.json'),JSON.stringify({commit:manifest.commit,date:'2026-10-29',simulation:true,legacy_preserved:true,images:packed},null,2));
console.log(`PASS ${packed.length} byte-identical PNG files in ${destination}; ${legacy.length} existing root images preserved.`);
