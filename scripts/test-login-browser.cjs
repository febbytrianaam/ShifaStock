const { chromium } = require('playwright');
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');

(async () => {
    // Exercise only the unauthenticated local UAT form; never submit credentials.
    const url = 'http://127.0.0.1:8010/login';
    const output = path.resolve(__dirname, '../storage/app/qa/login-visibility');
    fs.mkdirSync(output, { recursive: true });
    const browser = await chromium.launch({ headless: true, executablePath: 'C:/Program Files/Google/Chrome/Application/chrome.exe' });
    const rows = [];
    try {
        for (const viewport of [{ width: 1440, height: 900 }, { width: 390, height: 844 }]) {
            const page = await browser.newPage({ viewport });
            const errors = [];
            let posts = 0;
            page.on('pageerror', (error) => errors.push(error.message));
            page.on('request', (request) => { if (request.method() === 'POST') posts++; });
            const response = await page.goto(url);
            assert.equal(response.status(), 200);
            await page.locator('[data-uat-notice]').waitFor();
            const input = page.getByLabel('Password', { exact: true });
            const toggle = page.getByRole('button', { name: 'Tampilkan password', exact: true });
            await toggle.waitFor();
            await page.evaluate(() => document.fonts.ready);
            assert.equal(await input.getAttribute('type'), 'password');
            assert.equal(await toggle.getAttribute('type'), 'button');
            assert.equal(await toggle.locator('svg').count(), 1);
            const original = await input.boundingBox();
            // A field-only sample, not a stored account password.
            const sample = 'contoh-visibility';
            await input.fill(sample);
            await toggle.click();
            assert.equal(await input.getAttribute('type'), 'text');
            assert.equal(await input.inputValue(), sample);
            const hide = page.getByRole('button', { name: 'Sembunyikan password', exact: true });
            assert.equal(await hide.getAttribute('aria-pressed'), 'true');
            await hide.focus();
            await page.keyboard.press('Space');
            assert.equal(await input.getAttribute('type'), 'password');
            assert.equal(await input.inputValue(), sample);
            assert.equal(await toggle.getAttribute('aria-pressed'), 'false');
            assert.deepEqual(await input.boundingBox(), original);
            const field = await input.boundingBox();
            const button = await toggle.boundingBox();
            assert(button.x >= field.x && button.x + button.width <= field.x + field.width + 1);
            assert(button.y >= field.y && button.y + button.height <= field.y + field.height + 1);
            assert(!(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth)));
            assert.equal(posts, 0, 'Eye toggle submitted the login form');
            assert.deepEqual(errors, []);
            await input.fill('');
            await page.screenshot({ path: path.join(output, `Login_Eye_${viewport.width}.png`) });
            rows.push({ viewport, status: 'Lulus', checks: 'Masked default, reveal/hide, unchanged value, keyboard, Lucide icon, stable layout, no POST' });
            console.log(`PASS login eye ${viewport.width}x${viewport.height}`);
            await page.close();
        }
        const page = await browser.newPage({ javaScriptEnabled: false });
        assert.equal((await page.goto(url)).status(), 200);
        assert.equal(await page.locator('[data-password-toggle]').isVisible(), false);
        assert.equal(await page.getByLabel('Password', { exact: true }).getAttribute('type'), 'password');
        await page.getByRole('button', { name: 'Masuk', exact: true }).waitFor();
        rows.push({ name: 'Without JavaScript: password remains masked and login form usable', status: 'Lulus' });
        console.log('PASS login without JavaScript');
        await page.close();
    } finally {
        fs.writeFileSync(path.join(output, 'results.json'), JSON.stringify({ tested_at: new Date().toISOString(),
            environment: 'Local UAT login only; no credentials submitted or account changes', rows }, null, 2));
        await browser.close();
    }
})().catch(() => { console.error('Login visibility browser check failed.'); process.exitCode = 1; });
