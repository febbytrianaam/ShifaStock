<x-layouts.app title="Kartu Stok">
    <x-page-tabs :links="[
        ['route' => 'preview.reports', 'label' => 'Ringkasan', 'can' => 'view-reports'],
        ['route' => 'stock-card.index', 'label' => 'Kartu Stok', 'can' => 'view-stock-card'],
    ]" />

    @if($errors->any())
        <div role="alert" class="callout-danger mb-4 font-semibold">
            {{ $errors->first() }}
        </div>
    @endif

    @php
        $canPrintStockCard = !empty($filters['medicine_id']) && !empty($filters['location_id']) && !empty($filters['start']) && !empty($filters['end']);
        $typeLabels = ['opening_balance'=>'Stok Awal','receipt'=>'Penerimaan','transfer'=>'Transfer','issue'=>'Pengeluaran','physical_count'=>'Hitung Fisik','stock_opname_adjustment'=>'Opname','supplier_return'=>'Retur PBF','special_adjustment'=>'Kondisi Khusus','reversal'=>'Reversal'];
        $typeBadges = ['opening_balance' => 'badge-muted', 'receipt' => 'badge-ok', 'transfer' => 'badge-brand', 'issue' => 'badge-warn', 'physical_count' => 'badge-indigo',
            'stock_opname_adjustment' => 'badge-indigo', 'supplier_return' => 'badge-danger', 'special_adjustment' => 'badge-danger', 'reversal' => 'badge-muted'];
        $control = 'min-h-11 text-xs lg:min-h-0';
    @endphp

    <form method="GET" action="{{ route('stock-card.index') }}" class="card mb-4 grid grid-cols-2 gap-x-3 gap-y-3.5 p-4 xl:grid-cols-[minmax(0,2.2fr)_minmax(150px,1fr)_minmax(150px,1fr)_minmax(150px,1fr)]" data-required-alert="Lengkapi filter kartu stok" data-date-range-start="#card-start" data-date-range-end="#card-end" novalidate>
        <div class="col-span-2 md:col-span-1">
            <label class="label" for="card-medicine">Obat</label>
            <select class="field {{ $control }}" id="card-medicine" name="medicine_id" data-searchable-select data-search-placeholder="Cari kode atau nama obat..." data-required-label="Obat" required>
                <option value="">Pilih obat</option>
                @foreach($medicines as $medicine)
                    <option value="{{ $medicine->id }}" @selected(($filters['medicine_id'] ?? null)==$medicine->id)>{{ $medicine->code }} — {{ $medicine->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-span-2 md:col-span-1">
            <label class="label" for="card-location">Lokasi</label>
            <select class="field {{ $control }}" id="card-location" name="location_id" data-required-label="Lokasi" required>
                <option value="">Pilih lokasi</option>
                @foreach($locations as $location)
                    <option value="{{ $location->id }}" @selected(($filters['location_id'] ?? null)==$location->id)>{{ $location->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="label" for="card-start">Dari</label>
            <input class="field {{ $control }}" id="card-start" name="start" type="date" value="{{ $filters['start'] ?? '' }}" data-required-label="Tanggal mulai" required>
        </div>
        <div>
            <label class="label" for="card-end">Sampai</label>
            <input class="field {{ $control }}" id="card-end" name="end" type="date" value="{{ $filters['end'] ?? '' }}" data-required-label="Tanggal selesai" required>
        </div>
        <div class="col-span-2 xl:col-span-3">
            <label class="label" for="card-batch">Batch</label>
            <select class="field {{ $control }}" id="card-batch" name="batch_id" data-searchable-select data-search-placeholder="Cari batch..." data-depends-on="#card-medicine,#card-location" data-depends-empty="Pilih obat dan lokasi dulu">
                <option value="">Semua batch</option>
                @foreach($batchBalances as $balance)
                    <option value="{{ $balance->batch_id }}" data-depends-values="{{ $balance->medicine_id }}|{{ $balance->location_id }}" @selected(($filters['batch_id'] ?? null)==$balance->batch_id)>
                        {{ $balance->medicine?->code }} · {{ $balance->batch?->number }} · {{ $balance->location?->name }} · ED {{ $balance->batch?->expiry_date?->format('d/m/Y') }} · {{ \App\Support\Quantity::format((float)$balance->quantity) }} {{ $balance->medicine?->unit?->name }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="col-span-2 flex items-end gap-2 xl:col-span-1">
            <button class="btn min-h-11 flex-1 lg:min-h-[34px] lg:py-1.5"><x-ui.icon name="filter" />Terapkan</button>
            <a class="btn-secondary min-h-11 lg:min-h-[34px] lg:py-1.5" href="{{ route('stock-card.index') }}">Reset</a>
        </div>
    </form>

    <x-ui.section title="Mutasi kartu stok" icon="file" tone="brand" flush
        :subtitle="$canPrintStockCard ? 'Kartu stok siap dicetak dalam format kartu fisik klinik, sesuai filter obat, lokasi, batch, dan periode di atas.' : 'Tombol cetak akan muncul setelah obat, lokasi, dan periode kartu stok dipilih lalu diterapkan.'">
        <x-slot:actions>
            @if(!empty($filters['location_id']) && !empty($filters['start']))
                <span class="rounded-lg border border-slate-200 bg-slate-50 px-2.5 py-1 text-[13px] text-slate-600">Stok awal periode: <b class="font-extrabold tabular-nums text-slate-900">{{ \App\Support\Quantity::format((float)$opening) }}</b></span>
            @endif
            <span class="text-xs text-slate-500">{{ \App\Support\Quantity::format($rows->total()) }} mutasi</span>
            @if($canPrintStockCard)
                <a class="btn min-h-11 lg:min-h-[34px] lg:py-1.5" target="_blank" rel="noopener" href="{{ route('stock-card.print', $filters) }}"><x-ui.icon name="printer" />Cetak Kartu Fisik</a>
            @endif
        </x-slot:actions>
        <div class="overflow-x-auto">
            <table class="w-full whitespace-nowrap text-left text-[13px]">
                <thead class="table-head">
                    <tr>
                        <th class="px-4 py-2.5">Waktu</th>
                        <th class="px-4 py-2.5">Jenis</th>
                        <th class="px-4 py-2.5">Referensi</th>
                        <th class="px-4 py-2.5">Obat / Batch</th>
                        <th class="px-4 py-2.5">Dari</th>
                        <th class="px-4 py-2.5">Ke</th>
                        <th class="px-4 py-2.5 text-right">Masuk</th>
                        <th class="px-4 py-2.5 text-right">Keluar</th>
                        <th class="px-4 py-2.5 text-right">Stok</th>
                        <th class="px-4 py-2.5 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($rows as $row)
                        @php
                            $delta = (float) $row->getAttribute('card_delta');
                            $alreadyReversed = in_array($row->id, $reversedMovementIds ?? [], true);
                        @endphp
                        <tr>
                            <td class="px-4 py-2.5 text-xs tabular-nums text-slate-600">{{ $row->occurred_at->format('d/m/Y H:i') }}</td>
                            <td class="px-4 py-2.5">
                                <span class="badge {{ $typeBadges[$row->type] ?? 'badge-muted' }}">{{ $typeLabels[$row->type] ?? $row->type }}</span>
                            </td>
                            <td class="px-4 py-2.5"><span class="rounded bg-slate-100 px-1.5 font-mono text-[11px] font-semibold text-slate-600">{{ $row->reference }}</span></td>
                            <td class="px-4 py-2.5">
                                <p class="font-extrabold text-slate-900">{{ $row->medicine->name }}</p>
                                <p class="mt-0.5 text-xs text-slate-500">@if($row->batch?->number)<span class="font-mono text-[11px] font-semibold text-slate-600">{{ $row->batch->number }}</span>@else Tanpa batch @endif</p>
                            </td>
                            <td class="px-4 py-2.5 text-xs text-slate-600">{{ $row->sourceLocation?->name ?? '—' }}</td>
                            <td class="px-4 py-2.5 text-xs text-slate-600">{{ $row->destinationLocation?->name ?? '—' }}</td>
                            <td class="px-4 py-2.5 text-right font-extrabold tabular-nums {{ $delta > 0 ? 'text-emerald-700' : 'text-slate-300' }}">{{ $delta > 0 ? \App\Support\Quantity::format($delta) : '—' }}</td>
                            <td class="px-4 py-2.5 text-right font-extrabold tabular-nums {{ $delta < 0 ? 'text-rose-700' : 'text-slate-300' }}">{{ $delta < 0 ? \App\Support\Quantity::format(abs($delta)) : '—' }}</td>
                            <td class="px-4 py-2.5 text-right tabular-nums {{ $row->getAttribute('card_running_balance') === null ? 'text-xs text-slate-400' : 'font-extrabold text-slate-900' }}">{{ $row->getAttribute('card_running_balance') === null ? 'Pilih lokasi' : \App\Support\Quantity::format((float)$row->getAttribute('card_running_balance')) }}</td>
                            <td class="px-4 py-2.5 text-right">
                                @can('reverse-inventory-movements')
                                    @if($row->type !== 'reversal' && ! $row->reversed_movement_id && ! $alreadyReversed)
                                        <form method="POST" action="{{ route('movements.reverse', $row) }}" class="inline-flex items-center gap-2" data-confirm-action data-confirm-title="Batalkan transaksi ini?" data-confirm-text="Sistem mencatat transaksi pembalik yang terhubung ke transaksi asal. Stok akan disesuaikan kembali; riwayat asli tetap tersimpan." data-confirm-button="Ya, batalkan transaksi">
                                            @csrf
                                            <input type="hidden" name="reason" value="Koreksi transaksi final dari kartu stok">
                                            <button class="btn-danger min-h-11 px-2.5 py-1 text-xs lg:min-h-8">Batalkan Transaksi</button>
                                        </form>
                                    @else
                                        <span class="text-xs text-slate-400">{{ $alreadyReversed ? 'Sudah dibalik' : '—' }}</span>
                                    @endif
                                @else
                                    <span class="text-xs text-slate-400">—</span>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="10" class="empty-state">Belum ada mutasi sesuai filter.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-ui.section>
    <div class="mt-4">{{ $rows->links() }}</div>
</x-layouts.app>
