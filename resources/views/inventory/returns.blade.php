<x-layouts.app title="Retur PBF">
    @if(session('status'))
        <div role="status" class="callout-ok mb-4 font-semibold">{{ session('status') }}</div>
    @endif
    @if($errors->any())
        <div role="alert" class="callout-danger mb-4 font-semibold">{{ $errors->first() }}</div>
    @endif

    @can('operate-inventory')
    <form method="POST" action="{{ route('returns.store') }}" class="mb-4 flex flex-col gap-4" data-required-alert="Lengkapi data retur PBF" novalidate>
        @csrf
        <x-ui.section title="Data retur" icon="return">
            <div class="grid gap-x-4 gap-y-3.5 sm:grid-cols-2 xl:grid-cols-4">
                <div>
                    <label class="label" for="return-reference">Referensi</label>
                    <input class="field min-h-11 bg-slate-50 font-mono text-xs font-semibold text-slate-700 lg:min-h-9" id="return-reference" name="reference" value="{{ old('reference', $nextReference) }}" readonly required maxlength="100">
                </div>
                <div>
                    <label class="label" for="return-supplier">PBF / Supplier</label>
                    <input class="field min-h-11 text-xs lg:min-h-9" id="return-supplier" name="counterparty" value="{{ old('counterparty') }}" placeholder="Nama PBF" maxlength="255">
                </div>
                <div class="sm:col-span-2">
                    <label class="label" for="return-medicine">Obat</label>
                    <select class="field text-xs" id="return-medicine" name="medicine_id" data-searchable-select data-search-placeholder="Cari kode atau nama obat..." required>
                        <option value="">Pilih obat</option>
                        @foreach($medicines as $medicine)
                            <option value="{{ $medicine->id }}" @selected(old('medicine_id', $prefill['medicine_id'] ?? null)==$medicine->id)>{{ $medicine->code }} — {{ $medicine->name }} ({{ $medicine->unit?->name }})</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="label" for="return-location">Lokasi sumber</label>
                    <select class="field min-h-11 text-xs lg:min-h-9" id="return-location" name="source_location_id" required>
                        @foreach($locations as $location)
                            <option value="{{ $location->id }}" @selected(old('source_location_id', $prefill['source_location_id'] ?? null)==$location->id)>{{ $location->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="xl:col-span-2">
                    <label class="label" for="return-batch">Batch</label>
                    <select class="field text-xs" id="return-batch" name="batch_id" data-searchable-select data-search-placeholder="Cari batch..." data-depends-on="#return-medicine,#return-location" data-depends-empty="Pilih obat dan lokasi sumber dulu" required>
                        <option value="">Pilih batch tersedia</option>
                        @foreach($batchBalances as $balance)
                            <option value="{{ $balance->batch_id }}" data-depends-values="{{ $balance->medicine_id }}|{{ $balance->location_id }}" @selected(old('batch_id', $prefill['batch_id'] ?? null)==$balance->batch_id)>
                                {{ $balance->medicine->code }} · {{ $balance->batch->number }} · {{ $balance->location->name }} · {{ \App\Support\Quantity::format((float)$balance->quantity) }} {{ $balance->medicine->unit?->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="label" for="return-quantity">Jumlah</label>
                    <input class="field min-h-11 text-xs tabular-nums lg:min-h-9" id="return-quantity" name="quantity" type="number" min="0.0001" step="0.0001" value="{{ old('quantity') }}" required>
                </div>
                <div>
                    <label class="label" for="return-time">Waktu retur</label>
                    <input class="field min-h-11 text-xs lg:min-h-9" id="return-time" name="occurred_at" type="datetime-local" value="{{ old('occurred_at',now('Asia/Jakarta')->format('Y-m-d\\TH:i')) }}" required>
                </div>
                <div>
                    <label class="label" for="return-reason">Alasan</label>
                    <input class="field min-h-11 text-xs lg:min-h-9" id="return-reason" name="reason" value="{{ old('reason') }}" placeholder="Rusak, salah kirim, recall..." maxlength="255" required>
                </div>
                <div class="xl:col-span-2">
                    <label class="label" for="return-notes">Catatan</label>
                    <input class="field min-h-11 text-xs lg:min-h-9" id="return-notes" name="notes" value="{{ old('notes') }}" placeholder="Keterangan tambahan" maxlength="1000">
                </div>
                <x-stock-entry-context :medicines="$medicines" :batch-balances="$batchBalances" medicine="#return-medicine" location="#return-location" quantity="#return-quantity" batch="#return-batch" />
            </div>
        </x-ui.section>
        <div class="flex justify-end">
            <button class="btn min-h-11 w-full sm:w-auto lg:min-h-9">Simpan Retur &amp; Kurangi Stok</button>
        </div>
    </form>
    @endcan

    <x-ui.section title="Riwayat retur PBF" icon="history" tone="slate" flush :meta="$rows->total().' mutasi'">
        <div class="overflow-x-auto">
            <table class="w-full whitespace-nowrap text-left text-[13px]">
                <thead class="table-head">
                    <tr>
                        <th class="px-4 py-2.5">Waktu</th>
                        <th class="px-4 py-2.5">Referensi</th>
                        <th class="px-4 py-2.5">Obat / Batch</th>
                        <th class="px-4 py-2.5">Lokasi</th>
                        <th class="px-4 py-2.5">Jumlah</th>
                        <th class="px-4 py-2.5">PBF</th>
                        <th class="px-4 py-2.5">Catatan</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($rows as $row)
                        <tr>
                            <td class="px-4 py-2.5 tabular-nums text-slate-600">{{ $row->occurred_at->format('d/m/Y H:i') }}</td>
                            <td class="px-4 py-2.5"><span class="inline-block rounded bg-slate-100 px-1.5 font-mono text-[11px] font-semibold text-slate-600">{{ $row->reference }}</span></td>
                            <td class="px-4 py-2.5"><b class="font-extrabold text-slate-900">{{ $row->medicine->name }}</b><p class="text-xs text-slate-500">{{ $row->batch?->number }} · ED {{ $row->batch?->expiry_date?->format('d/m/Y') }}</p></td>
                            <td class="px-4 py-2.5 text-slate-700">{{ $row->sourceLocation?->name }}</td>
                            <td class="px-4 py-2.5 font-extrabold tabular-nums text-rose-700">-{{ \App\Support\Quantity::format((float)$row->quantity) }} {{ $row->medicine->unit?->name }}</td>
                            <td class="px-4 py-2.5 text-slate-600">{{ $row->counterparty ?: '—' }}</td>
                            <td class="px-4 py-2.5 text-slate-600">{{ $row->notes ?: '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="empty-state">Belum ada retur PBF.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($rows->hasPages())<div class="border-t border-slate-100 px-4 py-3">{{ $rows->links() }}</div>@endif
    </x-ui.section>
</x-layouts.app>
