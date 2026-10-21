const { chromium } = require('playwright');
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');

(async () => {
    const accounts = JSON.parse(process.env.UAT_BROWSER_ACCOUNTS);
    const meta = JSON.parse(process.env.UAT_BROWSER_META);
    assert(process.env.APP_ENV === 'uat' && process.env.DB_DATABASE === 'shifastock_uat');
    assert.equal(meta.url, 'http://127.0.0.1:8010');
    const output = path.resolve(__dirname, '../storage/app/qa/uat-browser');
    fs.mkdirSync(output, { recursive: true });
    const secrets = [...Object.values(accounts).map((account) => account.password), process.env.DB_PASSWORD, process.env.APP_KEY].filter(Boolean);
    const clean = (message) => secrets.reduce((text, secret) => text.replaceAll(secret, '[disembunyikan]'), message);
    const rows = [];
    const today = new Intl.DateTimeFormat('en-CA', { timeZone: 'Asia/Jakarta' }).format(new Date());
    const errors = [];
    let browser;
    let page;
    const ready = async () => { await page.waitForLoadState('networkidle'); await page.evaluate(() => document.fonts.ready); };
    const goto = async (url) => {
        const response = await page.goto(meta.url+url);
        assert.equal(response.status(), 200, url);
        await ready();
        await page.locator('[data-uat-notice]').waitFor();
        assert.deepEqual(errors, []);
    };
    const record = async (name, callback) => {
        rows.push({ name, status: 'Gagal' });
        await callback();
        rows.at(-1).status = 'Lulus';
        console.log('PASS '+name);
    };
    const capture = async (name) => {
        const text = await page.locator('body').innerText();
        secrets.forEach((secret) => assert(!text.includes(secret)));
        await page.screenshot({ path: path.join(output, name) });
    };
    const login = async (role, viewport = { width: 1440, height: 900 }) => {
        const context = await browser.newContext({ viewport, locale: 'id-ID', timezoneId: 'Asia/Jakarta', reducedMotion: 'reduce' });
        page = await context.newPage();
        page.on('pageerror', (error) => errors.push(error.message));
        await goto('/login');
        await page.getByLabel('Username', { exact: true }).fill(accounts[role].username);
        await page.getByLabel('Password', { exact: true }).fill(accounts[role].password);
        await page.getByRole('button', { name: 'Masuk', exact: true }).click();
        await page.waitForURL('**/dashboard');
        await ready();
        if (await page.locator('.shifa-tour').count()) {
            await page.locator('.shifa-tour').getByRole('button', { name: 'Lewati', exact: true }).click();
            await page.locator('.shifa-tour').waitFor({ state: 'detached' });
        }
    };
    try {
        browser = await chromium.launch({ headless: true, executablePath: 'C:/Program Files/Google/Chrome/Application/chrome.exe' });
        await record('Login Guest di lingkungan UAT dan sidebar tanpa administrasi', async () => {
            await login('guest');
            await page.locator('[data-uat-notice]').waitFor();
            const links = await page.locator('#sidebar a').allTextContents();
            assert(links.some((label) => label.includes('Stok Awal')));
            assert(links.some((label) => label.includes('Parameter Monitoring')));
            assert(!links.some((label) => /Pengguna|Audit Log|Referensi Sistem|Pengaturan Sistem/.test(label)));
            await capture('Guest_Dashboard_UAT.png');
        });
        const pages = ['/obat', '/stok-awal', '/obat-masuk', '/transfer', '/obat-keluar', '/retur-pbf', '/pengecekan-malam',
            '/stock-opname', '/riwayat-stok', '/kekurangan-permintaan', '/kedaluwarsa', '/kondisi-khusus', '/laporan',
            '/kartu-stok', '/parameter-monitoring', `/pengecekan-malam/${meta.check}`,
            `/stock-opname?session=${meta.session}`, `/kartu-stok?medicine_id=${meta.medicine}&location_id=${meta.location}&start=${today}&end=${today}`];
        for (const url of pages) await record('Guest lihat '+url, async () => {
            await goto(url);
            const forms = await page.locator('form[method=POST], form[method=post]').evaluateAll((nodes) => nodes.map((node) => new URL(node.action).pathname));
            assert(forms.every((action) => action === '/logout'), 'Guest memiliki form tulis: '+forms);
            assert.equal(await page.locator('input[name*="[physical_quantity]"], input[name*="minimums["]').count(), 0);
            if (url === '/parameter-monitoring') await capture('Guest_Parameter_Readonly.png');
            if (url.startsWith('/kartu-stok?')) assert((await page.locator('body').innerText()).includes('UAT-STOK-1'));
        });
        await record('Cetak kartu stok dan unduhan CSV memiliki penanda UAT', async () => {
            await goto(`/kartu-stok/cetak?medicine_id=${meta.medicine}&location_id=${meta.location}&start=${today}&end=${today}`);
            assert((await page.locator('body').innerText()).includes('UAT-STOK-1'));
            await capture('Kartu_Stok_UAT.png');
            const response = await page.request.get(meta.url+`/laporan?export=csv&start=${today}&end=${today}`);
            assert.equal(response.status(), 200);
            assert(response.headers()['content-disposition'].includes('uat-laporan-persediaan'));
        });
        await record('Aksi tulis Guest ditolak backend dengan token CSRF valid', async () => {
            await goto('/dashboard');
            const token = await page.locator('input[name=_token]').first().inputValue();
            for (const url of ['/stok-awal', '/obat-masuk', '/transfer', '/obat-keluar', '/retur-pbf', '/kondisi-khusus',
                '/parameter-monitoring', '/stock-opname', `/stock-opname/${meta.session}/save`, `/stock-opname/${meta.session}/finalize`,
                `/stock-opname/${meta.session}/recount`, '/pengecekan-malam', `/pengecekan-malam/${meta.check}`, `/pengecekan-malam/${meta.check}/review`,
                '/admin/pengguna', '/admin/pengguna/hak-akses', '/admin/referensi-sistem/lokasi', '/admin/pengaturan-sistem']) {
                const response = await page.request.post(meta.url+url, { headers: { 'X-CSRF-TOKEN': token, Accept: 'application/json' }, form: {} });
                assert.equal(response.status(), 403, url);
            }
            for (const [method, url] of [['put', `/obat-masuk/${meta.receipt}`], ['delete', `/obat-masuk/${meta.receipt}`], ['delete', `/pengecekan-malam/${meta.check}`]]) {
                const response = await page.request[method](meta.url+url, { headers: { 'X-CSRF-TOKEN': token, Accept: 'application/json' }, form: {} });
                assert.equal(response.status(), 403, url);
            }
        });
        await record('Seluruh menu superadmin ditolak untuk Guest', async () => {
            for (const url of ['/admin/pengguna', '/admin/audit-log', '/admin/referensi-sistem', '/admin/pengaturan-sistem']) {
                assert.equal((await page.request.get(meta.url+url)).status(), 403, url);
            }
        });
        await record('Akun UAT petugas memiliki form operasional sesuai role', async () => {
            await login('petugas_farmasi');
            await goto('/obat-masuk');
            assert(await page.locator('.receipt-entry').count());
            assert.equal((await page.request.get(meta.url+'/admin/pengguna')).status(), 403);
        });
        await record('Akun UAT apoteker dapat membuka master, stok awal dan parameter', async () => {
            await login('kepala_apoteker');
            await goto('/obat');
            await page.getByRole('button', { name: 'Tambah Obat', exact: true }).waitFor();
            await goto('/stok-awal');
            await page.locator('#opening-quantity').waitFor();
            await goto('/parameter-monitoring');
            await page.getByRole('button', { name: 'Simpan Parameter', exact: true }).waitFor();
            assert.equal((await page.request.get(meta.url+'/admin/pengguna')).status(), 403);
        });
        await record('Guest mobile tetap menampilkan menu gabungan dengan penanda UAT', async () => {
            await login('guest', { width: 390, height: 844 });
            await page.getByRole('button', { name: 'Buka menu', exact: true }).click();
            await page.locator('[data-tour-menu="monitoring-parameters.index"]').scrollIntoViewIfNeeded();
            await capture('Guest_Mobile_UAT.png');
        });
        console.log(`${rows.length}/${rows.length} pemeriksaan browser UAT lulus.`);
    } catch (error) {
        rows.at(-1) && (rows.at(-1).error = clean(error.message));
        console.error(clean(error.message));
        if (page) await capture('Kendala_UAT.png').catch(() => {});
        process.exitCode = 1;
    } finally {
        fs.writeFileSync(path.join(output, 'results.json'), JSON.stringify({ tested_at: new Date().toISOString(), simulation: true, results: rows }, null, 2));
        await browser?.close();
    }
})();
