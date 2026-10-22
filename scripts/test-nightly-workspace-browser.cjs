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
    assert(meta.phase === 'nightly-ui' && meta.simulation && password && new URL(meta.base_url).hostname === '127.0.0.1');
    const root = path.resolve(__dirname, '..');
    const server = spawn(process.env.FINAL_PHP, ['-S', `127.0.0.1:${meta.port}`, '-t', path.join(root, 'public'),
        path.join(root, 'vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php')],
    { cwd: path.join(root, 'public'), env: process.env, windowsHide: true, stdio: 'ignore' });
    let browser, page, posts = 0;
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
        await page.screenshot({ path: path.join(output, file) });
    };
    const login = async (username) => {
        if (page) await page.close();
        page = await browser.newPage({ viewport: { width: 1440, height: 900 }, locale: 'id-ID', timezoneId: 'Asia/Jakarta', reducedMotion: 'reduce' });
        page.on('pageerror', (error) => errors.push(error.message));
        page.on('request', (r) => { if (r.method() === 'POST' && /\/pengecekan-malam\/\d+$/.test(r.url())) posts += 1; });
        page.on('dialog', (dialog) => dialog.accept());
        await goto('/login');
        await page.getByLabel('Username', { exact: true }).fill(username);
        await page.getByLabel('Password', { exact: true }).fill(password);
        await page.getByRole('button', { name: 'Masuk', exact: true }).click();
        await page.waitForURL('**/dashboard');
        await ready();
    };
    const quantity = (index) => page.locator('[data-nightly-row]').nth(index).locator('input[name$="[quantity]"]');
    const resize = async (viewport) => {
        await page.setViewportSize(viewport);
        await page.waitForFunction(() => {
            const workspace = document.querySelector('[data-nightly-workspace]').getBoundingClientRect();
            const padding = parseFloat(getComputedStyle(document.querySelector('[data-nightly-workspace]').parentElement).paddingBottom);
            return Math.abs(workspace.bottom + padding - innerHeight) <= 1 && (innerWidth >= 768 || document.getElementById('sidebar').getBoundingClientRect().right <= 1);
        });
    };
    const checkFrame = async () => {
        const dimensions = await page.evaluate(() => {
            const list = document.getElementById('nightly-table-scroll').getBoundingClientRect();
            const footer = document.querySelector('[data-nightly-footer]').getBoundingClientRect();
            return { width: innerWidth, height: innerHeight, document: document.documentElement.scrollWidth,
                footer: { top: footer.top, bottom: footer.bottom }, list: { height: list.height, bottom: list.bottom },
                pageHeight: document.documentElement.scrollHeight, listWidth: document.getElementById('nightly-table-scroll').clientWidth,
                listScrollWidth: document.getElementById('nightly-table-scroll').scrollWidth };
        });
        assert(dimensions.document <= dimensions.width + 1, JSON.stringify(dimensions));
        assert(dimensions.footer.top > 0 && dimensions.footer.bottom <= dimensions.height + 1, JSON.stringify(dimensions));
        assert(dimensions.list.height > 80 && dimensions.list.bottom <= dimensions.footer.top + 1, JSON.stringify(dimensions));
        assert(dimensions.pageHeight <= dimensions.height + 24, JSON.stringify(dimensions));
        assert(dimensions.listScrollWidth <= dimensions.listWidth + 1, JSON.stringify(dimensions));
    };
    try {
        for (let attempt = 0; ; attempt++) {
            try { if ((await fetch(meta.base_url + '/login')).ok) break; } catch {}
            if (attempt > 100) throw new Error('Server UI belum siap');
            await new Promise((resolve) => setTimeout(resolve, 150));
        }
        browser = await chromium.launch({ headless: true, executablePath: 'C:/Program Files/Google/Chrome/Application/chrome.exe' });
        await record('167 obat: default 10 per halaman, footer terlihat, tanpa scroll horizontal desktop', async () => {
            await login('petugas_simulasi');
            await goto(`/pengecekan-malam/${meta.stress_check}`);
            assert.equal(await page.title(), 'Pemeriksaan Stok Harian · Klinik Shifa Medika');
            assert.equal(await page.locator('[data-nightly-row]').count(), 167);
            assert.equal(await page.locator('[data-nightly-row]:visible').count(), 10);
            assert((await page.locator('#nightly-page-info').innerText()).includes('1 dari 17'));
            await checkFrame();
            await capture('Pemeriksaan_Ringkas_Desktop.png');
        });
        await record('Desktop: kolom proporsional memenuhi area kerja, sejajar header, tanpa ruang kosong di kanan', async () => {
            for (const viewport of [{ width: 1440, height: 900 }, { width: 1920, height: 1080 }, { width: 2560, height: 1440 }, { width: 3840, height: 2160 }]) {
                await resize(viewport);
                await checkFrame();
                const columns = await page.locator('[data-nightly-row]').first().evaluate((row) => {
                    const medicine = row.querySelector('.nightly-medicine').getBoundingClientRect();
                    const stock = row.querySelector('.nightly-system').getBoundingClientRect();
                    const header = document.querySelector('.nightly-columns > span:nth-child(2)').getBoundingClientRect();
                    const physical = row.querySelector('.nightly-quantity').getBoundingClientRect();
                    const line = row.querySelector('.nightly-line').getBoundingClientRect();
                    const action = row.querySelector('[data-nightly-row-toggle]').getBoundingClientRect();
                    const workspace = document.querySelector('[data-nightly-workspace]').getBoundingClientRect();
                    const list = document.getElementById('nightly-table-scroll').getBoundingClientRect();
                    const content = document.querySelector('[data-tour-main]').getBoundingClientRect();
                    const contentPadding = parseFloat(getComputedStyle(document.querySelector('[data-tour-main]')).paddingLeft);
                    return { medicineWidth: medicine.width, stockOffset: stock.left - medicine.left,
                        gap: stock.left - medicine.right, aligned: Math.abs(header.left - stock.left),
                        physicalOffset: physical.left - stock.right, rightGap: line.right - action.right, listWidth: list.width,
                        unusedRight: Math.abs(workspace.right - list.right),
                        contentGap: Math.abs(workspace.width - (content.width - contentPadding * 2)),
                        listOffset: Math.abs(list.left - workspace.left) };
                });
                assert(columns.unusedRight < 1 && columns.medicineWidth < columns.listWidth * .32, JSON.stringify(columns));
                assert(columns.contentGap < 1 && columns.listOffset < 1, JSON.stringify(columns));
                assert(columns.rightGap <= 15, JSON.stringify(columns));
                assert(columns.gap <= 17 && columns.aligned < 1 && columns.physicalOffset <= 17, JSON.stringify(columns));
                await capture(`Desktop_${viewport.width}x${viewport.height}.png`);
                if (viewport.width === 2560) await capture('Pemeriksaan_Layar_Lebar.png');
            }
            await resize({ width: 1440, height: 900 });
        });
        await record('Input di filter Belum dicek tidak menghilang saat diketik; 0 dihitung sebagai diperiksa', async () => {
            await page.locator('#nightly-filter').selectOption('unchecked');
            await quantity(0).fill('0');
            assert(await quantity(0).isVisible());
            assert((await page.locator('[data-nightly-progress-label]').innerText()).includes('1 / 167'));
            assert.equal(await page.locator('[data-nightly-save-state]').innerText(), 'Belum disimpan');
            assert.equal(await page.locator('[data-nightly-row]').first().locator('[data-nightly-state]').innerText(), 'Kosong');
        });
        await record('Pagination dan pencarian tidak membuang hasil hitung, permintaan, atau catatan', async () => {
            await page.locator('#nightly-filter').selectOption('all');
            const first = page.locator('[data-nightly-row]').first();
            await first.locator('[data-nightly-row-toggle]').click();
            await first.locator('[name$="[note]"]').fill('Catatan simulasi UI');
            await first.locator('[name$="[requested_quantity]"]').fill('12');
            await first.locator('[name$="[is_low]"]').check();
            await first.locator('[data-nightly-row-toggle]').click();
            await page.locator('#nightly-next').click();
            assert((await page.locator('#nightly-page-info').innerText()).includes('2 dari 17'));
            await quantity(10).fill('0');
            await page.locator('#nightly-search').fill('WARN-167');
            assert.equal(await page.locator('[data-nightly-row]:visible').count(), 1);
            await quantity(166).fill('0');
            await page.locator('#nightly-search').fill('');
            assert.equal(await quantity(0).inputValue(), '0');
            assert.equal(await first.locator('[name$="[note]"]').inputValue(), 'Catatan simulasi UI');
            assert.equal(await first.locator('[name$="[requested_quantity]"]').inputValue(), '12');
            await capture('Pemeriksaan_Isian_Tersimpan_Di_Memori.png');
        });
        await record('Tab Ringkasan dan keyboard tidak menghapus isian; Enter tidak mengirim form', async () => {
            const before = posts;
            await page.getByRole('tab', { name: 'Ringkasan', exact: true }).click();
            assert(await page.locator('#nightly-summary').isVisible());
            await page.locator('[data-nightly-print]').click();
            assert((await page.locator('.swal2-popup').innerText()).includes('Simpan isian sebelum mencetak'));
            await page.getByRole('button', { name: 'Kembali ke isian', exact: true }).click();
            assert(await quantity(0).isVisible());
            await page.getByRole('tab', { name: 'Ringkasan', exact: true }).click();
            await page.getByRole('tab', { name: 'Ringkasan', exact: true }).press('Home');
            assert(await quantity(0).isVisible());
            await quantity(0).press('Enter');
            assert.equal(posts, before);
            assert.equal(await quantity(0).inputValue(), '0');
            await page.getByRole('button', { name: 'Ke obat berikutnya yang belum dicek', exact: true }).click();
            assert(await quantity(1).evaluate((input) => input === document.activeElement));
        });
        await record('Simpan nyata mempertahankan nilai pada halaman 1, 2, dan 17 beserta rincian', async () => {
            await page.getByRole('button', { name: 'Simpan Draf', exact: true }).click();
            await ready();
            assert((await page.locator('[role="status"]').innerText()).includes('Draf tersimpan'));
            assert((await page.locator('[data-nightly-progress-label]').innerText()).includes('3 / 167'));
            assert.equal(await quantity(10).inputValue(), '0');
            assert.equal(await quantity(166).inputValue(), '0');
            assert.equal(await page.locator('[data-nightly-save-state]').innerText(), 'Isian tersimpan');
            const first = page.locator('[data-nightly-row]').first();
            await first.locator('[data-nightly-row-toggle]').click();
            assert.equal(await first.locator('[name$="[note]"]').inputValue(), 'Catatan simulasi UI');
            assert.equal(await first.locator('[name$="[requested_quantity]"]').inputValue(), '12');
            await capture('Pemeriksaan_Rincian_Desktop.png');
            await first.locator('[data-nightly-row-toggle]').click();
        });
        await record('Responsif desktop, tablet, HP: tanpa overflow/tumpang tindih, termasuk rincian terbuka', async () => {
            for (const viewport of [
                { width: 1366, height: 768 }, { width: 1280, height: 800 }, { width: 1024, height: 768 },
                { width: 820, height: 1180 }, { width: 768, height: 1024 }, { width: 700, height: 900 },
                { width: 390, height: 844 }, { width: 360, height: 800 }, { width: 320, height: 740 },
            ]) {
                await resize(viewport);
                await checkFrame();
                const row = page.locator('[data-nightly-row]').first();
                const geometry = await row.evaluate((node) => {
                    const selectors = ['.nightly-medicine', '.nightly-system', '.nightly-quantity', '.nightly-row-actions'];
                    const rects = selectors.map((selector) => node.querySelector(selector).getBoundingClientRect());
                    const overlaps = rects.some((a, i) => rects.some((b, j) => j > i && a.left < b.right - 1 && a.right > b.left + 1 && a.top < b.bottom - 1 && a.bottom > b.top + 1));
                    const quantity = node.querySelector('.nightly-quantity');
                    return { overlaps, quantityWidth: quantity.clientWidth, quantityScrollWidth: quantity.scrollWidth };
                });
                assert(!geometry.overlaps && geometry.quantityScrollWidth <= geometry.quantityWidth + 1, JSON.stringify({ viewport, geometry }));
                await capture(`Responsif_${viewport.width}x${viewport.height}.png`);
                await row.locator('[data-nightly-row-toggle]').click();
                const request = row.locator('[name$="[requested_quantity]"]');
                await request.scrollIntoViewIfNeeded();
                const field = await request.boundingBox();
                const list = await page.locator('#nightly-table-scroll').boundingBox();
                assert(field.y >= list.y - 1 && field.y + field.height <= list.y + list.height + 1, JSON.stringify({ viewport, field, list }));
                await checkFrame();
                await capture(`Rincian_${viewport.width}x${viewport.height}.png`);
                await row.locator('[data-nightly-row-toggle]').click();
                await page.locator('#nightly-table-scroll').evaluate((node) => { node.scrollTop = 0; });
            }
            await resize({ width: 390, height: 844 });
            await capture('Pemeriksaan_Ringkas_Mobile.png');
        });
        await record('Dialog Tambah Obat dapat dipakai di tablet dan HP tanpa terpotong', async () => {
            for (const viewport of [{ width: 768, height: 1024 }, { width: 320, height: 740 }]) {
                await resize(viewport);
                await page.getByRole('button', { name: 'Tambah Obat', exact: true }).click();
                const dialog = page.locator('[data-nightly-add-dialog]');
                const bounds = await dialog.boundingBox();
                assert(bounds.x >= 0 && bounds.x + bounds.width <= viewport.width + 1 && bounds.y >= 0 && bounds.y + bounds.height <= viewport.height + 1, JSON.stringify({ viewport, bounds }));
                assert(await dialog.evaluate((node) => node.scrollWidth <= node.clientWidth + 1));
                await capture(`Dialog_${viewport.width}x${viewport.height}.png`);
                await page.getByRole('button', { name: 'Tutup pilihan obat', exact: true }).click();
            }
        });
        await record('Tidak ada hasil pencarian ditampilkan dengan jelas tanpa menghapus isian', async () => {
            await page.locator('#nightly-search').fill('tidak-ada-obat');
            assert(await page.locator('[data-nightly-empty]').isVisible());
            assert(await page.locator('#nightly-prev').isDisabled());
            assert(await page.locator('#nightly-next').isDisabled());
            await page.locator('#nightly-search').fill('');
            assert.equal(await quantity(0).inputValue(), '0');
        });
        await record('Batch di Rincian menjumlah otomatis dan memberi penjelasan input terkunci', async () => {
            await resize({ width: 1440, height: 900 });
            await goto('/pengecekan-malam');
            await page.locator('[name="location_id"]').selectOption(String(meta.pharmacy));
            await page.getByRole('button', { name: 'Buka / Mulai Pemeriksaan', exact: true }).click();
            await ready();
            await page.locator('[data-nightly-row-toggle]').first().click();
            await page.locator('[data-nightly-batch-count]').fill('18');
            assert.equal(await quantity(0).inputValue(), '18');
            assert(await quantity(0).evaluate((input) => input.readOnly));
            assert((await quantity(0).getAttribute('title')).includes('Rincian'));
            await page.locator('[data-nightly-batch-count]').fill('');
            assert.equal(await quantity(0).inputValue(), '');
            assert(!(await quantity(0).evaluate((input) => input.readOnly)));
            await capture('Pemeriksaan_Batch_Desktop.png');
        });
        await record('Validasi membuka halaman dan Rincian batch yang tersembunyi oleh filter', async () => {
            await page.locator('[data-nightly-row-toggle]').first().click();
            await quantity(0).fill('18');
            await page.locator('#nightly-filter').selectOption('unchecked');
            assert(await quantity(0).isHidden());
            const before = posts;
            await page.getByRole('button', { name: 'Kirim ke Kepala Apoteker', exact: true }).click();
            assert((await page.locator('.swal2-popup').innerText()).includes('Periksa hitungan stok'));
            await page.getByRole('button', { name: 'Perbaiki isian', exact: true }).click();
            assert.equal(posts, before);
            assert(await page.locator('[data-nightly-batch-count]').isVisible());
            assert(await page.locator('[data-nightly-batch-count]').evaluate((input) => input === document.activeElement));
            assert.equal(await page.locator('#nightly-filter').inputValue(), 'all');
        });
        await record('Guest dan apoteker bukan pembuat tetap lihat saja, bukan membuka hak edit', async () => {
            for (const username of ['guest_simulasi', 'apoteker_simulasi']) {
                await login(username);
                await goto(`/pengecekan-malam/${meta.stress_check}`);
                assert(await page.locator('[data-nightly-readonly]').isVisible());
                assert.equal(await page.locator('[data-nightly-count-form]').count(), 0);
                assert.equal(await page.locator('[data-nightly-unit]').count(), 0);
                await page.getByRole('tab', { name: 'Ringkasan', exact: true }).click();
                assert(await page.locator('#nightly-summary').isVisible());
            }
        });
        console.log(`${results.length}/${results.length} workspace checks passed.`);
    } catch (error) {
        if (page) await capture('Kendala_UI.png').catch(() => {});
        throw error;
    } finally {
        await browser?.close();
        if (server.exitCode === null) { const stopped = once(server, 'exit'); server.kill(); await stopped; }
        fs.writeFileSync(path.join(output, 'results.json'), JSON.stringify({ tested_at: new Date().toISOString(), commit: meta.commit,
            working_tree: true, simulation: true, results, errors }, null, 2));
    }
})().catch((error) => { console.error(error.message.replaceAll(process.env.FINAL_PASSWORD || 'NO_SECRET', '[disembunyikan]')); process.exitCode = 1; });
