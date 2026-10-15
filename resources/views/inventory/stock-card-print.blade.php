<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Kartu Stok {{ $medicine->name }} - Klinik Shifa Medika</title>
    <style>
        @page { size: A4 portrait; margin: 8mm; }
        * { box-sizing: border-box; }
        body { margin: 0; background: #f1f5f9; color: #111827; font-family: Arial, Helvetica, sans-serif; }
        .toolbar { display: flex; justify-content: flex-end; gap: 8px; padding: 12px; }
        .toolbar a, .toolbar button { border: 1px solid #cbd5e1; border-radius: 6px; background: #fff; color: #334155; cursor: pointer; font-size: 12px; font-weight: 700; padding: 8px 12px; text-decoration: none; }
        .sheet { width: 194mm; min-height: 281mm; margin: 0 auto 16px; background: #fff; border: 1.2px solid #111827; padding: 7mm; }
        .clinic { display: grid; grid-template-columns: 24mm 1fr 24mm; align-items: center; gap: 4mm; border-bottom: 1.4px solid #111827; padding-bottom: 4mm; text-align: center; }
        .clinic img { max-height: 18mm; max-width: 24mm; object-fit: contain; }
        .clinic h1 { margin: 0; font-size: 20px; letter-spacing: .7px; line-height: 1; }
        .clinic h2 { margin: 0 0 1.5mm; font-size: 11px; letter-spacing: .45px; line-height: 1.25; }
        .clinic p { margin: 1mm 0 0; font-size: 8px; line-height: 1.25; }
        .clinic-spacer { width: 24mm; }
        .title { border-bottom: 1.4px solid #111827; font-size: 14px; font-weight: 800; letter-spacing: 1.2px; padding: 3mm 0 2.5mm; text-align: center; }
        .meta { display: grid; grid-template-columns: 24mm 1fr 19mm 42mm; column-gap: 4mm; row-gap: 2mm; margin: 5mm 0 4mm; font-size: 10px; align-items: end; }
        .meta strong { letter-spacing: .2px; }
        .meta span { border-bottom: 1px solid #111827; display: block; font-weight: 700; min-height: 5mm; padding: 0 2mm 1mm; }
        table { border-collapse: collapse; width: 100%; table-layout: fixed; }
        th, td { border: .85px solid #111827; font-size: 8px; height: 7.2mm; padding: .8mm 1mm; vertical-align: middle; }
        th { background: #f8fafc; font-size: 7.5px; font-weight: 800; height: 8mm; line-height: 1.15; text-align: center; }
        td { line-height: 1.2; overflow-wrap: anywhere; }
        td.num { text-align: right; font-variant-numeric: tabular-nums; }
        td.center { text-align: center; }
        .footer { border: .85px solid #111827; border-top: 0; display: grid; grid-template-columns: 1fr 32mm 24mm; min-height: 24mm; }
        .footer div { border-left: .85px solid #111827; padding-top: 6mm; text-align: center; font-size: 8px; }
        .footer div:first-child { border-left: 0; }
        .sign-name { display: block; margin-top: 11mm; font-weight: 700; }
        .small { color: #475569; font-size: 8px; margin-top: 2mm; text-align: right; }
        @media print {
            body { background: #fff; }
            .toolbar { display: none; }
            .sheet { border: 0; margin: 0; min-height: auto; padding: 0; width: auto; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <a href="{{ route('stock-card.index', $filters) }}">Kembali</a>
        <button type="button" onclick="window.print()">Cetak / Simpan PDF</button>
    </div>

    <main class="sheet">
        <header class="clinic">
            <img src="{{ asset('images/shifa-medika.png') }}" alt="Logo Klinik Shifa Medika">
            <div>
                <h2>KLINIK PRATAMA RAWAT JALAN 24 JAM</h2>
                <h1>SHIFA MEDIKA</h1>
                <p>{{ config('clinic.address') }}</p>
                <p>Telp. (0267) 8760357</p>
            </div>
            <div class="clinic-spacer" aria-hidden="true"></div>
        </header>

        <div class="title">KARTU STOK</div>

        <section class="meta">
            <strong>NAMA OBAT</strong>
            <span>{{ $medicine->name }}</span>
            <strong>LOKASI</strong>
            <span>{{ $location->name }}</span>
            <strong>SATUAN</strong>
            <span>{{ $medicine->unit?->name ?? '-' }}</span>
            <strong>PERIODE</strong>
            <span>{{ \Carbon\Carbon::parse($filters['start'])->format('d/m/Y') }} - {{ \Carbon\Carbon::parse($filters['end'])->format('d/m/Y') }}</span>
        </section>

        <table aria-label="Kartu stok cetak">
            <colgroup>
                <col style="width: 7%">
                <col style="width: 21%">
                <col style="width: 16%">
                <col style="width: 16%">
                <col style="width: 10%">
                <col style="width: 10%">
                <col style="width: 10%">
                <col style="width: 10%">
            </colgroup>
            <thead>
                <tr>
                    <th>TGL</th>
                    <th>NAMA PBF / KETERANGAN</th>
                    <th>NO FAKTUR / REF</th>
                    <th>NO BATCH</th>
                    <th>ED</th>
                    <th>MASUK</th>
                    <th>KELUAR</th>
                    <th>JUMLAH</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td class="center">{{ \Carbon\Carbon::parse($filters['start'])->format('d/m/y') }}</td>
                    <td>Stok awal periode</td>
                    <td class="center">-</td>
                    <td class="center">-</td>
                    <td class="center">-</td>
                    <td class="num">-</td>
                    <td class="num">-</td>
                    <td class="num">{{ \App\Support\Quantity::format((float) $opening) }}</td>
                </tr>
                @foreach($rows as $row)
                    @php
                        $delta = (float) $row->getAttribute('card_delta');
                        $counterparty = $row->counterparty;
                        if (! $counterparty && $row->type === 'transfer') {
                            $counterparty = $delta > 0 ? $row->sourceLocation?->name : $row->destinationLocation?->name;
                        }
                        if (! $counterparty) {
                            $counterparty = $row->notes ?: ($row->creator?->name ?? '-');
                        }
                    @endphp
                    <tr>
                        <td class="center">{{ $row->occurred_at->format('d/m/y') }}</td>
                        <td>{{ \Illuminate\Support\Str::limit($counterparty, 36) }}</td>
                        <td>{{ \Illuminate\Support\Str::limit($row->reference, 24) }}</td>
                        <td>{{ \Illuminate\Support\Str::limit($row->batch?->number ?? '-', 24) }}</td>
                        <td class="center">{{ $row->batch?->expiry_date?->format('m/y') ?? '-' }}</td>
                        <td class="num">{{ $delta > 0 ? \App\Support\Quantity::format($delta) : '-' }}</td>
                        <td class="num">{{ $delta < 0 ? \App\Support\Quantity::format(abs($delta)) : '-' }}</td>
                        <td class="num">{{ \App\Support\Quantity::format((float) $row->getAttribute('card_running_balance')) }}</td>
                    </tr>
                @endforeach
                @for($i = 0, $fill = max(8, 22 - $rows->count()); $i < $fill; $i++)
                    <tr>
                        <td>&nbsp;</td><td></td><td></td><td></td><td></td><td></td><td></td><td></td>
                    </tr>
                @endfor
            </tbody>
        </table>

        <footer class="footer">
            <div>
                Mengetahui
                <span class="sign-name">Apt. Parnawati, S.Si</span>
            </div>
            <div></div>
            <div></div>
        </footer>
        <p class="small">Dicetak dari ShifaStock pada {{ now('Asia/Jakarta')->format('d/m/Y H:i') }}</p>
    </main>
</body>
</html>
