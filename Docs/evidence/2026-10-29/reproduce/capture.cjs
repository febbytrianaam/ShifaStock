const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');
const assert = require('assert/strict');
const crypto = require('crypto');

const output = process.env.EVIDENCE_OUTPUT;
const meta = JSON.parse(fs.readFileSync(process.env.EVIDENCE_META, 'utf8'));
const password = process.env.EVIDENCE_PASSWORD;
assert.equal(new URL(meta.base_url).hostname, '127.0.0.1');
assert.ok(password);
const results = [];
const images = [];
let browser;
let errors = [];
const ready = async page => {
    await page.waitForLoadState('networkidle');
    await page.evaluate(() => document.fonts.ready);
};
async function open(page, pathname) {
    const response = await page.goto(meta.base_url + pathname);
    assert.ok(response.ok(), `HTTP ${response.status()} on ${pathname}`);
    await ready(page);
}
async function contextFor(role) {
    const context = await browser.newContext({ viewport: { width: 1440, height: 900 }, deviceScaleFactor: 1,
        locale: 'id-ID', timezoneId: 'Asia/Jakarta', reducedMotion: 'reduce' });
    const page = await context.newPage();
    page.on('pageerror', error => errors.push(error.message));
    page.on('dialog', dialog => dialog.type() === 'beforeunload' ? dialog.accept() : dialog.dismiss());
    await open(page, '/login');
    await page.getByLabel('Username', { exact: true }).fill(`${role}_simulasi`);
    await page.getByLabel('Password', { exact: true }).fill(password);
    await page.getByRole('button', { name: 'Masuk', exact: true }).click();
    await page.waitForURL('**/dashboard');
    await ready(page);
    const skip = page.getByRole('button', { name: 'Lewati', exact: true });
    if (await skip.isVisible()) {
        await skip.click();
        await page.locator('.driver-overlay').waitFor({ state: 'hidden' });
        await ready(page);
    }
    return { context, page };
}
async function pick(page, id, query, label) {
    const wrapper = page.locator(id).locator('..');
    await wrapper.locator('input[role="combobox"]').fill(query);
    const options = wrapper.locator('.searchable-select-option:visible');
    const option = label ? options.filter({ hasText: label }).first() : options.first();
    await option.click();
}
async function capture(page, file, id, role, description) {
    await ready(page);
    assert.deepEqual(errors, [], 'No unhandled browser JavaScript errors');
    const text = await page.locator('body').innerText();
    assert.ok(!text.includes(password), 'No password in screenshot text');
    assert.ok(!/Internal Server Error|SQLSTATE\[|Whoops,|Page Expired/.test(text), 'No error page');
    assert.equal(await page.locator('input[type=password]:visible').count(), 0);
    await page.screenshot({ path: path.join(output, file), fullPage: false });
    images.push({ id, file, role, description, status: 'berhasil', source: 'aplikasi berjalan',
        captured_at: new Date().toISOString(), commit: meta.commit, simulation: true,
        page: new URL(page.url()).pathname + new URL(page.url()).search,
        sha256: crypto.createHash('sha256').update(fs.readFileSync(path.join(output, file))).digest('hex') });
}
async function group(id, run) {
    const start = new Date().toISOString();
    try {
        await run();
        results.push({ id, status: 'Lulus', method: 'Playwright browser', started_at: start, completed_at: new Date().toISOString() });
        console.log(`PASS ${id}`);
    } catch (error) {
        results.push({ id, status: 'Gagal', method: 'Playwright browser', started_at: start,
            message: error.message.replaceAll(password, '[redacted]') });
        throw error;
    }
}
(async () => {
    browser = await chromium.launch({ executablePath: 'C:/Program Files/Google/Chrome/Application/chrome.exe', headless: true });
    const { page } = await contextFor('petugas');
    await group('SS-01', async () => {
        await open(page, '/transfer');
        await page.locator('#transfer-source').selectOption(String(meta.warehouse));
        await page.locator('#transfer-target').selectOption(String(meta.pharmacy));
        await pick(page, '#transfer-medicine', 'SIM-001');
        await page.locator('#transfer-quantity').fill('12');
        await pick(page, '#transfer-medicine-1', 'SIM-002');
        await page.locator('#transfer-quantity-1').fill('8');
        await page.locator('#transfer-notes').fill('Simulasi transfer dua obat untuk dokumentasi akademik.');
        const reference = await page.locator('#transfer-reference').inputValue();
        await capture(page, 'screenshots/SS-01-transfer-multi-item.png', 'SS-01', 'Petugas Simulasi', 'Form transfer dua obat dengan alokasi batch otomatis FEFO, sebelum disimpan.');
        await page.getByRole('button', { name: 'Simpan Transfer', exact: true }).click();
        await page.getByRole('button', { name: 'Ya, simpan transfer', exact: true }).click();
        await ready(page);
        assert.match(await page.locator('body').innerText(), /Transfer diposting/);
        const rows = page.locator('tbody tr').filter({ hasText: reference });
        assert.equal(await rows.count(), 2);
        assert.match(await rows.filter({ hasText: 'Paracetamol' }).innerText(), /SIM-BATCH-SAMA/);
        assert.match(await rows.filter({ hasText: 'Cetirizine' }).innerText(), /SIM-BATCH-2/);
        await rows.last().scrollIntoViewIfNeeded();
        await capture(page, 'supporting/SS-01-hasil-transfer.png', 'SS-01-result', 'Petugas Simulasi', 'Dua item transfer tersimpan dengan satu referensi dan tercatat pada riwayat.');
    });
    await group('SS-05', async () => {
        await open(page, '/retur-pbf');
        await page.locator('#return-supplier').fill('PBF Simulasi');
        await pick(page, '#return-medicine', 'SIM-001');
        await page.locator('#return-location').selectOption(String(meta.warehouse));
        await pick(page, '#return-batch', 'SIM-BATCH-SAMA');
        await page.locator('#return-quantity').fill('3');
        await page.locator('#return-reason').fill('Simulasi retur 3 tablet.');
        await capture(page, 'supporting/SS-05-retur-pbf.png', 'SS-05-return', 'Petugas Simulasi', 'Form retur tiga tablet dari Gudang ke PBF Simulasi, sebelum disimpan.');
        await page.getByRole('button', { name: 'Simpan Retur & Kurangi Stok', exact: true }).click();
        await ready(page);
        assert.match(await page.locator('body').innerText(), /Retur.*diposting/i);
        await open(page, '/kondisi-khusus');
        await pick(page, '#adjust-medicine', 'SIM-001');
        await page.locator('#adjust-location').selectOption(String(meta.pharmacy));
        await pick(page, '#adjust-batch', 'SIM-BATCH-SAMA');
        await page.locator('#adjust-condition').selectOption('rusak');
        await page.locator('#adjust-quantity').fill('2');
        await page.locator('#adjust-notes').fill('Simulasi dua tablet rusak; bukan kejadian operasional klinik.');
        await capture(page, 'screenshots/SS-05-retur-kondisi-obat.png', 'SS-05', 'Petugas Simulasi', 'Form kondisi khusus obat rusak di Apotek, sebelum posting pengurangan dua tablet.');
        await page.locator('[data-adjust-save]').click();
        await ready(page);
        assert.match(await page.locator('body').innerText(), /Kondisi khusus diposting/);
        await page.locator('tbody tr').first().scrollIntoViewIfNeeded();
        await capture(page, 'supporting/SS-05-hasil-kondisi-khusus.png', 'SS-05-result', 'Petugas Simulasi', 'Riwayat posting obat rusak yang mengurangi dua tablet di Apotek.');
    });
    await group('SS-06', async () => {
        await open(page, '/kondisi-khusus');
        await pick(page, '#adjust-medicine', 'SIM-001');
        await page.locator('#adjust-location').selectOption(String(meta.warehouse));
        await pick(page, '#adjust-batch', 'SIM-BATCH-SAMA');
        assert.match(await page.locator('#adjust-batch option:checked').innerText(), /Gudang/);
        await page.locator('#adjust-location').selectOption(String(meta.pharmacy));
        await pick(page, '#adjust-batch', 'SIM-BATCH-SAMA');
        const label = await page.locator('#adjust-batch').locator('..').locator('input[role=combobox]').inputValue();
        const native = await page.locator('#adjust-batch option:checked').innerText();
        assert.match(label, /Apotek/);
        assert.match(native, /Apotek/);
        assert.ok(!label.includes('Gudang'));
        assert.match(label, /40/);
        await capture(page, 'screenshots/SS-06-dropdown-batch.png', 'SS-06', 'Petugas Simulasi', 'Batch bernomor sama mengikuti lokasi Apotek dengan sisa 40 tablet pada batch tersebut; diuji setelah berpindah dari Gudang.');
    });
    await group('SS-04', async () => {
        await open(page, '/kedaluwarsa?search=SIM-001&status=all');
        const text = await page.locator('body').innerText();
        for (const label of ['SIM-BATCH-SAMA', 'SIM-BATCH-LANJUT', 'Gudang', 'Apotek']) assert.ok(text.includes(label));
        // The actual view groups locations within one row per batch.
        assert.equal(await page.locator('tbody tr').count(), 2);
        for (const batch of ['SIM-BATCH-SAMA', 'SIM-BATCH-LANJUT']) {
            const row = await page.locator('tbody tr').filter({ hasText: batch }).innerText();
            assert.match(row, /Apotek/);
            assert.match(row, /Gudang/);
        }
        await capture(page, 'screenshots/SS-04-stok-per-batch.png', 'SS-04', 'Petugas Simulasi', 'Dua batch Paracetamol pada dua lokasi, beserta tanggal kedaluwarsa dan stok setelah transaksi.');
    });
    await group('SS-02', async () => {
        await open(page, '/pengecekan-malam');
        await page.locator('#location').selectOption(String(meta.pharmacy));
        await page.getByRole('button', { name: 'Buka / Mulai Pemeriksaan', exact: true }).click();
        await page.waitForURL(/\/pengecekan-malam\/\d+$/);
        await ready(page);
        const checkPath = new URL(page.url()).pathname;
        await page.getByLabel(`Jumlah ${meta.medicines[0].name}`, { exact: true }).fill('80');
        await page.getByLabel(`Jumlah ${meta.medicines[1].name}`, { exact: true }).fill('28');
        await page.getByRole('button', { name: 'Simpan Draf', exact: true }).click();
        await ready(page);
        assert.match(await page.locator('[data-nightly-progress-label]').innerText(), /2\s*\/\s*3/);
        await capture(page, 'supporting/SS-02-proses-petugas.png', 'SS-02-process', 'Petugas Simulasi', 'Petugas menyimpan dua dari tiga hasil hitung pada draf pemeriksaan.');
        const second = await contextFor('apoteker');
        await open(second.page, checkPath);
        await second.page.getByLabel(`Jumlah ${meta.medicines[2].name}`, { exact: true }).fill('15');
        await second.page.getByRole('button', { name: 'Simpan Draf', exact: true }).click();
        await ready(second.page);
        assert.match(await second.page.locator('[data-nightly-creator]').innerText(), /Petugas Simulasi/);
        assert.match(await second.page.locator('[data-nightly-last-worker]').innerText(), /Apoteker Simulasi/);
        assert.match(await second.page.locator('[data-nightly-progress-label]').innerText(), /3\s*\/\s*3/);
        for (let i = 0; i < 3; i++) assert.equal(await second.page.getByLabel(`Jumlah ${meta.medicines[i].name}`, { exact: true }).inputValue(), String([80, 28, 15][i]));
        await capture(second.page, 'screenshots/SS-02-pemeriksaan-harian.png', 'SS-02', 'Apoteker Simulasi', 'Apoteker melanjutkan draf buatan petugas; tiga hasil hitung tersimpan dan pembuat serta pelaksana terakhir tercatat.');
        await second.page.locator('[data-nightly-view=activity]').click();
        const activities = await second.page.locator('[data-nightly-activities]').innerText();
        assert.match(activities, /Petugas Simulasi/);
        assert.match(activities, /Apoteker Simulasi/);
        await capture(second.page, 'supporting/SS-02-aktivitas-bersama.png', 'SS-02-activity', 'Apoteker Simulasi', 'Jejak aktivitas menunjukkan petugas membuat/mengisi draf dan apoteker meneruskan pekerjaan.');
    });
})().catch(error => { console.error(error.message.replaceAll(password, '[redacted]')); process.exitCode = 1; })
    .finally(async () => {
        if (browser) await browser.close();
        fs.writeFileSync(path.join(output, 'testing', 'Feature_Capture.json'), JSON.stringify({ commit: meta.commit, simulation: true, groups: results, images }, null, 2));
    });
