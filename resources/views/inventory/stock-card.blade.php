<x-layouts.app title="Kartu Stok">
    <x-page-tabs :links="[
        ['route' => 'preview.reports', 'label' => 'Ringkasan', 'can' => 'view-reports'],
        ['route' => 'stock-card.index', 'label' => 'Kartu Stok', 'can' => 'view-stock-card'],
    ]" />

    @if($errors->any())
        <div class="mb-4 rounded-lg border border-rose-200 bg-rose-50 p-3 text-sm font-semibold text-rose-700">
            {{ $errors->first() }}
        </div>
    @endif

    @php($canPrintStockCard = !empty($filters['medicine_id']) && !empty($filters['location_id']) && !empty($filters['start']) && !empty($filters['end']))

    <form method="GET" action="{{ route('stock-card.index') }}" class="card mb-5 grid gap-4 p-4 md:grid-cols-2 xl:grid-cols-5" data-required-alert="Lengkapi filter kartu stok" data-date-range-start="#card-start" data-date-range-end="#card-end" novalidate>
        <div class="xl:col-span-2">
            <label class="label" for="card-medicine">Obat</label>
            <select class="field text-xs" id="card-medicine" name="medicine_id" data-searchable-select data-search-placeholder="Cari kode atau nama obat..." data-required-label="Obat" required>
                <option value="">Pilih obat</option>
                @foreach($medicines as $medicine)
                    <option value="{{ $medicine->id }}" @selected(($filters['medicine_id'] ?? null)==$medicine->id)>{{ $medicine->code }} — {{ $medicine->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="label" for="card-location">Lokasi</label>
            <select class="field text-xs" id="card-location" name="location_id" data-required-label="Lokasi" required>
                <option value="">Pilih lokasi</option>
                @foreach($locations as $location)
                    <option value="{{ $location->id }}" @selected(($filters['location_id'] ?? null)==$location->id)>{{ $location->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="label" for="card-start">Dari</label>
            <input class="field text-xs" id="card-start" name="start" type="date" value="{{ $filters['start'] ?? '' }}" data-required-label="Tanggal mulai" required>
        </div>
        <div>
            <label class="label" for="card-end">Sampai</label>
            <input class="field text-xs" id="card-end" name="end" type="date" value="{{ $filters['end'] ?? '' }}" data-required-label="Tanggal selesai" required>
        </div>
        <div class="xl:col-span-4">
            <label class="label" for="card-batch">Batch</label>
            <select class="field text-xs" id="card-batch" name="batch_id" data-searchable-select data-search-placeholder="Cari batch..." data-depends-on="#card-medicine,#card-location" data-depends-empty="Pilih obat dan lokasi dulu">
                <option value="">Semua batch</option>
                @foreach($batchBalances as $balance)
                    <option value="{{ $balance->batch_id }}" data-depends-values="{{ $balance->medicine_id }}|{{ $balance->location_id }}" @selected(($filters['batch_id'] ?? null)==$balance->batch_id)>
                        {{ $balance->medicine?->code }} · {{ $balance->batch?->number }} · {{ $balance->location?->name }} · ED {{ $balance->batch?->expiry_date?->format('d/m/Y') }} · {{ \App\Support\Quantity::format((float)$balance->quantity) }} {{ $balance->medicine?->unit?->name }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="flex items-end gap-2">
            <button class="btn flex-1 text-xs">Terapkan</button>
            <a class="btn-secondary text-xs" href="{{ route('stock-card.index') }}">Reset</a>
        </div>
    </form>

    @if($canPrintStockCard)
        <div class="mb-5 flex flex-col justify-between gap-3 rounded-lg border border-[#b2d4ee] bg-[#edf5fb] px-4 py-3 text-sm text-[#1f4f78] sm:flex-row sm:items-center">
            <div>
                <p class="font-bold text-[#266194]">Kartu stok siap dicetak</p>
                <p class="mt-0.5 text-xs">Buka format kartu fisik klinik berdasarkan filter obat, lokasi, batch, dan periode di atas.</p>
            </div>
            <a class="btn bg-[#3277B2] text-xs font-bold text-white hover:bg-[#266194]" target="_blank" rel="noopener" href="{{ route('stock-card.print', $filters) }}">Cetak Kartu Fisik</a>
        </div>
    @else
        <div class="mb-5 rounded-lg border border-slate-200 bg-white px-4 py-3 text-sm text-slate-500">
            Tombol cetak akan muncul setelah obat, lokasi, dan periode kartu stok dipilih lalu diterapkan.
        </div>
    @endif

    @if(!empty($filters['location_id']) && !empty($filters['start']))
        <div class="mb-4 rounded-lg border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-700">
            Stok awal periode: <span class="tabular-nums text-slate-950">{{ \App\Support\Quantity::format((float)$opening) }}</span>
        </div>
    @endif

    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full whitespace-nowrap text-left text-sm">
                <thead class="bg-[#edf5fb] border-b border-[#d6e8f6] text-[#266194]">
                    <tr>
                        <th class="p-4">Waktu</th>
                        <th class="p-4">Jenis</th>
                        <th class="p-4">Referensi</th>
                        <th class="p-4">Obat / Batch</th>
                        <th class="p-4">Dari</th>
                        <th class="p-4">Ke</th>
                        <th class="p-4">Masuk</th>
                        <th class="p-4">Keluar</th>
                        <th class="p-4">Stok</th>
                        <th class="p-4 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($rows as $row)
                        @php($delta = (float) $row->getAttribute('card_delta'))
                        @php($alreadyReversed = in_array($row->id, $reversedMovementIds ?? [], true))
                        <tr>
                            <td class="p-4 text-xs font-medium text-slate-600 tabular-nums">{{ $row->occurred_at->format('d/m/Y H:i') }}</td>
                            <td class="p-4">
                                <span class="inline-flex rounded-md bg-slate-100 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider text-slate-700">
                                    {{ ['opening_balance'=>'Stok Awal','receipt'=>'Penerimaan','transfer'=>'Transfer','issue'=>'Pengeluaran','physical_count'=>'Hitung Fisik','stock_opname_adjustment'=>'Opname','supplier_return'=>'Retur PBF','special_adjustment'=>'Kondisi Khusus','reversal'=>'Reversal'][$row->type] ?? $row->type }}
                                </span>
                            </td>
                            <td class="p-4 font-mono text-xs font-semibold text-slate-700">{{ $row->reference }}</td>
                            <td class="p-4"><b>{{ $row->medicine->name }}</b><br><span class="text-xs text-slate-500">{{ $row->batch?->number ?? 'Tanpa batch' }}</span></td>
                            <td class="p-4 text-xs font-medium text-slate-600">{{ $row->sourceLocation?->name ?? '—' }}</td>
                            <td class="p-4 text-xs font-medium text-slate-600">{{ $row->destinationLocation?->name ?? '—' }}</td>
                            <td class="p-4 font-bold tabular-nums text-emerald-700">{{ $delta > 0 ? \App\Support\Quantity::format($delta) : '—' }}</td>
                            <td class="p-4 font-bold tabular-nums text-rose-700">{{ $delta < 0 ? \App\Support\Quantity::format(abs($delta)) : '—' }}</td>
                            <td class="p-4 font-bold tabular-nums text-slate-900">{{ $row->getAttribute('card_running_balance') === null ? 'Pilih lokasi' : \App\Support\Quantity::format((float)$row->getAttribute('card_running_balance')) }}</td>
                            <td class="p-4 text-right">
                                @can('reverse-inventory-movements')
                                    @if($row->type !== 'reversal' && ! $row->reversed_movement_id && ! $alreadyReversed)
                                        <form method="POST" action="{{ route('movements.reverse', $row) }}" class="inline-flex items-center gap-2" data-confirm-action data-confirm-title="Batalkan transaksi ini?" data-confirm-text="Sistem mencatat transaksi pembalik yang terhubung ke transaksi asal. Stok akan disesuaikan kembali; riwayat asli tetap tersimpan." data-confirm-button="Ya, batalkan transaksi">
                                            @csrf
                                            <input type="hidden" name="reason" value="Koreksi transaksi final dari kartu stok">
                                            <button class="rounded-lg px-2.5 py-1 text-xs font-bold text-rose-700 hover:bg-rose-50">Batalkan Transaksi</button>
                                        </form>
                                    @else
                                        <span class="text-xs font-medium text-slate-400">{{ $alreadyReversed ? 'Sudah dibalik' : '—' }}</span>
                                    @endif
                                @else
                                    <span class="text-xs font-medium text-slate-400">—</span>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="10" class="p-8 text-center text-slate-500 font-medium">Belum ada mutasi sesuai filter.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-4">{{ $rows->links() }}</div>
</x-layouts.app>
