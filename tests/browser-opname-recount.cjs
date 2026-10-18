// Uses read-only fixtures; every request is intercepted, including the recount POST.
const { chromium } = require('playwright');
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');

(async () => {
    const root = path.resolve(__dirname, '..');
    const fixture = path.join(root, 'storage/app/qa/inventory');
    const executablePath = [process.env.SHIFA_BROWSER_PATH, 'C:/Program Files/Google/Chrome/Application/chrome.exe',
        'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe'].find((file) => file && fs.existsSync(file));
    const browser = await chromium.launch({ headless: true, ...(executablePath ? { executablePath } : {}) });
    try {
        const page = await browser.newPage({ viewport: { width: 1440, height: 1000 } });
        const errors = [];
        const posts = [];
        page.on('pageerror', (error) => errors.push(error.message));
        await page.route('**/*', (route) => {
            const request = route.request();
            const url = new URL(request.url());
            if (request.method() === 'POST') {
                posts.push(url.pathname);
                return route.fulfill({ contentType: 'text/html', body: '<html><body>Hitung ulang dimulai</body></html>' });
            }
            if (request.isNavigationRequest()) return route.fulfill({ contentType: 'text/html', body: fs.readFileSync(path.join(fixture, 'opname.html'), 'utf8') });
            if (url.pathname.startsWith('/build/') || url.pathname.startsWith('/images/')) {
                const file = path.resolve(root, 'public', '.' + url.pathname);
                assert(file.startsWith(path.join(root, 'public') + path.sep));
                return route.fulfill({ contentType: file.endsWith('.js') ? 'text/javascript' : file.endsWith('.css') ? 'text/css' : 'image/png', body: fs.readFileSync(file) });
            }
            return route.abort();
        });
        await page.goto('http://127.0.0.1:8000/stock-opname?session=999998');
        await page.getByRole('button', { name: 'Mulai Hitung Ulang', exact: true }).click();
        await page.locator('.swal2-popup').waitFor();
        await page.getByRole('button', { name: 'Batal', exact: true }).click();
        await page.locator('.swal2-popup').waitFor({ state: 'detached' });
        assert.deepEqual(posts, []);
        await page.screenshot({ path: path.join(fixture, 'opname-recount-desktop.png'), fullPage: true });
        await page.setViewportSize({ width: 390, height: 844 });
        await page.waitForFunction(() => document.getElementById('sidebar').getBoundingClientRect().right <= 1);
        assert(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1));
        await page.screenshot({ path: path.join(fixture, 'opname-recount-mobile.png'), fullPage: true });
        await page.getByRole('button', { name: 'Mulai Hitung Ulang', exact: true }).click();
        await page.getByRole('button', { name: 'Ya, mulai hitung ulang', exact: true }).click();
        await page.waitForURL('**/stock-opname/999998/recount');
        assert.deepEqual(posts, ['/stock-opname/999998/recount']);
        assert.deepEqual(errors, []);
        console.log('PASS: opname recount SweetAlert cancel/confirm, isolated POST, desktop/mobile overflow.');
    } finally {
        await browser.close();
    }
})().catch((error) => { console.error(error); process.exitCode = 1; });
