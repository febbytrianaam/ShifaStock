const { chromium } = require('playwright');
const { spawn, execFileSync } = require('node:child_process');
const { once } = require('node:events');
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');

// One compressed clinic day through the real UI, chained so each step works on the stock the previous one left:
// opening balance, receipt draft and posting, transfer, PBF return, manual issues, daily count and approval,
// Neoklinik import, opname while service continues, stock card and reversal.
// Checks named "Perilaku saat ini" pin behaviour reported in the 4 Nov review (F03, F04, F05) and log it as OBSERVASI.
(async () => {
    const meta = JSON.parse(fs.readFileSync(process.env.FINAL_META, 'utf8'));
    const password = process.env.FINAL_PASSWORD;
    const output = process.env.FINAL_OUTPUT;
    assert(meta.phase === 'daily-cycle' && meta.simulation && password && new URL(meta.base_url).hostname === '127.0.0.1');
    const root = path.resolve(__dirname, '..');
    const server = spawn(process.env.FINAL_PHP, ['-S', `127.0.0.1:${meta.port}`, '-t', path.join(root, 'public'),
        path.join(root, 'vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php')],
    { cwd: path.join(root, 'public'), env: process.env, windowsHide: true, stdio: 'ignore' });
    const results = [], errors = [], observations = [], pages = {};
    const refs = {};
    const moments = {};
    let browser, page;
    const ready = async () => { await page.waitForLoadState('networkidle'); await page.evaluate(() => document.fonts.ready); };
    const goto = async (url) => { const response = await page.goto(meta.base_url + url); assert.equal(response.status(), 200); await ready(); };
    const record = async (name, run) => {
        results.push({ name, status: 'Gagal' });
        await run();
        assert.deepEqual(errors, []);
        results.at(-1).status = 'Lulus';
        console.log('PASS ' + name);
    };
    const observe = (finding, text) => { observations.push({ finding, text }); console.log(`OBSERVASI ${finding}: ${text}`); };
    const capture = async (file) => {
        assert(!(await page.locator('body').innerText()).includes(password));
        await page.screenshot({ path: path.join(output, file) });
    };
    const as = async (username) => {
        if (!pages[username]) {
            page = pages[username] = await browser.newPage({ viewport: { width: 1440, height: 900 }, locale: 'id-ID', timezoneId: 'Asia/Jakarta', reducedMotion: 'reduce' });
            page.on('pageerror', (error) => errors.push(error.message));
            page.on('dialog', async (dialog) => {
                if (dialog.type() === 'beforeunload') await dialog.accept();
                else { errors.push('Dialog browser tidak diharapkan: ' + dialog.message()); await dialog.dismiss(); }
            });
            await goto('/login');
            await page.getByLabel('Username', { exact: true }).fill(username);
            await page.getByLabel('Password', { exact: true }).fill(password);
            await page.getByRole('button', { name: 'Masuk', exact: true }).click();
            await page.waitForURL('**/dashboard');
            await ready();
        }
        page = pages[username];
    };
    const pick = async (select, query) => {
        const wrapper = select.locator('..');
        await wrapper.locator('input[role=combobox]').fill(query);
        await wrapper.locator('[role=option]').filter({ hasText: query }).first().click();
    };
    const body = async () => page.locator('body').innerText();
    const wib = (date = new Date()) => {
        const part = Object.fromEntries(new Intl.DateTimeFormat('en-CA', { timeZone: 'Asia/Jakarta', year: 'numeric', month: '2-digit', day: '2-digit',
            hour: '2-digit', minute: '2-digit', second: '2-digit', hourCycle: 'h23' }).formatToParts(date).map(({ type, value }) => [type, value]));
        const day = `${part.year}-${part.month}-${part.day}`;
        return { day, month: `${part.year}-${part.month}`, minute: `${day}T${part.hour}:${part.minute}`, second: `${day} ${part.hour}:${part.minute}:${part.second}`, clock: `${part.hour}:${part.minute}:${part.second}` };
    };
    const monthsAhead = (months) => { const date = new Date(); date.setUTCMonth(date.getUTCMonth() + months); return wib(date).day; };
    const neoklinik = (file, rows) => execFileSync(process.env.FINAL_PHP, [path.join(root, 'scripts/neoklinik-test-file.php'), path.join(output, file)],
        { input: JSON.stringify(rows.map((row) => ({ code: 'FINAL-001', name: 'Obat Simulasi Final', unit: 'TABLET', ...row }))) });
    const stockCard = async (medicine, location) => {
        const today = wib().day;
        await goto(`/kartu-stok?medicine_id=${medicine}&location_id=${location}&start=${today}&end=${today}`);
        return page.locator('table tbody tr');
    };
    const lastBalance = async (rows) => (await rows.last().locator('td').nth(8).innerText()).trim();
    const issue = async ({ location, quantity, occurredAt, source, notes }) => {
        await goto('/obat-keluar');
        const reference = await page.locator('#issue-reference').inputValue();
        await pick(page.locator('#issue-medicine'), 'FINAL-001');
        await page.locator('#issue-location').selectOption(String(location));
        await page.locator('#issue-quantity').fill(String(quantity));
        if (occurredAt) await page.locator('#issue-time').fill(occurredAt);
        if (source) await page.locator('#issue-source-reference').fill(source);
        await page.locator('#issue-notes').fill(notes);
        await page.getByRole('button', { name: 'Simpan & Kurangi Stok', exact: true }).click();
        await ready();
        assert((await body()).includes('Pengeluaran obat diposting'), 'Pengeluaran tidak diposting');
        assert((await body()).includes(reference));
        return reference;
    };
    const upload = async (file) => {
        await goto('/obat-keluar/impor');
        await page.getByLabel('File ekspor Neoklinik').setInputFiles(path.join(output, file));
        await page.locator('#import-location').selectOption(String(meta.pharmacy));
        if (!(await page.locator('#import-start').evaluate((element) => element.readOnly))) await page.locator('#import-start').fill(meta.import_start);
        await page.locator('[name="cutoff_confirmed"]').check();
        await page.getByRole('button', { name: 'Baca File & Lanjutkan' }).click();
        await page.waitForURL(/\/obat-keluar\/impor\/\d+$/);
        await ready();
        if (await page.locator('[data-import-mappings]').count()) {
            assert.equal(await page.locator('[data-import-match-row] select').first().inputValue(), String(meta.medicine), 'Saran obat FINAL-001 tidak terpilih');
            await page.locator('[data-import-map-confirm]').check();
            await page.getByRole('button', { name: 'Simpan Pilihan & Lanjutkan' }).click();
            await ready();
        }
    };
    const opnameRow = (batch) => page.locator('tbody tr').filter({ hasText: batch });
    const fillOpname = async () => {
        await opnameRow('SIM-BATCH-SAMA').locator('input[name$="[physical_quantity]"]').fill('38');
        await opnameRow('SIM-B-AWAL').locator('input[name$="[physical_quantity]"]').fill('9');
        await opnameRow('SIM-B-AWAL').locator('input[name$="[reason]"]').fill('Satu tablet pecah saat penyimpanan');
        await page.getByRole('button', { name: 'Simpan Isian', exact: true }).click();
        await ready();
        assert((await body()).includes('Isian stock opname disimpan.'));
    };
    const finalizeOpname = async () => {
        await page.getByRole('button', { name: 'Finalisasi Kepala Apoteker', exact: true }).click();
        await page.getByRole('button', { name: 'Ya, finalisasi', exact: true }).click();
        await ready();
    };
    try {
        for (let attempt = 0; ; attempt++) {
            try { if ((await fetch(meta.base_url + '/login')).ok) break; } catch {}
            if (attempt > 100) throw new Error('Server satu hari klinik belum siap');
            await new Promise((resolve) => setTimeout(resolve, 150));
        }
        browser = await chromium.launch({ headless: true, executablePath: 'C:/Program Files/Google/Chrome/Application/chrome.exe' });

        await record('Kepala apoteker mencatat stok awal obat B di Gudang lewat form', async () => {
            await as('apoteker_simulasi');
            await goto('/stok-awal');
            await pick(page.locator('#opening-medicine'), 'FINAL-002');
            await page.locator('#opening-location').selectOption(String(meta.warehouse));
            await page.locator('#opening-batch').fill('SIM-B-AWAL');
            await page.locator('#opening-expiry').fill(monthsAhead(6));
            await page.locator('#opening-quantity').fill('40');
            await page.locator('#opening-notes').fill('Saldo awal simulasi satu hari');
            await page.getByRole('button', { name: 'Catat Stok Awal', exact: true }).click();
            await ready();
            assert((await body()).includes('Stok awal dicatat dan dapat ditelusuri di kartu stok.'));
            assert((await page.locator('table tbody').innerText()).includes('SIM-B-AWAL'));
        });

        await record('Penerimaan obat A disimpan sebagai draf lalu ditambahkan ke stok Gudang', async () => {
            await as('petugas_simulasi');
            await goto('/obat-masuk');
            refs.receipt = await page.locator('#receipt-reference').inputValue();
            assert.equal(await page.locator('#receipt-destination').inputValue(), String(meta.warehouse));
            await page.locator('#receipt-invoice').fill('INV-SIM-HARIAN-01');
            await page.locator('#receipt-supplier').fill('PBF Simulasi Sehat');
            await pick(page.locator('[name="items[0][medicine_id]"]'), 'FINAL-001');
            await page.locator('[name="items[0][batch_number]"]').fill('SIM-A-TERIMA');
            await page.locator('[name="items[0][expiry_date]"]').fill(monthsAhead(3));
            await page.locator('[name="items[0][input_quantity]"]').fill('30');
            await page.locator('[name="items[0][input_quantity]"]').dispatchEvent('input');
            assert.equal(await page.locator('[name="items[0][quantity]"]').inputValue(), '30');
            await page.getByRole('button', { name: 'Simpan Draf', exact: true }).click();
            await page.waitForURL(/tab=riwayat/);
            await ready();
            assert((await body()).includes('Draf penerimaan tersimpan'));
            const document = page.locator('tr').filter({ hasText: refs.receipt }).filter({ has: page.getByRole('button', { name: 'Tambah ke Stok' }) });
            await document.getByRole('button', { name: 'Tambah ke Stok' }).click();
            await ready();
            assert((await body()).includes(`Draft penerimaan ${refs.receipt} berhasil diposting.`));
            assert((await page.locator('tr').filter({ hasText: refs.receipt }).first().innerText()).includes('Stok ditambahkan'));
        });

        await record('Transfer dua obat Gudang ke Apotek memakai batch kedaluwarsa terdekat', async () => {
            await goto('/transfer');
            refs.transfer = await page.locator('#transfer-reference').inputValue();
            assert.equal(await page.locator('#transfer-source').inputValue(), String(meta.warehouse));
            assert.equal(await page.locator('#transfer-target').inputValue(), String(meta.pharmacy));
            const lines = page.locator('[data-transfer-row]');
            await pick(lines.nth(0).locator('[data-transfer-medicine]'), 'FINAL-001');
            await lines.nth(0).locator('[data-transfer-quantity]').fill('35');
            await pick(lines.nth(1).locator('[data-transfer-medicine]'), 'FINAL-002');
            await lines.nth(1).locator('[data-transfer-quantity]').fill('10');
            await page.getByRole('button', { name: 'Simpan Transfer', exact: true }).click();
            await page.getByRole('button', { name: 'Ya, simpan transfer', exact: true }).click();
            await ready();
            assert((await body()).includes('Transfer diposting'));
            const history = page.locator('tr').filter({ has: page.getByText(refs.transfer, { exact: true }) });
            assert.equal(await history.count(), 2);
            assert((await history.allInnerTexts()).join(' ').includes('SIM-BATCH-SAMA'), 'Transfer obat A tidak memakai batch kedaluwarsa terdekat');
        });

        await record('Retur PBF mengurangi batch penerimaan yang dipilih di Gudang', async () => {
            await goto('/retur-pbf');
            refs.return = await page.locator('#return-reference').inputValue();
            await page.locator('#return-supplier').fill('PBF Simulasi Sehat');
            await pick(page.locator('#return-medicine'), 'FINAL-001');
            await page.locator('#return-location').selectOption(String(meta.warehouse));
            await pick(page.locator('#return-batch'), 'SIM-A-TERIMA');
            await page.locator('#return-quantity').fill('5');
            await page.locator('#return-reason').fill('Kemasan rusak saat diterima');
            await page.getByRole('button', { name: 'Simpan Retur & Kurangi Stok', exact: true }).click();
            await ready();
            assert((await body()).includes('Retur PBF diposting. Stok sumber sudah berkurang.'));
            assert((await body()).includes(refs.return));
        });

        await record('Pengeluaran manual Apotek memakai FEFO; satu pengeluaran Gudang tercatat keliru', async () => {
            await goto('/obat-keluar');
            await pick(page.locator('#issue-medicine'), 'FINAL-001');
            await page.locator('#issue-location').selectOption(String(meta.pharmacy));
            await page.locator('#issue-quantity').fill('3');
            await page.locator('[data-stock-context-plan]').filter({ hasText: 'SIM-BATCH-SAMA' }).waitFor();
            refs.issue = await issue({ location: meta.pharmacy, quantity: 3, source: 'RESEP-SIM-PAGI', notes: 'Resep pagi' });
            refs.mistake = await issue({ location: meta.warehouse, quantity: 1, notes: 'Salah pilih lokasi, akan dibalik' });
        });

        await record('Petugas mengirim hitung fisik Apotek dengan selisih per batch', async () => {
            await goto('/pengecekan-malam');
            await page.locator('[name="location_id"]').selectOption(String(meta.pharmacy));
            await page.getByRole('button', { name: 'Buka / Mulai Pemeriksaan', exact: true }).click();
            await page.waitForURL(/\/pengecekan-malam\/\d+$/);
            await ready();
            refs.daily = new URL(page.url()).pathname;
            const medicineA = page.locator('[data-nightly-row]').filter({ hasText: 'FINAL-001' });
            const medicineB = page.locator('[data-nightly-row]').filter({ hasText: 'FINAL-002' });
            assert.equal(await page.locator('[data-nightly-row]').count(), 2);
            assert.equal(Number(await medicineA.getAttribute('data-system-quantity')), 52);
            await medicineA.locator('[data-nightly-row-toggle]').click();
            assert.equal(await medicineA.locator('[data-nightly-batch-count]').count(), 2);
            await medicineA.getByLabel('Hasil hitung batch SIM-BATCH-SAMA untuk Obat Simulasi Final', { exact: true }).fill('50');
            await medicineB.locator('input[name$="[quantity]"]').fill('10');
            await page.getByRole('button', { name: 'Kirim ke Kepala Apoteker', exact: true }).click();
            await ready();
            assert((await page.locator('[role="status"]').innerText()).includes('Laporan dikirim'));
            moments.submitted = new Date();
            // A sale happens in Neoklinik after the count was taken but before the head pharmacist approves it.
            await page.waitForTimeout(1500);
            moments.saleBeforeApproval = new Date();
            await page.waitForTimeout(1500);
        });

        await record('Kepala apoteker menyetujui hitung fisik dan stok Apotek disesuaikan', async () => {
            await as('apoteker_simulasi');
            await goto(refs.daily);
            await page.getByRole('tab', { name: 'Tinjauan Apoteker', exact: true }).click();
            await page.getByRole('button', { name: 'Setujui & Sesuaikan Stok', exact: true }).click();
            await page.getByRole('button', { name: 'Ya, setujui dan sesuaikan', exact: true }).click();
            await ready();
            assert((await page.locator('[role="status"]').innerText()).includes('Disetujui'));
            moments.approved = new Date();
            await page.waitForTimeout(1500);
            moments.saleAfterApproval = new Date();
        });

        await record('Perilaku saat ini (F03): ekspor sehari penuh tertahan karena penjualan sebelum persetujuan', async () => {
            await as('petugas_simulasi');
            neoklinik('Neoklinik_Sehari.xlsx', [
                { sold_at: wib(moments.saleBeforeApproval).second, sale: 'SIM-JUAL-SIANG', quantity: 4 },
                { sold_at: wib(moments.saleAfterApproval).second, sale: 'SIM-JUAL-SORE', quantity: 6 },
            ]);
            await upload('Neoklinik_Sehari.xlsx');
            assert((await page.locator('[data-import-problems]').innerText()).includes('Pengeluaran lebih lama dari stok awal atau hitung fisik terakhir'));
            assert((await body()).includes('Belum ada stok yang dikurangi'));
            assert.equal(await page.locator('[data-import-post] input[type="checkbox"]').count(), 0);
            await capture('Satu_Hari_Impor_Tertahan.png');
            observe('F03', `Ekspor sehari ditolak seluruhnya: penjualan 4 tablet pukul ${wib(moments.saleBeforeApproval).clock} terjadi setelah hitung dikirim `
                + `(${wib(moments.submitted).clock}) tetapi sebelum disetujui (${wib(moments.approved).clock}), sehingga penjualan 6 tablet pukul `
                + `${wib(moments.saleAfterApproval).clock} ikut tertahan.`);
        });

        await record('Ekspor sesudah persetujuan diposting FEFO dengan pemetaan tersimpan', async () => {
            neoklinik('Neoklinik_Sore.xlsx', [{ sold_at: wib(moments.saleAfterApproval).second, sale: 'SIM-JUAL-SORE', quantity: 6 }]);
            await upload('Neoklinik_Sore.xlsx');
            assert((await page.locator('[data-import-summary]').innerText()).includes('1 siap'));
            for (const input of await page.locator('[data-import-post] input[type="checkbox"]').all()) await input.check();
            await page.locator('[data-import-post] button[type="submit"]').click();
            await page.getByRole('button', { name: 'Ya, simpan pengeluaran', exact: true }).click();
            await ready();
            assert((await page.locator('[role="status"]').innerText()).includes('1 transaksi sumber diposting'));
        });

        await record('Perilaku saat ini (F04): penjualan siang dicatat manual dengan waktu mundur tanpa peringatan', async () => {
            const backdated = wib(moments.saleBeforeApproval).minute;
            refs.backdated = await issue({ location: meta.pharmacy, quantity: 4, occurredAt: backdated, source: 'SIM-JUAL-SIANG', notes: 'Penjualan siang yang tertahan di impor' });
            observe('F04', `Pengeluaran manual bertanggal ${backdated.replace('T', ' ')} diterima tanpa peringatan walau hitung fisik sudah disetujui `
                + `pukul ${wib(moments.approved).clock}; aplikasi tidak bisa membedakan penjualan yang sudah atau belum termasuk hitungan.`);
        });

        await record('Perilaku saat ini (F05): opname Apotek tertahan karena pelayanan berjalan, lalu final setelah hitung ulang', async () => {
            await as('apoteker_simulasi');
            await goto('/stock-opname');
            await page.locator('#session-period').fill(wib().month);
            await page.locator('#session-location').selectOption(String(meta.pharmacy));
            await page.locator('#session-cutoff').fill(wib().minute);
            await page.locator('#session-notes').fill('Opname simulasi saat pelayanan berjalan');
            await page.getByRole('button', { name: 'Buat Sesi Bulanan', exact: true }).click();
            await ready();
            assert((await body()).includes('Sesi stock opname dibuat dari snapshot stok saat ini.'));
            const session = new URL(page.url());
            assert.equal(await page.locator('tbody tr').filter({ has: page.locator('input[name$="[physical_quantity]"]') }).count(), 2);

            await as('petugas_simulasi');
            refs.service = await issue({ location: meta.pharmacy, quantity: 2, source: 'RESEP-SIM-SAAT-OPNAME', notes: 'Pelayanan saat opname' });

            await as('apoteker_simulasi');
            await goto(session.pathname + session.search);
            await fillOpname();
            await finalizeOpname();
            assert((await body()).includes('Stok, transaksi, atau satuan berubah sejak snapshot dibuat.'));
            await capture('Satu_Hari_Opname_Tertahan.png');
            observe('F05', 'Satu pengeluaran 2 tablet di Apotek setelah sesi opname dibuat membuat finalisasi ditolak; '
                + 'opname baru bisa final setelah Mulai Hitung Ulang dan seluruh jumlah fisik diisi lagi.');

            await page.getByRole('button', { name: 'Mulai Hitung Ulang', exact: true }).click();
            await page.getByRole('button', { name: 'Ya, mulai hitung ulang', exact: true }).click();
            await ready();
            assert((await body()).includes('Snapshot diperbarui.'));
            await fillOpname();
            await finalizeOpname();
            assert((await body()).includes('Stock opname difinalisasi. Selisih sudah dicatat sebagai mutasi penyesuaian.'));
        });

        await record('Kartu stok Apotek menelusuri seluruh hari dan saldo akhir sesuai', async () => {
            const rows = await stockCard(meta.medicine, meta.pharmacy);
            // textContent, not innerText: the type badge is upper-cased by CSS.
            const text = (await rows.allTextContents()).join('\n');
            for (const reference of [refs.transfer, refs.issue, refs.backdated, refs.service]) assert(text.includes(reference), 'Referensi tidak ada di kartu stok: ' + reference);
            for (const type of ['Stok Awal', 'Transfer', 'Pengeluaran', 'Hitung Fisik', 'Opname']) assert(text.includes(type), 'Jenis tidak ada di kartu stok: ' + type);
            assert(text.includes('OUT-IMP-'), 'Mutasi impor tidak ada di kartu stok');
            assert.equal(await lastBalance(rows), '38');
            await capture('Satu_Hari_Kartu_Stok_Apotek.png');
        });

        await record('Kepala apoteker membalik pengeluaran Gudang yang keliru dari kartu stok', async () => {
            let rows = await stockCard(meta.medicine, meta.warehouse);
            assert.equal(await lastBalance(rows), '39');
            await rows.filter({ hasText: refs.mistake }).getByRole('button', { name: 'Batalkan Transaksi' }).click();
            await page.getByRole('button', { name: 'Ya, batalkan transaksi', exact: true }).click();
            await ready();
            rows = await stockCard(meta.medicine, meta.warehouse);
            assert((await rows.filter({ hasText: refs.mistake }).first().innerText()).includes('Sudah dibalik'));
            assert.equal(await rows.filter({ hasText: 'Reversal' }).count(), 1);
            assert.equal(await lastBalance(rows), '40');
            for (const reference of [refs.receipt, refs.transfer, refs.return]) assert((await rows.allInnerTexts()).join('\n').includes(reference), 'Referensi Gudang hilang: ' + reference);
            await capture('Satu_Hari_Reversal_Gudang.png');
        });
        console.log(`${results.length}/${results.length} daily-cycle browser checks passed.`);
    } catch (error) {
        if (results.length) results.at(-1).error = error.message.replaceAll(password, '[disembunyikan]');
        if (page) await capture('Kendala_Satu_Hari.png').catch(() => {});
        throw error;
    } finally {
        if (browser) await browser.close();
        if (server.exitCode === null) { const stopped = once(server, 'exit'); server.kill(); await stopped; }
        fs.writeFileSync(path.join(output, 'results.json'), JSON.stringify({ tested_at: new Date().toISOString(), commit: meta.commit,
            working_tree: true, simulation: true, results, observations, errors }, null, 2));
    }
})().catch((error) => { console.error(error.message.replaceAll(process.env.FINAL_PASSWORD || 'NO_SECRET', '[disembunyikan]')); process.exitCode = 1; });
