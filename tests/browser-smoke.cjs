// Run with Playwright available in NODE_PATH (or installed in the test environment).
const { chromium } = require('playwright');

(async () => {
    const browser = await chromium.launch({
        headless: true,
        ...(process.env.SHIFA_BROWSER_PATH ? { executablePath: process.env.SHIFA_BROWSER_PATH } : {}),
    });
    const page = await browser.newPage({ viewport: { width: 1440, height: 1050 } });
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));

    await page.goto('http://127.0.0.1:8000/login');
    await page.getByLabel('Username', { exact: true }).fill('kepala');
    await page.getByLabel('Password', { exact: true }).fill('123');
    await Promise.all([
        page.waitForURL('**/dashboard'),
        page.getByRole('button', { name: 'Masuk', exact: true }).click(),
    ]);
    await page.locator('#stockChart').waitFor();

    const liveRoutes = [
        'obat',
        'obat-masuk',
        'transfer',
        'pengecekan-malam',
        'riwayat-stok',
        'stock-opname',
        'kekurangan-permintaan',
        'kedaluwarsa',
        'laporan',
    ];

    for (const route of liveRoutes) {
        const response = await page.goto(`http://127.0.0.1:8000/${route}`);
        if (response.status() !== 200) throw new Error(`Route failed: ${route}`);
        const body = await page.locator('body').innerText();
        if (body.includes('PRATINJAU UI') || body.includes('data simulasi')) {
            throw new Error(`Placeholder copy is still exposed on: ${route}`);
        }
    }

    await page.goto('http://127.0.0.1:8000/obat');
    const rowsBeforeSearch = await page.locator('tbody tr').count();
    if (!rowsBeforeSearch) throw new Error('Database medicines are missing.');
    await page.locator('#search').fill('Amoxicillin');
    await page.waitForTimeout(400);
    if (!(await page.locator('tbody').innerText()).toLowerCase().includes('amoxicillin')) {
        throw new Error('Medicine search failed.');
    }

    const checkResponse = await page.goto('http://127.0.0.1:8000/pengecekan-malam/1');
    if (checkResponse.status() === 200) {
        const position = await page.locator('thead').first().evaluate(node => getComputedStyle(node).position);
        if (position !== 'sticky') throw new Error('Daily-check table header is not sticky.');
    }

    for (const route of ['obat-keluar', 'kartu-stok', 'preview-dokumen/kartu-stok']) {
        const response = await page.goto(`http://127.0.0.1:8000/${route}`);
        if (response.status() !== 404) throw new Error(`Legacy route is still exposed: ${route}`);
    }

    await page.goto('http://127.0.0.1:8000/dashboard');
    await page.setViewportSize({ width: 390, height: 844 });
    const overflow = await page.evaluate(() => document.documentElement.scrollWidth > innerWidth);
    if (overflow) throw new Error('Dashboard overflows on mobile.');

    if (errors.length) throw new Error(errors.join('\n'));
    console.log('Browser checks passed: live inventory pages, medicine search, sticky daily-check header, legacy routes, mobile layout, and no JS errors.');
    await browser.close();
})().catch(error => {
    console.error(error);
    process.exit(1);
});
