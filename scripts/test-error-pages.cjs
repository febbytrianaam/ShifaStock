const { chromium } = require('playwright');
const { spawn } = require('node:child_process');
const { once } = require('node:events');
const fs = require('node:fs');
const path = require('node:path');
const net = require('node:net');
const crypto = require('node:crypto');
const assert = require('node:assert/strict');

(async () => {
    const root = path.resolve(__dirname, '..');
    const output = path.join(root, 'storage/app/qa/error-pages');
    fs.mkdirSync(path.join(output, 'views'), { recursive: true });
    const probe = net.createServer();
    probe.listen(0, '127.0.0.1');
    await once(probe, 'listening');
    const port = probe.address().port;
    await new Promise((resolve) => probe.close(resolve));
    const base = `http://127.0.0.1:${port}`;
    const env = {
        ...process.env, APP_ENV: 'testing', APP_DEBUG: 'false', APP_URL: base,
        APP_KEY: 'base64:'+crypto.randomBytes(32).toString('base64'),
        DB_CONNECTION: 'sqlite', DB_DATABASE: ':memory:', DB_URL: '',
        SESSION_DRIVER: 'array', CACHE_STORE: 'array', LOG_CHANNEL: 'stderr',
        APP_CONFIG_CACHE: path.join(output, 'unused-config.php'),
        APP_ROUTES_CACHE: path.join(output, 'unused-routes.php'),
        APP_EVENTS_CACHE: path.join(output, 'unused-events.php'),
        VIEW_COMPILED_PATH: path.join(output, 'views'),
    };
    const log = fs.openSync(path.join(output, 'server.log'), 'w');
    const server = spawn(process.env.ERROR_TEST_PHP || 'php', ['-S', `127.0.0.1:${port}`, '-t', path.join(root, 'public'),
        path.join(root, 'tests/Browser/error-pages-router.php')],
    { cwd: root, env, windowsHide: true, stdio: ['ignore', log, log] });
    fs.closeSync(log);
    const rows = [];
    let browser;
    try {
        for (let attempt = 0; ; attempt++) {
            try { if ((await fetch(base+'/__qa/missing')).status === 404) break; } catch {}
            if (attempt > 100 || server.exitCode !== null) throw new Error('Isolated error-page server did not start.');
            await new Promise((resolve) => setTimeout(resolve, 150));
        }
        browser = await chromium.launch({ headless: true, executablePath: process.env.CHROME_PATH || 'C:/Program Files/Google/Chrome/Application/chrome.exe' });
        for (const viewport of [{ width: 1440, height: 900 }, { width: 390, height: 844 }]) {
            const page = await browser.newPage({ viewport, locale: 'id-ID' });
            const errors = [];
            page.on('pageerror', (error) => errors.push(error.message));
            for (const [code, title] of [[403, 'Akses belum tersedia untuk akun Anda'], [404, 'Halaman yang dicari tidak ditemukan'], [500, 'Permintaan belum dapat diproses']]) {
                const row = { code, viewport, status: 'Gagal' };
                rows.push(row);
                const response = await page.goto(base+(code === 404 ? '/__qa/missing' : `/__qa/error/${code}`));
                assert.equal(response.status(), code);
                await page.getByRole('heading', { name: title, exact: true }).waitFor();
                await page.evaluate(() => document.fonts.ready);
                const body = await page.locator('body').innerText();
                assert(!body.includes('QA_PRIVATE_EXCEPTION_MARKER'));
                assert(!body.includes(root));
                assert.equal(await page.locator('script, link[rel=stylesheet]').count(), 0);
                assert.deepEqual(errors, []);
                assert(await page.locator('img').evaluate((image) => image.complete && image.naturalWidth > 0));
                const geometry = await page.evaluate(() => {
                    const targets = [...document.querySelectorAll('h1, .description, .guidance, .button, .help')];
                    return {
                        overflow: document.documentElement.scrollWidth > innerWidth,
                        withinViewport: targets.every((element) => {
                            const r = element.getBoundingClientRect();
                            return r.left >= 0 && r.right <= innerWidth && r.top >= 0 && r.bottom <= innerHeight;
                        }),
                    };
                });
                assert(!geometry.overflow && geometry.withinViewport, 'Text or controls exceed viewport.');
                const link = page.getByRole('link', { name: 'Kembali ke aplikasi', exact: true });
                assert.equal(await link.getAttribute('href'), base);
                await link.focus();
                assert(await link.evaluate((element) => element === document.activeElement));
                row.evidence = `Error_${code}_${viewport.width}.png`;
                await page.screenshot({ path: path.join(output, row.evidence) });
                row.status = 'Lulus';
                console.log(`PASS ${code} ${viewport.width}x${viewport.height}`);
            }
            await page.goto(base+'/__qa/missing');
            await page.getByRole('link', { name: 'Kembali ke aplikasi' }).click();
            await page.waitForURL(base+'/login');
            await page.getByLabel('Username', { exact: true }).waitFor();
            await page.close();
        }
        // An error after a write must offer safe GET navigation, not POST resubmission.
        const context = await browser.newContext();
        const response = await context.request.post(base+'/__qa/error/500', { form: { simulation: 'true' } });
        assert.equal(response.status(), 500);
        const html = await response.text();
        assert(html.includes('agar transaksi tidak tercatat dua kali'));
        assert(!/<form|location\.reload|history\.back/i.test(html));
        rows.push({ name: 'POST 500: navigation does not resend the transaction', status: 'Lulus' });
        await context.close();
        console.log('PASS safe navigation after POST 500');
    } catch (error) {
        console.error(error.message);
        process.exitCode = 1;
    } finally {
        fs.writeFileSync(path.join(output, 'results.json'), JSON.stringify({ tested_at: new Date().toISOString(),
            environment: 'Isolated testing process, SQLite :memory:, no clinic or UAT data writes',
            rows, passed: rows.filter((row) => row.status === 'Lulus').length }, null, 2));
        await browser?.close();
        if (server.exitCode === null) {
            const ended = once(server, 'exit');
            server.kill();
            await ended;
        }
    }
})().catch((error) => { console.error(error.message); process.exitCode = 1; });
