<x-layouts.app title="Penerimaan Obat">
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
    <form method="POST" action="{{ route('receipts.store') }}" class="card mb-5 grid gap-4 p-4 md:grid-cols-2 xl:grid-cols-4">
        @csrf
        <div>
            <label class="label text-xs font-semibold text-slate-700" for="receipt-reference">Nomor faktur / referensi</label>
            <input class="field w-full text-xs" id="receipt-reference" name="reference" value="{{ old('reference', $nextReference) }}" placeholder="Contoh: FKT-2026-001" required maxlength="100">
        </div>
        <div>
            <label class="label text-xs font-semibold text-slate-700" for="receipt-supplier">Supplier / PBF</label>
            <input class="field w-full text-xs" id="receipt-supplier" name="counterparty" value="{{ old('counterparty') }}" placeholder="Nama Distributor Farmasi" maxlength="255">
        </div>
        <div class="md:col-span-2">
            <label class="label text-xs font-semibold text-slate-700" for="receipt-medicine">Obat</label>
            <select class="field w-full text-xs" id="receipt-medicine" name="medicine_id" required>
                <option value="">Pilih obat terverifikasi</option>
                @foreach($medicines as $medicine)
                    <option value="{{ $medicine->id }}" @selected(old('medicine_id')==$medicine->id)>{{ $medicine->code }} — {{ $medicine->name }} ({{ $medicine->unit->name }})</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="label text-xs font-semibold text-slate-700" for="receipt-batch">Nomor batch</label>
            <input class="field w-full text-xs" id="receipt-batch" name="batch_number" value="{{ old('batch_number') }}" placeholder="Contoh: BA-2026" required maxlength="100">
        </div>
        <div>
            <label class="label text-xs font-semibold text-slate-700" for="receipt-expiry">Tanggal kedaluwarsa</label>
            <input class="field w-full text-xs" id="receipt-expiry" name="expiry_date" type="date" value="{{ old('expiry_date') }}" required>
        </div>
        <div>
            <label class="label text-xs font-semibold text-slate-700" for="receipt-quantity">Jumlah satuan dasar</label>
            <input class="field w-full text-xs" id="receipt-quantity" name="quantity" type="number" min="0.0001" step="0.0001" placeholder="0" value="{{ old('quantity') }}" required>
        </div>
        <div>
            <label class="label text-xs font-semibold text-slate-700" for="receipt-time">Waktu diterima</label>
            <input class="field w-full text-xs" id="receipt-time" name="occurred_at" type="datetime-local" value="{{ old('occurred_at',now('Asia/Jakarta')->format('Y-m-d\\TH:i')) }}" required>
        </div>
        <div class="md:col-span-2 xl:col-span-3">
            <label class="label text-xs font-semibold text-slate-700" for="receipt-notes">Catatan</label>
            <input class="field w-full text-xs" id="receipt-notes" name="notes" value="{{ old('notes') }}" placeholder="Keterangan tambahan (opsional)" maxlength="1000">
        </div>
        <div class="flex items-end">
            <button class="btn w-full bg-[#3277B2] hover:bg-[#266194] text-white font-bold text-xs">Posting Penerimaan</button>
        </div>
    </form>
    @endcan

    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full whitespace-nowrap text-left text-sm">
                <thead class="bg-[#edf5fb] border-b border-[#d6e8f6] text-[#266194]">
                    <tr>
                        <th class="p-4 text-[11px] font-bold uppercase tracking-wider">Waktu</th>
                        <th class="p-4 text-[11px] font-bold uppercase tracking-wider">Referensi</th>
                        <th class="p-4 text-[11px] font-bold uppercase tracking-wider">Obat / Batch</th>
                        <th class="p-4 text-[11px] font-bold uppercase tracking-wider">Supplier</th>
                        <th class="p-4 text-[11px] font-bold uppercase tracking-wider">Masuk Gudang</th>
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
                            <td class="p-4">
                                <b class="font-bold text-slate-900 tracking-tight">{{ $row->medicine->name }}</b><br>
                                <span class="text-xs font-mono text-slate-500">{{ $row->batch?->number }} · ED {{ $row->batch?->expiry_date?->format('d/m/Y') }}</span>
                            </td>
                            <td class="p-4 text-slate-700 font-medium">{{ $row->counterparty ?: '—' }}</td>
                            <td class="p-4 font-bold tabular-nums text-[#266194]">
                                +{{ number_format((float)$row->quantity, 0, ',', '.') }} {{ $row->medicine->unit?->name }}
                                <p class="mt-1 text-xs font-semibold text-slate-500">{{ $row->destinationLocation?->name ?? 'Lokasi tidak diketahui' }}</p>
                            </td>
                            <td class="p-4 text-slate-600 font-medium text-xs">{{ $row->creator->name }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="p-8 text-center text-slate-500 font-medium">Belum ada penerimaan yang diposting.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-4">{{ $rows->links() }}</div>
</x-layouts.app>
