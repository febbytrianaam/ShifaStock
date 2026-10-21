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
            assert((await page.locator('.swal2-popup').innerText()).includes('Periksa hitungan stok'));
            await page.getByRole('button', { name: 'Perbaiki isian', exact: true }).click();
        });
        await record('Explicit zero and requested quantity submit with per-batch zero counts', async () => {
            await page.locator('[data-nightly-batch-details]').evaluate((element) => { element.open = true; });
            await page.locator('[data-nightly-batch-count]').fill('0');
            await page.locator('input[name$="[requested_quantity]"]').fill('30');
            assert.equal(await page.locator('input[name$="[quantity]"]').inputValue(), '0');
            await page.getByRole('button', { name: 'Kirim ke Kepala Apoteker', exact: true }).click();
            await ready();
            assert.equal(await page.locator('[data-nightly-count-form]').count(), 0);
            assert((await page.locator('[role="status"]').innerText()).includes('Laporan dikirim'));
            assert((await page.locator('#nightly-summary').inputValue()).includes('permintaan 30 Tablet'));
            await capture('Pemeriksaan_Tertentu_Terkirim.png');
        });
        await record('Pharmacist approves selected report using stock-review confirmation', async () => {
            await login('apoteker_simulasi');
            await goto(reportUrl);
            assert((await page.locator('body').innerText()).includes('hanya untuk 1 obat'));
            await page.getByRole('button', { name: 'Setujui & Sesuaikan Stok', exact: true }).click();
            await page.getByRole('button', { name: 'Ya, setujui dan sesuaikan', exact: true }).click();
            await ready();
            assert((await page.locator('[role="status"]').innerText()).includes('Disetujui'));
            await goto('/kekurangan-permintaan');
            assert((await page.locator('body').innerText()).includes('Obat Simulasi Final'));
            assert((await page.locator('body').innerText()).includes('masing-masing obat'));
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
