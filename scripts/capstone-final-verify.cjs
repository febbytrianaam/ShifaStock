const fs = require('node:fs');
const path = require('node:path');
const crypto = require('node:crypto');
const assert = require('node:assert/strict');
const sharp = require('sharp');

(async () => {
    const root = path.resolve(__dirname, '..');
    const output = path.join(root, 'Docs/Capstone_Testing_Final');
    const proof = path.join(output, 'Bukti_Pengujian');
    const meta = JSON.parse(fs.readFileSync(path.join(proof, 'Eksekusi_Terbaru.json'), 'utf8'));
    const browser = JSON.parse(fs.readFileSync(path.join(proof, 'Browser_final.json'), 'utf8'));
    assert(meta.commands.every((row) => row.exit_code === 0));
    assert(browser.results.length >= 13 && browser.results.every((row) => row.status === 'Lulus'));
    assert.equal(browser.server_stopped, true);
    const hash = (file) => crypto.createHash('sha256').update(fs.readFileSync(file)).digest('hex');
    for (const file of ['resources/js/app.js', 'tests/Feature/CapstoneFunctionalTest.php']) {
        assert.equal(hash(path.join(root, file)), meta.source_sha256[file], 'Source aplikasi/tes berubah sesudah PHPUnit: '+file);
    }
    const reconciliation = JSON.parse(fs.readFileSync(path.join(proof, 'Browser_Rekonsiliasi.json'), 'utf8'));
    assert.equal(reconciliation.status, 'Lulus');
    assert.equal(reconciliation.reconciliation.mismatched_pairs, 0);
    const images = [];
    for (const file of fs.readdirSync(proof).filter((file) => file.endsWith('.png'))) {
        const full = path.join(proof, file);
        const dimensions = await sharp(full).metadata();
        assert.equal(dimensions.width, 1440);
        assert.equal(dimensions.height, 900);
        // Laravel's expected 403 page is mostly white; inspect its actual message region.
        const stats = file === 'Browser_07_Akses_Ditolak.png'
            ? await sharp(await sharp(full).extract({ left: 480, top: 360, width: 560, height: 180 }).toBuffer()).stats()
            : await sharp(full).stats();
        assert(stats.channels.some((channel) => channel.stdev > 8), 'Gambar kosong: '+file);
        const row = browser.results.find((row) => row.evidence === file);
        if (row) assert.equal(row.sha256, hash(full));
        images.push({ file, width: dimensions.width, height: dimensions.height, sha256: hash(full), nonblank: true,
            expected_access_denial: file === 'Browser_07_Akses_Ditolak.png' });
    }
    const allFiles = (dir) => fs.readdirSync(dir, { withFileTypes: true }).flatMap((entry) => entry.isDirectory() ? allFiles(path.join(dir, entry.name)) : [path.join(dir, entry.name)]);
    const secrets = /\$2[aby]\$\d{2}\$[./A-Za-z0-9]{53}|base64:[A-Za-z0-9+/]{40,}={0,2}|(?:DB_PASSWORD|APP_KEY)\s*[=:]\s*(?!\[disembunyikan\])[^\s"']+/;
    for (const file of allFiles(output).filter((file) => /\.(md|json|txt|xml|html)$/.test(file))) {
        const text = fs.readFileSync(file, 'utf8');
        assert(!secrets.test(text), 'Pola sensitif ditemukan: '+path.relative(output, file));
        if (file.endsWith('.md')) for (const match of text.matchAll(/\]\(([^)]+)\)/g)) {
            if (!/^(https?:|#)/.test(match[1])) assert(fs.existsSync(path.resolve(path.dirname(file), match[1])), 'Tautan tidak tersedia: '+match[1]);
        }
    }
    const sources = {};
    for (const file of ['resources/js/app.js', 'tests/Feature/CapstoneFunctionalTest.php', 'scripts/capstone-final-tests.php',
        'scripts/capstone-final-browser.php', 'scripts/capstone-final-browser.cjs', 'scripts/capstone-final-docs.php', 'scripts/capstone-final-verify.cjs']) sources[file] = hash(path.join(root, file));
    fs.writeFileSync(path.join(proof, 'Verifikasi_Akhir.json'), JSON.stringify({ verified_at: new Date().toISOString(), commit_base: meta.commit,
        working_tree_modified: true, sources_sha256: sources, all_commands_exit_zero: true, browser_cases_passed: browser.results.length,
        images, text_secret_pattern_findings: 0, local_markdown_links_valid: true, automatic_checks_only: true }, null, 2));
    console.log(`PASS: ${images.length} PNG 1440x900 nonkosong; hash, ${browser.results.length} kasus browser, tautan, dan pola sensitif diperiksa.`);
})().catch((error) => { console.error(error.message); process.exitCode = 1; });
