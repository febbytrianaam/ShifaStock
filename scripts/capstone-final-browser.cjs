const { chromium } = require('playwright');
const { spawn } = require('node:child_process');
const { once } = require('node:events');
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
const crypto = require('node:crypto');

(async () => {
    const meta = JSON.parse(fs.readFileSync(process.env.FINAL_META, 'utf8'));
    const password = process.env.FINAL_PASSWORD;
    const output = process.env.FINAL_OUTPUT;
    assert(meta.simulation && password && new URL(meta.base_url).hostname === '127.0.0.1');
    const root = path.resolve(__dirname, '..');
    const server = spawn(process.env.FINAL_PHP, ['-S', `127.0.0.1:${meta.port}`, '-t', path.join(root, 'public'),
        path.join(root, 'vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php')],
    { cwd: path.join(root, 'public'), env: process.env, windowsHide: true, stdio: 'ignore' });
    let browser;
    const rows = [];
    let page;
    const errors = [];
    const record = async (name, action, file) => {
        const row = { name, method: 'Browser otomatis (Playwright)', status: 'Gagal', captured_at: new Date().toISOString() };
        rows.push(row);
        try {
            await action();
            assert.deepEqual(errors, [], 'Ada JavaScript error');
            assert(!(await page.locator('body').innerText()).includes(password));
            if (file) {
                await page.screenshot({ path: path.join(output, file) });
                row.evidence = file;
                row.sha256 = crypto.createHash('sha256').update(fs.readFileSync(path.join(output, file))).digest('hex');
            }
            row.status = 'Lulus';
            console.log('PASS '+name);
        } catch (error) { row.error = error.message.replaceAll(password, '[disembunyikan]'); throw error; }
    };
    const ready = async () => {
        await page.waitForLoadState('networkidle');
        await page.evaluate(() => document.fonts.ready);
    };
    const goto = async (url) => { const r = await page.goto(meta.base_url+url); assert(r.ok(), 'HTTP '+r.status()); await ready(); };
    const wrapper = (id) => page.locator(id).locator('..');
    const pick = async (id, query) => {
        await wrapper(id).locator('input[role=combobox]').fill(query);
        await wrapper(id).locator('.searchable-select-menu [role=option]').first().click();
    };
    const assertLocation = async (label) => {
        const text = await wrapper('#adjust-batch').locator('input').inputValue();
        assert(text.includes('SIM-BATCH-SAMA') && text.includes(label), 'Label batch salah: '+text);
        const native = await page.locator('#adjust-batch').evaluate((s) => s.selectedOptions[0].textContent.trim());
        assert(native.includes(label), 'Native select salah: '+native);
    };
    try {
        for (let attempt = 0; ; attempt++) {
            try { if ((await fetch(meta.base_url+'/login')).ok) break; } catch {}
            if (attempt > 100) throw new Error('Server lokal tidak siap');
            await new Promise((resolve) => setTimeout(resolve, 150));
        }
        browser = await chromium.launch({ headless: true, executablePath: 'C:/Program Files/Google/Chrome/Application/chrome.exe' });
        const context = await browser.newContext({ viewport: { width: 1440, height: 900 }, deviceScaleFactor: 1, locale: 'id-ID', timezoneId: 'Asia/Jakarta', reducedMotion: 'reduce' });
        page = await context.newPage();
        page.on('dialog', async (dialog) => {
            if (dialog.type() === 'beforeunload') await dialog.accept();
            else { errors.push('Dialog browser tidak diharapkan'); await dialog.dismiss(); }
        });
        page.on('pageerror', (error) => errors.push(error.message));
        if (meta.phase === 'final') await record('Login tidak valid ditolak', async () => {
            await goto('/login');
            await page.getByLabel('Username', { exact: true }).fill('petugas_simulasi');
            await page.getByLabel('Password', { exact: true }).fill(crypto.randomBytes(24).toString('hex'));
            await page.getByRole('button', { name: 'Masuk', exact: true }).click();
            await ready();
            assert(new URL(page.url()).pathname === '/login');
            assert(await page.locator('[role=alert], .text-rose-700, .text-red-600').count() || /tidak cocok|salah|valid/i.test(await page.locator('body').innerText()));
            await page.getByLabel('Password', { exact: true }).fill('');
        }, 'Browser_01_Login_Ditolak.png');
        await record('Login valid petugas', async () => {
            await goto('/login');
            await page.getByLabel('Username', { exact: true }).fill('petugas_simulasi');
            await page.getByLabel('Password', { exact: true }).fill(password);
            await page.getByRole('button', { name: 'Masuk', exact: true }).click();
            await page.waitForURL('**/dashboard');
            await ready();
        });
        if (meta.phase !== 'baseline') {
            await record('Panduan pengeluaran menjelaskan pencatatan manual, jumlah keluar, FEFO, dan stok pada desktop/tablet/HP', async () => {
                await goto('/obat-keluar');
                const initialQuantity = await page.locator('#issue-quantity').inputValue();
                const initialReference = await page.locator('#issue-reference').inputValue();
                for (const viewport of [{ width: 1440, height: 900 }, { width: 768, height: 1024 }, { width: 390, height: 844 }]) {
                    await page.setViewportSize(viewport);
                    await page.waitForFunction(() => innerWidth >= 768 || document.getElementById('sidebar').getBoundingClientRect().right <= 1);
                    const text = await page.locator('[data-issue-guide]').innerText();
                    for (const expected of ['tidak terhubung ke Neoklinik', 'bukan sisa stok', 'FEFO', 'tidak menunggu persetujuan', 'menjadi 17 tablet', 'pencatatan ganda']) assert(text.includes(expected));
                    const layout = await page.evaluate(() => ({ width: innerWidth, page: document.documentElement.scrollWidth,
                        guideBottom: document.querySelector('[data-issue-guide]').getBoundingClientRect().bottom,
                        formTop: document.querySelector('[data-issue-form]').getBoundingClientRect().top }));
                    assert(layout.page <= layout.width + 1 && layout.formTop >= layout.guideBottom, JSON.stringify(layout));
                    assert(!(await page.locator('body').innerText()).includes(password));
                    await page.screenshot({ path: path.join(output, `Panduan_Pengeluaran_${viewport.width}.png`), fullPage: true });
                }
                assert.equal(await page.locator('#issue-quantity').inputValue(), initialQuantity);
                assert.equal(await page.locator('#issue-reference').inputValue(), initialReference);
                assert((await page.locator('#issue-quantity-help').innerText()).includes('bukan sisa stok'));
                await page.setViewportSize({ width: 1440, height: 900 });
            });
            await goto('/dashboard');
        }
        if (meta.phase !== 'baseline') {
            await record('Dashboard minimum stok memakai stok transaksi tanpa menunggu pemeriksaan harian', async () => {
                assert.equal((await page.locator('[data-stock-shortages] > p').innerText()).trim(), '2');
                assert.equal(await page.locator('[data-stock-shortages]').getByText('Tindak Lanjut →', { exact: true })
                    .evaluate((element) => getComputedStyle(element).whiteSpace), 'nowrap');
                const alerts = page.locator('[data-stock-alerts]');
                assert.equal(await alerts.locator('[data-stock-alert]').count(), 2);
                assert((await alerts.innerText()).includes('50 Tablet'));
                assert((await alerts.innerText()).includes('20 Tablet'));
                assert(!(await alerts.innerText()).includes('Belum ada hasil pemeriksaan'));
            }, 'Browser_Dashboard_Monitoring_Desktop.png');
            await record('Dashboard mobile dan tautan monitoring mempertahankan obat serta lokasi', async () => {
                await page.setViewportSize({ width: 390, height: 844 });
                await page.waitForFunction(() => document.getElementById('sidebar').getBoundingClientRect().right <= 1);
                await page.locator('[data-stock-alerts]').scrollIntoViewIfNeeded();
                assert(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth + 1));
                await page.screenshot({ path: path.join(output, 'Browser_Dashboard_Monitoring_Mobile.png') });
                await page.locator('[data-stock-alert]').filter({ hasText: 'Gudang' }).click();
                await ready();
                const url = new URL(page.url());
                assert.equal(url.searchParams.get('location_id'), String(meta.warehouse));
                assert.equal(url.searchParams.get('search'), 'FINAL-001');
                assert.equal(url.hash, '#stok-minimum');
                assert.equal(await page.locator('#stok-minimum tbody tr').count(), 1);
                assert((await page.locator('#stok-minimum').innerText()).includes('50 Tablet'));
                await page.setViewportSize({ width: 1440, height: 900 });
            });
        }
        await record(meta.phase === 'baseline' ? 'Reproduksi bug label batch Apotek sebelum perbaikan' : 'Batch Apotek dipilih dengan label dan native option sesuai lokasi', async () => {
            await goto('/kondisi-khusus');
            await pick('#adjust-medicine', 'FINAL-001');
            await page.locator('#adjust-location').selectOption(String(meta.pharmacy));
            await wrapper('#adjust-batch').locator('input').fill('SIM-BATCH-SAMA');
            const labels = await wrapper('#adjust-batch').locator('.searchable-select-menu [role=option]').allTextContents();
            assert.equal(labels.length, 1);
            assert(labels[0].includes('Apotek') && !labels[0].includes('Gudang'));
            await wrapper('#adjust-batch').locator('.searchable-select-menu [role=option]').click();
            if (meta.phase === 'baseline') {
                const actual = await wrapper('#adjust-batch').locator('input').inputValue();
                assert(actual.includes('Gudang'), 'Bug sebelumnya tidak tereproduksi');
                rows.at(-1).actual = 'Lokasi Apotek, opsi menu Apotek, tetapi label terpilih Gudang';
            } else await assertLocation('Apotek');
        }, meta.phase === 'baseline' ? 'Bug_Sebelum_Perbaikan.png' : 'Browser_02_Batch_Apotek.png');
        if (meta.phase === 'baseline') return;
        await record('Pergantian lokasi memilih opsi batch yang sesuai', async () => {
            await page.locator('#adjust-location').selectOption(String(meta.warehouse));
            await assertLocation('Gudang');
            await page.locator('#adjust-location').selectOption(String(meta.pharmacy));
            await assertLocation('Apotek');
        });
        await record('Prefill batch Gudang dan Apotek dari tautan monitoring', async () => {
            await goto(`/kondisi-khusus?medicine_id=${meta.medicine}&location_id=${meta.warehouse}&batch_id=${meta.batch}`);
            await assertLocation('Gudang');
            await goto(`/kondisi-khusus?medicine_id=${meta.medicine}&location_id=${meta.pharmacy}&batch_id=${meta.batch}`);
            await assertLocation('Apotek');
        }, 'Browser_03_Prefill_Apotek.png');
        await record('Validasi SweetAlert tidak mengembalikan label ke Gudang', async () => {
            await page.locator('[data-adjust-save]').click();
            await page.locator('.swal2-popup').waitFor({ state: 'visible' });
            await page.locator('.swal2-popup').evaluate(async (popup) => {
                await Promise.all(popup.getAnimations({ subtree: true }).map((animation) => animation.finished));
            });
            assert((await page.locator('.swal2-popup').innerText()).includes('Jumlah'));
            await assertLocation('Apotek');
        }, 'Browser_04_Validasi_Form.png');
        await page.locator('.swal2-confirm').click();
        await record('Mengubah obat membersihkan batch yang tidak cocok', async () => {
            await pick('#adjust-medicine', 'FINAL-002');
            assert.equal(await page.locator('#adjust-batch').inputValue(), '');
            await wrapper('#adjust-batch').locator('input').fill('SIM-BATCH-SAMA');
            assert.equal(await wrapper('#adjust-batch').locator('.searchable-select-menu [role=option]').count(), 0);
        });
        await record('Posting kondisi khusus mengurangi stok Apotek saja', async () => {
            await goto(`/kondisi-khusus?medicine_id=${meta.medicine}&location_id=${meta.pharmacy}&batch_id=${meta.batch}`);
            await page.locator('#adjust-quantity').fill('2');
            await page.locator('#adjust-notes').fill('SIMULASI pengujian final; bukan kejadian klinik.');
            await page.locator('[data-adjust-save]').click();
            await ready();
            assert((await page.locator('body').innerText()).includes('Kondisi khusus diposting'));
            const row = page.locator('tbody tr').filter({ hasText: 'SIMULASI pengujian final' });
            assert.equal(await row.count(), 1);
            assert((await row.innerText()).includes('Apotek'));
        }, 'Browser_05_Posting_Apotek.png');
        await record('Widget dashboard membaca ulang stok Apotek setelah posting kondisi khusus', async () => {
            await goto('/dashboard');
            assert.equal((await page.locator('[data-stock-shortages] > p').innerText()).trim(), '2');
            const alerts = page.locator('[data-stock-alerts]');
            assert((await alerts.locator('[data-stock-alert]').filter({ hasText: 'Apotek' }).innerText()).includes('18 Tablet'));
            assert((await alerts.locator('[data-stock-alert]').filter({ hasText: 'Gudang' }).innerText()).includes('50 Tablet'));
        }, 'Browser_Dashboard_Monitoring_Setelah_Posting.png');
        for (const [route, prefix, location] of [['/transfer', 'transfer', 'source'], ['/obat-keluar', 'issue', 'location'], ['/retur-pbf', 'return', 'location']]) {
            await record('Regresi dropdown lintas lokasi pada '+route, async () => {
                await goto(route);
                await pick(`#${prefix}-medicine`, 'FINAL-001');
                await page.locator(`#${prefix}-${location}`).selectOption(String(meta.pharmacy));
                await pick(`#${prefix}-batch`, 'SIM-BATCH-SAMA');
                assert((await wrapper(`#${prefix}-batch`).locator('input').inputValue()).includes('Apotek'));
                assert((await page.locator(`#${prefix}-batch`).evaluate((s) => s.selectedOptions[0].textContent)).includes('Apotek'));
                await page.locator(`#${prefix}-${location}`).selectOption(String(meta.warehouse));
                assert((await wrapper(`#${prefix}-batch`).locator('input').inputValue()).includes('Gudang'));
            });
        }
        await record('Monitoring menampilkan lokasi dan indikator minimum serta kedaluwarsa', async () => {
            await goto('/kekurangan-permintaan');
            assert((await page.locator('body').innerText()).includes('Obat Simulasi Final'));
            await goto('/kedaluwarsa');
            const row = page.locator('tbody tr').filter({ hasText: 'SIM-BATCH-SAMA' });
            assert((await row.innerText()).includes('Apotek') && (await row.innerText()).includes('Gudang'));
            assert(await page.locator('.attention-warning').count());
        }, 'Browser_06_Monitoring.png');
        await record('Guest dapat melihat tetapi tidak dapat mengirim transaksi atau membuka pengguna', async () => {
            await context.clearCookies();
            await goto('/login');
            await page.getByLabel('Username', { exact: true }).fill('guest_simulasi');
            await page.getByLabel('Password', { exact: true }).fill(password);
            await page.getByRole('button', { name: 'Masuk', exact: true }).click();
            await page.waitForURL('**/dashboard');
            await goto('/kondisi-khusus');
            assert.equal(await page.locator('#adjust-batch').count(), 0);
            const token = await page.locator('form[action$="/logout"] input[name="_token"]').inputValue();
            const response = await page.request.post(meta.base_url+'/kondisi-khusus', { form: { _token: token } });
            assert.equal(response.status(), 403);
            const forbidden = await page.goto(meta.base_url+'/admin/pengguna');
            assert.equal(forbidden.status(), 403);
        }, 'Browser_07_Akses_Ditolak.png');
    } finally {
        if (browser) await browser.close();
        if (server.exitCode === null) {
            const stopped = once(server, 'exit');
            server.kill();
            await stopped;
        }
        fs.writeFileSync(path.join(output, `Browser_${meta.phase}.json`), JSON.stringify({ ...meta, base_url: 'HTTP loopback sementara',
            finished_at: new Date().toISOString(), viewport: '1440 x 900', results: rows,
            server_stopped: server.exitCode !== null || server.signalCode !== null }, null, 2));
    }
})().catch((error) => { console.error(error.message); process.exitCode = 1; });
