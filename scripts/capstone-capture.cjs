// App images use live HTTP; image 19 is a separately identified report from actual test evidence.
const { chromium } = require('playwright');
const fs = require('node:fs');
const path = require('node:path');
const { pathToFileURL } = require('node:url');
const assert = require('node:assert/strict');
const crypto = require('node:crypto');

(async () => {
    const meta = JSON.parse(fs.readFileSync(process.env.CAPSTONE_METADATA, 'utf8'));
    const password = process.env.CAPSTONE_PASSWORD;
    const output = process.env.CAPSTONE_OUTPUT;
    assert(password && meta.simulation === true);
    const executablePath = [process.env.SHIFA_BROWSER_PATH, 'C:/Program Files/Google/Chrome/Application/chrome.exe',
        'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe'].find((p) => p && fs.existsSync(p));
    const browser = await chromium.launch({ headless: true, ...(executablePath ? { executablePath } : {}) });
    const contexts = new Map();
    const rows = [];
    const findings = [];
    const roleLabels = { admin: 'Administrator', petugas: 'Petugas Farmasi', kepala: 'Kepala Apoteker', anonymous: 'Tanpa login', evidence: 'Tidak berlaku (laporan dokumentasi hasil tes)' };
    const settle = async (page) => {
        await page.waitForLoadState('networkidle');
        await page.evaluate(() => document.fonts.ready);
        await page.waitForFunction(() => [...document.images].every((image) => image.complete && image.naturalWidth > 0));
        await page.waitForTimeout(650);
    };
    const pageFor = async (role) => {
        if (contexts.has(role)) return contexts.get(role);
        const context = await browser.newContext({ viewport: { width: 1440, height: 900 }, deviceScaleFactor: 1,
            locale: 'id-ID', timezoneId: 'Asia/Jakarta', reducedMotion: 'reduce' });
        const page = await context.newPage();
        page.auditErrors = [];
        page.on('pageerror', (error) => page.auditErrors.push(error.message));
        page.on('response', (response) => {
            if (response.status() >= 400) page.auditErrors.push('HTTP '+response.status()+' '+new URL(response.url()).pathname);
        });
        page.on('dialog', async (dialog) => {
            if (dialog.type() === 'beforeunload') await dialog.accept();
            else { page.auditErrors.push('Dialog browser tidak diharapkan'); await dialog.dismiss(); }
        });
        contexts.set(role, page);
        if (!['anonymous', 'evidence'].includes(role)) {
            await page.goto(meta.base_url+'/login');
            await page.getByLabel('Username', { exact: true }).fill(role+'_simulasi');
            await page.getByLabel('Password', { exact: true }).fill(password);
            await page.getByRole('button', { name: 'Masuk', exact: true }).click();
            await page.waitForURL('**/dashboard');
            await settle(page);
        }
        return page;
    };
    const goto = async (page, route) => {
        const response = await page.goto(meta.base_url+route);
        assert(response && response.ok(), 'Halaman tidak berhasil: '+route);
        assert(!new URL(page.url()).pathname.endsWith('/login'), 'Sesi tidak memiliki akses');
        await settle(page);
    };
    const searchable = async (page, selector, query) => {
        const wrapper = page.locator(selector).locator('..');
        await wrapper.locator('input[role="combobox"]').fill(query);
        await wrapper.locator('.searchable-select-menu [role="option"]').filter({ hasText: query }).first().click();
        assert(await page.locator(selector).inputValue(), 'Pilihan backend belum terisi');
    };
    const take = async (number, file, module, role, description, action, extra = false) => {
        const target = path.join(output, file);
        const row = { number, file, module, role: roleLabels[role], description, status: 'gagal', extra,
            commit: meta.commit, simulation: true, captured_at: new Date().toLocaleString('sv-SE', { timeZone: 'Asia/Jakarta' })+' WIB' };
        try {
            const page = await pageFor(role);
            const previousErrors = page.auditErrors.length;
            await action(page);
            await settle(page);
            assert.equal(page.auditErrors.length, previousErrors, page.auditErrors.slice(previousErrors).join('; '));
            const visible = await page.locator('body').innerText();
            assert(!visible.includes(password), 'Nilai password terlihat');
            assert(!/SQLSTATE\[|Whoops|Internal Server Error|Stack trace:|419\s+PAGE EXPIRED/.test(visible), 'Tampilan mengandung error');
            await page.screenshot({ path: target, fullPage: false });
            row.status = 'berhasil';
            row.url = role === 'evidence' ? 'Bukti_Pengujian/Laporan_Pengujian.html (laporan dokumentasi)' : new URL(page.url()).pathname+new URL(page.url()).search;
            row.sha256 = crypto.createHash('sha256').update(fs.readFileSync(target)).digest('hex');
            row.dimensions = '1440 x 900';
            console.log('OK '+file);
        } catch (error) {
            if (fs.existsSync(target)) fs.unlinkSync(target);
            row.reason = String(error.message).split(password).join('[disembunyikan]').slice(0, 1200);
            findings.push(module+': '+row.reason);
            console.log('GAGAL '+file+': '+row.reason.split('\n')[0]);
        }
        rows.push(row);
        fs.writeFileSync(path.join(output, 'manifest.json'), JSON.stringify({ ...meta, base_url: undefined, screenshots: rows, findings }, null, 2));
    };
    try {
        await take(1, 'Gambar_01_Login.png', 'Login', 'anonymous', 'Form login asli; kolom password kosong.', async (p) => {
            await p.goto(meta.base_url+'/login'); await p.getByLabel('Username', { exact: true }).fill('petugas_simulasi');
            assert.equal(await p.getByLabel('Password', { exact: true }).inputValue(), '');
        });
        await take(2, 'Gambar_02_Pengguna_dan_Hak_Akses.png', 'Pengelolaan Pengguna dan Hak Akses', 'admin', 'Daftar empat akun simulasi dengan role berbeda; tab Hak Akses tersedia.', async (p) => {
            await goto(p, '/admin/pengguna'); assert(await p.locator('tr').filter({ hasText: 'petugas_simulasi' }).count());
        });
        await take('02b', 'Gambar_02b_Matriks_Hak_Akses.png', 'Matriks Hak Akses (pendukung)', 'admin', 'Tab matriks RBAC asli; tidak mengubah konfigurasi hak akses.', async (p) => {
            await goto(p, '/admin/pengguna?tab=permissions'); assert(await p.getByText('Matrix Hak Akses Role').count());
        }, true);
        await take(3, 'Gambar_03_Dashboard_Data_Real.png', 'Dashboard - Mode Data Real', 'petugas', 'Mode Data Real membaca database simulasi, bukan database klinik.', async (p) => {
            await goto(p, '/dashboard?data=real'); assert(await p.getByText('Data Real', { exact: true }).count());
            assert(await p.locator('canvas').count());
            await p.waitForTimeout(900);
        });
        await take(4, 'Gambar_04_Master_Obat.png', 'Daftar Master Obat', 'kepala', 'Enam obat simulasi dengan satuan, verifikasi, dan status aktif.', async (p) => {
            await goto(p, '/obat'); assert(await p.getByText('Paracetamol 500 mg', { exact: true }).count());
        });
        await take(5, 'Gambar_05_Form_Edit_Obat.png', 'Form Tambah/Edit Obat', 'kepala', 'Form Edit Obat diisi dari master simulasi; tidak menyimpan perubahan.', async (p) => {
            await goto(p, '/obat'); await p.locator('tr').filter({ hasText: 'SIM-001' }).getByRole('button', { name: 'Edit', exact: true }).click();
            await p.locator('#medicine-dialog[open]').waitFor();
            await p.waitForFunction(() => document.getElementById('name').value === 'Paracetamol 500 mg');
        });
        await take(6, 'Gambar_06_Form_Penerimaan_Obat.png', 'Form Penerimaan Obat', 'petugas', 'Edit draf penerimaan asli berisi dua item dan konversi Box; draf belum menambah stok.', async (p) => {
            await goto(p, '/obat-masuk/'+meta.draft_id+'/edit'); assert.equal(await p.locator('[data-receipt-row]').count(), 2);
            assert.equal(await p.locator('#receipt-invoice').inputValue(), 'SIM-PBF-004');
        });
        await take(7, 'Gambar_07_Riwayat_Penerimaan.png', 'Daftar/Detail Penerimaan', 'petugas', 'Riwayat tiga dokumen terposting dan satu draf simulasi.', async (p) => {
            await goto(p, '/obat-masuk?tab=riwayat'); assert(await p.getByText('SIM-FKT-001', { exact: true }).count());
        });
        await take(8, 'Gambar_08_Transfer_Gudang_Apotek.png', 'Form Transfer Gudang-Apotek', 'petugas', 'Isian transfer 20 tablet dengan FEFO otomatis; belum dikirim.', async (p) => {
            await goto(p, '/transfer'); await p.locator('#transfer-source').selectOption(String(meta.warehouse_id));
            await p.locator('#transfer-target').selectOption(String(meta.pharmacy_id)); await searchable(p, '#transfer-medicine', 'SIM-001');
            await p.locator('#transfer-quantity').fill('20'); await p.locator('#transfer-notes').fill('Simulasi distribusi untuk dokumentasi capstone.');
        });
        await take(9, 'Gambar_09_Pengeluaran_Obat.png', 'Form Pengeluaran Obat', 'petugas', 'Pengeluaran simulasi dari Apotek tanpa data pasien; isian belum dikirim.', async (p) => {
            await goto(p, '/obat-keluar'); await p.locator('#issue-location').selectOption(String(meta.pharmacy_id));
            await searchable(p, '#issue-medicine', 'SIM-001'); await p.locator('#issue-quantity').fill('5');
            await p.locator('#issue-source-reference').fill('SIM-LAYANAN-002'); await p.locator('#issue-notes').fill('Contoh input simulasi tanpa identitas pasien.');
        });
        await take(10, 'Gambar_10_Stok_Awal.png', 'Form Stok Awal', 'kepala', 'Role Kepala Apoteker sesuai izin default. Contoh stok awal belum dikirim.', async (p) => {
            await goto(p, '/stok-awal'); await searchable(p, '#opening-medicine', 'SIM-004');
            await p.locator('#opening-location').selectOption(String(meta.warehouse_id)); await p.locator('#opening-batch').fill('SIM-DOK-001');
            await p.locator('#opening-expiry').fill('2028-01-31'); await p.locator('#opening-quantity').fill('25');
            await p.locator('#opening-notes').fill('Contoh hasil hitung awal simulasi capstone.');
        });
        await take(11, 'Gambar_11_Kartu_Stok_dan_Mutasi.png', 'Kartu Stok dan Riwayat Mutasi', 'petugas', 'Kartu Paracetamol di Gudang; stok awal, penerimaan, transfer dan retur berasal dari transaksi simulasi.', async (p) => {
            await goto(p, '/kartu-stok?'+new URLSearchParams({ medicine_id: meta.medicine_id, location_id: meta.warehouse_id, start: meta.start, end: meta.end }));
            assert(await p.getByText('SIM-TRF-001', { exact: true }).count());
            await p.locator('table').first().scrollIntoViewIfNeeded();
        });
        await take(12, 'Gambar_12_Retur_PBF.png', 'Form Retur PBF', 'petugas', 'Contoh retur batch dari Gudang dengan alasan dan supplier simulasi; belum dikirim.', async (p) => {
            await goto(p, '/retur-pbf'); await p.locator('#return-location').selectOption(String(meta.warehouse_id));
            await searchable(p, '#return-medicine', 'SIM-001'); await searchable(p, '#return-batch', 'SIM-PCT-01');
            await p.locator('#return-supplier').fill('PBF Simulasi Capstone'); await p.locator('#return-quantity').fill('2');
            await p.locator('#return-reason').fill('Kemasan rusak pada data simulasi');
        });
        await take(13, 'Gambar_13_Kondisi_Khusus.png', 'Form Kondisi Khusus', 'petugas', 'Contoh pengurangan batch rusak di Gudang dengan label lokasi konsisten; belum dikirim. Lihat kendala label Apotek pada catatan terpisah.', async (p) => {
            await goto(p, '/kondisi-khusus'); await p.locator('#adjust-location').selectOption(String(meta.warehouse_id));
            await searchable(p, '#adjust-medicine', 'SIM-001'); await searchable(p, '#adjust-batch', 'SIM-PCT-01');
            await p.locator('#adjust-quantity').fill('1'); await p.locator('#adjust-notes').fill('Simulasi satu tablet rusak saat pemeriksaan.');
            assert((await p.locator('#adjust-batch').locator('..').locator('input[role="combobox"]').inputValue()).includes('Gudang'));
        });
        await take(14, 'Gambar_14_Pemeriksaan_Stok_Harian.png', 'Pemeriksaan Stok Harian', 'petugas', 'Draf pemeriksaan terisi enam obat sesuai stok database simulasi.', async (p) => {
            await goto(p, '/pengecekan-malam/'+meta.daily_id); assert(await p.getByRole('button', { name: 'Kirim ke Kepala Apoteker', exact: true }).count());
            await p.locator('#nightly-table-scroll').scrollIntoViewIfNeeded();
        });
        // Submit through the real form before the reviewer screenshot; do not approve or change stock.
        try {
            const p = await pageFor('petugas'); await goto(p, '/pengecekan-malam/'+meta.daily_id);
            await p.getByRole('button', { name: 'Kirim ke Kepala Apoteker', exact: true }).click();
            await settle(p);
            assert(await p.getByText('Laporan dikirim untuk ditinjau kepala apoteker.').count(), 'Laporan tidak berhasil dikirim');
        } catch (error) { findings.push('Pengiriman pemeriksaan: '+String(error.message).split(password).join('[disembunyikan]')); }
        await take(15, 'Gambar_15_Review_Pemeriksaan_Harian.png', 'Review Pemeriksaan Harian', 'kepala', 'Laporan terkirim; perbandingan stok dan tindakan persetujuan/koreksi. Tidak mengklaim telah disetujui.', async (p) => {
            await goto(p, '/pengecekan-malam/'+meta.daily_id); await p.locator('form').filter({ has: p.locator('#review') }).scrollIntoViewIfNeeded();
            assert(await p.getByRole('button', { name: 'Setujui & Sesuaikan Stok', exact: true }).count());
        });
        await take(16, 'Gambar_16_Sesi_Stock_Opname_Bulanan.png', 'Sesi Stock Opname Bulanan', 'petugas', 'Sesi bulanan Gudang dibuat dari snapshot aktual database simulasi; belum final.', async (p) => {
            await goto(p, '/stock-opname?period='+meta.period+'&session='+meta.session_id);
            assert(await p.getByText('Sesi opname bulanan simulasi capstone.').count() || await p.getByText('Sesi Bulanan', { exact: true }).count());
        });
        await take(17, 'Gambar_17_Detail_Penghitungan_Stock_Opname.png', 'Detail Penghitungan Stock Opname', 'kepala', 'Detail snapshot dan hitungan fisik yang telah disimpan; tombol finalisasi tersedia tetapi tidak dijalankan.', async (p) => {
            await goto(p, '/stock-opname?period='+meta.period+'&session='+meta.session_id);
            await p.locator('section').filter({ has: p.locator('form[action$="/finalize"]') }).scrollIntoViewIfNeeded();
            assert(await p.getByRole('button', { name: 'Finalisasi Kepala Apoteker', exact: true }).count());
        });
        await take(18, 'Gambar_18_Laporan_Persediaan.png', 'Laporan Persediaan', 'kepala', 'Ringkasan persediaan dan transaksi dari database simulasi.', async (p) => {
            await goto(p, '/laporan'); assert(await p.getByText('Paracetamol 500 mg', { exact: true }).count());
        });
        await take(19, 'Gambar_19_Hasil_Pengujian.png', 'Bukti Hasil Pengujian Otomatis', 'evidence', 'Tabel ringkasan dari JUnit dan keluaran MySQL aktual; bukan halaman fitur aplikasi. Keluaran mentah tetap disertakan.', async (p) => {
            await p.goto(pathToFileURL(path.join(output, 'Bukti_Pengujian/Laporan_Pengujian.html')).href);
            assert(await p.getByRole('heading', { name: 'Hasil Pengujian Otomatis', exact: true }).count());
        });
    } finally {
        for (const page of contexts.values()) await page.context().close();
        await browser.close();
        const header = ['# Screenshot Implementasi ShifaStock', '', 'Dokumentasi Laporan Kemajuan Capstone Universitas Terbuka (STSI4440).', '',
            '- Commit aplikasi: `'+meta.commit+'`.', '- Waktu mulai pengambilan: '+meta.captured_at+' (Asia/Jakarta).',
            '- Resolusi setiap PNG: **1440 x 900**, skala perangkat 1, tanpa penggabungan atau perubahan isi screenshot.',
            '- **Semua akun, supplier, batch, jumlah stok, dan transaksi adalah simulasi**, dibuat di database MySQL terpisah.',
            '- Nama/identitas visual Klinik Shifa Medika merupakan identitas aplikasi; tidak ada data pasien atau kredensial akun nyata.',
            '- Mode Data Real berarti data dari database aplikasi simulasi, **bukan data klinik**.',
            '- Gambar 01-18 dan 02b diambil dari aplikasi melalui HTTP lokal dengan Playwright tanpa manipulasi isi halaman. Gambar 19 adalah laporan HTML dokumentasi dari hasil tes aktual, bukan fitur aplikasi.',
            '- Form yang belum disimpan/dikirim disebutkan secara eksplisit; keberadaan form bukan bukti semua kasus validasi atau UAT telah lulus.',
            '- Password acak hanya diteruskan melalui memori/environment proses; tidak disimpan dalam repository, screenshot, atau metadata.', '',
            '## Daftar Gambar', '', '| No. | Modul / File | Deskripsi | Role | Status |', '| --- | --- | --- | --- | --- |'];
        for (const row of rows) header.push('| '+row.number+' | '+(row.status === 'berhasil' ? '['+row.module+']('+row.file+')' : row.module)+' | '+row.description.replaceAll('|', '/')+' | '+row.role+' | '+row.status+' |');
        header.push('', 'Commit, tanggal per gambar, URL relatif, dan SHA-256 setiap PNG dicatat di [manifest.json](manifest.json). Gambar 02b adalah pendukung tambahan untuk memperlihatkan matriks permission, terpisah dari daftar pengguna.', '',
            '## Bukti Pengujian', '', 'Gambar 19 adalah screenshot [laporan tabel](Bukti_Pengujian/Laporan_Pengujian.html), bukan tampilan fitur ShifaStock. Jumlah kasus dan assertion dihitung dari JUnit XML; skenario MySQL, build, serta rekonsiliasi berasal dari log aktual. Rincian kasus tersedia di bagian bawah HTML. Keluaran lengkap dan kode exit tersedia di [Bukti_Pengujian](Bukti_Pengujian/hasil-eksekusi.json). Tidak mengambil angka historis dari README proyek.', '',
            '## Kendala Dan Batasan', '', ...(findings.length ? findings.map((f) => '- '+f.replaceAll('\n', ' ').replaceAll('|', '/')) : ['- Tidak ada kegagalan pengambilan pada rangkaian terakhir.']), '',
            '- Temuan UI label batch lintas lokasi pada Kondisi Khusus dan kendala shutdown server Windows dijelaskan di [Kendala dan verifikasi](Kendala_dan_Verifikasi.md). Bukti UI asli ada di folder Bukti_Kendala. Keberhasilan gambar utama tidak meniadakan temuan tersebut.', '',
            'Pengujian otomatis dan tangkapan ini tidak menggantikan UAT petugas, audit keamanan menyeluruh, atau pembuktian seluruh kondisi produksi. Source fitur aplikasi tidak diubah untuk keperluan dokumentasi.', '',
            '## Pengambilan Ulang', '', 'Jalankan `php scripts/capstone-capture.php` setelah dependency PHP, frontend, MySQL, dan Playwright tersedia. Runner memerlukan izin membuat/menghapus database uji; server dibatasi ke 127.0.0.1. Gunakan resolusi modul Node.js yang memuat Playwright. Runner membersihkan database dan server simulasinya saat selesai.', '');
        fs.writeFileSync(path.join(output, 'README.md'), header.join('\n'));
    }
    const main = rows.filter((r) => !r.extra);
    console.log('Selesai: '+main.filter((r) => r.status === 'berhasil').length+'/19 screenshot utama; '+rows.filter((r) => r.extra && r.status === 'berhasil').length+' pendukung.');
    if (main.some((r) => r.status !== 'berhasil')) process.exitCode = 1;
})().catch((error) => { console.error('Capture gagal: '+String(error.message).split(process.env.CAPSTONE_PASSWORD || '\0').join('[disembunyikan]')); process.exitCode = 1; });
