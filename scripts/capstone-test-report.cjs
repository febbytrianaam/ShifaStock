const { chromium } = require('playwright');
const fs = require('node:fs');
const path = require('node:path');
const crypto = require('node:crypto');
const assert = require('node:assert/strict');
const { pathToFileURL } = require('node:url');

(async () => {
    const output = path.resolve(__dirname, '../Docs/Capstone_Screenshots');
    const evidence = path.join(output, 'Bukti_Pengujian');
    const manifest = JSON.parse(fs.readFileSync(path.join(output, 'manifest.json'), 'utf8'));
    const summary = JSON.parse(fs.readFileSync(path.join(evidence, 'Ringkasan_Tabel.json'), 'utf8'));
    const executablePath = [process.env.SHIFA_BROWSER_PATH, 'C:/Program Files/Google/Chrome/Application/chrome.exe',
        'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe'].find((file) => file && fs.existsSync(file));
    const browser = await chromium.launch({ headless: true, ...(executablePath ? { executablePath } : {}) });
    const target = path.join(output, 'Gambar_19_Hasil_Pengujian.png');
    const original = path.join(evidence, 'Screenshot_Keluaran_Asli.png');
    try {
        const page = await browser.newPage({ viewport: { width: 1440, height: 900 }, deviceScaleFactor: 1, locale: 'id-ID', timezoneId: 'Asia/Jakarta' });
        const errors = [];
        page.on('pageerror', (error) => errors.push(error.message));
        await page.goto(pathToFileURL(path.join(evidence, 'Laporan_Pengujian.html')).href);
        await page.evaluate(() => document.fonts.ready);
        assert.equal(await page.locator('tfoot td').nth(1).innerText(), String(summary.totals.tests));
        assert.equal(await page.locator('tfoot td').nth(2).innerText(), String(summary.totals.assertions));
        assert.equal(await page.locator('details tbody tr').count(), summary.totals.tests);
        assert(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth));
        assert((await page.locator('.source-note').boundingBox()).y + (await page.locator('.source-note').boundingBox()).height <= 900,
            'Catatan batasan tidak muat di screenshot.');
        assert.deepEqual(errors, []);
        if (!fs.existsSync(original)) fs.copyFileSync(target, original);
        await page.screenshot({ path: target, fullPage: false });
        const entry = manifest.screenshots.find((row) => row.number === 19);
        entry.role = 'Tidak berlaku (laporan dokumentasi hasil tes)';
        entry.description = 'Laporan tabel dari JUnit dan keluaran tes MySQL aktual; bukan halaman fitur aplikasi. Keluaran mentah dipertahankan.';
        entry.captured_at = new Date().toLocaleString('sv-SE', { timeZone: 'Asia/Jakarta' })+' WIB';
        entry.url = 'Bukti_Pengujian/Laporan_Pengujian.html (laporan dokumentasi)';
        entry.sha256 = crypto.createHash('sha256').update(fs.readFileSync(target)).digest('hex');
        fs.writeFileSync(path.join(output, 'manifest.json'), JSON.stringify(manifest, null, 2));
        console.log(`PASS: tabel sesuai JUnit, ${summary.totals.tests} detail tersedia, gambar 19 diperbarui; bukti mentah dipertahankan.`);
    } finally { await browser.close(); }
})().catch((error) => { console.error(error.message); process.exitCode = 1; });
