const { chromium } = require('playwright');
const { spawn } = require('node:child_process');
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');

(async () => {
    const meta = JSON.parse(fs.readFileSync(process.env.FINAL_META, 'utf8'));
    const password = process.env.FINAL_PASSWORD;
    assert(meta.simulation && meta.phase === 'onboarding' && new URL(meta.base_url).hostname === '127.0.0.1' && password);
    const root = path.resolve(__dirname, '..');
    const output = process.env.FINAL_OUTPUT;
    const results = [];
    const errors = [];
    const server = spawn(process.env.FINAL_PHP, ['-S', `127.0.0.1:${meta.port}`, '-t', path.join(root, 'public'),
        path.join(root, 'vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php')],
    { cwd: path.join(root, 'public'), env: process.env, windowsHide: true, stdio: 'ignore' });
    let browser;
    let page;
    const ready = async () => { await page.waitForLoadState('networkidle'); await page.evaluate(() => document.fonts.ready); };
    const check = async (name, action) => {
        const row = { name, status: 'Gagal' };
        results.push(row);
        await action();
        assert.deepEqual(errors, []);
        row.status = 'Lulus';
        console.log('PASS '+name);
    };
    const login = async (username) => {
        const response = await page.goto(meta.base_url+'/login');
        assert(response.ok(), 'Login HTTP '+response.status());
        await page.getByLabel('Username', { exact: true }).fill(username);
        await page.getByLabel('Password', { exact: true }).fill(password);
        await page.getByRole('button', { name: 'Masuk', exact: true }).click();
        await page.waitForURL('**/dashboard');
        await ready();
    };
    const tour = () => page.locator('.shifa-tour');
    const assertPopoverFits = async () => {
        const viewport = page.viewportSize();
        const rect = await tour().boundingBox();
        assert(rect && rect.x >= 0 && rect.y >= 0 && rect.x + rect.width <= viewport.width + 1
            && rect.y + rect.height <= viewport.height + 1, 'Popover terpotong: '+JSON.stringify(rect));
        for (const button of await tour().locator('button:visible').all()) {
            const bounds = await button.boundingBox();
            assert(bounds.x >= rect.x && bounds.y >= rect.y && bounds.x + bounds.width <= rect.x + rect.width + 1
                && bounds.y + bounds.height <= rect.y + rect.height + 1, 'Tombol keluar popover');
        }
    };
    const assertCenteredIntro = async () => {
        await tour().getByText('Selamat datang di ShifaStock', { exact: true }).waitFor();
        await assertPopoverFits();
        const rect = await tour().boundingBox();
        const viewport = page.viewportSize();
        assert(Math.abs(rect.x + rect.width / 2 - viewport.width / 2) < 3
            && Math.abs(rect.y + rect.height / 2 - viewport.height / 2) < 3, 'Pembuka tidak di tengah: '+JSON.stringify(rect));
        assert.equal(await page.locator('[data-tour-main].driver-active-element').count(), 0, 'Seluruh dashboard masih menjadi target');
    };
    const next = () => tour().getByRole('button', { name: 'Selanjutnya', exact: true }).click();
    const skip = async () => {
        await tour().getByRole('button', { name: 'Lewati', exact: true }).click();
        await tour().waitFor({ state: 'detached' });
    };
    const capture = async (name) => {
        assert(!(await page.locator('body').innerText()).includes(password));
        await page.screenshot({ path: path.join(output, name) });
    };
    try {
        for (let i = 0; ; i++) {
            try { if ((await fetch(meta.base_url+'/login')).ok) break; } catch {}
            if (i > 100) throw new Error('Server lokal tidak siap');
            await new Promise((resolve) => setTimeout(resolve, 200));
        }
        browser = await chromium.launch({ headless: true, executablePath: 'C:/Program Files/Google/Chrome/Application/chrome.exe' });
        const makePage = async (viewport) => {
            const context = await browser.newContext({ viewport, locale: 'id-ID', timezoneId: 'Asia/Jakarta', reducedMotion: 'reduce' });
            const result = await context.newPage();
            result.on('pageerror', (error) => errors.push(error.message));
            return result;
        };
        page = await makePage({ width: 1440, height: 900 });
        await check('Login pertama menampilkan spotlight dan navigasi kembali/selanjutnya', async () => {
            await login('petugas_simulasi');
            await tour().waitFor();
            assert(await page.locator('.driver-overlay').count());
            assert((await tour().innerText()).includes('Selamat datang'));
            await assertCenteredIntro();
            await capture('Panduan_Pembuka_Desktop.png');
            await next();
            await next();
            await tour().getByRole('button', { name: 'Kembali', exact: true }).click();
            assert((await tour().innerText()).includes('Dashboard'));
            await capture('Panduan_Desktop.png');
        });
        await check('Lewati tersimpan di akun lintas sesi browser', async () => {
            await skip();
            const previous = page;
            page = await makePage({ width: 1440, height: 900 });
            await login('petugas_simulasi');
            assert.equal(await tour().count(), 0);
            await previous.context().close();
        });
        await check('Dashboard tidak memiliki mode dummy dan memakai stok fixture database', async () => {
            assert.equal(await page.getByText('Data Demo', { exact: true }).count(), 0);
            assert((await page.locator('body').innerText()).includes('Obat Simulasi Final'));
            await capture('Dashboard_Database.png');
        });
        await check('Buka ulang dan selesaikan panduan', async () => {
            await page.getByRole('button', { name: 'Buka panduan', exact: true }).click();
            for (let i = 0; i < 25; i++) {
                if (await tour().getByRole('button', { name: 'Selesai', exact: true }).count()) break;
                await assertPopoverFits();
                await next();
            }
            await tour().getByRole('button', { name: 'Selesai', exact: true }).click();
            await tour().waitFor({ state: 'detached' });
        });
        for (const username of ['admin_simulasi', 'apoteker_simulasi', 'guest_simulasi']) {
            await check('Panduan sesuai hak akses '+username, async () => {
                page = await makePage({ width: 1440, height: 900 });
                await login(username);
                await assertCenteredIntro();
                const config = await page.locator('[data-onboarding-config]').textContent();
                const steps = JSON.parse(config).steps;
                assert.equal(steps.some((step) => step.title === 'Pengguna dan hak akses'), username === 'admin_simulasi');
                if (username === 'guest_simulasi') assert(config.includes('hanya dapat melihat'));
                if (username === 'admin_simulasi') {
                    await next(); await next();
                    assert((await tour().innerText()).includes('Pengguna dan hak akses'));
                }
                await skip();
            });
        }
        await check('Mobile: spotlight sidebar dan seluruh tombol muat dalam viewport', async () => {
            page = await makePage({ width: 390, height: 844 });
            await login('petugas_simulasi');
            await page.getByRole('button', { name: 'Buka panduan', exact: true }).click();
            await assertCenteredIntro();
            await capture('Panduan_Pembuka_Mobile.png');
            await next();
            assert.equal(await page.locator('#sidebar-toggle').getAttribute('aria-expanded'), 'true');
            for (let i = 0; i < 25; i++) {
                await assertPopoverFits();
                if (i === 2) await capture('Panduan_Mobile.png');
                if (await tour().getByRole('button', { name: 'Selesai', exact: true }).count()) break;
                await next();
            }
            await skip();
            assert.equal(await page.locator('#sidebar-toggle').getAttribute('aria-expanded'), 'false');
        });
        await check('Pembuka tetap di tengah saat resize, kembali dari menu, dan layar pendek', async () => {
            for (const viewport of [{ width: 1623, height: 853 }, { width: 1280, height: 720 },
                { width: 320, height: 568 }, { width: 844, height: 390 }]) {
                await page.setViewportSize(viewport);
                await page.getByRole('button', { name: 'Buka panduan', exact: true }).click();
                await assertCenteredIntro();
                await next();
                await assertPopoverFits();
                await tour().getByRole('button', { name: 'Kembali', exact: true }).click();
                await assertCenteredIntro();
                await page.setViewportSize({ width: viewport.width + 16, height: viewport.height + 16 });
                await page.waitForTimeout(100);
                await assertCenteredIntro();
                if (viewport.width === 1623) await capture('Panduan_Pembuka_Layar_Pengguna.png');
                await skip();
            }
        });
        console.log(`${results.length}/${results.length} browser checks passed.`);
    } catch (error) {
        const message = error.message.replaceAll(password, '[disembunyikan]');
        results.at(-1) && (results.at(-1).error = message);
        console.error(message);
        if (page) await capture('Kendala_Browser.png').catch(() => {});
        process.exitCode = 1;
    } finally {
        fs.writeFileSync(path.join(output, 'results.json'), JSON.stringify({ tested_at: new Date().toISOString(), commit: meta.commit,
            working_tree: true, simulation: true, results }, null, 2));
        await browser?.close();
        server.kill();
    }
})();
