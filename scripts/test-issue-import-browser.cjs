const { chromium } = require('playwright');
const { spawn } = require('node:child_process');
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');

(async () => {
    const output = process.env.ISSUE_QA_OUTPUT;
    const password = process.env.ISSUE_QA_PASSWORD;
    const meta = JSON.parse(fs.readFileSync(path.join(output, 'meta.json'), 'utf8'));
    assert(meta.simulation && password && new URL(meta.base_url).hostname === '127.0.0.1');
    const root = path.resolve(__dirname, '..');
    const server = spawn(process.env.ISSUE_QA_PHP, ['-S', `127.0.0.1:${meta.port}`, '-t', path.join(root, 'public'),
        path.join(root, 'vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php')],
    { cwd: path.join(root, 'public'), env: { ...process.env, APP_ENV: 'local' }, windowsHide: true, stdio: 'ignore' });
    let browser, page;
    const errors = [], results = [];
    const ready = async () => { await page.waitForLoadState('networkidle'); await page.evaluate(() => document.fonts.ready); };
    const goto = async (url) => { const response = await page.goto(meta.base_url + url); assert.equal(response.status(), 200); await ready(); };
    const record = async (name, action) => {
        results.push({ name, status: 'Gagal' });
        try {
            await action();
        } catch (error) {
            if (page) {
                await page.screenshot({ path: path.join(output, 'Kegagalan.png'), fullPage: true });
                const text = (await page.locator('body').innerText()).replaceAll(password, '[redacted]');
                fs.writeFileSync(path.join(output, 'Kegagalan.json'), JSON.stringify({ url: page.url(), text: text.slice(0, 4000) }, null, 2));
            }
            throw error;
        }
        assert.deepEqual(errors, []);
        results.at(-1).status = 'Lulus';
        console.log('PASS ' + name);
    };
    const screenshot = async (file) => {
        const text = await page.locator('body').innerText();
        for (const secret of [password, 'PASIEN_SINTETIS_PRIVASI', 'DOKTER_SINTETIS_PRIVASI', 'SIM-SALE-1']) assert(!text.includes(secret));
        await page.evaluate(() => window.scrollTo(0, 0));
        await page.waitForTimeout(100);
        await page.screenshot({ path: path.join(output, file), fullPage: true });
    };
    const frame = async () => {
        const size = await page.evaluate(() => {
            const text = [], walker = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT);
            while (walker.nextNode()) {
                const node = walker.currentNode;
                if (!node.textContent.trim() || node.parentElement.closest('table, .searchable-select-menu, script, style')) continue;
                const range = document.createRange(); range.selectNode(node);
                if ([...range.getClientRects()].some((rect) => rect.right > innerWidth + 1)) text.push({ text: node.textContent.trim().slice(0, 90), parent: node.parentElement.outerHTML.slice(0, 200) });
            }
            return { viewport: innerWidth, page: document.documentElement.scrollWidth, text,
                offenders: [...document.querySelectorAll('body *')].filter((el) => el.getBoundingClientRect().right > innerWidth + 1 && !el.closest('table, .searchable-select-menu')).slice(0, 8).map((el) => ({ tag: el.tagName, class: el.className, right: el.getBoundingClientRect().right })) };
        });
        assert(size.page <= size.viewport + 1, JSON.stringify(size));
    };
    const login = async (user) => {
        if (page) await page.close();
        page = await browser.newPage({ viewport: { width: 1440, height: 900 }, locale: 'id-ID', timezoneId: 'Asia/Jakarta', reducedMotion: 'reduce' });
        page.on('pageerror', (error) => errors.push(error.message));
        await goto('/login');
        await page.getByLabel('Username', { exact: true }).fill(user);
        await page.getByLabel('Password', { exact: true }).fill(password);
        await page.getByRole('button', { name: 'Masuk', exact: true }).click();
        await page.waitForURL('**/dashboard');
        await ready();
    };
    const upload = async (file) => {
        await goto('/obat-keluar/impor');
        await page.getByLabel('File ekspor Neoklinik').setInputFiles(path.join(output, file));
        if (!(await page.getByLabel('Mulai pencatatan (WIB)').evaluate((element) => element.readOnly))) {
            await page.getByLabel('Mulai pencatatan (WIB)').fill(meta.cutoff);
        }
        await page.locator('[name="cutoff_confirmed"]').check();
        await page.getByRole('button', { name: 'Baca File & Lanjutkan' }).click();
        await page.waitForURL(/\/obat-keluar\/impor\/\d+$/);
        await ready();
    };
    const post = async () => {
        for (const input of await page.locator('[data-import-post] input[type="checkbox"]').all()) await input.check();
        await page.locator('[data-import-post] button[type="submit"]').click();
        await page.getByRole('button', { name: 'Ya, simpan pengeluaran' }).click();
        await ready();
        await page.waitForSelector('[role="status"]');
    };
    try {
        for (let attempt = 0; ; attempt++) {
            try { if ((await fetch(meta.base_url + '/login')).ok) break; } catch {}
            if (attempt > 100) throw new Error('Local QA server not ready');
            await new Promise((resolve) => setTimeout(resolve, 150));
        }
        browser = await chromium.launch({ headless: true, executablePath: 'C:/Program Files/Google/Chrome/Application/chrome.exe' });
        await record('Unggah dan riwayat responsif desktop, tablet, dan HP', async () => {
            await login('petugas_simulasi');
            await goto('/obat-keluar/impor');
            for (const viewport of [{ width: 1440, height: 900 }, { width: 2560, height: 1440 }, { width: 768, height: 1024 }, { width: 390, height: 844 }]) {
                await page.setViewportSize(viewport);
                await page.waitForTimeout(350);
                await frame();
                await screenshot(`Impor_${viewport.width}.png`);
            }
            await page.setViewportSize({ width: 1440, height: 900 });
        });
        await record('Unggah tidak langsung posting; obat cocok tetap perlu persetujuan', async () => {
            await upload('Simulasi.xlsx');
            assert((await page.locator('[data-import-summary]').innerText()).includes('2 perlu diperbaiki'));
            assert.equal(await page.locator('[data-import-post]').count(), 0);
            assert.equal(await page.getByRole('navigation', { name: 'Langkah impor' }).count(), 1);
            assert.equal(await page.locator('[data-import-match-row]').count(), 1);
            await screenshot('Pratinjau_Belum_Disetujui.png');
        });
        await record('Beralih ke Input Manual dapat kembali ke impor yang sama tanpa unggah ulang', async () => {
            const importPath = new URL(page.url()).pathname;
            const factor = page.locator('[data-import-match-row] input[type="number"]').first();
            await factor.fill('2');
            await page.getByRole('navigation', { name: 'Pengeluaran obat', exact: true }).getByRole('link', { name: 'Input Manual', exact: true }).click();
            assert(await page.getByText('Ada isian yang belum disimpan', { exact: true }).isVisible());
            await page.getByRole('button', { name: 'Tetap di halaman', exact: true }).click();
            assert.equal(new URL(page.url()).pathname, importPath);
            assert.equal(await factor.inputValue(), '2');
            await factor.fill('1');
            await page.getByRole('navigation', { name: 'Pengeluaran obat', exact: true }).getByRole('link', { name: 'Input Manual', exact: true }).click();
            await ready();
            assert.equal(new URL(page.url()).pathname, '/obat-keluar');
            const resume = page.locator('[data-import-resume]');
            assert((await resume.innerText()).includes('Tidak perlu unggah ulang'));
            await screenshot('Lanjutkan_Impor_Dari_Manual.png');
            const importTab = page.getByRole('navigation', { name: 'Pengeluaran obat', exact: true }).getByRole('link', { name: 'Impor & Riwayat Excel', exact: true });
            assert.equal(new URL(await importTab.getAttribute('href')).pathname, importPath);
            await importTab.click();
            await ready();
            assert.equal(new URL(page.url()).pathname, importPath);
            assert.equal(await page.locator('[data-import-match-row]').count(), 1);
            await goto('/obat-keluar/impor');
            await page.locator('[data-import-resume]').getByRole('link', { name: 'Lanjutkan pemeriksaan' }).click();
            await ready();
            assert.equal(new URL(page.url()).pathname, importPath);
        });
        await record('Nomor, tanggal WIB, dan rincian Excel terbaca tanpa identitas pasien', async () => {
            const group = page.locator('[data-import-match-row]').first();
            assert.equal(await group.locator('[data-label="No."]').innerText(), '1');
            assert((await group.locator('[data-label="Tanggal Jual (WIB)"]').innerText()).includes('09:00:01'));
            const details = page.locator('[data-import-source-details]').first();
            await details.locator(':scope > summary').click();
            const rows = details.locator('[data-import-source-row]');
            assert.equal(await rows.count(), 2);
            assert.deepEqual(await rows.locator('[data-label="Baris Excel"]').allTextContents(), ['2', '3']);
            assert.deepEqual(await rows.locator('[data-label="Jumlah"]').allTextContents(), ['15', '2']);
            for (const viewport of [{ width: 2560, height: 1440 }, { width: 1440, height: 900 }, { width: 768, height: 1024 }, { width: 390, height: 844 }]) {
                await page.setViewportSize(viewport);
                await details.scrollIntoViewIfNeeded();
                await page.waitForTimeout(200);
                await frame();
                const width = await details.locator('[data-import-source-table]').evaluate((table) => ({ scroll: table.parentElement.scrollWidth, client: table.parentElement.clientWidth }));
                assert(width.scroll <= width.client + 1, JSON.stringify(width));
                if (viewport.width < 1024) {
                    assert(await details.locator('.issue-import-source-scroll').evaluate((element) => element.scrollHeight <= element.clientHeight + 1), 'Rincian HP tidak boleh punya gulir vertikal bertingkat');
                }
                await screenshot(`Rincian_Excel_${viewport.width}.png`);
            }
            await page.setViewportSize({ width: 1440, height: 900 });
            const help = details.locator('[data-import-status-help]').first();
            await help.locator(':scope > summary').click();
            const instruction = await help.innerText();
            for (const expected of ['Cocokkan obat', 'periksa isi per satuan', 'centang Benar', 'Simpan Pilihan & Lanjutkan']) assert(instruction.includes(expected));
            assert.equal(await page.locator('[data-import-map-confirm]:checked').count(), 0);
            await screenshot('Petunjuk_Konfirmasi.png');
            await help.locator(':scope > summary').click();
            await details.locator(':scope > summary').click();
            assert.equal(await page.locator('[data-import-map-confirm]:checked').count(), 0);
        });
        await record('Dropdown searchable, pemetaan tersimpan, dan validasi SweetAlert', async () => {
            const search = page.locator('[data-import-mappings] .searchable-select-input');
            await search.fill('SIM-IMPORT');
            await page.locator('.searchable-select-option:visible').filter({ hasText: 'Obat Simulasi Impor' }).click();
            await page.locator('[data-import-mappings] input[type="checkbox"]').check();
            await page.getByRole('button', { name: 'Simpan Pilihan & Lanjutkan' }).click();
            await ready();
            assert((await page.locator('[data-import-summary]').innerText()).includes('2 siap'));
            assert.equal(await page.locator('[data-import-mappings]').count(), 0);
            await page.locator('[data-import-post] button').click();
            await page.getByRole('button', { name: 'Mengerti', exact: true }).click();
            assert.equal(await page.locator('[data-import-post]').count(), 1);
            await screenshot('Pratinjau_Siap.png');
        });
        await record('Pratinjau dan pencocokan tidak membuat halaman melebar di empat viewport', async () => {
            for (const viewport of [{ width: 1440, height: 900 }, { width: 2560, height: 1440 }, { width: 768, height: 1024 }, { width: 390, height: 844 }]) {
                await page.setViewportSize(viewport);
                await page.waitForTimeout(350);
                await frame();
                await screenshot(`Pratinjau_${viewport.width}.png`);
            }
            await page.setViewportSize({ width: 1440, height: 900 });
            await page.getByRole('navigation', { name: 'Langkah impor' }).getByRole('link', { name: '2. Cocokkan obat' }).click();
            await ready();
            for (const viewport of [{ width: 1440, height: 900 }, { width: 768, height: 1024 }, { width: 390, height: 844 }]) {
                await page.setViewportSize(viewport);
                await page.waitForTimeout(350);
                await frame();
                const width = await page.locator('[data-import-mappings]').evaluate((table) => ({ scroll: table.parentElement.scrollWidth, client: table.parentElement.clientWidth }));
                assert(width.scroll <= width.client + 1, JSON.stringify(width));
                await screenshot(`Pencocokan_${viewport.width}.png`);
            }
            await page.setViewportSize({ width: 1440, height: 900 });
            await page.getByRole('navigation', { name: 'Langkah impor' }).getByRole('link', { name: '3. Periksa & simpan' }).click();
            await ready();
        });
        await record('Posting FEFO menampilkan dua batch dan tautan kartu stok', async () => {
            await post();
            const text = await page.locator('body').innerText();
            for (const expected of ['2 transaksi baru diposting', 'SIM-AWAL', 'SIM-AKHIR', 'Lihat mutasi']) assert(text.includes(expected));
            await screenshot('Posting_Berhasil.png');
            await page.getByRole('link', { name: 'Lihat mutasi', exact: true }).first().click();
            await ready();
            assert((await page.locator('body').innerText()).includes('OUT-IMP-'));
        });
        await record('Unggah ulang memakai pemetaan tersimpan dan tidak membuat mutasi kedua', async () => {
            await upload('Simulasi.xlsx');
            assert((await page.locator('[data-import-summary]').innerText()).includes('2 sudah diimpor'));
            await post();
            assert((await page.locator('body').innerText()).includes('0 transaksi baru diposting'));
        });
        await record('Transaksi sumber yang berubah diblokir dan tidak diam-diam dikurangi', async () => {
            await upload('Simulasi_Berubah.xlsx');
            assert((await page.locator('[data-import-problems]').innerText()).includes('Transaksi pernah dicatat, tetapi isinya berubah'));
            assert(await page.locator('[data-import-post] button').isDisabled());
            await screenshot('Data_Berubah_Diblokir.png');
        });
        await record('Hapus dari detail memakai SweetAlert; Batal mempertahankan draf', async () => {
            const draftUrl = page.url();
            const draftId = draftUrl.split('/').at(-1);
            const remove = page.getByRole('button', { name: `Hapus pratinjau IMP-${draftId}`, exact: true });
            assert.equal(await remove.locator('svg').count(), 1);
            await remove.click();
            assert((await page.locator('.swal2-popup').innerText()).includes('Stok tidak berubah'));
            await page.getByRole('button', { name: 'Batal', exact: true }).click();
            await page.locator('.swal2-popup').waitFor({ state: 'hidden' });
            assert.equal(page.url(), draftUrl);
            await remove.waitFor({ state: 'visible' });
            await remove.focus();
            await remove.press('Enter');
            await page.getByRole('button', { name: 'Ya, hapus pratinjau', exact: true }).click();
            await page.waitForURL(/\/obat-keluar\/impor\/?$/);
            await ready();
            assert((await page.getByRole('status').innerText()).includes('Pratinjau impor dihapus'));
            assert.equal(await page.locator(`form[action$="/impor/${draftId}"]`).count(), 0);
            await screenshot('Hapus_Pratinjau_Berhasil.png');
        });
        await record('Hapus dari riwayat tersedia di HP; dokumen terposting tetap terlindungi', async () => {
            await upload('Simulasi_Berubah.xlsx');
            const draftId = page.url().split('/').at(-1);
            await goto('/obat-keluar/impor');
            await page.locator('[data-import-history] > summary').click();
            await page.setViewportSize({ width: 390, height: 844 });
            await frame();
            const remove = page.getByRole('button', { name: `Hapus pratinjau IMP-${draftId}`, exact: true });
            await remove.scrollIntoViewIfNeeded();
            await screenshot('Hapus_Pratinjau_HP.png');
            await remove.click();
            await page.getByRole('button', { name: 'Ya, hapus pratinjau', exact: true }).click();
            await ready();
            assert.equal(await page.getByRole('button', { name: `Hapus pratinjau IMP-${draftId}`, exact: true }).count(), 0);
            assert.equal(await page.locator('tbody tr').filter({ hasText: 'Terposting' }).locator('[data-issue-import-delete]').count(), 0);
            await page.setViewportSize({ width: 1440, height: 900 });
        });
        await record('Pilihan tersimpan disembunyikan dan persetujuan tidak otomatis mengikuti perubahan', async () => {
            await upload('Simulasi_Periksa.xlsx');
            assert.equal(await page.locator('[data-import-match-row]:visible').count(), 1);
            assert.equal(await page.locator('[data-import-map-confirm]:checked').count(), 0);
            await page.locator('[data-import-select-visible]').click();
            assert.equal(await page.locator('[data-import-map-confirm]:checked').count(), 0);
            const row = page.locator('[data-import-match-row]:visible');
            await row.locator('.searchable-select-input').fill('SIM-IMPORT');
            await page.locator('.searchable-select-option:visible').filter({ hasText: 'Obat Simulasi Impor' }).click();
            await page.locator('[data-import-select-visible]').check();
            assert.equal(await page.locator('[data-import-map-confirm]:checked').count(), 1);
            await row.locator('input[type="number"]').fill('2');
            assert.equal(await page.locator('[data-import-map-confirm]:checked').count(), 0);
            await page.locator('[data-import-show-saved]').check();
            assert.equal(await page.locator('[data-import-match-row]:visible').count(), 2);
            assert.equal(await page.locator('[data-import-match-details]:visible').count(), 2);
            await page.locator('[data-import-select-visible]').check();
            assert.equal(await page.locator('[data-import-map-confirm]:checked').count(), 2);
            // Deliberately discard unsaved choices; no new mapping is saved in this case.
            page.once('dialog', (dialog) => dialog.accept());
            await page.reload();
            await ready();
            assert.equal(await page.locator('[data-import-map-confirm]:checked').count(), 0);
        });
        await record('Cari obat mempertahankan centang dan hanya menandai pilihan yang tampil', async () => {
            await page.locator('[data-import-show-saved]').check();
            const search = page.getByRole('searchbox', { name: 'Cari obat pada Excel' });
            await search.fill('sim-unmatched');
            assert.equal(await page.locator('[data-import-match-row]:visible').count(), 1);
            assert.equal(await page.locator('[data-import-match-details]:visible').count(), 1);
            await page.locator('[data-import-match-row]:visible .searchable-select-input').fill('SIM-IMPORT');
            await page.locator('.searchable-select-option:visible').filter({ hasText: 'Obat Simulasi Impor' }).click();
            await page.locator('[data-import-select-visible]').check();
            assert.equal(await page.locator('[data-import-map-confirm]:checked').count(), 1);
            await search.fill('Obat Simulasi Impor');
            assert.equal(await page.locator('[data-import-match-row]:visible').count(), 1);
            assert.equal(await page.locator('[data-import-match-row]:visible [data-import-map-confirm]:checked').count(), 0);
            await search.fill('Tidak ada obat ini');
            assert.equal(await page.locator('[data-import-match-row]:visible').count(), 0);
            assert.equal(await page.locator('[data-import-match-details]:visible').count(), 0);
            assert((await page.locator('[data-import-match-result]').innerText()).includes('Obat tidak ditemukan'));
            const url = page.url();
            await search.press('Enter');
            await page.waitForTimeout(150);
            assert.equal(page.url(), url);
            await search.fill('');
            assert.equal(await page.locator('[data-import-match-row]:visible').count(), 2);
            assert.equal(await page.locator('[data-import-map-confirm]:checked').count(), 1);
            page.once('dialog', (dialog) => dialog.accept());
            await page.reload();
            await ready();
            assert.equal(await page.locator('[data-import-map-confirm]:checked').count(), 0);
        });
        await record('Peringatan tanggal dan obat dipisahkan; filter tidak mengubah stok', async () => {
            await page.getByRole('navigation', { name: 'Langkah impor' }).getByRole('link', { name: '3. Periksa & simpan' }).click();
            await ready();
            const problems = await page.locator('[data-import-problems]').innerText();
            for (const expected of ['Pengeluaran lebih awal dari', 'Pilihan obat dan satuan perlu dikonfirmasi', 'Jangan memundurkan tanggal']) assert(problems.includes(expected));
            assert(await page.locator('[data-import-post] button').isDisabled());
            await page.locator('[data-import-review-filter]').selectOption('ready');
            assert.equal(await page.locator('[data-import-review-row]:visible').count(), 0);
            assert.equal(await page.locator('[data-import-review-details]:visible').count(), 0);
            assert((await page.locator('[data-import-review-count]').innerText()).includes('0 dari 2'));
            await page.locator('[data-import-review-filter]').selectOption('all');
            assert.equal(await page.locator('[data-import-review-row]:visible').count(), 2);
            assert.equal(await page.locator('[data-import-review-details]:visible').count(), 2);
            await screenshot('Petunjuk_Perbaikan.png');
        });
        await record('Daftar panjang di kiri, panel informasi di kanan, dan susunan HP tidak melebar', async () => {
            await upload('Simulasi_Banyak.xlsx');
            assert.equal(await page.locator('[data-import-match-row]').count(), 48);
            assert.equal(await page.locator('[data-import-info] nav[aria-label="Langkah impor"]').count(), 1);
            assert.equal(await page.locator('[data-import-info] [data-import-file-details][open]').count(), 0);
            await page.locator('[data-import-file-details] > summary').click();
            assert(await page.getByText('Periode pada Excel', { exact: true }).isVisible());
            await page.locator('[data-import-file-details] > summary').click();
            await page.locator('[data-import-matching-guide] > summary').click();
            assert(await page.getByText('Pilih obat yang sama di ShifaStock.', { exact: false }).isVisible());
            await page.locator('[data-import-matching-guide] > summary').click();
            assert.equal(await page.locator('[data-import-map-confirm]:checked').count(), 0);
            const layout = async (viewport, prefix) => {
                await page.setViewportSize(viewport);
                await page.evaluate(() => window.scrollTo(0, 0));
                await page.waitForTimeout(200);
                await frame();
                const geometry = await page.locator('.issue-import-workspace').evaluate((workspace) => {
                    const list = workspace.querySelector('[data-import-list]').getBoundingClientRect();
                    const info = workspace.querySelector('[data-import-info]').getBoundingClientRect();
                    const scroller = workspace.querySelector('.issue-import-list-scroll');
                    const rect = scroller.getBoundingClientRect();
                    const tabs = document.querySelector('nav[aria-label="Pengeluaran obat"]').getBoundingClientRect();
                    return { list: { right: list.right, top: list.top, bottom: list.bottom },
                        info: { left: info.left, top: info.top }, tableOverflow: scroller.scrollWidth - scroller.clientWidth,
                        scrolls: scroller.scrollHeight > scroller.clientHeight, bottom: rect.bottom, gapAfterTabs: list.top - tabs.bottom };
                });
                assert(geometry.tableOverflow <= 1, JSON.stringify(geometry));
                assert(geometry.scrolls, JSON.stringify(geometry));
                if (viewport.width >= 1280) {
                    assert(geometry.gapAfterTabs <= 24, JSON.stringify(geometry));
                    assert(geometry.info.left >= geometry.list.right + 20, JSON.stringify(geometry));
                    assert(Math.abs(geometry.info.top - geometry.list.top) <= 2, JSON.stringify(geometry));
                    assert(geometry.bottom <= viewport.height + 1, JSON.stringify(geometry));
                    if (prefix === 'Panel_Pencocokan') {
                        const button = page.getByRole('button', { name: 'Simpan Pilihan & Lanjutkan' });
                        const before = await button.boundingBox();
                        assert(before.y >= 0 && before.y + before.height <= viewport.height, JSON.stringify(before));
                        await page.locator('.issue-import-list-scroll').evaluate((element) => { element.scrollTop = element.scrollHeight; });
                        const after = await button.boundingBox();
                        assert(Math.abs(after.y - before.y) <= 1, 'Tombol ikut bergeser saat daftar digulir');
                    }
                } else {
                    assert(geometry.info.top >= geometry.list.bottom, JSON.stringify(geometry));
                }
                await page.locator('.issue-import-list-scroll').evaluate((element) => { element.scrollTop = 0; });
                await screenshot(`${prefix}_${viewport.width}.png`);
            };
            const viewports = [{ width: 2560, height: 1440 }, { width: 1920, height: 1080 }, { width: 1440, height: 900 },
                { width: 1280, height: 800 }, { width: 1024, height: 768 }, { width: 768, height: 1024 },
                { width: 390, height: 844 }, { width: 320, height: 740 }];
            for (const viewport of viewports) await layout(viewport, 'Panel_Pencocokan');
            await page.setViewportSize({ width: 1440, height: 900 });
            await page.getByRole('navigation', { name: 'Langkah impor' }).getByRole('link', { name: '3. Periksa & simpan' }).click();
            await ready();
            assert.equal(await page.locator('[data-import-review-row]').count(), 48);
            for (const viewport of viewports) await layout(viewport, 'Panel_Pemeriksaan');
        });
        await record('Guest dapat melihat riwayat namun tidak bisa mengunggah atau posting', async () => {
            await login('guest_simulasi');
            await goto('/obat-keluar/impor');
            assert.equal(await page.locator('input[type="file"]').count(), 0);
            assert.equal(await page.locator('[data-issue-import-delete]').count(), 0);
            const link = page.getByRole('link', { name: 'Lihat', exact: true }).first();
            await link.click();
            await ready();
            assert.equal(await page.locator('[data-import-post]').count(), 0);
            assert.equal(await page.locator('[data-import-mappings]').count(), 0);
            assert.equal(await page.locator('[data-issue-import-delete]').count(), 0);
        });
    } finally {
        fs.writeFileSync(path.join(output, 'Playwright.json'), JSON.stringify({ checked_at: new Date().toISOString(), simulation: true, results, errors }, null, 2));
        if (browser) await browser.close();
        server.kill();
        await new Promise((resolve) => { if (server.exitCode !== null) resolve(); else server.once('exit', resolve); });
    }
})().catch((error) => { console.error(error.message); process.exitCode = 1; });
