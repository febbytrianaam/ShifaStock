// Requires locally generated fixtures; all requests are intercepted and no clinic records are written.
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
        const page = await browser.newPage({ viewport: { width: 1440, height: 1050 } });
        const errors = [];
        const submissions = [];
        page.on('pageerror', (error) => errors.push(error.message));
        await page.route('**/*', async (route) => {
            const request = route.request();
            const url = new URL(request.url());
            if (request.method() === 'POST') {
                submissions.push(new URLSearchParams(request.postData()));
                return route.fulfill({ contentType: 'text/html', body: '<html><body>Hitungan dikirim</body></html>' });
            }
            if (request.isNavigationRequest()) return route.fulfill({ contentType: 'text/html', body: fs.readFileSync(path.join(fixtures, 'nightly.html'), 'utf8') });
            if (url.pathname.startsWith('/build/') || url.pathname.startsWith('/images/')) {
                const file = path.resolve(root, 'public', '.' + url.pathname);
                assert(file.startsWith(path.join(root, 'public') + path.sep));
                return route.fulfill({ contentType: file.endsWith('.js') ? 'text/javascript' : file.endsWith('.css') ? 'text/css' : 'image/png', body: fs.readFileSync(file) });
            }
            return route.abort();
        });
        await page.goto('http://127.0.0.1:8000/pengecekan-malam/999999');
        await page.waitForFunction(() => document.querySelector('[data-nightly-row]')?.dataset.batchReady === '1');
        await page.getByRole('button', { name: 'Kirim ke Kepala Apoteker', exact: true }).click();
        await page.getByRole('button', { name: 'Perbaiki isian' }).click();
        await page.locator('.swal2-container').waitFor({ state: 'detached' });
        const inputs = page.locator('[data-nightly-batch-count]');
        const expected = {};
        let total = 0;
        for (let index = 0; index < await inputs.count(); index++) {
            const input = inputs.nth(index);
            const current = Number(await input.getAttribute('data-system-quantity'));
            if (current <= 0) continue;
            const quantity = Math.max(0, current - 1);
            await input.fill(String(quantity));
            expected[await input.getAttribute('data-batch-id')] = String(quantity);
            total += quantity;
        }
        assert.equal(await page.locator('input[name$="[quantity]"]').inputValue(), String(total));
        assert(await page.locator('input[name$="[quantity]"]').evaluate((field) => field.readOnly));
        assert(await page.evaluate(() => !window.dispatchEvent(new Event('beforeunload', { cancelable: true }))));
        await page.screenshot({ path: path.join(fixtures, 'daily-batches-desktop.png'), fullPage: true });
        await page.setViewportSize({ width: 390, height: 844 });
        assert(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth + 1));
        await page.screenshot({ path: path.join(fixtures, 'daily-batches-mobile.png'), fullPage: true });
        await page.getByRole('button', { name: 'Kirim ke Kepala Apoteker', exact: true }).click();
        await page.getByText('Hitungan dikirim', { exact: true }).waitFor();
        assert.equal(submissions.length, 1);
        assert.equal(submissions[0].get('action'), 'submit');
        const payload = JSON.parse(submissions[0].get('batch_counts_payload'));
        assert.deepEqual(payload['999999'], expected);
        assert.equal(submissions[0].get('items[999999][quantity]'), String(total));
        assert.deepEqual(errors, []);
        console.log('PASS: daily batch validation, auto-total, unsaved guard, compact JSON payload, desktop/mobile layout.');
    } finally {
        await browser.close();
    }
})().catch((error) => { console.error(error); process.exitCode = 1; });
