const fs = require('fs');
const path = require('path');
const { execFileSync } = require('child_process');
const { chromium } = require('playwright');
const crypto = require('crypto');
const assert = require('assert/strict');
const out = path.dirname(__dirname);
const testing = path.join(out, 'testing');
const read = name => JSON.parse(fs.readFileSync(path.join(testing, name), 'utf8'));
const run = read('Run.json');
assert.equal(run.exit_code, 0, 'A successful real execution is required for the success reports');
const junit = JSON.parse(execFileSync('php', [path.join(__dirname, 'junit-summary.php')], { encoding: 'utf8' }));
const playwright = read('Playwright.json');
const features = read('Feature_Capture.json');
const escape = text => String(text).replace(/[&<>"']/g, ch => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[ch]));
const categories = [
    ['Pemeriksaan harian dan kolaborasi', /AddNightly|DailyBatch|Nightly|SelectedNightly|PartialNightly|SharedNightly/],
    ['Impor Excel Neoklinik', /IssueImport/],
    ['Transfer multi-item', /MultiTransfer/],
    ['Penerimaan dan posting', /ReceiptWorkflow/],
    ['Persediaan, mutasi, dan rekonsiliasi', /InventoryFlow|InventoryIntegration|InventoryReconciliation|InventoryUsability|StockUnitProtection/],
    ['Hak akses dan pengguna', /AdminUser|GuestRole/],
    ['Pengujian fungsional lintas modul', /CapstoneFunctional/],
    ['Stock opname dan audit', /OpnameAudit/],
    ['Dashboard dan panduan awal', /Dashboard/],
    ['Halaman error dan panduan pengeluaran', /ErrorPage|IssueGuidance/],
    ['Isolasi UAT dan pembersihan simulasi', /UatEnvironment|DemoInventoryCleanup/],
    ['Fondasi, tampilan, dan unit test lainnya', /.*/],
];
const grouped = categories.map(([name]) => ({ name, tests: 0, assertions: 0, failures: 0, errors: 0, skipped: 0 }));
for (const row of junit.classes) {
    const index = categories.findIndex(([, match]) => match.test(row.name));
    for (const field of ['tests','assertions','failures','errors','skipped']) grouped[index][field] += row[field];
}
assert.equal(grouped.reduce((sum,row) => sum + row.tests,0), junit.summary.tests);
const testTime = new Date(run.completed_at).toLocaleString('id-ID', { timeZone: 'Asia/Jakarta', dateStyle: 'long', timeStyle: 'short' });
const html = (title, subtitle, stats, headers, rows, source, note) => `<!doctype html><html lang="id"><meta charset="utf-8"><title>${escape(title)}</title><style>
*{box-sizing:border-box}body{margin:0;background:#f4f7fa;color:#1c2835;font:14px Arial,sans-serif;letter-spacing:0}.page{padding:34px 46px;width:1440px;height:900px}header{display:flex;justify-content:space-between;align-items:flex-start;border-bottom:2px solid #21749c;padding-bottom:18px}.brand{font-size:14px;color:#276c88;font-weight:700}h1{font-size:28px;margin:9px 0 7px}p{margin:5px 0;line-height:1.5}.meta{text-align:right;color:#526071;font-size:13px;line-height:1.7}.stats{display:flex;gap:0;margin:20px 0;border:1px solid #d9e1e7;background:white}.stat{flex:1;padding:13px 20px;border-right:1px solid #d9e1e7}.stat:last-child{border:0}.stat strong{display:block;font-size:27px;margin:5px 0;color:#126946}.stat span{font-size:12px;color:#5a6672}table{width:100%;border-collapse:collapse;background:white}th{text-align:left;background:#e9f0f5;color:#355263;font-size:12px;padding:10px 14px}td{padding:8px 14px;border-bottom:1px solid #e4e9ee;font-size:13px}th:first-child,td:first-child{width:42px;text-align:center}.pass{color:#126946;font-weight:bold}.footer{border-left:3px solid #21749c;margin-top:16px;padding:8px 14px;background:#eaf3f8;color:#405464;font-size:12px}.fine{font-size:12px;color:#607080;margin-top:12px}</style><main class="page"><header><div><div class="brand">SHIFASTOCK | BUKTI PENGUJIAN CAPSTONE</div><h1>${escape(title)}</h1><p>${escape(subtitle)}</p></div><div class="meta">${escape(testTime)} WIB<br>Commit ${escape(run.commit.slice(0,7))}<br>Database pengujian terpisah</div></header><div class="stats">${stats.map(([value,label])=>`<div class="stat"><strong>${escape(value)}</strong><span>${escape(label)}</span></div>`).join('')}</div><table><thead><tr>${headers.map(h=>`<th>${escape(h)}</th>`).join('')}</tr></thead><tbody>${rows.map((row,i)=>`<tr><td>${i+1}</td>${row.map((cell,j)=>`<td class="${cell === 'Lulus'?'pass':''}">${escape(cell)}</td>`).join('')}</tr>`).join('')}</tbody></table><div class="footer">${escape(note)}<br>Sumber aktual: ${escape(source)}</div><p class="fine">Laporan dokumentasi dari hasil eksekusi, bukan halaman fitur aplikasi. Data simulasi; bukan stok operasional klinik.</p></main></html>`;
const summary = { commit: run.commit, generated_at: new Date().toISOString(), source: 'eksekusi ulang pada sesi dokumentasi ini',
    phpunit: junit, grouped, playwright, feature_capture: features.groups, environment: { php: run.php, laravel: run.laravel, browser: 'Google Chrome headless / Playwright', viewport: run.viewport } };
fs.writeFileSync(path.join(testing, 'Summary.json'), JSON.stringify(summary,null,2));
const phpStatus = row => row.failures || row.errors ? 'Gagal' : row.skipped ? 'Ada dilewati' : 'Lulus';
const specs = [
    ['TEST-01-phpunit',html('Hasil Pengujian PHPUnit','Pengujian otomatis backend, otorisasi, dan aturan persediaan.',
        [[junit.summary.tests,'Tes dieksekusi'],[junit.summary.assertions.toLocaleString('id-ID'),'Assertions'],[junit.summary.failures+junit.summary.errors,'Gagal / error'],[junit.summary.skipped,'Dilewati']],
        ['No.','Kelompok pengujian','Tes','Assertions','Status'],grouped.map(row=>[row.name,row.tests,row.assertions,phpStatus(row)]),
        'phpunit-results.xml dan PHPUnit.log',`Seluruh kelas tes dikelompokkan tanpa penghitungan ganda. Durasi ${junit.summary.time.toFixed(2)} detik. PHPUnit menggunakan SQLite sementara; bukan pengujian MySQL.`)],
    ['TEST-02-playwright',html('Hasil Pengujian Playwright','Pengujian browser impor Excel Neoklinik pada MySQL terisolasi.',
        [[playwright.results.filter(r=>r.status==='Lulus').length,'Kelompok skenario lulus'],[playwright.results.length,'Kelompok dieksekusi'],[playwright.errors.length,'Error browser'],['Simulasi','Sumber data']],
        ['No.','Kelompok skenario browser','Status'],playwright.results.map(row=>[row.name,row.status]),
        'Playwright.json, Playwright.log, dan MySQL.json','16 kelompok ini khusus alur impor, bukan klaim 16 tes untuk seluruh modul. Alur fitur SS-01/02/04/05/06 dijalankan terpisah pada Feature_Capture.json.')]
];
(async()=>{
    const browser = await chromium.launch({ executablePath:'C:/Program Files/Google/Chrome/Application/chrome.exe',headless:true });
    try {
        const page = await browser.newPage({viewport:{width:1440,height:900},deviceScaleFactor:1});
        const manifest = [...features.images];
        for(const [name,content] of specs){
            const file = path.join(testing,name+'.html');
            const compactReport = content.replace('td{padding:8px 14px;', 'td{padding:6px 14px;');
            fs.writeFileSync(file,compactReport);
            await page.goto(require('url').pathToFileURL(file).href);
            await page.evaluate(()=>document.fonts.ready);
            assert.ok(await page.evaluate(()=>document.documentElement.scrollHeight<=900),'Report fits 900px');
            await page.screenshot({path:path.join(testing,name+'.png'),fullPage:false});
            manifest.push({id:name,file:'testing/'+name+'.png',status:'berhasil',source:'laporan dari artefak eksekusi aktual',role:'Runner pengujian',captured_at:new Date().toISOString(),commit:run.commit,simulation:true});
        }
        for(const [file,id,description] of [
            ['screenshots/SS-03-import-neoklinik.png','SS-03','Hasil impor diposting dengan alokasi batch FEFO dan tautan kartu stok.'],
            ['supporting/SS-03-validasi-pencocokan.png','SS-03-validation','Pratinjau memblokir pemetaan yang belum disetujui.'],
            ['supporting/SS-03-form-impor.png','SS-03-upload','Halaman unggah Excel untuk pengeluaran obat.']
        ]) {
            const original = { 'SS-03':'Posting_Berhasil.png','SS-03-validation':'Pratinjau_Belum_Disetujui.png','SS-03-upload':'Impor_1440.png' }[id];
            const captured = fs.statSync(path.resolve(out,'../../..',run.import_evidence,original)).mtime.toISOString();
            manifest.push({id,file,description,status:'berhasil',source:'aplikasi berjalan; eksekusi test-issue-import.php',role:'Petugas Simulasi',captured_at:captured,commit:run.commit,simulation:true});
        }
        for(const item of manifest){
            const data=fs.readFileSync(path.join(out,item.file));
            assert.equal(data.readUInt32BE(16),1440,item.file+' width');
            assert.equal(data.readUInt32BE(20),900,item.file+' height');
            item.width=1440;item.height=900;
            item.sha256=crypto.createHash('sha256').update(data).digest('hex');
            assert.ok(data.length>10000,item.file+' non-empty');
            item.pixel_check = await page.evaluate(async dataUrl => {
                const img = new Image(); img.src = dataUrl; await img.decode();
                const canvas = document.createElement('canvas'); canvas.width=img.width; canvas.height=img.height;
                const ctx=canvas.getContext('2d'); ctx.drawImage(img,0,0);
                const pixels=ctx.getImageData(0,0,img.width,img.height).data;
                const colors=new Set(); let dark=0, sampled=0;
                for(let i=0;i<pixels.length;i+=64){
                    colors.add(`${pixels[i]},${pixels[i+1]},${pixels[i+2]}`);
                    if(Math.max(pixels[i],pixels[i+1],pixels[i+2])<180) dark++;
                    sampled++;
                }
                return {sampled,unique_colors:colors.size,dark_pixels:dark};
            },'data:image/png;base64,'+data.toString('base64'));
            assert.ok(item.pixel_check.unique_colors>100 && item.pixel_check.dark_pixels>100,item.file+' nonblank pixels');
        }
        fs.writeFileSync(path.join(out,'manifest.json'),JSON.stringify({commit:run.commit,simulation:true,images:manifest},null,2));
        console.log(`PASS ${manifest.length} PNG files verified at 1440x900; test reports generated from actual artifacts.`);
    } finally {await browser.close();}
})().catch(error=>{console.error(error);process.exitCode=1;});
