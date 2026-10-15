<x-layouts.app title="Penerimaan Obat">
    @if(session('status'))
        <div class="mb-4 rounded-lg border border-[#b2d4ee] bg-[#edf5fb] p-3 text-sm font-semibold text-[#266194]">{{ session('status') }}</div>
    @endif
    @if($errors->any())
        <div class="mb-4 rounded-lg border border-rose-200 bg-rose-50 p-3 text-sm font-semibold text-rose-700">{{ $errors->first() }}</div>
    @endif

    <nav class="mb-4 flex gap-5 border-b border-slate-200 text-sm" aria-label="Penerimaan">
        @can('operate-inventory')
            <a href="{{ route('preview.receipts') }}" class="border-b-2 px-1 py-2 {{ !$receiptHistory ? 'border-blue-600 text-blue-700' : 'border-transparent text-slate-500' }}">Input Penerimaan</a>
        @endcan
        <a href="{{ route('preview.receipts', ['tab' => 'riwayat']) }}" class="border-b-2 px-1 py-2 {{ $receiptHistory ? 'border-blue-600 text-blue-700' : 'border-transparent text-slate-500' }}">Riwayat Penerimaan</a>
    </nav>
    @if(!$receiptHistory)
    @can('operate-inventory')
        @php
            $draftItems = $editingReceipt?->items->map(fn ($item) => array_merge($item->toArray(), [
                'expiry_date' => $item->expiry_date?->format('Y-m-d'),
                'input_quantity' => \App\Support\Quantity::input($item->input_quantity),
                'conversion_factor' => \App\Support\Quantity::input($item->conversion_factor),
                'quantity' => \App\Support\Quantity::input($item->quantity),
            ]))->all();
            $oldItems = old('items', $draftItems ?? [
                ['medicine_id' => '', 'batch_number' => '', 'expiry_date' => '', 'input_quantity' => '', 'input_unit_name' => '', 'conversion_factor' => '', 'quantity' => '', 'notes' => ''],
                ['medicine_id' => '', 'batch_number' => '', 'expiry_date' => '', 'input_quantity' => '', 'input_unit_name' => '', 'conversion_factor' => '', 'quantity' => '', 'notes' => ''],
            ]);
            $defaultLocationId = old('destination_location_id', $editingReceipt?->destination_location_id ?? $defaultReceiptLocationId ?? $locations->firstWhere('code', 'GDG')?->id ?? $locations->first()?->id);
        @endphp
        @if($editingReceipt)
            <p class="mb-4 text-sm font-semibold">Edit Draf {{ $editingReceipt->reference }}</p>
        @endif
        <form method="POST" action="{{ $editingReceipt ? route('receipts.update', $editingReceipt) : route('receipts.store') }}" class="receipt-entry mb-5" data-check-invoice="{{ route('receipts.check-invoice') }}" data-receipt-id="{{ $editingReceipt?->id }}" data-required-alert="Lengkapi data penerimaan" novalidate>
            @csrf
            @if($editingReceipt) @method('PUT') @endif
            <label class="mb-4 flex items-start gap-2 rounded-md border border-amber-300 bg-amber-50 p-3 text-xs text-amber-900" data-duplicate-invoice-warning @if(!$errors->has('duplicate_invoice')) hidden @endif>
                <input type="checkbox" name="duplicate_invoice_confirmed" value="1" data-duplicate-invoice-confirmed>
                <span>Supplier dan nomor faktur ini sudah tercatat. Saya sudah memeriksa riwayat dan memastikan penerimaan ini memang berbeda.</span>
            </label>
            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
                <div>
                    <label class="label" for="receipt-reference">Nomor penerimaan otomatis</label>
                    <input class="field bg-slate-50 text-xs font-semibold text-slate-700" id="receipt-reference" name="reference" value="{{ old('reference', $editingReceipt?->reference ?? $nextReference) }}" readonly required maxlength="100">
                </div>
                <div>
                    <label class="label" for="receipt-invoice">Nomor faktur supplier</label>
                    <input class="field text-xs" id="receipt-invoice" name="supplier_invoice_number" value="{{ old('supplier_invoice_number', $editingReceipt?->supplier_invoice_number) }}" maxlength="100" placeholder="Sesuai faktur yang diterima">
                </div>
                <div>
                    <label class="label" for="receipt-supplier">Supplier / PBF</label>
                    <input class="field text-xs" id="receipt-supplier" name="counterparty" value="{{ old('counterparty', $editingReceipt?->counterparty) }}" placeholder="Nama Distributor Farmasi" maxlength="255">
                </div>
                <div>
                    <label class="label" for="receipt-time">Waktu diterima fisik</label>
                    <input class="field text-xs" id="receipt-time" name="occurred_at" type="datetime-local" value="{{ old('occurred_at',($editingReceipt?->received_at ?? now('Asia/Jakarta'))->format('Y-m-d\\TH:i')) }}" required>
                </div>
                <div>
                    <label class="label" for="receipt-destination">Lokasi tujuan</label>
                    <select class="field text-xs" id="receipt-destination" name="destination_location_id" required>
                        @foreach($locations as $location)
                            <option value="{{ $location->id }}" @selected((string) $defaultLocationId === (string) $location->id)>{{ $location->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="label" for="receipt-notes">Catatan dokumen</label>
                    <input class="field text-xs" id="receipt-notes" name="notes" value="{{ old('notes', $editingReceipt?->notes) }}" placeholder="Keterangan umum faktur" maxlength="1000">
                </div>
            </div>

            <div class="mt-5 overflow-hidden rounded-lg border border-slate-200">
                <div class="flex items-center justify-between border-b border-slate-200 bg-slate-50 px-3 py-2">
                    <p class="text-xs font-bold uppercase tracking-wider text-slate-500">Item Penerimaan</p>
                    <button type="button" class="btn-secondary px-2.5 py-1 text-xs" data-receipt-add-row>+ Baris</button>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[960px] text-left text-sm">
                        <thead class="bg-[#edf5fb] text-[#266194]">
                            <tr>
                                <th class="p-3">Nama Obat</th>
                                <th class="p-3">Batch</th>
                                <th class="p-3">Kedaluwarsa</th>
                                <th class="p-3">Qty Faktur</th>
                                <th class="p-3">Satuan Faktur</th>
                                <th class="p-3">Jumlah Stok</th>
                                <th class="p-3 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100" data-receipt-items>
                            @foreach($oldItems as $index => $item)
                                <tr data-receipt-row>
                                    <td class="p-3">
                                        <span class="block px-3 pt-1 text-[10px] text-slate-500" data-receipt-row-label>Baris {{ $index + 1 }}</span>
                                        <select class="field text-xs" name="items[{{ $index }}][medicine_id]" data-searchable-select data-search-placeholder="Cari kode atau nama obat..." data-receipt-medicine {{ $index === 0 ? 'required' : '' }}>
                                            <option value="">Pilih obat</option>
                                            @foreach($medicines as $medicine)
                                                <option value="{{ $medicine->id }}" data-packages="{{ $medicine->packages->map(fn ($package) => ['name' => $package->name, 'factor' => $package->conversion_factor])->toJson() }}" data-stock-unit="{{ $medicine->unit?->name }}" data-purchase-unit="{{ $medicine->packages->first()?->name ?: ($medicine->purchase_unit_name ?: $medicine->unit?->name) }}" data-conversion="{{ $medicine->packages->first()?->conversion_factor ?? $medicine->conversion_candidate }}" @selected(($item['medicine_id'] ?? '') == $medicine->id)>{{ $medicine->code }} - {{ $medicine->name }} ({{ $medicine->unit?->name }})</option>
                                            @endforeach
                                        </select>
                                        <x-receipt-field-error :name="'items.'.$index.'.medicine_id'" :message="$errors->first('items.'.$index.'.medicine_id')" />
                                    </td>
                                    <td class="p-3"><input class="field text-xs" name="items[{{ $index }}][batch_number]" value="{{ $item['batch_number'] ?? '' }}" placeholder="Batch" {{ $index === 0 ? 'required' : '' }} maxlength="100"><x-receipt-field-error :name="'items.'.$index.'.batch_number'" :message="$errors->first('items.'.$index.'.batch_number')" /></td>
                                    <td class="p-3"><input class="field text-xs" name="items[{{ $index }}][expiry_date]" type="date" value="{{ $item['expiry_date'] ?? '' }}" {{ $index === 0 ? 'required' : '' }}><x-receipt-field-error :name="'items.'.$index.'.expiry_date'" :message="$errors->first('items.'.$index.'.expiry_date')" /></td>
                                    <td class="p-3"><input class="field text-xs" name="items[{{ $index }}][input_quantity]" data-receipt-input-quantity type="number" min="0.0001" step="0.0001" value="{{ $item['input_quantity'] ?? '' }}" placeholder="0"><x-receipt-field-error :name="'items.'.$index.'.input_quantity'" :message="$errors->first('items.'.$index.'.input_quantity')" /></td>
                                    <td class="p-3"><select class="field text-xs" name="items[{{ $index }}][input_unit_name]" data-receipt-input-unit data-initial-unit="{{ $item['input_unit_name'] ?? '' }}"><option value="">Pilih satuan</option></select><label class="receipt-conversion flex items-center gap-1 px-2 pb-2 text-[11px] text-slate-500" data-receipt-conversion-label hidden><span class="whitespace-nowrap" data-receipt-unit-equation></span><input class="field text-xs" name="items[{{ $index }}][conversion_factor]" data-receipt-conversion type="number" min="0.0001" step="0.0001" value="{{ $item['conversion_factor'] ?? '' }}" placeholder="1"><span data-receipt-conversion-unit></span></label><x-receipt-field-error :name="'items.'.$index.'.input_unit_name'" :message="$errors->first('items.'.$index.'.input_unit_name')" /><x-receipt-field-error :name="'items.'.$index.'.conversion_factor'" :message="$errors->first('items.'.$index.'.conversion_factor')" /></td>
                                    <td class="p-3"><input class="field text-xs" name="items[{{ $index }}][quantity]" data-receipt-stock-quantity readonly tabindex="-1" type="number" min="0.0001" step="0.0001" value="{{ $item['quantity'] ?? '' }}" placeholder="Satuan stok" {{ $index === 0 ? 'required' : '' }}><span class="block px-3 pb-2 text-xs text-slate-500" data-receipt-stock-unit></span><x-receipt-field-error :name="'items.'.$index.'.quantity'" :message="$errors->first('items.'.$index.'.quantity')" /></td>
                                    <td class="p-3 text-right"><button type="button" class="btn-secondary px-2.5 py-1 text-xs" data-receipt-remove-row aria-label="Hapus baris" title="Hapus baris">&times;</button></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="mt-4 flex flex-wrap justify-end gap-2">
                <button class="btn-secondary text-xs" name="action" value="draft">Simpan Draf</button>
                <button class="btn text-xs" name="action" value="post">Simpan &amp; Tambah Stok</button>
            </div>
        </form>

        <template id="receipt-row-template">
            <tr data-receipt-row>
                <td class="p-3">
                    <span class="block px-3 pt-1 text-[10px] text-slate-500" data-receipt-row-label></span>
                    <select class="field text-xs" data-name="medicine_id" data-searchable-select data-search-placeholder="Cari kode atau nama obat..." data-receipt-medicine>
                        <option value="">Pilih obat</option>
                        @foreach($medicines as $medicine)
                            <option value="{{ $medicine->id }}" data-packages="{{ $medicine->packages->map(fn ($package) => ['name' => $package->name, 'factor' => $package->conversion_factor])->toJson() }}" data-stock-unit="{{ $medicine->unit?->name }}" data-purchase-unit="{{ $medicine->packages->first()?->name ?: ($medicine->purchase_unit_name ?: $medicine->unit?->name) }}" data-conversion="{{ $medicine->packages->first()?->conversion_factor ?? $medicine->conversion_candidate }}">{{ $medicine->code }} - {{ $medicine->name }} ({{ $medicine->unit?->name }})</option>
                        @endforeach
                    </select>
                </td>
                <td class="p-3"><input class="field text-xs" data-name="batch_number" placeholder="Batch" maxlength="100"></td>
                <td class="p-3"><input class="field text-xs" data-name="expiry_date" type="date"></td>
                <td class="p-3"><input class="field text-xs" data-name="input_quantity" data-receipt-input-quantity type="number" min="0.0001" step="0.0001" placeholder="0"></td>
                <td class="p-3"><select class="field text-xs" data-name="input_unit_name" data-receipt-input-unit><option value="">Pilih satuan</option></select><label class="receipt-conversion flex items-center gap-1 px-2 pb-2 text-[11px] text-slate-500" data-receipt-conversion-label hidden><span class="whitespace-nowrap" data-receipt-unit-equation></span><input class="field text-xs" data-name="conversion_factor" data-receipt-conversion type="number" min="0.0001" step="0.0001" placeholder="1"><span data-receipt-conversion-unit></span></label></td>
                <td class="p-3"><input class="field text-xs" data-name="quantity" data-receipt-stock-quantity readonly tabindex="-1" type="number" min="0.0001" step="0.0001" placeholder="Satuan stok"><span class="block px-3 pb-2 text-xs text-slate-500" data-receipt-stock-unit></span></td>
                <td class="p-3 text-right"><button type="button" class="btn-secondary px-2.5 py-1 text-xs" data-receipt-remove-row aria-label="Hapus baris" title="Hapus baris">&times;</button></td>
            </tr>
        </template>
    @endcan

    @endif
    @if($receiptHistory || !auth()->user()->can('operate-inventory'))
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
                            <th class="p-4">Lokasi</th>
                            <th class="p-4">Item</th>
                            <th class="p-4">Diterima</th>
                            <th class="p-4">Posting</th>
                            <th class="p-4 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($receipts as $receipt)
                            <tr>
                                <td class="p-4 font-mono text-xs font-semibold text-slate-700">{{ $receipt->reference }}<p class="mt-1 font-sans font-normal">Faktur supplier: {{ $receipt->supplier_invoice_number ?: 'Belum dicatat' }}</p></td>
                                <td class="p-4"><span class="badge {{ $receipt->status === 'posted' ? 'badge-ok' : 'badge-warn' }}">{{ $receipt->status === 'posted' ? 'Stok ditambahkan' : 'Draf' }}</span></td>
                                <td class="p-4 text-xs font-medium text-slate-600">{{ $receipt->counterparty ?: '-' }}</td>
                                <td class="p-4 text-xs font-semibold text-slate-600">{{ $receipt->destinationLocation?->name ?? 'Gudang' }}</td>
                                <td class="p-4">
                                    <p class="text-xs font-bold text-slate-900">{{ $receipt->items->count() }} item</p>
                                    <p class="text-xs text-slate-500">{{ \App\Support\Quantity::format($receipt->items->sum(fn ($item) => (float) $item->quantity)) }} satuan dasar</p>
                                </td>
                                <td class="p-4 text-xs font-medium text-slate-600 tabular-nums">{{ $receipt->received_at->format('d/m/Y H:i') }}</td>
                                <td class="p-4 text-xs font-medium text-slate-600 tabular-nums">{{ $receipt->posted_at?->format('d/m/Y H:i') ?? 'Belum posting' }}</td>
                                <td class="p-4">
                                    <div class="flex justify-end gap-2">
                                        @if($receipt->status === 'draft')
                                            @can('operate-inventory')
                                                <a class="btn-secondary px-2.5 py-1 text-xs" href="{{ route('receipts.edit', $receipt) }}">Edit Draf</a>
                                                <form method="POST" action="{{ route('receipts.post', $receipt) }}">
                                                    @csrf
                                                    <button class="btn px-2.5 py-1 text-xs">Tambah ke Stok</button>
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
                                <td colspan="8" class="px-4 py-2">
                                    <div class="flex flex-wrap gap-2">
                                        @foreach($receipt->items as $item)
                                            <span class="inline-flex rounded-md border border-slate-200 bg-white px-2 py-1 text-[11px] font-semibold text-slate-600">
                                                {{ $item->medicine->name }} · {{ $item->batch_number }} · {{ \App\Support\Quantity::format((float)$item->quantity) }} {{ $item->medicine->unit?->name }}
                                                @if($item->input_quantity && ($item->input_unit_name || (float) $item->conversion_factor !== 1.0))
                                                    · faktur {{ \App\Support\Quantity::format((float)$item->input_quantity) }} {{ $item->input_unit_name ?: 'satuan' }} x {{ \App\Support\Quantity::format((float)$item->conversion_factor) }}
                                                @endif
                                            </span>
                                        @endforeach
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="p-8 text-center font-medium text-slate-500">Belum ada dokumen penerimaan.</td></tr>
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
                            <th class="p-4">Masuk Lokasi</th>
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
                                <td class="p-4 font-bold tabular-nums text-[#266194]">+{{ \App\Support\Quantity::format((float)$row->quantity) }} {{ $row->medicine->unit?->name }}<p class="mt-1 text-xs font-semibold text-slate-500">{{ $row->destinationLocation?->name ?? 'Lokasi tidak diketahui' }}</p></td>
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
    @endif
</x-layouts.app>
