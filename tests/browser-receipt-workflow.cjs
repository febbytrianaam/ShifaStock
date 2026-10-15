// Generate local read-only fixtures with: php scripts/verify-inventory-usability.php --fixtures
// Run with Playwright available in NODE_PATH. All browser requests are intercepted locally.
const { chromium } = require('playwright');
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');

(async () => {
    const root = path.resolve(__dirname, '..');
    const fixtures = path.join(root, 'storage/app/qa/inventory');
    const chrome = [process.env.SHIFA_BROWSER_PATH, 'C:/Program Files/Google/Chrome/Application/chrome.exe', 'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe'].find((file) => file && fs.existsSync(file));
    const browser = await chromium.launch({ headless: true, ...(chrome ? { executablePath: chrome } : {}) });
    try {
        const page = await browser.newPage({ viewport: { width: 1440, height: 1000 } });
        const errors = [];
        const submissions = [];
        page.on('pageerror', (error) => errors.push(error.message));
        await page.route('**/*', async (route) => {
            const request = route.request();
            const url = new URL(request.url());
            if (url.pathname === '/obat-masuk/cek-faktur') {
                return route.fulfill({ json: { matches: [{ reference: 'FKT-EXISTING', status: 'Stok ditambahkan' }] } });
            }
            if (request.method() === 'POST') {
                submissions.push(new URLSearchParams(request.postData()));
                return route.fulfill({ contentType: 'text/html', body: '<html><body>Penerimaan tersimpan</body></html>' });
            }
            if (request.isNavigationRequest()) {
                let html = fs.readFileSync(path.join(fixtures, 'receipts.html'), 'utf8');
                if (url.searchParams.has('restored')) {
                    html = html.replace(/(<script[^>]*data-restored-input-fields>)[\s\S]*?(<\/script>)/, '$1["counterparty"]$2');
                }
                return route.fulfill({ contentType: 'text/html', body: html });
            }
            if (url.pathname.startsWith('/build/') || url.pathname.startsWith('/images/')) {
                const file = path.resolve(root, 'public', '.' + url.pathname);
                assert(file.startsWith(path.join(root, 'public') + path.sep));
                const type = file.endsWith('.js') ? 'text/javascript' : file.endsWith('.css') ? 'text/css' : 'image/png';
                return route.fulfill({ contentType: type, body: fs.readFileSync(file) });
            }
            return route.abort();
        });

        await page.goto('http://127.0.0.1:8000/obat-masuk');
        await page.locator('.receipt-entry [role="combobox"]').first().waitFor();
        const medicine = page.locator('[data-receipt-medicine]').first();
        const first = await medicine.locator('option').nth(1).evaluate((option) => ({ text: option.textContent, unit: option.dataset.stockUnit }));
        await page.locator('.receipt-entry [role="combobox"]').first().fill(first.text);
        const option = page.locator('.searchable-select-option').first();
        await option.waitFor();
        const menuBox = await page.locator('.receipt-entry .searchable-select-menu').first().boundingBox();
        assert(menuBox && menuBox.height > 20 && menuBox.y + menuBox.height <= 1000, 'Search dropdown is clipped.');
        await option.click();
        await page.locator('button[name="action"][value="draft"]').click();
        await page.getByRole('button', { name: 'Perbaiki isian' }).waitFor();
        assert((await page.locator('.swal2-html-container').innerText()).includes('Baris 1'));
        assert(await page.locator('[data-receipt-field-error]').count() > 0);
        await page.getByRole('button', { name: 'Perbaiki isian' }).click();
        await page.locator('[name="items[0][batch_number]"]').fill('QA-BATCH');
        await page.locator('[name="items[0][expiry_date]"]').fill('2028-01-01');
        await page.locator('[name="items[0][input_quantity]"]').fill('2');
        await page.locator('[data-receipt-input-unit]').first().selectOption(first.unit);
        assert.equal(await page.locator('[data-receipt-stock-quantity]').first().inputValue(), '2');

        await page.getByRole('link', { name: 'Riwayat Penerimaan', exact: true }).click();
        await page.getByRole('button', { name: 'Tetap di halaman' }).waitFor();
        await page.getByRole('button', { name: 'Tetap di halaman' }).click();
        await page.locator('.swal2-container').waitFor({ state: 'detached' });
        assert.equal(await page.locator('[name="items[0][batch_number]"]').inputValue(), 'QA-BATCH');
        assert(await page.evaluate(() => !window.dispatchEvent(new Event('beforeunload', { cancelable: true }))));

        await page.locator('#receipt-supplier').fill('PBF QA');
        await page.locator('#receipt-invoice').fill('INV-QA');
        await page.screenshot({ path: path.join(fixtures, 'receipt-desktop.png'), fullPage: true });
        await page.locator('button[name="action"][value="draft"]').click();
        await page.getByRole('button', { name: 'Periksa kembali', exact: true }).waitFor();
        await page.getByRole('button', { name: 'Periksa kembali', exact: true }).click();
        assert.equal(submissions.length, 0);
        await page.locator('button[name="action"][value="draft"]').click();
        await page.getByRole('button', { name: 'Sudah diperiksa, lanjutkan', exact: true }).click();
        await page.getByText('Penerimaan tersimpan', { exact: true }).waitFor();
        assert.equal(submissions.length, 1);
        assert.equal(submissions[0].get('duplicate_invoice_confirmed'), '1');
        assert.equal(submissions[0].get('action'), 'draft');

        await page.setViewportSize({ width: 390, height: 844 });
        await page.goto('http://127.0.0.1:8000/obat-masuk');
        await page.locator('.receipt-entry [role="combobox"]').first().waitFor();
        assert(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth + 1), 'Mobile page overflows horizontally.');
        await page.screenshot({ path: path.join(fixtures, 'receipt-mobile.png'), fullPage: true });
        assert(await page.evaluate(() => window.dispatchEvent(new Event('beforeunload', { cancelable: true }))), 'Untouched form is incorrectly marked dirty.');
        await page.goto('http://127.0.0.1:8000/obat-masuk?restored=1');
        await page.locator('.receipt-entry [role="combobox"]').first().waitFor();
        assert(await page.evaluate(() => !window.dispatchEvent(new Event('beforeunload', { cancelable: true }))), 'Restored invalid input is not protected.');
        assert.deepEqual(errors, []);
        console.log('PASS: row errors, conversion, unsaved navigation, duplicate cancel/confirm, submit action, desktop/mobile layout.');
        console.log('Screenshots: ' + fixtures);
    } finally {
        await browser.close();
    }
})().catch((error) => { console.error(error); process.exitCode = 1; });
