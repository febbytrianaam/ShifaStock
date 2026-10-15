<x-layouts.app title="Stok Awal">
    @if(session('status'))
        <div class="mb-4 rounded-lg border border-[#b2d4ee] bg-[#edf5fb] p-3 text-sm font-semibold text-[#266194]">{{ session('status') }}</div>
    @endif
    @if($errors->any())
        <div class="mb-4 rounded-lg border border-rose-200 bg-rose-50 p-3 text-sm font-semibold text-rose-700">{{ $errors->first() }}</div>
    @endif

    @can('operate-inventory')
    <form method="POST" action="{{ route('opening-balances.store') }}" class="card mb-5 grid gap-4 p-4 md:grid-cols-2 xl:grid-cols-4" data-required-alert="Lengkapi stok awal" novalidate>
        @csrf
        <div>
            <label class="label" for="opening-reference">Referensi</label>
            <input class="field bg-slate-50 text-xs font-semibold text-slate-700" id="opening-reference" name="reference" value="{{ old('reference', $nextReference) }}" readonly required maxlength="100">
        </div>
        <div class="md:col-span-2">
            <label class="label" for="opening-medicine">Obat</label>
            <select class="field text-xs" id="opening-medicine" name="medicine_id" data-searchable-select data-search-placeholder="Cari kode atau nama obat..." required>
                <option value="">Pilih obat</option>
                @foreach($medicines as $medicine)
                    <option value="{{ $medicine->id }}" @selected(old('medicine_id')==$medicine->id)>{{ $medicine->code }} — {{ $medicine->name }} ({{ $medicine->unit?->name }})</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="label" for="opening-location">Lokasi</label>
            <select class="field text-xs" id="opening-location" name="location_id" required>
                @foreach($locations as $location)
                    <option value="{{ $location->id }}" @selected(old('location_id')==$location->id)>{{ $location->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="label" for="opening-batch">Batch</label>
            <input class="field text-xs" id="opening-batch" name="batch_number" value="{{ old('batch_number') }}" placeholder="Nomor batch" required maxlength="100">
        </div>
        <div>
            <label class="label" for="opening-expiry">Kedaluwarsa</label>
            <input class="field text-xs" id="opening-expiry" name="expiry_date" type="date" value="{{ old('expiry_date') }}" required>
        </div>
        <div>
            <label class="label" for="opening-quantity">Jumlah</label>
            <input class="field text-xs" id="opening-quantity" name="quantity" type="number" min="0.0001" step="0.0001" value="{{ old('quantity') }}" required>
        </div>
        <div>
            <label class="label" for="opening-time">Waktu catat</label>
            <input class="field text-xs" id="opening-time" name="occurred_at" type="datetime-local" value="{{ old('occurred_at',now('Asia/Jakarta')->format('Y-m-d\\TH:i')) }}" required>
        </div>
        <div class="md:col-span-2 xl:col-span-3">
            <label class="label" for="opening-notes">Sumber data</label>
            <input class="field text-xs" id="opening-notes" name="notes" value="{{ old('notes') }}" placeholder="Contoh: hasil opname awal / dokumen stok awal" maxlength="1000">
        </div>
        <div class="flex items-end">
            <button class="btn w-full text-xs">Catat Stok Awal</button>
        </div>
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
                        <th class="p-4">Sumber</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($rows as $row)
                        <tr>
                            <td class="p-4 text-xs font-medium text-slate-600 tabular-nums">{{ $row->occurred_at->format('d/m/Y H:i') }}</td>
                            <td class="p-4 font-mono text-xs font-semibold text-slate-700">{{ $row->reference }}</td>
                            <td class="p-4"><b>{{ $row->medicine->name }}</b><br><span class="text-xs text-slate-500">{{ $row->batch?->number }} · ED {{ $row->batch?->expiry_date?->format('d/m/Y') }}</span></td>
                            <td class="p-4 text-xs font-semibold text-slate-600">{{ $row->destinationLocation?->name }}</td>
                            <td class="p-4 font-bold tabular-nums text-emerald-700">+{{ \App\Support\Quantity::format((float)$row->quantity) }} {{ $row->medicine->unit?->name }}</td>
                            <td class="p-4 text-xs text-slate-600">{{ $row->notes ?: '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="p-8 text-center text-slate-500 font-medium">Belum ada stok awal.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-4">{{ $rows->links() }}</div>
</x-layouts.app>
