<x-layouts.app title="Stok Awal">
    @if(session('status'))
        <div role="status" class="callout mb-4 font-semibold">{{ session('status') }}</div>
    @endif
    @if($errors->any())
        <div role="alert" class="callout-danger mb-4 font-semibold">{{ $errors->first() }}</div>
    @endif

    @can('operate-inventory')
    <x-ui.section title="Catat stok awal" subtitle="Referensi dibuat otomatis. Jumlah diisi dalam satuan stok yang tertera pada pilihan obat." icon="opening" tone="brand" class="mb-4">
        <form method="POST" action="{{ route('opening-balances.store') }}" class="grid grid-cols-2 gap-x-3 gap-y-3.5 xl:grid-cols-6" data-required-alert="Lengkapi stok awal" novalidate>
            @csrf
            <div class="col-span-2 xl:col-span-1">
                <label class="label" for="opening-reference">Referensi</label>
                <input class="field min-h-11 bg-slate-50 font-mono text-xs font-semibold text-slate-700 lg:min-h-0" id="opening-reference" name="reference" value="{{ old('reference', $nextReference) }}" readonly required maxlength="100">
            </div>
            <div class="col-span-2">
                <label class="label" for="opening-medicine">Obat</label>
                <select class="field min-h-11 text-xs lg:min-h-0" id="opening-medicine" name="medicine_id" data-searchable-select data-search-placeholder="Cari kode atau nama obat..." required>
                    <option value="">Pilih obat</option>
                    @foreach($medicines as $medicine)
                        <option value="{{ $medicine->id }}" @selected(old('medicine_id')==$medicine->id)>{{ $medicine->code }} — {{ $medicine->name }} ({{ $medicine->unit?->name }})</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="label" for="opening-location">Lokasi</label>
                <select class="field min-h-11 text-xs lg:min-h-0" id="opening-location" name="location_id" required>
                    @foreach($locations as $location)
                        <option value="{{ $location->id }}" @selected(old('location_id')==$location->id)>{{ $location->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="label" for="opening-batch">Batch</label>
                <input class="field min-h-11 text-xs lg:min-h-0" id="opening-batch" name="batch_number" value="{{ old('batch_number') }}" placeholder="Nomor batch" required maxlength="100">
            </div>
            <div>
                <label class="label" for="opening-expiry">Kedaluwarsa</label>
                <input class="field min-h-11 text-xs lg:min-h-0" id="opening-expiry" name="expiry_date" type="date" value="{{ old('expiry_date') }}" required>
            </div>
            <div>
                <label class="label" for="opening-quantity">Jumlah</label>
                <input class="field min-h-11 text-xs tabular-nums lg:min-h-0" id="opening-quantity" name="quantity" type="number" min="0.0001" step="0.0001" value="{{ old('quantity') }}" required>
            </div>
            <div class="col-span-2 xl:col-span-1">
                <label class="label" for="opening-time">Waktu catat</label>
                <input class="field min-h-11 text-xs lg:min-h-0" id="opening-time" name="occurred_at" type="datetime-local" value="{{ old('occurred_at',now('Asia/Jakarta')->format('Y-m-d\\TH:i')) }}" required>
            </div>
            <div class="col-span-2 xl:col-span-3">
                <label class="label" for="opening-notes">Sumber data</label>
                <input class="field min-h-11 text-xs lg:min-h-0" id="opening-notes" name="notes" value="{{ old('notes') }}" placeholder="Contoh: hasil opname awal / dokumen stok awal" maxlength="1000">
            </div>
            <div class="col-span-2 flex items-end xl:col-span-1">
                <button class="btn min-h-11 w-full lg:min-h-[34px] lg:py-1.5"><x-ui.icon name="plus" />Catat Stok Awal</button>
            </div>
        </form>
    </x-ui.section>
    @endcan

    <x-ui.section title="Riwayat stok awal" subtitle="Stok awal yang sudah tercatat, terbaru di atas." icon="history" tone="slate" flush :meta="\App\Support\Quantity::format($rows->total()).' catatan'">
        <div class="overflow-x-auto">
            <table class="w-full whitespace-nowrap text-left text-[13px]">
                <thead class="table-head">
                    <tr>
                        <th class="px-4 py-2.5">Waktu</th>
                        <th class="px-4 py-2.5">Referensi</th>
                        <th class="px-4 py-2.5">Obat / Batch</th>
                        <th class="px-4 py-2.5">Lokasi</th>
                        <th class="px-4 py-2.5 text-right">Jumlah</th>
                        <th class="px-4 py-2.5">Sumber</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($rows as $row)
                        <tr>
                            <td class="px-4 py-2.5 text-xs tabular-nums text-slate-600">{{ $row->occurred_at->format('d/m/Y H:i') }}</td>
                            <td class="px-4 py-2.5"><span class="rounded bg-slate-100 px-1.5 font-mono text-[11px] font-semibold text-slate-600">{{ $row->reference }}</span></td>
                            <td class="px-4 py-2.5">
                                <p class="font-extrabold text-slate-900">{{ $row->medicine->name }}</p>
                                <p class="mt-0.5 text-xs text-slate-500"><span class="font-mono text-[11px] font-semibold text-slate-600">{{ $row->batch?->number }}</span> · ED <span class="tabular-nums">{{ $row->batch?->expiry_date?->format('d/m/Y') }}</span></p>
                            </td>
                            <td class="px-4 py-2.5"><span class="badge {{ str_contains(strtolower($row->destinationLocation?->name ?? ''), 'gudang') ? 'badge-brand' : '' }}">{{ $row->destinationLocation?->name }}</span></td>
                            <td class="px-4 py-2.5 text-right font-extrabold tabular-nums text-emerald-700">+{{ \App\Support\Quantity::format((float)$row->quantity) }} <span class="font-semibold text-slate-500">{{ $row->medicine->unit?->name }}</span></td>
                            <td class="max-w-[360px] truncate px-4 py-2.5 text-xs text-slate-600" title="{{ $row->notes }}">{{ $row->notes ?: '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="empty-state">Belum ada stok awal.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-ui.section>
    <div class="mt-4">{{ $rows->links() }}</div>
</x-layouts.app>
