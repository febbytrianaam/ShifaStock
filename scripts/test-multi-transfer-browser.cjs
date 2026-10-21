const { chromium } = require('playwright');
const { spawn } = require('node:child_process');
const { once } = require('node:events');
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');

(async () => {
    const meta = JSON.parse(fs.readFileSync(process.env.FINAL_META, 'utf8'));
    const password = process.env.FINAL_PASSWORD;
    assert(meta.simulation && meta.phase === 'transfer' && new URL(meta.base_url).hostname === '127.0.0.1' && password);
    const root = path.resolve(__dirname, '..');
    const output = process.env.FINAL_OUTPUT;
    const server = spawn(process.env.FINAL_PHP, ['-S', `127.0.0.1:${meta.port}`, '-t', path.join(root, 'public'),
        path.join(root, 'vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php')],
    { cwd: path.join(root, 'public'), env: process.env, windowsHide: true, stdio: 'ignore' });
    const rows = [];
    const errors = [];
    let browser;
    let page;
    let reference;
    const ready = async () => { await page.waitForLoadState('networkidle'); await page.evaluate(() => document.fonts.ready); };
    const goto = async (url) => { const response = await page.goto(meta.base_url+url); assert.equal(response.status(), 200); await ready(); };
    const record = async (name, callback) => {
        rows.push({ name, status: 'Gagal' });
        await callback();
        assert.deepEqual(errors, []);
        rows.at(-1).status = 'Lulus';
        console.log('PASS '+name);
    };
    const pick = async (select, query) => {
        const wrapper = select.locator('..');
        await wrapper.locator('input[role=combobox]').fill(query);
        await wrapper.locator('[role=option]').filter({ hasText: query }).first().click();
    };
    const capture = async (name) => {
        assert(!(await page.locator('body').innerText()).includes(password));
        await page.screenshot({ path: path.join(output, name) });
    };
    const login = async (username, viewport = { width: 1440, height: 900 }) => {
        page = await browser.newPage({ viewport, locale: 'id-ID', timezoneId: 'Asia/Jakarta' });
        page.on('pageerror', (error) => errors.push(error.message));
        await goto('/login');
        await page.getByLabel('Username', { exact: true }).fill(username);
        await page.getByLabel('Password', { exact: true }).fill(password);
        await page.getByRole('button', { name: 'Masuk', exact: true }).click();
        await page.waitForURL('**/dashboard');
        await ready();
        await goto('/transfer');
    };
    const fillItems = async (a, b) => {
        const lines = page.locator('[data-transfer-row]');
        await pick(lines.nth(0).locator('[data-transfer-medicine]'), 'FINAL-001');
        await lines.nth(0).locator('[data-transfer-quantity]').fill(String(a));
        await pick(lines.nth(1).locator('[data-transfer-medicine]'), 'FINAL-002');
        await lines.nth(1).locator('[data-transfer-quantity]').fill(String(b));
    };
    const save = async () => {
        await page.getByRole('button', { name: 'Simpan Transfer', exact: true }).click();
        await page.locator('.swal2-popup').waitFor();
        await page.getByRole('button', { name: 'Ya, simpan transfer', exact: true }).click();
        await ready();
    };
    try {
        for (let i = 0; ; i++) {
            try { if ((await fetch(meta.base_url+'/login')).ok) break; } catch {}
            if (i > 100) throw new Error('Isolated transfer server not ready');
            await new Promise((resolve) => setTimeout(resolve, 150));
        }
        browser = await chromium.launch({ headless: true, executablePath: 'C:/Program Files/Google/Chrome/Application/chrome.exe' });
        await record('Desktop: defaults and independent searchable item rows', async () => {
            await login('petugas_simulasi');
            assert.equal(await page.locator('[data-transfer-row]').count(), 2);
            assert.equal(await page.locator('#transfer-source').inputValue(), String(meta.warehouse));
            assert.equal(await page.locator('#transfer-target').inputValue(), String(meta.pharmacy));
            await fillItems(52, 7);
            reference = await page.locator('#transfer-reference').inputValue();
            assert((await page.locator('[data-transfer-row]').nth(0).innerText()).includes('SIM-LATE'));
            await capture('Transfer_Multi_Desktop.png');
        });
        await record('Add/remove keeps existing medicines, quantities and unique controls', async () => {
            await page.getByRole('button', { name: 'Baris', exact: true }).click();
            assert.equal(await page.locator('[data-transfer-row]').count(), 3);
            await pick(page.locator('[data-transfer-row]').nth(2).locator('[data-transfer-medicine]'), 'FINAL-001');
            await page.locator('[data-transfer-row]').nth(2).getByRole('button', { name: 'Hapus baris' }).click();
            assert.equal(await page.locator('[data-transfer-row]').count(), 2);
            assert.equal(await page.locator('#transfer-medicine').inputValue(), String(meta.medicine));
            assert.equal(await page.locator('#transfer-quantity').inputValue(), '52');
            assert.equal(await page.locator('#transfer-quantity-1').inputValue(), '7');
            const ids = await page.locator('[data-transfer-form] [id]').evaluateAll((nodes) => nodes.map((node) => node.id));
            assert.equal(new Set(ids).size, ids.length);
        });
        await record('Batch labels follow medicine and source, including shared batch at two locations', async () => {
            await pick(page.locator('#transfer-batch'), 'SIM-BATCH-SAMA');
            await pick(page.locator('#transfer-batch-1'), 'SIM-OTHER');
            await page.locator('#transfer-source').selectOption(String(meta.pharmacy));
            const label = await page.locator('#transfer-batch').locator('..').locator('input[role=combobox]').inputValue();
            assert(label.includes('Apotek') && !label.includes('Gudang'));
            assert.equal(await page.locator('#transfer-batch-1').inputValue(), '');
            await page.locator('#transfer-source').selectOption(String(meta.warehouse));
            assert((await page.locator('#transfer-batch').locator('..').locator('input[role=combobox]').inputValue()).includes('Gudang'));
            await page.locator('#transfer-batch').locator('..').getByRole('button', { name: 'Bersihkan pilihan' }).click();
            await page.locator('#transfer-batch').locator('..').locator('input[role=combobox]').press('Escape');
            await pick(page.locator('#transfer-batch-1'), 'SIM-OTHER');
        });
        await record('Missing/invalid fields use SweetAlert before any transfer request', async () => {
            let posts = 0;
            const listener = (request) => { if (request.method() === 'POST' && new URL(request.url()).pathname === '/transfer') posts++; };
            page.on('request', listener);
            await page.locator('#transfer-quantity-1').fill('0');
            await page.getByRole('button', { name: 'Simpan Transfer', exact: true }).click();
            await page.getByText('Periksa item transfer', { exact: true }).waitFor();
            assert((await page.locator('.swal2-popup').innerText()).includes('Baris 2'));
            await page.getByRole('button', { name: 'Perbaiki isian', exact: true }).click();
            assert.equal(posts, 0);
            await page.locator('#transfer-quantity-1').fill('7');
            page.off('request', listener);
        });
        await record('One save posts both medicines and multiple FEFO allocations under one reference', async () => {
            await save();
            assert((await page.locator('body').innerText()).includes('Transfer diposting'));
            const history = page.locator('tr').filter({ has: page.getByText(reference, { exact: true }) });
            assert.equal(await history.count(), 3);
            assert.equal(await history.locator('a', { hasText: 'Tujuan' }).count(), 3);
            await capture('Transfer_Multi_Berhasil.png');
        });
        await record('Each medicine is traceable in the destination stock card', async () => {
            const today = new Intl.DateTimeFormat('en-CA', { timeZone: 'Asia/Jakarta' }).format(new Date());
            for (const medicine of [meta.medicine, meta.other_medicine]) {
                await goto(`/kartu-stok?medicine_id=${medicine}&location_id=${meta.pharmacy}&start=${today}&end=${today}`);
                assert((await page.locator('body').innerText()).includes(reference));
            }
            await goto('/transfer');
        });
        await record('Failed second item shows row error, restores input and rolls back the first item', async () => {
            await fillItems(1, 999);
            await pick(page.locator('#transfer-batch-1'), 'SIM-OTHER');
            await save();
            await page.getByText(/Baris 2: Batch tidak tersedia/).first().waitFor();
            assert.equal(await page.locator('#transfer-quantity').inputValue(), '1');
            assert.equal(await page.locator('#transfer-quantity-1').inputValue(), '999');
            await capture('Transfer_Multi_Gagal_Atomic.png');
        });
        await record('Mobile: spreadsheet is scrollable, dropdown fits viewport and dynamic rows work', async () => {
            await login('petugas_simulasi', { width: 390, height: 844 });
            const layout = await page.evaluate(() => ({ width: innerWidth, scrollWidth: document.documentElement.scrollWidth,
                outside: [...document.querySelectorAll('body *')].filter((node) => node.getBoundingClientRect().right > innerWidth + 1)
                    .slice(0, 12).map((node) => ({ tag: node.tagName, class: typeof node.className === 'string' ? node.className : '', right: node.getBoundingClientRect().right })) }));
            assert(layout.scrollWidth <= layout.width, 'Mobile horizontal overflow: '+JSON.stringify(layout));
            await pick(page.locator('#transfer-medicine'), 'FINAL-001');
            const input = page.locator('#transfer-medicine').locator('..').locator('input[role=combobox]');
            await input.fill('FINAL');
            const bounds = await page.locator('#transfer-medicine').locator('..').locator('[role=listbox]').boundingBox();
            assert(bounds.x >= 0 && bounds.x + bounds.width <= 390 && bounds.y >= 0 && bounds.y + bounds.height <= 844);
            await capture('Transfer_Multi_Mobile.png');
            await input.press('Escape');
            await page.getByRole('button', { name: 'Baris', exact: true }).click();
            assert.equal(await page.locator('[data-transfer-row]').count(), 3);
            await page.locator('[data-transfer-row]').nth(2).getByRole('button', { name: 'Hapus baris' }).click();
            assert.equal(await page.locator('[data-transfer-row]').count(), 2);
        });
        await record('Guest can read history but cannot open write form or post multi-item transfers', async () => {
            await login('guest_simulasi');
            assert.equal(await page.locator('[data-transfer-form]').count(), 0);
            assert((await page.locator('body').innerText()).includes(reference));
            const token = await page.locator('form[action$="/logout"] input[name="_token"]').inputValue();
            const response = await page.request.post(meta.base_url+'/transfer', {
                headers: { Accept: 'application/json', 'X-CSRF-TOKEN': token },
                data: { reference: 'FORBIDDEN', items: [{ medicine_id: meta.medicine, quantity: 1 }] },
            });
            assert.equal(response.status(), 403);
        });
        console.log(`${rows.length}/${rows.length} multi-transfer browser checks passed.`);
    } catch (error) {
        const message = error.message.replaceAll(password, '[hidden]');
        if (rows.length) rows.at(-1).error = message;
        console.error(message);
        if (page) await capture('Kendala_Transfer.png').catch(() => {});
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
})().catch(() => { console.error('Multi-transfer browser runner failed.'); process.exitCode = 1; });
