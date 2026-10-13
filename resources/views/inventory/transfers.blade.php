<x-layouts.app title="Transfer Gudang–Apotek">
    @if(session('status'))
        <div class="mb-4 rounded-lg border border-[#b2d4ee] bg-[#edf5fb] p-3 text-sm font-semibold text-[#266194]">
            {{ session('status') }}
        </div>
    @endif
    @if($errors->any())
        <div class="mb-4 rounded-lg border border-rose-200 bg-rose-50 p-3 text-sm font-semibold text-rose-700">
            {{ $errors->first() }}
        </div>
    @endif

    @can('operate-inventory')
    <form method="POST" action="{{ route('transfers.store') }}" class="card mb-5 grid gap-4 p-4 md:grid-cols-2 xl:grid-cols-4" data-required-alert="Lengkapi data transfer" novalidate>
        @csrf
        <div>
            <label class="label text-xs font-semibold text-slate-700" for="transfer-reference">Referensi</label>
            <input class="field w-full bg-slate-50 text-xs font-semibold text-slate-700" id="transfer-reference" name="reference" value="{{ old('reference', $nextReference) }}" readonly required maxlength="100">
        </div>
        <div class="md:col-span-2">
            <label class="label text-xs font-semibold text-slate-700" for="transfer-medicine">Obat</label>
            <select class="field w-full text-xs" id="transfer-medicine" name="medicine_id" data-searchable-select data-search-placeholder="Cari kode atau nama obat..." required>
                <option value="">Pilih obat</option>
                @foreach($medicines as $medicine)
                    <option value="{{ $medicine->id }}" @selected(old('medicine_id')==$medicine->id)>{{ $medicine->code }} — {{ $medicine->name }} ({{ $medicine->unit->name }})</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="label text-xs font-semibold text-slate-700" for="transfer-batch">Batch (opsional)</label>
            <select class="field w-full text-xs" id="transfer-batch" name="batch_id" data-searchable-select data-search-placeholder="Cari batch..." data-depends-on="#transfer-medicine,#transfer-source" data-depends-empty="Pilih obat dan lokasi sumber dulu">
                <option value="">FEFO otomatis jika memungkinkan</option>
                @foreach($batchBalances as $balance)
                    <option value="{{ $balance->batch_id }}" data-depends-values="{{ $balance->medicine_id }}|{{ $balance->location_id }}" @selected(old('batch_id')==$balance->batch_id)>
                        {{ $balance->medicine->code }} · {{ $balance->batch->number }} · {{ $balance->location->name }} · {{ number_format((float)$balance->quantity,0,',','.') }} {{ $balance->medicine->unit?->name }}
                    </option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="label text-xs font-semibold text-slate-700" for="transfer-source">Lokasi sumber</label>
            <select class="field w-full text-xs" id="transfer-source" name="source_location_id" required>
                @foreach($locations as $location)
                    <option value="{{ $location->id }}" @selected(old('source_location_id')==$location->id)>{{ $location->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="label text-xs font-semibold text-slate-700" for="transfer-target">Lokasi tujuan</label>
            <select class="field w-full text-xs" id="transfer-target" name="destination_location_id" required>
                @foreach($locations as $location)
                    <option value="{{ $location->id }}" @selected(old('destination_location_id')==$location->id)>{{ $location->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="label text-xs font-semibold text-slate-700" for="transfer-quantity">Jumlah satuan dasar</label>
            <input class="field w-full text-xs" id="transfer-quantity" name="quantity" type="number" min="0.0001" step="0.0001" placeholder="0" value="{{ old('quantity') }}" required>
        </div>
        <div>
            <label class="label text-xs font-semibold text-slate-700" for="transfer-time">Waktu transfer</label>
            <input class="field w-full text-xs" id="transfer-time" name="occurred_at" type="datetime-local" value="{{ old('occurred_at',now('Asia/Jakarta')->format('Y-m-d\\TH:i')) }}" required>
        </div>
        <div class="md:col-span-2 xl:col-span-3">
            <label class="label text-xs font-semibold text-slate-700" for="transfer-notes">Catatan</label>
            <input class="field w-full text-xs" id="transfer-notes" name="notes" value="{{ old('notes') }}" placeholder="Catatan mutasi antar unit (opsional)" maxlength="1000">
        </div>
        <div class="flex items-end">
            <button class="btn w-full bg-[#3277B2] hover:bg-[#266194] text-white font-bold text-xs">Posting Transfer</button>
        </div>
    </form>
    @endcan

    <div class="mb-6 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        @forelse($balances as $balance)
            <div class="card p-4">
                <p class="font-bold text-slate-900 tracking-tight text-sm">{{ $balance->medicine->name }}</p>
                <p class="mt-1 text-xs font-semibold text-[#3277B2]">{{ $balance->location->name }}</p>
                <p class="mt-2 text-lg font-bold tabular-nums text-slate-900">{{ number_format((float)$balance->quantity,0,',','.') }} <span class="text-xs font-medium text-slate-500">{{ $balance->medicine->unit?->name }}</span></p>
            </div>
        @empty
            <div class="card p-4 text-slate-500 font-medium">Belum ada saldo untuk ditransfer.</div>
        @endforelse
    </div>

    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full whitespace-nowrap text-left text-sm">
                <thead class="bg-[#edf5fb] border-b border-[#d6e8f6] text-[#266194]">
                    <tr>
                        <th class="p-4 text-[11px] font-bold uppercase tracking-wider">Waktu</th>
                        <th class="p-4 text-[11px] font-bold uppercase tracking-wider">Referensi</th>
                        <th class="p-4 text-[11px] font-bold uppercase tracking-wider">Obat</th>
                        <th class="p-4 text-[11px] font-bold uppercase tracking-wider">Perpindahan</th>
                        <th class="p-4 text-[11px] font-bold uppercase tracking-wider">Jumlah</th>
                        <th class="p-4 text-[11px] font-bold uppercase tracking-wider">Petugas</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($rows as $row)
                        <tr class="hover:bg-slate-50/70 transition-colors">
                            <td class="p-4 text-xs font-medium text-slate-600 tabular-nums">{{ $row->occurred_at->format('d/m/Y H:i') }}</td>
                            <td class="p-4">
                                <span class="inline-flex rounded-md bg-slate-100 px-2 py-0.5 font-mono text-[11px] font-semibold text-slate-700">{{ $row->reference }}</span>
                            </td>
                            <td class="p-4 font-bold text-slate-900 tracking-tight">{{ $row->medicine->name }}</td>
                            <td class="p-4 text-slate-700 font-medium">
                                <span class="inline-flex items-center gap-1.5 rounded-lg bg-[#edf5fb] text-[#266194] px-2.5 py-1 text-xs font-semibold">
                                    {{ $row->sourceLocation->name }} → {{ $row->destinationLocation->name }}
                                </span>
                            </td>
                            <td class="p-4 font-bold tabular-nums text-slate-900">
                                {{ number_format((float)$row->quantity, 0, ',', '.') }} {{ $row->medicine->unit?->name }}
                            </td>
                            <td class="p-4 text-slate-600 font-medium text-xs">{{ $row->creator->name }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="p-8 text-center text-slate-500 font-medium">Belum ada transfer yang diposting.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-4">{{ $rows->links() }}</div>
</x-layouts.app>
