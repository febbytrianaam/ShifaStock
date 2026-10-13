<x-layouts.app title="Penerimaan Obat">
    @if(session('status'))
        <div class="mb-4 rounded-lg border border-[#b2d4ee] bg-[#edf5fb] p-3 text-sm font-semibold text-[#266194]">{{ session('status') }}</div>
    @endif
    @if($errors->any())
        <div class="mb-4 rounded-lg border border-rose-200 bg-rose-50 p-3 text-sm font-semibold text-rose-700">{{ $errors->first() }}</div>
    @endif

    @can('operate-inventory')
        @php
            $oldItems = old('items', [
                ['medicine_id' => '', 'batch_number' => '', 'expiry_date' => '', 'quantity' => '', 'notes' => ''],
                ['medicine_id' => '', 'batch_number' => '', 'expiry_date' => '', 'quantity' => '', 'notes' => ''],
            ]);
        @endphp
        <form method="POST" action="{{ route('receipts.store') }}" class="card mb-5 p-4" data-required-alert="Lengkapi data penerimaan" novalidate>
            @csrf
            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <div>
                    <label class="label" for="receipt-reference">Nomor faktur / referensi</label>
                    <input class="field bg-slate-50 text-xs font-semibold text-slate-700" id="receipt-reference" name="reference" value="{{ old('reference', $nextReference) }}" readonly required maxlength="100">
                </div>
                <div>
                    <label class="label" for="receipt-supplier">Supplier / PBF</label>
                    <input class="field text-xs" id="receipt-supplier" name="counterparty" value="{{ old('counterparty') }}" placeholder="Nama Distributor Farmasi" maxlength="255">
                </div>
                <div>
                    <label class="label" for="receipt-time">Waktu diterima fisik</label>
                    <input class="field text-xs" id="receipt-time" name="occurred_at" type="datetime-local" value="{{ old('occurred_at',now('Asia/Jakarta')->format('Y-m-d\\TH:i')) }}" required>
                </div>
                <div>
                    <label class="label" for="receipt-notes">Catatan dokumen</label>
                    <input class="field text-xs" id="receipt-notes" name="notes" value="{{ old('notes') }}" placeholder="Keterangan umum faktur" maxlength="1000">
                </div>
            </div>

            <div class="mt-5 overflow-hidden rounded-lg border border-slate-200">
                <div class="flex items-center justify-between border-b border-slate-200 bg-slate-50 px-3 py-2">
                    <p class="text-xs font-bold uppercase tracking-wider text-slate-500">Item Penerimaan</p>
                    <button type="button" class="btn-secondary px-2.5 py-1 text-xs" data-receipt-add-row>+ Baris</button>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[980px] text-left text-sm">
                        <thead class="bg-[#edf5fb] text-[#266194]">
                            <tr>
                                <th class="p-3">Obat</th>
                                <th class="p-3">Batch</th>
                                <th class="p-3">Kedaluwarsa</th>
                                <th class="p-3">Jumlah</th>
                                <th class="p-3">Catatan Item</th>
                                <th class="p-3 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100" data-receipt-items>
                            @foreach($oldItems as $index => $item)
                                <tr data-receipt-row>
                                    <td class="p-3">
                                        <select class="field text-xs" name="items[{{ $index }}][medicine_id]" data-searchable-select data-search-placeholder="Cari kode atau nama obat..." {{ $index === 0 ? 'required' : '' }}>
                                            <option value="">Pilih obat</option>
                                            @foreach($medicines as $medicine)
                                                <option value="{{ $medicine->id }}" @selected(($item['medicine_id'] ?? '') == $medicine->id)>{{ $medicine->code }} - {{ $medicine->name }} ({{ $medicine->unit?->name }})</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td class="p-3"><input class="field text-xs" name="items[{{ $index }}][batch_number]" value="{{ $item['batch_number'] ?? '' }}" placeholder="Batch" {{ $index === 0 ? 'required' : '' }} maxlength="100"></td>
                                    <td class="p-3"><input class="field text-xs" name="items[{{ $index }}][expiry_date]" type="date" value="{{ $item['expiry_date'] ?? '' }}" {{ $index === 0 ? 'required' : '' }}></td>
                                    <td class="p-3"><input class="field text-xs" name="items[{{ $index }}][quantity]" type="number" min="0.0001" step="0.0001" value="{{ $item['quantity'] ?? '' }}" placeholder="0" {{ $index === 0 ? 'required' : '' }}></td>
                                    <td class="p-3"><input class="field text-xs" name="items[{{ $index }}][notes]" value="{{ $item['notes'] ?? '' }}" placeholder="Opsional" maxlength="1000"></td>
                                    <td class="p-3 text-right"><button type="button" class="btn-secondary px-2.5 py-1 text-xs" data-receipt-remove-row>Hapus</button></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="mt-4 flex flex-wrap justify-end gap-2">
                <button class="btn-secondary text-xs" name="action" value="draft">Simpan Draft</button>
                <button class="btn text-xs" name="action" value="post">Final Posting</button>
            </div>
        </form>

        <template id="receipt-row-template">
            <tr data-receipt-row>
                <td class="p-3">
                    <select class="field text-xs" data-name="medicine_id" data-searchable-select data-search-placeholder="Cari kode atau nama obat...">
                        <option value="">Pilih obat</option>
                        @foreach($medicines as $medicine)
                            <option value="{{ $medicine->id }}">{{ $medicine->code }} - {{ $medicine->name }} ({{ $medicine->unit?->name }})</option>
                        @endforeach
                    </select>
                </td>
                <td class="p-3"><input class="field text-xs" data-name="batch_number" placeholder="Batch" maxlength="100"></td>
                <td class="p-3"><input class="field text-xs" data-name="expiry_date" type="date"></td>
                <td class="p-3"><input class="field text-xs" data-name="quantity" type="number" min="0.0001" step="0.0001" placeholder="0"></td>
                <td class="p-3"><input class="field text-xs" data-name="notes" placeholder="Opsional" maxlength="1000"></td>
                <td class="p-3 text-right"><button type="button" class="btn-secondary px-2.5 py-1 text-xs" data-receipt-remove-row>Hapus</button></td>
            </tr>
        </template>
    @endcan

    <section class="mb-6">
        <div class="mb-3 flex items-center gap-2">
            <span class="h-2 w-2 rounded-full bg-[#3277B2]"></span>
            <h3 class="text-base font-bold tracking-tight text-slate-900">Dokumen Penerimaan</h3>
        </div>
        <div class="card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full whitespace-nowrap text-left text-sm">
                    <thead class="bg-[#edf5fb] border-b border-[#d6e8f6] text-[#266194]">
                        <tr>
                            <th class="p-4">Referensi</th>
                            <th class="p-4">Status</th>
                            <th class="p-4">Supplier</th>
                            <th class="p-4">Item</th>
                            <th class="p-4">Diterima</th>
                            <th class="p-4">Posting</th>
                            <th class="p-4 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($receipts as $receipt)
                            <tr>
                                <td class="p-4 font-mono text-xs font-semibold text-slate-700">{{ $receipt->reference }}</td>
                                <td class="p-4"><span class="badge {{ $receipt->status === 'posted' ? 'badge-ok' : 'badge-warn' }}">{{ $receipt->status === 'posted' ? 'Posted' : 'Draft' }}</span></td>
                                <td class="p-4 text-xs font-medium text-slate-600">{{ $receipt->counterparty ?: '-' }}</td>
                                <td class="p-4">
                                    <p class="text-xs font-bold text-slate-900">{{ $receipt->items->count() }} item</p>
                                    <p class="text-xs text-slate-500">{{ number_format($receipt->items->sum(fn ($item) => (float) $item->quantity),0,',','.') }} satuan dasar</p>
                                </td>
                                <td class="p-4 text-xs font-medium text-slate-600 tabular-nums">{{ $receipt->received_at->format('d/m/Y H:i') }}</td>
                                <td class="p-4 text-xs font-medium text-slate-600 tabular-nums">{{ $receipt->posted_at?->format('d/m/Y H:i') ?? 'Belum posting' }}</td>
                                <td class="p-4">
                                    <div class="flex justify-end gap-2">
                                        @if($receipt->status === 'draft')
                                            @can('operate-inventory')
                                                <form method="POST" action="{{ route('receipts.post', $receipt) }}">
                                                    @csrf
                                                    <button class="btn px-2.5 py-1 text-xs">Posting</button>
                                                </form>
                                                <form method="POST" action="{{ route('receipts.destroy', $receipt) }}" data-confirm-receipt-draft>
                                                    @csrf
                                                    @method('DELETE')
                                                    <button class="btn-secondary px-2.5 py-1 text-xs text-rose-700">Batalkan</button>
                                                </form>
                                            @endcan
                                        @else
                                            <span class="text-xs font-semibold text-slate-400">Selesai</span>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                            <tr class="bg-slate-50/60">
                                <td colspan="7" class="px-4 py-2">
                                    <div class="flex flex-wrap gap-2">
                                        @foreach($receipt->items as $item)
                                            <span class="inline-flex rounded-md border border-slate-200 bg-white px-2 py-1 text-[11px] font-semibold text-slate-600">
                                                {{ $item->medicine->name }} · {{ $item->batch_number }} · {{ number_format((float)$item->quantity,0,',','.') }} {{ $item->medicine->unit?->name }}
                                            </span>
                                        @endforeach
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="p-8 text-center font-medium text-slate-500">Belum ada dokumen penerimaan.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="mt-4">{{ $receipts->links() }}</div>
    </section>

    <section>
        <div class="mb-3 flex items-center gap-2">
            <span class="h-2 w-2 rounded-full bg-slate-400"></span>
            <h3 class="text-base font-bold tracking-tight text-slate-900">Item Terposting Terbaru</h3>
        </div>
        <div class="card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full whitespace-nowrap text-left text-sm">
                    <thead class="bg-[#edf5fb] border-b border-[#d6e8f6] text-[#266194]">
                        <tr>
                            <th class="p-4">Waktu</th>
                            <th class="p-4">Referensi</th>
                            <th class="p-4">Obat / Batch</th>
                            <th class="p-4">Supplier</th>
                            <th class="p-4">Masuk Gudang</th>
                            <th class="p-4">Petugas</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($rows as $row)
                            <tr>
                                <td class="p-4 text-xs font-medium text-slate-600 tabular-nums">{{ $row->occurred_at->format('d/m/Y H:i') }}</td>
                                <td class="p-4"><span class="inline-flex rounded-md bg-slate-100 px-2 py-0.5 font-mono text-[11px] font-semibold text-slate-700">{{ $row->reference }}</span></td>
                                <td class="p-4"><b class="font-bold text-slate-900 tracking-tight">{{ $row->medicine->name }}</b><br><span class="font-mono text-xs text-slate-500">{{ $row->batch?->number }} · ED {{ $row->batch?->expiry_date?->format('d/m/Y') }}</span></td>
                                <td class="p-4 font-medium text-slate-700">{{ $row->counterparty ?: '-' }}</td>
                                <td class="p-4 font-bold tabular-nums text-[#266194]">+{{ number_format((float)$row->quantity, 0, ',', '.') }} {{ $row->medicine->unit?->name }}<p class="mt-1 text-xs font-semibold text-slate-500">{{ $row->destinationLocation?->name ?? 'Lokasi tidak diketahui' }}</p></td>
                                <td class="p-4 text-xs font-medium text-slate-600">{{ $row->creator->name }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="p-8 text-center text-slate-500 font-medium">Belum ada penerimaan yang diposting.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="mt-4">{{ $rows->links() }}</div>
    </section>
</x-layouts.app>
