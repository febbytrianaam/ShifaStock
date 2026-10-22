const { chromium } = require('playwright');
const { spawn } = require('node:child_process');
const { once } = require('node:events');
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');

(async () => {
    const meta = JSON.parse(fs.readFileSync(process.env.FINAL_META, 'utf8'));
    const password = process.env.FINAL_PASSWORD;
    assert(meta.simulation && meta.phase === 'selected-nightly' && new URL(meta.base_url).hostname === '127.0.0.1' && password);
    const root = path.resolve(__dirname, '..');
    const output = process.env.FINAL_OUTPUT;
    const server = spawn(process.env.FINAL_PHP, ['-S', `127.0.0.1:${meta.port}`, '-t', path.join(root, 'public'),
        path.join(root, 'vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php')],
    { cwd: path.join(root, 'public'), env: process.env, windowsHide: true, stdio: 'ignore' });
    const rows = [];
    const errors = [];
    let browser;
    let page;
    let reportUrl;
    let dailyPosts = 0;
    let reportPosts = 0;
    const ready = async () => { await page.waitForLoadState('networkidle'); await page.evaluate(() => document.fonts.ready); };
    const goto = async (url) => { const response = await page.goto(meta.base_url + url); assert.equal(response.status(), 200); await ready(); };
    const record = async (name, callback) => {
        rows.push({ name, status: 'Gagal' });
        await callback();
        assert.deepEqual(errors, []);
        rows.at(-1).status = 'Lulus';
        console.log('PASS ' + name);
    };
    const capture = async (name) => {
        assert(!(await page.locator('body').innerText()).includes(password));
        await page.screenshot({ path: path.join(output, name) });
    };
    const login = async (username, viewport = { width: 1440, height: 900 }) => {
        page = await browser.newPage({ viewport, locale: 'id-ID', timezoneId: 'Asia/Jakarta' });
        page.on('pageerror', (error) => errors.push(error.message));
        page.on('request', (request) => { if (request.method() === 'POST' && request.url().endsWith('/pengecekan-malam')) dailyPosts += 1; });
        page.on('request', (request) => { if (request.method() === 'POST' && /\/pengecekan-malam\/\d+$/.test(request.url())) reportPosts += 1; });
        await goto('/login');
        await page.getByLabel('Username', { exact: true }).fill(username);
        await page.getByLabel('Password', { exact: true }).fill(password);
        await page.getByRole('button', { name: 'Masuk', exact: true }).click();
        await page.waitForURL('**/dashboard');
        await ready();
        await goto('/pengecekan-malam');
    };
    const checkWidth = async () => {
        const sizes = await page.evaluate(() => ({ width: window.innerWidth, document: document.documentElement.scrollWidth }));
        assert(sizes.document <= sizes.width + 1, JSON.stringify(sizes));
    };
    try {
        for (let i = 0; ; i++) {
            try { if ((await fetch(meta.base_url + '/login')).ok) break; } catch {}
            if (i > 100) throw new Error('Isolated daily-count server not ready');
            await new Promise((resolve) => setTimeout(resolve, 150));
        }
        browser = await chromium.launch({ headless: true, executablePath: 'C:/Program Files/Google/Chrome/Application/chrome.exe' });
        await record('Desktop: complete default and selected mode with searchable medicine checkboxes', async () => {
            await login('petugas_simulasi');
            assert(await page.locator('[name="scope"][value="complete"]').isChecked());
            assert(await page.locator('[data-nightly-medicine-picker]').isHidden());
            await page.locator('[name="scope"][value="selected"]').check();
            assert(await page.locator('[data-nightly-medicine-picker]').isVisible());
            await page.locator('#location').selectOption(String(meta.pharmacy));
            await checkWidth();
        });
        await record('No selection: SweetAlert warning prevents empty selected-report POST', async () => {
            const before = dailyPosts;
            await page.getByRole('button', { name: 'Buka / Mulai Pemeriksaan', exact: true }).click();
            await page.locator('.swal2-popup').waitFor();
            assert((await page.locator('.swal2-popup').innerText()).includes('Pilih obat terlebih dahulu'));
            assert.equal(dailyPosts, before);
            await page.getByRole('button', { name: 'Pilih obat', exact: true }).click();
        });
        await record('Selection survives search, no-results query, and scope toggle', async () => {
            const search = page.locator('#nightly-medicine-search');
            await search.fill('FINAL-001');
            assert.equal(await page.locator('[data-nightly-medicine-option]:visible').count(), 1);
            await page.locator(`[name="medicine_ids[]"][value="${meta.medicine}"]`).check();
            await search.fill('FINAL-002');
            assert.equal(await page.locator('[data-nightly-medicine-option]:visible').count(), 1);
            assert((await page.locator('[data-nightly-selected-count]').innerText()).includes('1 obat dipilih'));
            await search.fill('not-a-medicine');
            assert(await page.locator('[data-nightly-no-matches]').isVisible());
            await page.locator('[name="scope"][value="complete"]').check();
            assert(await page.locator(`[name="medicine_ids[]"][value="${meta.medicine}"]`).isDisabled());
            await page.locator('[name="scope"][value="selected"]').check();
            assert(await page.locator(`[name="medicine_ids[]"][value="${meta.medicine}"]`).isChecked());
            await search.fill('');
            await capture('Pemeriksaan_Pilih_Obat_Desktop.png');
        });
        await record('Selected report creates one medicine row; blank draft can save but cannot submit', async () => {
            await page.getByRole('button', { name: 'Buka / Mulai Pemeriksaan', exact: true }).click();
            await page.waitForURL(/\/pengecekan-malam\/\d+$/);
            await ready();
            reportUrl = new URL(page.url()).pathname;
            assert.equal(await page.locator('[data-nightly-row]').count(), 1);
            assert.equal(await page.locator('[data-nightly-unit]').inputValue(), 'Tablet');
            assert.deepEqual(await page.locator('[data-nightly-unit] option').allTextContents(), ['Tablet']);
            assert((await page.locator('body').innerText()).includes('Obat tertentu'));
            await page.getByRole('button', { name: 'Simpan Draf', exact: true }).click();
            await ready();
            assert((await page.locator('[role="status"]').innerText()).includes('Draf tersimpan'));
            assert.equal(await page.locator('[data-nightly-unit]').inputValue(), 'Tablet');
            await page.getByRole('button', { name: 'Kirim ke Kepala Apoteker', exact: true }).click();
            await page.locator('.swal2-popup').waitFor();
            assert((await page.locator('.swal2-popup').innerText()).includes('Belum ada obat yang diperiksa'));
            await page.getByRole('button', { name: 'Perbaiki isian', exact: true }).click();
        });
        await record('Print warns about unsaved draft input instead of silently printing older counts', async () => {
            await page.locator('input[name$="[note]"]').fill('Catatan cetak belum disimpan');
            const before = page.context().pages().length;
            await page.locator('[data-nightly-print]').click();
            await page.locator('.swal2-popup').waitFor();
            assert((await page.locator('.swal2-popup').innerText()).includes('Simpan isian sebelum mencetak'));
            assert.equal(page.context().pages().length, before);
            assert.equal(await page.locator('input[name$="[note]"]').inputValue(), 'Catatan cetak belum disimpan');
            await page.getByRole('button', { name: 'Kembali ke isian', exact: true }).click();
            await page.locator('input[name$="[note]"]').fill('');
        });
        await record('Add picker excludes existing medicines; close and Escape preserve unsaved desktop/mobile counts', async () => {
            await page.locator('[data-nightly-batch-details]').evaluate((element) => { element.open = true; });
            await page.locator('[data-nightly-batch-count]').fill('20');
            await page.locator('input[name$="[requested_quantity]"]').fill('30');
            await page.locator('input[name$="[note]"]').fill('Catatan sebelum tambah obat');
            await page.locator('input[name$="[is_low]"]').check();
            const before = reportPosts;
            await page.getByRole('button', { name: 'Tambah Obat', exact: true }).click();
            const dialog = page.locator('[data-nightly-add-dialog]');
            assert.equal(await dialog.locator('[data-nightly-add-option]').count(), 1);
            assert(!(await dialog.innerText()).includes('FINAL-001'));
            await dialog.locator('input[type="search"]').fill('not-found');
            assert(await dialog.locator('[data-nightly-add-empty]').isVisible());
            await dialog.locator('input[type="search"]').fill('FINAL-002');
            await dialog.locator('input[type="checkbox"]').check();
            await dialog.locator('input[type="search"]').press('Enter');
            assert.equal(reportPosts, before);
            assert(await dialog.isVisible());
            await capture('Tambah_Obat_Draf_Desktop.png');
            await page.getByRole('button', { name: 'Tutup pilihan obat', exact: true }).click();
            assert(await dialog.isHidden());
            assert.equal(await page.locator('[data-nightly-batch-count]').inputValue(), '20');
            assert.equal(await page.locator('input[name$="[note]"]').inputValue(), 'Catatan sebelum tambah obat');
            await page.setViewportSize({ width: 390, height: 844 });
            await page.getByRole('button', { name: 'Tambah Obat', exact: true }).click();
            assert.equal(await dialog.locator('input[type="checkbox"]:checked').count(), 0);
            await dialog.locator('input[type="checkbox"]').check();
            const bounds = await dialog.boundingBox();
            assert(bounds.x >= 0 && bounds.x + bounds.width <= 391 && bounds.y >= 0 && bounds.y + bounds.height <= 845, JSON.stringify(bounds));
            await checkWidth();
            await capture('Tambah_Obat_Draf_Mobile.png');
            await page.keyboard.press('Escape');
            assert(await dialog.isHidden());
            assert.equal(await page.locator('input[name$="[quantity]"]').inputValue(), '20');
            await page.setViewportSize({ width: 1440, height: 900 });
        });
        await record('Add without selection uses visible SweetAlert and restores picker without losing counts', async () => {
            await page.getByRole('button', { name: 'Tambah Obat', exact: true }).click();
            const before = reportPosts;
            await page.getByRole('button', { name: 'Tambahkan ke Pemeriksaan', exact: true }).click();
            await page.locator('.swal2-popup').waitFor();
            assert((await page.locator('.swal2-popup').innerText()).includes('Pilih obat tambahan'));
            assert.equal(reportPosts, before);
            await page.getByRole('button', { name: 'Pilih obat', exact: true }).click();
            assert(await page.locator('[data-nightly-add-dialog]').isVisible());
            await page.getByRole('button', { name: 'Batal', exact: true }).click();
        });
        await record('Real add saves unsaved quantity, batch, unit, flag, requested quantity and note; new row stays blank', async () => {
            const originalInput = await page.locator('input[name$="[quantity]"]').getAttribute('name');
            await page.getByRole('button', { name: 'Tambah Obat', exact: true }).click();
            await page.locator('[data-nightly-add-dialog] input[type="checkbox"]').check();
            await page.getByRole('button', { name: 'Tambahkan ke Pemeriksaan', exact: true }).click();
            await ready();
            assert((await page.locator('[role="status"]').innerText()).includes('1 obat ditambahkan'));
            assert.equal(await page.locator('[data-nightly-row]').count(), 2);
            const first = page.locator('[data-nightly-row]').filter({ hasText: 'FINAL-001' });
            const other = page.locator('[data-nightly-row]').filter({ hasText: 'FINAL-002' });
            assert.equal(await first.locator('input[name$="[quantity]"]').getAttribute('name'), originalInput);
            assert.equal(await first.locator('input[name$="[quantity]"]').inputValue(), '20');
            assert.equal(await first.locator('[data-nightly-batch-count]').inputValue(), '20');
            assert.equal(await first.locator('[data-nightly-unit]').inputValue(), 'Tablet');
            assert.equal(await first.locator('input[name$="[requested_quantity]"]').inputValue(), '30');
            assert.equal(await first.locator('input[name$="[note]"]').inputValue(), 'Catatan sebelum tambah obat');
            assert(await first.locator('input[name$="[is_low]"]').isChecked());
            assert.equal(await other.locator('input[name$="[quantity]"]').inputValue(), '');
            assert.equal(await other.locator('[data-nightly-unit]').inputValue(), 'Tablet');
            assert(await page.getByRole('button', { name: 'Tambah Obat', exact: true }).isDisabled());
            await capture('Tambah_Obat_Isian_Tersimpan.png');
        });
        await record('Newly added unchecked medicine requires partial confirmation; cancel preserves the draft', async () => {
            const before = reportPosts;
            await page.getByRole('button', { name: 'Kirim ke Kepala Apoteker', exact: true }).click();
            await page.locator('.swal2-popup').waitFor();
            assert.equal(reportPosts, before);
            assert((await page.locator('.swal2-popup').innerText()).includes('1 obat belum diperiksa'));
            await page.getByRole('button', { name: 'Kembali', exact: true }).click();
            await page.locator('[data-nightly-row]').filter({ hasText: 'FINAL-002' }).locator('input[name$="[quantity]"]').fill('0');
        });
        await record('Explicit zero and requested quantity submit with per-batch zero counts', async () => {
            await page.locator('[data-nightly-batch-details]').first().evaluate((element) => { element.open = true; });
            await page.locator('[data-nightly-batch-count]').fill('0');
            assert.equal(await page.locator('[data-nightly-row]').filter({ hasText: 'FINAL-001' }).locator('input[name$="[quantity]"]').inputValue(), '0');
            await page.getByRole('button', { name: 'Kirim ke Kepala Apoteker', exact: true }).click();
            await ready();
            assert.equal(await page.locator('[data-nightly-count-form]').count(), 0);
            assert.equal(await page.locator('[data-nightly-add-open]').count(), 0);
            assert((await page.locator('[role="status"]').innerText()).includes('Laporan dikirim'));
            assert((await page.locator('#nightly-summary').inputValue()).includes('permintaan 30 Tablet'));
            await capture('Pemeriksaan_Tertentu_Terkirim.png');
        });
        await record('Pharmacist approves selected report using stock-review confirmation', async () => {
            await login('apoteker_simulasi');
            await goto(reportUrl);
            assert((await page.locator('body').innerText()).includes('hanya untuk 2 obat'));
            await page.getByRole('button', { name: 'Setujui & Sesuaikan Stok', exact: true }).click();
            await page.getByRole('button', { name: 'Ya, setujui dan sesuaikan', exact: true }).click();
            await ready();
            assert((await page.locator('[role="status"]').innerText()).includes('Disetujui'));
            await goto('/kekurangan-permintaan');
            assert((await page.locator('body').innerText()).includes('Obat Simulasi Final'));
            assert((await page.locator('body').innerText()).includes('masing-masing obat'));
        });
        await record('Saved approved report opens an A4 portrait clinic report with logo, recorded batches and print-only layout', async () => {
            await goto(reportUrl);
            const opened = page.context().waitForEvent('page');
            await page.locator('[data-nightly-print]').click();
            const report = await opened;
            await report.waitForLoadState('networkidle');
            assert(report.url().endsWith(reportUrl + '/cetak'));
            assert.equal(await report.locator('body[data-nightly-report]').count(), 1);
            assert.equal(await report.locator('input, select, textarea, aside').count(), 0);
            assert.equal(await report.locator('img').count(), 1);
            assert(await report.locator('img').evaluate((image) => image.complete && image.naturalWidth > 0));
            assert((await report.locator('body').innerText()).includes('DISETUJUI'));
            assert((await report.locator('body').innerText()).includes('SIM-BATCH-SAMA'));
            assert.equal(await report.getByRole('table', { name: 'Hasil pemeriksaan stok', exact: true }).locator('tbody tr').count(), 2);
            const paperSize = await report.evaluate(() => Array.from(document.styleSheets)
                .flatMap((sheet) => Array.from(sheet.cssRules))
                .find((rule) => rule.type === CSSRule.PAGE_RULE).style.getPropertyValue('size'));
            // Chrome may omit the default portrait keyword when serializing CSS.
            assert.match(paperSize.toLowerCase(), /^a4(?: portrait)?$/);
            await report.emulateMedia({ media: 'print' });
            assert(await report.locator('.toolbar').isHidden());
            await report.pdf({ path: path.join(output, 'Laporan_Pemeriksaan.pdf'), preferCSSPageSize: true, printBackground: true, displayHeaderFooter: false });
            await report.close();
        });
        await record('Long draft print retains all 70 saved rows, inactive masters and draft warnings across PDF pages', async () => {
            const report = page;
            const response = await report.goto(meta.base_url + `/pengecekan-malam/${meta.long_check}/cetak`);
            assert.equal(response.status(), 200);
            await report.waitForLoadState('networkidle');
            assert.equal(await report.getByRole('table', { name: 'Hasil pemeriksaan stok', exact: true }).locator('tbody tr').count(), 70);
            assert((await report.locator('body').innerText()).includes('SIM-PRINT-070'));
            assert((await report.locator('body').innerText()).includes('DRAF - Belum disetujui'));
            await report.pdf({ path: path.join(output, 'Laporan_Pemeriksaan_Panjang.pdf'), preferCSSPageSize: true, printBackground: true, displayHeaderFooter: false });
            await goto(reportUrl);
        });
        await record('Mobile: medicine selection and report stay within 390px viewport', async () => {
            await login('petugas_simulasi', { width: 390, height: 844 });
            await page.locator('[name="scope"][value="selected"]').check();
            await page.locator('#nightly-medicine-search').fill('FINAL-002');
            await page.locator(`[name="medicine_ids[]"][value="${meta.other_medicine}"]`).check();
            await checkWidth();
            await capture('Pemeriksaan_Pilih_Obat_Mobile.png');
            await goto(reportUrl);
            await checkWidth();
        });
        await record('Guest: read-only report without creation form; selected-report POST denied', async () => {
            await login('guest_simulasi');
            assert.equal(await page.locator('[data-nightly-start-form]').count(), 0);
            await goto(reportUrl);
            assert.equal(await page.locator('[data-nightly-count-form]').count(), 0);
            assert.equal(await page.locator('[data-nightly-add-open]').count(), 0);
            const token = await page.locator('form[action$="/logout"] input[name="_token"]').inputValue();
            const response = await page.request.post(meta.base_url + '/pengecekan-malam', {
                headers: { Accept: 'application/json', 'X-CSRF-TOKEN': token },
                data: { scope: 'selected', medicine_ids: [meta.other_medicine], location_id: meta.pharmacy, date: '2026-01-01' },
            });
            assert.equal(response.status(), 403);
        });
        console.log(`${rows.length}/${rows.length} selected-daily browser checks passed.`);
    } catch (error) {
        const message = error.message.replaceAll(password, '[hidden]');
        if (rows.length) rows.at(-1).error = message;
        console.error(message);
        if (page) await capture('Kendala_Pemeriksaan.png').catch(() => {});
        process.exitCode = 1;
    } finally {
        fs.writeFileSync(path.join(output, 'results.json'), JSON.stringify({ tested_at: new Date().toISOString(),
            commit: meta.commit, working_tree: true, simulation: true, rows }, null, 2));
        await browser?.close();
        if (server.exitCode === null) {
            const stopped = once(server, 'exit');
            server.kill();
            await stopped;
        }
    }
})().catch(() => { console.error('Selected-daily browser runner failed.'); process.exitCode = 1; });
