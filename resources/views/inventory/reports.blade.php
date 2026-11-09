<x-layouts.app title="Laporan Persediaan">
    <x-page-tabs :links="[
        ['route' => 'preview.reports', 'label' => 'Ringkasan', 'can' => 'view-reports'],
        ['route' => 'stock-card.index', 'label' => 'Kartu Stok', 'can' => 'view-stock-card'],
    ]" />

    @php
        $typeLabels = ['opening_balance'=>'Stok Awal','receipt'=>'Penerimaan','transfer'=>'Transfer','issue'=>'Pengeluaran','physical_count'=>'Penyesuaian Fisik','stock_opname_adjustment'=>'Opname','supplier_return'=>'Retur PBF','special_adjustment'=>'Kondisi Khusus','reversal'=>'Reversal'];
        $typeBadges = ['opening_balance' => 'badge-muted', 'receipt' => 'badge-ok', 'transfer' => 'badge-brand', 'issue' => 'badge-warn', 'physical_count' => 'badge-indigo',
            'stock_opname_adjustment' => 'badge-indigo', 'supplier_return' => 'badge-danger', 'special_adjustment' => 'badge-danger', 'reversal' => 'badge-muted'];
        $periodLabel = \Illuminate\Support\Carbon::parse($filters['start'])->format('d/m/Y').' – '.\Illuminate\Support\Carbon::parse($filters['end'])->format('d/m/Y');
        $uncounted = $balances->whereNull('last_counted_at')->count();
        $latestMovement = $movements->first();
        $latestCheck = $checks->first();
        // Long tables scroll inside their panel (header stays visible), so every row is still on the page without a very tall page.
        $scroll = 'max-h-[560px] overflow-auto';
        $head = 'table-head sticky top-0 z-10 shadow-[inset_0_-1px_0_#e2e8f0]';
        $isWarehouse = fn ($name) => str_contains(strtolower($name ?? ''), 'gudang');
    @endphp

    <nav aria-label="Bagian laporan" class="grid grid-cols-2 gap-3 xl:grid-cols-4 xl:gap-4">
        <x-ui.stat href="#rekap-periode" label="Rekap periode" :value="\App\Support\Quantity::format(count($periodRows))" unit="baris obat-lokasi" :sub="$periodLabel" theme="brand" icon="report" />
        <x-ui.stat href="#stok-saat-ini" label="Stok saat ini" :value="\App\Support\Quantity::format($balances->count())" unit="obat-lokasi"
            :sub="$uncounted > 0 ? $uncounted.' belum pernah dihitung fisik' : 'Semua sudah dihitung fisik'" theme="teal" icon="box" />
        <x-ui.stat href="#pemeriksaan-disetujui" label="Pemeriksaan disetujui" :value="\App\Support\Quantity::format($checks->count())" unit="laporan"
            :sub="$latestCheck ? 'Terbaru '.\Illuminate\Support\Carbon::parse($latestCheck->date)->format('d/m/Y') : 'Belum ada pemeriksaan disetujui'" theme="green" icon="check" />
        <x-ui.stat href="#pergerakan-terbaru" label="Pergerakan terbaru" :value="\App\Support\Quantity::format($movements->count())" unit="mutasi"
            :sub="$latestMovement ? 'Terakhir '.$latestMovement->occurred_at->format('d/m/Y H:i') : 'Belum ada pergerakan stok'" theme="violet" icon="history" />
    </nav>

    <x-ui.section id="rekap-periode" title="Rekap Periode" icon="report" tone="brand" flush class="mt-4 scroll-mt-20"
        :subtitle="($reportSettings['clinic_name'] ?? 'Klinik Shifa Medika').' · stok awal, masuk, keluar, dan stok akhir per obat dan lokasi'">
        <x-slot:actions>
            <form method="GET" class="grid w-full grid-cols-2 gap-2 sm:flex sm:w-auto sm:flex-wrap sm:items-center" data-required-alert="Lengkapi periode laporan" data-date-range-start="#report-start" data-date-range-end="#report-end" novalidate>
                <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:gap-1.5">
                    <label class="text-xs font-bold text-slate-600" for="report-start">Dari</label>
                    <input class="field min-h-11 sm:w-auto lg:min-h-[34px] lg:py-1.5" id="report-start" name="start" type="date" value="{{ $filters['start'] }}" required>
                </div>
                <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:gap-1.5">
                    <label class="text-xs font-bold text-slate-600" for="report-end">Sampai</label>
                    <input class="field min-h-11 sm:w-auto lg:min-h-[34px] lg:py-1.5" id="report-end" name="end" type="date" value="{{ $filters['end'] }}" required>
                </div>
                <button class="btn min-h-11 lg:min-h-[34px] lg:py-1.5">Tampilkan</button>
                <button class="btn-secondary min-h-11 lg:min-h-[34px] lg:py-1.5" name="export" value="csv"><x-ui.icon name="download" />Export CSV</button>
            </form>
        </x-slot:actions>
        <div class="{{ $scroll }}">
            <table class="w-full whitespace-nowrap text-left text-[13px]">
                <thead class="{{ $head }}">
                    <tr>
                        <th class="px-4 py-2.5">Obat</th>
                        <th class="px-4 py-2.5">Lokasi</th>
                        <th class="px-4 py-2.5 text-right">Stok Awal</th>
                        <th class="px-4 py-2.5 text-right">Masuk</th>
                        <th class="px-4 py-2.5 text-right">Keluar</th>
                        <th class="px-4 py-2.5 text-right">Stok Akhir</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($periodRows as $row)
                        <tr>
                            <td class="px-4 py-2.5">
                                <span class="font-extrabold text-slate-900">{{ $row['medicine_name'] }}</span>
                                <span class="ml-1.5 rounded bg-slate-100 px-1.5 font-mono text-[11px] font-semibold text-slate-600">{{ $row['medicine_code'] }}</span>
                            </td>
                            <td class="px-4 py-2.5"><span class="badge {{ $isWarehouse($row['location_name']) ? 'badge-brand' : '' }}">{{ $row['location_name'] }}</span></td>
                            <td class="px-4 py-2.5 text-right font-bold tabular-nums text-slate-700">{{ \App\Support\Quantity::format($row['opening']) }} <span class="text-xs font-semibold text-slate-500">{{ $row['unit_name'] }}</span></td>
                            <td class="px-4 py-2.5 text-right font-bold tabular-nums {{ (float) $row['incoming'] > 0 ? 'text-emerald-700' : 'text-slate-400' }}">{{ \App\Support\Quantity::format($row['incoming']) }} <span class="text-xs font-semibold text-slate-500">{{ $row['unit_name'] }}</span></td>
                            <td class="px-4 py-2.5 text-right font-bold tabular-nums {{ (float) $row['outgoing'] > 0 ? 'text-rose-700' : 'text-slate-400' }}">{{ \App\Support\Quantity::format($row['outgoing']) }} <span class="text-xs font-semibold text-slate-500">{{ $row['unit_name'] }}</span></td>
                            <td class="px-4 py-2.5 text-right font-extrabold tabular-nums text-brand-800">{{ \App\Support\Quantity::format($row['ending']) }} <span class="text-xs font-semibold text-slate-500">{{ $row['unit_name'] }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="empty-state">Belum ada mutasi untuk periode ini.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="flex flex-wrap items-center justify-between gap-x-4 gap-y-1 border-t border-slate-100 px-4 py-2.5 text-xs text-slate-500">
            <span>Periode <b class="font-bold tabular-nums text-slate-700">{{ $periodLabel }}</b> · {{ \App\Support\Quantity::format(count($periodRows)) }} baris</span>
            @if(!empty($reportSettings['footer']))
                <span>{{ $reportSettings['footer'] }}</span>
            @endif
        </div>
    </x-ui.section>

    <div class="mt-4 grid items-start gap-4 xl:grid-cols-[minmax(0,1.25fr)_minmax(0,1fr)]">
        <x-ui.section id="stok-saat-ini" title="Stok Saat Ini per Lokasi" icon="box" tone="teal" flush class="scroll-mt-20" :meta="\App\Support\Quantity::format($balances->count()).' baris'">
            <x-slot:actions>
                <a class="link text-xs" href="{{ route('nightly.history') }}">Riwayat pemeriksaan →</a>
            </x-slot:actions>
            <div class="{{ $scroll }}">
                <table class="w-full whitespace-nowrap text-left text-[13px]">
                    <thead class="{{ $head }}">
                        <tr>
                            <th class="px-4 py-2.5">Obat</th>
                            <th class="px-4 py-2.5">Lokasi</th>
                            <th class="px-4 py-2.5 text-right">Jumlah</th>
                            <th class="px-4 py-2.5">Terakhir Dihitung</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($balances as $balance)
                            <tr>
                                <td class="px-4 py-2.5 font-extrabold text-slate-900">{{ $balance->medicine->name }}</td>
                                <td class="px-4 py-2.5"><span class="badge {{ $isWarehouse($balance->location->name) ? 'badge-brand' : '' }}">{{ $balance->location->name }}</span></td>
                                <td class="px-4 py-2.5 text-right font-extrabold tabular-nums {{ (float) $balance->quantity > 0 ? 'text-slate-900' : 'text-rose-700' }}">{{ \App\Support\Quantity::format((float)$balance->quantity) }} <span class="text-xs font-semibold text-slate-500">{{ $balance->medicine->unit?->name }}</span></td>
                                <td class="px-4 py-2.5 text-xs tabular-nums {{ $balance->last_counted_at ? 'text-slate-600' : 'text-slate-400' }}">{{ $balance->last_counted_at?->format('d/m/Y H:i') ?? 'Belum dihitung fisik' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="empty-state">Belum ada stok persediaan.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-ui.section>

        <x-ui.section id="pemeriksaan-disetujui" title="Pemeriksaan Disetujui" subtitle="20 pemeriksaan terbaru yang sudah disetujui." icon="check" tone="green" flush class="scroll-mt-20">
            <x-slot:actions>
                <a class="link text-xs" href="{{ route('preview.opname') }}">Buka rekap periodik →</a>
            </x-slot:actions>
            <div class="{{ $scroll }}">
                <table class="w-full whitespace-nowrap text-left text-[13px]">
                    <thead class="{{ $head }}">
                        <tr>
                            <th class="px-4 py-2.5">Tanggal</th>
                            <th class="px-4 py-2.5">Lokasi</th>
                            <th class="px-4 py-2.5">Disetujui</th>
                            <th class="px-4 py-2.5 text-right">Detail</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($checks as $check)
                            <tr>
                                <td class="px-4 py-2.5 text-xs font-bold tabular-nums text-slate-700">{{ $check->date }}</td>
                                <td class="px-4 py-2.5 font-bold text-slate-900">{{ $check->location }}<span class="mt-0.5 block text-[11px] font-normal text-slate-500">{{ $check->scope === 'selected' ? 'Obat tertentu' : 'Pemeriksaan lengkap' }}</span></td>
                                <td class="px-4 py-2.5 text-xs tabular-nums text-slate-600">{{ $check->approved_at }}</td>
                                <td class="px-4 py-2.5 text-right">
                                    <a class="inline-flex min-h-11 items-center rounded-lg border border-brand-200 bg-white px-2.5 text-xs font-bold text-brand-800 hover:bg-brand-50 lg:min-h-8" href="{{ route('nightly.show',$check->id) }}">
                                        Lihat laporan →
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="empty-state">Belum ada pemeriksaan disetujui.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-ui.section>
    </div>

    <x-ui.section id="pergerakan-terbaru" title="50 Pergerakan Terbaru" subtitle="Mutasi stok terakhir dari semua jenis transaksi dan lokasi." icon="history" tone="violet" flush class="mt-4 scroll-mt-20" :meta="\App\Support\Quantity::format($movements->count()).' mutasi'">
        <div class="{{ $scroll }}">
            <table class="w-full whitespace-nowrap text-left text-[13px]">
                <thead class="{{ $head }}">
                    <tr>
                        <th class="px-4 py-2.5">Waktu</th>
                        <th class="px-4 py-2.5">Jenis</th>
                        <th class="px-4 py-2.5">Referensi</th>
                        <th class="px-4 py-2.5">Obat</th>
                        <th class="px-4 py-2.5">Dari</th>
                        <th class="px-4 py-2.5">Ke</th>
                        <th class="px-4 py-2.5 text-right">Jumlah</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($movements as $movement)
                        <tr>
                            <td class="px-4 py-2.5 text-xs tabular-nums text-slate-600">{{ $movement->occurred_at->format('d/m/Y H:i') }}</td>
                            <td class="px-4 py-2.5"><span class="badge {{ $typeBadges[$movement->type] ?? 'badge-muted' }}">{{ $typeLabels[$movement->type] ?? $movement->type }}</span></td>
                            <td class="px-4 py-2.5"><span class="rounded bg-slate-100 px-1.5 font-mono text-[11px] font-semibold text-slate-600">{{ $movement->reference }}</span></td>
                            <td class="px-4 py-2.5 font-extrabold text-slate-900">{{ $movement->medicine->name }}</td>
                            <td class="px-4 py-2.5 text-xs text-slate-600">{{ $movement->sourceLocation?->name ?? '—' }}</td>
                            <td class="px-4 py-2.5 text-xs text-slate-600">{{ $movement->destinationLocation?->name ?? '—' }}</td>
                            <td class="px-4 py-2.5 text-right font-extrabold tabular-nums text-slate-900">{{ \App\Support\Quantity::format((float)$movement->quantity) }} <span class="text-xs font-semibold text-slate-500">{{ $movement->medicine->unit?->name }}</span></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="empty-state">Belum ada pergerakan stok.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-ui.section>
</x-layouts.app>
