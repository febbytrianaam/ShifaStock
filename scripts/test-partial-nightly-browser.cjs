const { chromium } = require('playwright');
const { spawn } = require('node:child_process');
const { once } = require('node:events');
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');

(async () => {
    const meta = JSON.parse(fs.readFileSync(process.env.FINAL_META, 'utf8'));
    const password = process.env.FINAL_PASSWORD;
    const output = process.env.FINAL_OUTPUT;
    assert(meta.phase === 'partial-nightly' && meta.simulation && password && new URL(meta.base_url).hostname === '127.0.0.1');
    const root = path.resolve(__dirname, '..');
    const server = spawn(process.env.FINAL_PHP, ['-S', `127.0.0.1:${meta.port}`, '-t', path.join(root, 'public'),
        path.join(root, 'vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php')],
    { cwd: path.join(root, 'public'), env: process.env, windowsHide: true, stdio: 'ignore' });
    let browser, page, reportUrl;
    let posts = 0;
    const results = [], errors = [];
    const ready = async () => { await page.waitForLoadState('networkidle'); await page.evaluate(() => document.fonts.ready); };
    const goto = async (url) => { const response = await page.goto(meta.base_url + url); assert.equal(response.status(), 200); await ready(); };
    const record = async (name, run) => {
        results.push({ name, status: 'Gagal' });
        await run();
        assert.deepEqual(errors, []);
        results.at(-1).status = 'Lulus';
        console.log('PASS ' + name);
    };
    const capture = async (file) => {
        assert(!(await page.locator('body').innerText()).includes(password));
        const popup = page.locator('.swal2-popup');
        if (await popup.isVisible()) await popup.evaluate(async (node) => {
            await Promise.all(node.getAnimations({ subtree: true }).map((animation) => animation.finished.catch(() => {})));
        });
        await page.screenshot({ path: path.join(output, file) });
    };
    const login = async (username) => {
        if (page) await page.close();
        page = await browser.newPage({ viewport: { width: 1440, height: 900 }, locale: 'id-ID', timezoneId: 'Asia/Jakarta', reducedMotion: 'reduce' });
        page.on('pageerror', (error) => errors.push(error.message));
        page.on('request', (r) => { if (r.method() === 'POST' && /\/pengecekan-malam\/\d+$/.test(r.url())) posts += 1; });
        await goto('/login');
        await page.getByLabel('Username', { exact: true }).fill(username);
        await page.getByLabel('Password', { exact: true }).fill(password);
        await page.getByRole('button', { name: 'Masuk', exact: true }).click();
        await page.waitForURL('**/dashboard');
        await ready();
    };
    const send = async () => { await page.getByRole('button', { name: 'Kirim ke Kepala Apoteker', exact: true }).click(); await page.locator('.swal2-popup').waitFor(); };
    try {
        for (let attempt = 0; ; attempt++) {
            try { if ((await fetch(meta.base_url + '/login')).ok) break; } catch {}
            if (attempt > 100) throw new Error('Server parsial belum siap');
            await new Promise((resolve) => setTimeout(resolve, 150));
        }
        browser = await chromium.launch({ headless: true, executablePath: 'C:/Program Files/Google/Chrome/Application/chrome.exe' });
        await record('Daftar semua obat tetap dapat disimpan sebagai draf sebagian', async () => {
            await login('petugas_simulasi');
            await goto('/pengecekan-malam');
            await page.locator('[name="location_id"]').selectOption(String(meta.pharmacy));
            await page.getByRole('button', { name: 'Buka / Mulai Pemeriksaan', exact: true }).click();
            await ready();
            reportUrl = new URL(page.url()).pathname;
            assert.equal(await page.locator('[data-nightly-row]').count(), 2);
            await page.locator('[data-nightly-batch-details]').first().evaluate((details) => { details.open = true; });
            await page.locator('[data-nightly-batch-count]').fill('18');
            await page.getByRole('button', { name: 'Simpan Draf', exact: true }).click();
            await ready();
            assert((await page.locator('[role="status"]').innerText()).includes('1 obat belum diperiksa'));
            assert.equal(await page.locator('[data-nightly-row]').filter({ hasText: 'FINAL-002' }).locator('input[name$="[quantity]"]').inputValue(), '');
        });
        await record('Konfirmasi sebagian dapat dibatalkan tanpa mengirim atau kehilangan isian', async () => {
            const before = posts;
            await send();
            const popup = page.locator('.swal2-popup');
            assert((await popup.innerText()).includes('1 obat belum diperiksa'));
            assert((await popup.innerText()).includes('tidak dianggap kosong'));
            await page.getByRole('button', { name: 'Kembali', exact: true }).click();
            assert.equal(posts, before);
            assert.equal(await page.locator('[data-nightly-batch-count]').inputValue(), '18');
        });
        await record('Simpan Draf tersedia langsung dari konfirmasi sebagian', async () => {
            await send();
            await page.locator('.swal2-deny').click();
            await ready();
            assert((await page.locator('[role="status"]').innerText()).includes('Draf tersimpan'));
        });
        await record('Mobile: konfirmasi ringkas terlihat penuh dan hanya hasil terisi yang dikirim', async () => {
            await page.setViewportSize({ width: 390, height: 844 });
            await page.waitForFunction(() => document.getElementById('sidebar').getBoundingClientRect().right <= 1);
            await send();
            const bounds = await page.locator('.swal2-popup').boundingBox();
            assert(bounds.x >= 0 && bounds.x + bounds.width <= 391 && bounds.y >= 0 && bounds.y + bounds.height <= 845);
            await capture('Konfirmasi_Pemeriksaan_Sebagian_Mobile.png');
            await page.getByRole('button', { name: 'Kirim 1 obat', exact: true }).click();
            await ready();
            assert.equal(await page.locator('[data-nightly-count-form]').count(), 0);
            assert((await page.locator('[data-nightly-incomplete]').innerText()).includes('1 obat belum diperiksa'));
        });
        await record('Apoteker menyetujui hanya obat terhitung dan melihat peringatan obat belum diperiksa', async () => {
            await login('apoteker_simulasi');
            await goto(reportUrl);
            await page.getByRole('button', { name: 'Setujui & Sesuaikan Stok', exact: true }).click();
            assert((await page.locator('.swal2-popup').innerText()).includes('1 obat belum diperiksa dan tidak akan diubah stoknya'));
            await capture('Review_Pemeriksaan_Sebagian.png');
            await page.getByRole('button', { name: 'Ya, setujui dan sesuaikan', exact: true }).click();
            await ready();
            assert((await page.locator('[role="status"]').innerText()).includes('Disetujui'));
            assert((await page.locator('#nightly-summary').inputValue()).includes('belum diperiksa: 1 obat'));
        });
        await record('Laporan tanpa satu pun hasil hitung tidak dapat dikirim', async () => {
            await login('petugas_simulasi');
            await goto('/pengecekan-malam');
            await page.locator('[name="location_id"]').selectOption(String(meta.warehouse));
            await page.getByRole('button', { name: 'Buka / Mulai Pemeriksaan', exact: true }).click();
            await ready();
            const before = posts;
            await send();
            assert((await page.locator('.swal2-popup').innerText()).includes('Belum ada obat yang diperiksa'));
            assert.equal(posts, before);
            await page.getByRole('button', { name: 'Perbaiki isian', exact: true }).click();
        });
        await record('Warning 30 baris dikelompokkan, dibatasi contoh, aman dari HTML, dan membuka halaman baris bermasalah', async () => {
            await goto(`/pengecekan-malam/${meta.stress_check}`);
            await page.locator('#nightly-page-size').selectOption('50');
            const rows = page.locator('[data-nightly-row]');
            for (let index = 0; index < 30; index++) await rows.nth(index).locator('input[name$="[quantity]"]').fill(index < 25 ? '0' : '1');
            await page.locator('#nightly-page-size').selectOption('25');
            await send();
            const popup = page.locator('.swal2-popup');
            assert((await popup.innerText()).includes('5 obat perlu diperbaiki'));
            assert((await popup.innerText()).includes('dan 2 obat lainnya'));
            assert.equal(await popup.locator('.swal2-html-container img').count(), 0);
            assert.equal(await page.evaluate(() => window.unsafeWarning), undefined);
            assert((await popup.innerText()).length < 1000);
            await capture('Warning_Hitungan_Terkelompok.png');
            await page.getByRole('button', { name: 'Perbaiki isian', exact: true }).click();
            assert((await page.locator('#nightly-page-info').innerText()).includes('Halaman 2'));
            assert(await rows.nth(25).isVisible());
        });
        console.log(`${results.length}/${results.length} partial daily checks passed.`);
    } finally {
        if (browser) await browser.close();
        if (server.exitCode === null) { const stopped = once(server, 'exit'); server.kill(); await stopped; }
        fs.writeFileSync(path.join(output, 'results.json'), JSON.stringify({ tested_at: new Date().toISOString(), commit: meta.commit,
            working_tree: true, simulation: true, results, errors }, null, 2));
    }
})().catch((error) => { console.error(error.message.replaceAll(process.env.FINAL_PASSWORD || 'NO_SECRET', '[disembunyikan]')); process.exitCode = 1; });
