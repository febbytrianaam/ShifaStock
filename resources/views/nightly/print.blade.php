<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Laporan Pemeriksaan Stok {{ $check->date }} - {{ $check->location }}</title>
    <style>
        @page { size: A4 portrait; margin: 12mm; }
        * { box-sizing: border-box; }
        body { margin: 0; color: #17212b; background: #f1f5f9; font: 12px/1.45 Arial, Helvetica, sans-serif; }
        .toolbar { max-width: 210mm; margin: auto; padding: 12px; display: flex; justify-content: flex-end; gap: 8px; }
        .toolbar a, .toolbar button { border: 1px solid #cbd5e1; border-radius: 4px; background: white; padding: 8px 12px; color: #17212b; font: inherit; text-decoration: none; cursor: pointer; }
        .sheet { width: 210mm; min-width: 210mm; margin: 0 auto 16px; padding: 12mm; background: white; }
        .clinic { display: grid; grid-template-columns: 24mm 1fr 24mm; align-items: center; gap: 5mm; border-bottom: 2px solid #17212b; padding-bottom: 4mm; text-align: center; }
        .clinic img { width: 24mm; height: 21mm; object-fit: contain; }
        .clinic h1 { margin: 0; font-size: 21px; text-transform: uppercase; }
        .clinic p { margin: 2px 0; }
        h2 { margin: 4mm 0; font-size: 16px; text-align: center; }
        h3 { margin: 5mm 0 2mm; font-size: 12px; }
        .meta { display: grid; grid-template-columns: 1fr 1fr; gap: 2mm 10mm; margin: 4mm 0; }
        .meta p { margin: 0; overflow-wrap: anywhere; }
        .notice { margin: 3mm 0; padding: 2mm 3mm; border: 1px solid #8b98a5; font-size: 11px; }
        .uat { border: 2px solid #17212b; text-align: center; font-weight: bold; }
        .summary { margin: 3mm 0; font-size: 11px; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        th, td { border: 1px solid #657180; padding: 1.5mm; font-size: 11px; vertical-align: top; overflow-wrap: anywhere; }
        th { background: #edf2f6; text-align: left; }
        .num { text-align: right; font-variant-numeric: tabular-nums; }
        .small { display: block; color: #475569; font-size: 10px; }
        .footer { margin-top: 5mm; font-size: 9px; color: #475569; }
        @media print {
            body { background: white; }
            .toolbar { display: none; }
            .sheet { width: auto; min-width: 0; padding: 0; margin: 0; }
            thead { display: table-header-group; }
            tr { break-inside: avoid; }
            h3 { break-after: avoid; }
        }
        @media screen and (max-width: 640px) {
            .toolbar { justify-content: flex-start; }
        }
    </style>
</head>
<body data-nightly-report>
    <nav class="toolbar" aria-label="Aksi laporan cetak">
        <a href="{{ route('nightly.show', $check->id) }}">Kembali ke pemeriksaan</a>
        <button type="button" onclick="window.print()">Cetak / Simpan PDF</button>
    </nav>
    <main class="sheet">
        @if(app()->environment('uat'))
            <p class="notice uat" data-uat-notice>UAT / SIMULASI - Bukan data stok operasional klinik</p>
        @endif
        <header class="clinic">
            <img src="{{ asset('images/shifa-medika.png') }}" alt="Logo Klinik Shifa Medika">
            <div>
                <p>KLINIK PRATAMA RAWAT JALAN 24 JAM</p>
                <h1>{{ $clinicName }}</h1>
                <p>{{ config('clinic.address') }}</p>
            </div>
            <div aria-hidden="true"></div>
        </header>
        <h2>LAPORAN PEMERIKSAAN STOK HARIAN</h2>
        <section class="meta" aria-label="Identitas laporan">
            <p><strong>Nomor laporan:</strong> CHECK-{{ $check->id }}</p>
            <p><strong>Lokasi:</strong> {{ $check->location }}</p>
            <p><strong>Tanggal pemeriksaan:</strong> {{ \Carbon\Carbon::parse($check->date)->format('d/m/Y') }}</p>
            <p><strong>Dibuat oleh:</strong> {{ $check->creator }}</p>
            <p><strong>Dikerjakan terakhir:</strong> {{ $lastWork?->actor_name ?? 'Belum tercatat' }}</p>
            <p><strong>Daftar awal:</strong> {{ $check->scope === 'selected' ? 'Obat tertentu' : 'Semua obat' }} ({{ $items->count() }} obat)</p>
            <p><strong>Status:</strong> {{ ['draft' => 'DRAF - Belum disetujui', 'submitted' => 'MENUNGGU TINJAUAN - Belum disetujui', 'approved' => 'DISETUJUI'][$check->status] ?? 'Belum disetujui' }}</p>
            <p><strong>Peninjau:</strong> {{ $reviewer ?: 'Belum tercatat' }}</p>
            <p><strong>Disetujui pada:</strong> {{ $check->status === 'approved' && $check->approved_at ? \Carbon\Carbon::parse($check->approved_at)->timezone('Asia/Jakarta')->format('d/m/Y H:i').' WIB' : '-' }}</p>
        </section>
        @if($check->status !== 'approved')
            <p class="notice">Laporan ini belum disetujui kepala apoteker. Hasil hitung di sini belum merupakan persetujuan penyesuaian stok.</p>
        @endif
        @if($check->scope === 'selected')
            <p class="notice">Pemeriksaan hanya mencakup obat dalam laporan ini, bukan seluruh persediaan di lokasi tersebut.</p>
        @endif
        @if($uncheckedCount > 0)
            <p class="notice">Pemeriksaan belum lengkap: {{ $checkedCount }} obat sudah diperiksa dan {{ $uncheckedCount }} obat belum diperiksa. Obat yang belum diperiksa tidak dianggap kosong dan stoknya tidak disesuaikan.</p>
        @endif
        <p class="summary">Jumlah obat: <strong>{{ $items->count() }}</strong> &nbsp;|&nbsp; Sudah dihitung: <strong>{{ $items->whereNotNull('quantity')->count() }}</strong> &nbsp;|&nbsp; Belum diperiksa: <strong>{{ $items->whereNull('quantity')->count() }}</strong> &nbsp;|&nbsp; Kosong: <strong>{{ $items->filter(fn ($item) => $item->quantity !== null && (int) $item->quantity === 0)->count() }}</strong> &nbsp;|&nbsp; Menipis: <strong>{{ $items->filter(fn ($item) => $item->quantity !== null && $item->quantity > 0 && $item->is_low)->count() }}</strong></p>
        <table aria-label="Hasil pemeriksaan stok">
            <colgroup><col style="width:5%"><col style="width:24%"><col style="width:13%"><col style="width:10%"><col style="width:8%"><col style="width:10%"><col style="width:10%"><col style="width:20%"></colgroup>
            <thead><tr><th>No.</th><th>Nama / Kode Obat</th><th>Hasil Sebelumnya</th><th>Hasil Hitung</th><th>Satuan</th><th>Kondisi</th><th>Jumlah Diminta</th><th>Catatan</th></tr></thead>
            <tbody>
                @foreach($items as $item)
                    @php($before = $baseline->get($item->medicine_id))
                    <tr>
                        <td class="num">{{ $loop->iteration }}</td>
                        <td><strong>{{ $item->name }}</strong><span class="small">{{ $item->code }}</span></td>
                        <td>@if($before){{ \App\Support\Quantity::format($before->quantity) }} {{ $before->unit }}<span class="small">{{ \Carbon\Carbon::parse($before->checked_date)->format('d/m/Y') }}</span>@else Belum tersedia @endif</td>
                        <td class="num">{{ $item->quantity === null ? 'Belum diperiksa' : \App\Support\Quantity::format($item->quantity) }}</td>
                        <td>{{ $item->unit ?: '-' }}</td>
                        <td>{{ $item->quantity === null ? 'Belum diperiksa' : ((int) $item->quantity === 0 ? 'Kosong' : ($item->is_low ? 'Menipis' : 'Cukup')) }}</td>
                        <td class="num">{{ $item->requested_quantity === null ? '-' : \App\Support\Quantity::format($item->requested_quantity) }}</td>
                        <td>{{ $item->note ?: '-' }}@if($item->last_edited_name)<span class="small">Diperbarui: {{ $item->last_edited_name }}</span>@endif</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <p class="small">Pembanding adalah hasil terakhir masing-masing obat yang disetujui sebelum tanggal pemeriksaan. Jumlah diminta adalah usulan kebutuhan, bukan pesanan ke supplier.</p>
        @if($items->contains(fn ($item) => !empty($item->saved_batch_counts)))
            <h3>RINCIAN HASIL HITUNG PER BATCH</h3>
            <table aria-label="Rincian hitungan batch">
                <thead><tr><th style="width:36%">Obat</th><th style="width:24%">Nomor Batch</th><th style="width:16%">Kedaluwarsa</th><th style="width:12%">Hasil Hitung</th><th style="width:12%">Satuan</th></tr></thead>
                <tbody>
                    @foreach($items as $item)
                        @foreach($batchOptions->get($item->medicine_id, collect()) as $batch)
                            @if(array_key_exists($batch->id, $item->saved_batch_counts))
                                <tr><td>{{ $item->name }}<span class="small">{{ $item->code }}</span></td><td>{{ $batch->number }}</td><td>{{ $batch->expiry_date?->format('d/m/Y') ?? '-' }}</td><td class="num">{{ \App\Support\Quantity::format($item->saved_batch_counts[$batch->id]) }}</td><td>{{ $item->unit ?: '-' }}</td></tr>
                            @endif
                        @endforeach
                    @endforeach
                </tbody>
            </table>
        @endif
        @if($check->review_note)
            <h3>CATATAN TINJAUAN</h3>
            <p>{{ $check->review_note }}</p>
        @endif
        <p class="footer">Dicetak dari ShifaStock pada {{ now('Asia/Jakarta')->format('d/m/Y H:i') }} WIB. Dokumen ini menampilkan data terakhir yang disimpan. Nama petugas dan peninjau bukan tanda tangan digital.</p>
    </main>
</body>
</html>
