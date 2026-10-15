<x-layouts.app title="Retur PBF">
    @if(session('status'))
        <div class="mb-4 rounded-lg border border-[#b2d4ee] bg-[#edf5fb] p-3 text-sm font-semibold text-[#266194]">{{ session('status') }}</div>
    @endif
    @if($errors->any())
        <div class="mb-4 rounded-lg border border-rose-200 bg-rose-50 p-3 text-sm font-semibold text-rose-700">{{ $errors->first() }}</div>
    @endif

    @can('operate-inventory')
    <form method="POST" action="{{ route('returns.store') }}" class="card mb-5 grid gap-4 p-4 md:grid-cols-2 xl:grid-cols-4" data-required-alert="Lengkapi data retur PBF" novalidate>
        @csrf
        <div>
            <label class="label" for="return-reference">Referensi</label>
            <input class="field bg-slate-50 text-xs font-semibold text-slate-700" id="return-reference" name="reference" value="{{ old('reference', $nextReference) }}" readonly required maxlength="100">
        </div>
        <div>
            <label class="label" for="return-supplier">PBF / Supplier</label>
            <input class="field text-xs" id="return-supplier" name="counterparty" value="{{ old('counterparty') }}" placeholder="Nama PBF" maxlength="255">
        </div>
        <div class="md:col-span-2">
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
            <select class="field text-xs" id="return-location" name="source_location_id" required>
                @foreach($locations as $location)
                    <option value="{{ $location->id }}" @selected(old('source_location_id', $prefill['source_location_id'] ?? null)==$location->id)>{{ $location->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
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
            <input class="field text-xs" id="return-quantity" name="quantity" type="number" min="0.0001" step="0.0001" value="{{ old('quantity') }}" required>
        </div>
        <div>
            <label class="label" for="return-time">Waktu retur</label>
            <input class="field text-xs" id="return-time" name="occurred_at" type="datetime-local" value="{{ old('occurred_at',now('Asia/Jakarta')->format('Y-m-d\\TH:i')) }}" required>
        </div>
        <div>
            <label class="label" for="return-reason">Alasan</label>
            <input class="field text-xs" id="return-reason" name="reason" value="{{ old('reason') }}" placeholder="Rusak, salah kirim, recall..." maxlength="255" required>
        </div>
        <div class="md:col-span-2 xl:col-span-3">
            <label class="label" for="return-notes">Catatan</label>
            <input class="field text-xs" id="return-notes" name="notes" value="{{ old('notes') }}" placeholder="Keterangan tambahan" maxlength="1000">
        </div>
        <div class="flex items-end">
            <button class="btn w-full text-xs">Simpan Retur &amp; Kurangi Stok</button>
        </div>
        <x-stock-entry-context :medicines="$medicines" :batch-balances="$batchBalances" medicine="#return-medicine" location="#return-location" quantity="#return-quantity" batch="#return-batch" />
    </form>
    @endcan

    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full whitespace-nowrap text-left text-sm">
                <thead class="bg-[#edf5fb] border-b border-[#d6e8f6] text-[#266194]">
                    <tr>
                        <th class="p-4">Waktu</th>
                        <th class="p-4">Referensi</th>
                        <th class="p-4">Obat / Batch</th>
                        <th class="p-4">Lokasi</th>
                        <th class="p-4">Jumlah</th>
                        <th class="p-4">PBF</th>
                        <th class="p-4">Catatan</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($rows as $row)
                        <tr>
                            <td class="p-4 text-xs font-medium text-slate-600 tabular-nums">{{ $row->occurred_at->format('d/m/Y H:i') }}</td>
                            <td class="p-4 font-mono text-xs font-semibold text-slate-700">{{ $row->reference }}</td>
                            <td class="p-4"><b>{{ $row->medicine->name }}</b><br><span class="text-xs text-slate-500">{{ $row->batch?->number }} · ED {{ $row->batch?->expiry_date?->format('d/m/Y') }}</span></td>
                            <td class="p-4 text-xs font-semibold text-slate-600">{{ $row->sourceLocation?->name }}</td>
                            <td class="p-4 font-bold tabular-nums text-rose-700">-{{ \App\Support\Quantity::format((float)$row->quantity) }} {{ $row->medicine->unit?->name }}</td>
                            <td class="p-4 text-xs font-medium text-slate-600">{{ $row->counterparty ?: '—' }}</td>
                            <td class="p-4 text-xs text-slate-600">{{ $row->notes ?: '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="p-8 text-center text-slate-500 font-medium">Belum ada retur PBF.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-4">{{ $rows->links() }}</div>
</x-layouts.app>
