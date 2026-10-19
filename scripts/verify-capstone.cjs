const fs = require('node:fs');
const path = require('node:path');
const crypto = require('node:crypto');
const assert = require('node:assert/strict');
const sharp = require('sharp');

(async () => {
    const root = path.resolve(__dirname, '..');
    const output = path.join(root, 'Docs/Capstone_Screenshots');
    const manifest = JSON.parse(fs.readFileSync(path.join(output, 'manifest.json'), 'utf8'));
    const checked = [];
    for (const item of manifest.screenshots) {
        if (item.status !== 'berhasil') continue;
        const image = fs.readFileSync(path.join(output, item.file));
        const dimensions = await sharp(image).metadata();
        const statistics = await sharp(image).stats();
        assert.equal(dimensions.width, 1440, item.file);
        assert.equal(dimensions.height, 900, item.file);
        assert(statistics.channels.some((channel) => channel.stdev > 8), 'Gambar kosong: '+item.file);
        assert.equal(crypto.createHash('sha256').update(image).digest('hex'), item.sha256, 'Gambar berubah setelah diambil');
        checked.push({ file: item.file, width: dimensions.width, height: dimensions.height, sha256: item.sha256, nonblank: true });
    }
    const main = manifest.screenshots.filter((item) => !item.extra);
    assert.equal(main.length, 19);
    const diagnostic = [];
    const diagnosticFolder = path.join(output, 'Bukti_Kendala');
    if (fs.existsSync(diagnosticFolder)) for (const file of fs.readdirSync(diagnosticFolder).filter((name) => name.endsWith('.png'))) {
        const bytes = fs.readFileSync(path.join(diagnosticFolder, file));
        const dimensions = await sharp(bytes).metadata();
        const statistics = await sharp(bytes).stats();
        assert.equal(dimensions.width, 1440);
        assert.equal(dimensions.height, 900);
        assert(statistics.channels.some((channel) => channel.stdev > 8));
        diagnostic.push({ file: 'Bukti_Kendala/'+file, width: 1440, height: 900,
            sha256: crypto.createHash('sha256').update(bytes).digest('hex'), nonblank: true, purpose: 'Bukti kendala, bukan keberhasilan fitur' });
    }
    const proof = JSON.parse(fs.readFileSync(path.join(output, 'Bukti_Pengujian/hasil-eksekusi.json'), 'utf8'));
    assert(proof.commands.every((item) => item.exit_code === 0), 'Ada pengujian aktual yang gagal.');
    const textual = [path.join(output, 'README.md'), path.join(output, 'manifest.json'),
        ...fs.readdirSync(path.join(output, 'Bukti_Pengujian')).filter((file) => /\.(txt|xml|json|html)$/.test(file)).map((file) => path.join(output, 'Bukti_Pengujian', file))];
    for (const file of textual) {
        const text = fs.readFileSync(file, 'utf8');
        assert(!/-----BEGIN (?:RSA |EC |OPENSSH )?PRIVATE KEY-----|ghp_[A-Za-z0-9]{30,}|APP_KEY=base64:[A-Za-z0-9+/=]{20,}|\$2[aby]\$\d{2}\$[./A-Za-z0-9]{53}/.test(text), 'Pola rahasia pada '+path.basename(file));
    }
    fs.writeFileSync(path.join(output, 'Verifikasi_Teknis.json'), JSON.stringify({
        commit: manifest.commit, verified_at: new Date().toISOString(), main_success: main.filter((item) => item.status === 'berhasil').length,
        main_total: main.length, images: checked, diagnostic, actual_tests_exit_zero: true, text_secret_pattern_findings: 0,
        note: 'Pemeriksaan visual/manual tetap diperlukan; dimensi dan pola teks saja tidak membuktikan semua data aman.',
    }, null, 2));
    // Preview-only contact sheets are kept outside the deliverables. Originals remain unchanged.
    const previews = path.join(root, 'storage/app/qa/capstone-visual-review');
    fs.mkdirSync(previews, { recursive: true });
    for (let start = 0; start < checked.length; start += 4) {
        const group = checked.slice(start, start + 4);
        const composite = [];
        for (let index = 0; index < group.length; index++) composite.push({
            input: await sharp(path.join(output, group[index].file)).resize(720, 450).toBuffer(),
            left: (index % 2) * 720, top: Math.floor(index / 2) * 450,
        });
        const target = path.join(previews, 'kontak-'+(Math.floor(start / 4) + 1)+'.png');
        await sharp({ create: { width: 1440, height: 900, channels: 3, background: '#ffffff' } }).composite(composite).png().toFile(target);
        console.log(path.basename(target)+': '+group.map((item) => item.file).join(', '));
    }
    console.log('Verified '+checked.length+' original PNGs and actual test artifacts.');
})().catch((error) => { console.error(error.message); process.exitCode = 1; });
