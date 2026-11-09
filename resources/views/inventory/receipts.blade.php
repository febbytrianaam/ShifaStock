<x-layouts.app title="Penerimaan Obat">
    @php
        $canOperate = auth()->user()->can('operate-inventory');
        $code = 'inline-block rounded bg-slate-100 px-1.5 font-mono text-[11px] font-semibold text-slate-600';
        $small = 'min-h-11 px-2.5 py-1 text-xs lg:min-h-8';
        $receiptTabs = array_values(array_filter([
            $canOperate ? ['label' => 'Input Penerimaan', 'href' => route('preview.receipts'), 'active' => ! $receiptHistory] : null,
            ['label' => 'Riwayat Penerimaan', 'href' => route('preview.receipts', ['tab' => 'riwayat']), 'active' => $receiptHistory || ! $canOperate, 'count' => $receipts->total()],
        ]));
    @endphp
    @if(session('status'))
        <div role="status" class="callout-ok mb-4 font-semibold">{{ session('status') }}</div>
    @endif
    @if($errors->any())
        <div role="alert" class="callout-danger mb-4 font-semibold">{{ $errors->first() }}</div>
    @endif

    <x-ui.tabs label="Penerimaan" class="mb-4" :links="$receiptTabs" />
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
            $headerField = 'field min-h-11 text-xs lg:min-h-9';
        @endphp
        <form method="POST" action="{{ $editingReceipt ? route('receipts.update', $editingReceipt) : route('receipts.store') }}" class="receipt-entry flex flex-col gap-4" data-check-invoice="{{ route('receipts.check-invoice') }}" data-receipt-id="{{ $editingReceipt?->id }}" data-required-alert="Lengkapi data penerimaan" novalidate>
            @csrf
            @if($editingReceipt) @method('PUT') @endif
            <label class="callout-warn flex items-start gap-2.5" data-duplicate-invoice-warning @if(!$errors->has('duplicate_invoice')) hidden @endif>
                <input type="checkbox" name="duplicate_invoice_confirmed" value="1" data-duplicate-invoice-confirmed class="mt-1 shrink-0">
                <span>Supplier dan nomor faktur ini sudah tercatat. Saya sudah memeriksa riwayat dan memastikan penerimaan ini memang berbeda.</span>
            </label>

            <x-ui.section title="Dokumen penerimaan" icon="file">
                @if($editingReceipt)
                    <x-slot:actions><span class="badge badge-warn">Edit Draf {{ $editingReceipt->reference }}</span></x-slot:actions>
                @endif
                <div class="grid gap-x-4 gap-y-3.5 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-6">
                    <div>
                        <label class="label" for="receipt-reference">Nomor penerimaan otomatis</label>
                        <input class="{{ $headerField }} bg-slate-50 font-mono font-semibold text-slate-700" id="receipt-reference" name="reference" value="{{ old('reference', $editingReceipt?->reference ?? $nextReference) }}" readonly required maxlength="100">
                    </div>
                    <div>
                        <label class="label" for="receipt-invoice">Nomor faktur supplier</label>
                        <input class="{{ $headerField }}" id="receipt-invoice" name="supplier_invoice_number" value="{{ old('supplier_invoice_number', $editingReceipt?->supplier_invoice_number) }}" maxlength="100" placeholder="Sesuai faktur yang diterima">
                    </div>
                    <div>
                        <label class="label" for="receipt-supplier">Supplier / PBF</label>
                        <input class="{{ $headerField }}" id="receipt-supplier" name="counterparty" value="{{ old('counterparty', $editingReceipt?->counterparty) }}" placeholder="Nama Distributor Farmasi" maxlength="255">
                    </div>
                    <div>
                        <label class="label" for="receipt-time">Waktu diterima fisik</label>
                        <input class="{{ $headerField }}" id="receipt-time" name="occurred_at" type="datetime-local" value="{{ old('occurred_at',($editingReceipt?->received_at ?? now('Asia/Jakarta'))->format('Y-m-d\\TH:i')) }}" required>
                    </div>
                    <div>
                        <label class="label" for="receipt-destination">Lokasi tujuan</label>
                        <select class="{{ $headerField }}" id="receipt-destination" name="destination_location_id" required>
                            @foreach($locations as $location)
                                <option value="{{ $location->id }}" @selected((string) $defaultLocationId === (string) $location->id)>{{ $location->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="label" for="receipt-notes">Catatan dokumen</label>
                        <input class="{{ $headerField }}" id="receipt-notes" name="notes" value="{{ old('notes', $editingReceipt?->notes) }}" placeholder="Keterangan umum faktur" maxlength="1000">
                    </div>
                </div>
            </x-ui.section>

            <x-ui.section title="Item penerimaan" subtitle="Satu baris per batch. Jumlah stok dihitung dari qty faktur dan isi kemasan." icon="list" tone="teal" flush>
                <x-slot:actions>
                    <button type="button" class="btn-secondary {{ $small }}" data-receipt-add-row><x-ui.icon name="plus" class="h-3.5 w-3.5" />Baris</button>
                </x-slot:actions>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[960px] text-left text-[13px]">
                        <thead class="table-head">
                            <tr>
                                <th>Nama Obat</th>
                                <th>Batch</th>
                                <th>Kedaluwarsa</th>
                                <th>Qty Faktur</th>
                                <th>Satuan Faktur</th>
                                <th>Jumlah Stok</th>
                                <th class="text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody data-receipt-items>
                            @foreach($oldItems as $index => $item)
                                <tr data-receipt-row>
                                    <td>
                                        <span class="block px-3 pt-2 text-[11px] font-semibold text-slate-400" data-receipt-row-label>Baris {{ $index + 1 }}</span>
                                        <select class="field text-xs" name="items[{{ $index }}][medicine_id]" data-searchable-select data-search-placeholder="Cari kode atau nama obat..." data-receipt-medicine {{ $index === 0 ? 'required' : '' }}>
                                            <option value="">Pilih obat</option>
                                            @foreach($medicines as $medicine)
                                                <option value="{{ $medicine->id }}" data-packages="{{ $medicine->packages->map(fn ($package) => ['name' => $package->name, 'factor' => $package->conversion_factor])->toJson() }}" data-stock-unit="{{ $medicine->unit?->name }}" data-purchase-unit="{{ $medicine->packages->first()?->name ?: ($medicine->purchase_unit_name ?: $medicine->unit?->name) }}" data-conversion="{{ $medicine->packages->first()?->conversion_factor ?? $medicine->conversion_candidate }}" @selected(($item['medicine_id'] ?? '') == $medicine->id)>{{ $medicine->code }} - {{ $medicine->name }} ({{ $medicine->unit?->name }})</option>
                                            @endforeach
                                        </select>
                                        <x-receipt-field-error :name="'items.'.$index.'.medicine_id'" :message="$errors->first('items.'.$index.'.medicine_id')" />
                                    </td>
                                    <td><input class="field text-xs" name="items[{{ $index }}][batch_number]" value="{{ $item['batch_number'] ?? '' }}" placeholder="Batch" {{ $index === 0 ? 'required' : '' }} maxlength="100"><x-receipt-field-error :name="'items.'.$index.'.batch_number'" :message="$errors->first('items.'.$index.'.batch_number')" /></td>
                                    <td><input class="field text-xs" name="items[{{ $index }}][expiry_date]" type="date" value="{{ $item['expiry_date'] ?? '' }}" {{ $index === 0 ? 'required' : '' }}><x-receipt-field-error :name="'items.'.$index.'.expiry_date'" :message="$errors->first('items.'.$index.'.expiry_date')" /></td>
                                    <td><input class="field text-xs tabular-nums" name="items[{{ $index }}][input_quantity]" data-receipt-input-quantity type="number" min="0.0001" step="0.0001" value="{{ $item['input_quantity'] ?? '' }}" placeholder="0"><x-receipt-field-error :name="'items.'.$index.'.input_quantity'" :message="$errors->first('items.'.$index.'.input_quantity')" /></td>
                                    <td><select class="field text-xs" name="items[{{ $index }}][input_unit_name]" data-receipt-input-unit data-initial-unit="{{ $item['input_unit_name'] ?? '' }}"><option value="">Pilih satuan</option></select><label class="receipt-conversion flex items-center gap-1.5 px-3 pb-2.5 text-[11px] text-slate-500" data-receipt-conversion-label hidden><span class="whitespace-nowrap" data-receipt-unit-equation></span><input class="field text-xs" name="items[{{ $index }}][conversion_factor]" data-receipt-conversion type="number" min="0.0001" step="0.0001" value="{{ $item['conversion_factor'] ?? '' }}" placeholder="1"><span data-receipt-conversion-unit></span></label><x-receipt-field-error :name="'items.'.$index.'.input_unit_name'" :message="$errors->first('items.'.$index.'.input_unit_name')" /><x-receipt-field-error :name="'items.'.$index.'.conversion_factor'" :message="$errors->first('items.'.$index.'.conversion_factor')" /></td>
                                    <td><input class="field text-xs font-semibold tabular-nums" name="items[{{ $index }}][quantity]" data-receipt-stock-quantity readonly tabindex="-1" type="number" min="0.0001" step="0.0001" value="{{ $item['quantity'] ?? '' }}" placeholder="Satuan stok" {{ $index === 0 ? 'required' : '' }}><span class="block px-3 pb-2 text-xs text-slate-500" data-receipt-stock-unit></span><x-receipt-field-error :name="'items.'.$index.'.quantity'" :message="$errors->first('items.'.$index.'.quantity')" /></td>
                                    <td class="text-center"><button type="button" class="inline-flex h-11 w-11 items-center justify-center rounded-md text-slate-500 hover:bg-rose-50 hover:text-rose-700 lg:h-8 lg:w-8" data-receipt-remove-row aria-label="Hapus baris" title="Hapus baris"><x-ui.icon name="trash" /></button></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-ui.section>

            <div class="flex flex-wrap items-center justify-end gap-2">
                <p class="w-full text-xs text-slate-500 sm:mr-auto sm:w-auto">Simpan Draf belum mengubah stok.</p>
                <button class="btn-secondary min-h-11 flex-1 sm:flex-none lg:min-h-9" name="action" value="draft">Simpan Draf</button>
                <button class="btn min-h-11 flex-1 sm:flex-none lg:min-h-9" name="action" value="post">Simpan &amp; Tambah Stok</button>
            </div>
        </form>

        <template id="receipt-row-template">
            <tr data-receipt-row>
                <td>
                    <span class="block px-3 pt-2 text-[11px] font-semibold text-slate-400" data-receipt-row-label></span>
                    <select class="field text-xs" data-name="medicine_id" data-searchable-select data-search-placeholder="Cari kode atau nama obat..." data-receipt-medicine>
                        <option value="">Pilih obat</option>
                        @foreach($medicines as $medicine)
                            <option value="{{ $medicine->id }}" data-packages="{{ $medicine->packages->map(fn ($package) => ['name' => $package->name, 'factor' => $package->conversion_factor])->toJson() }}" data-stock-unit="{{ $medicine->unit?->name }}" data-purchase-unit="{{ $medicine->packages->first()?->name ?: ($medicine->purchase_unit_name ?: $medicine->unit?->name) }}" data-conversion="{{ $medicine->packages->first()?->conversion_factor ?? $medicine->conversion_candidate }}">{{ $medicine->code }} - {{ $medicine->name }} ({{ $medicine->unit?->name }})</option>
                        @endforeach
                    </select>
                </td>
                <td><input class="field text-xs" data-name="batch_number" placeholder="Batch" maxlength="100"></td>
                <td><input class="field text-xs" data-name="expiry_date" type="date"></td>
                <td><input class="field text-xs tabular-nums" data-name="input_quantity" data-receipt-input-quantity type="number" min="0.0001" step="0.0001" placeholder="0"></td>
                <td><select class="field text-xs" data-name="input_unit_name" data-receipt-input-unit><option value="">Pilih satuan</option></select><label class="receipt-conversion flex items-center gap-1.5 px-3 pb-2.5 text-[11px] text-slate-500" data-receipt-conversion-label hidden><span class="whitespace-nowrap" data-receipt-unit-equation></span><input class="field text-xs" data-name="conversion_factor" data-receipt-conversion type="number" min="0.0001" step="0.0001" placeholder="1"><span data-receipt-conversion-unit></span></label></td>
                <td><input class="field text-xs font-semibold tabular-nums" data-name="quantity" data-receipt-stock-quantity readonly tabindex="-1" type="number" min="0.0001" step="0.0001" placeholder="Satuan stok"><span class="block px-3 pb-2 text-xs text-slate-500" data-receipt-stock-unit></span></td>
                <td class="text-center"><button type="button" class="inline-flex h-11 w-11 items-center justify-center rounded-md text-slate-500 hover:bg-rose-50 hover:text-rose-700 lg:h-8 lg:w-8" data-receipt-remove-row aria-label="Hapus baris" title="Hapus baris"><x-ui.icon name="trash" /></button></td>
            </tr>
        </template>
    @endcan

    @endif
    @if($receiptHistory || !auth()->user()->can('operate-inventory'))
    <div class="flex flex-col gap-4">
        <x-ui.section title="Dokumen penerimaan" icon="file" tone="slate" flush :meta="$receipts->total().' dokumen'">
            {{-- One row per document so its reference, items and actions stay together; below xl each row stacks into a card. --}}
            <table class="block w-full text-left text-[13px] xl:table xl:table-fixed">
                <thead class="table-head hidden xl:table-header-group">
                    <tr>
                        <th class="px-4 py-2.5">Referensi &amp; obat</th>
                        <th class="w-[124px] px-4 py-2.5">Status</th>
                        <th class="w-[13%] px-4 py-2.5">Supplier &amp; lokasi</th>
                        <th class="w-[128px] px-4 py-2.5">Item</th>
                        <th class="w-[188px] px-4 py-2.5">Waktu</th>
                        <th class="w-[240px] px-4 py-2.5 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="block divide-y divide-slate-100 xl:table-row-group">
                    @forelse($receipts as $receipt)
                        <tr class="grid grid-cols-2 gap-x-3 gap-y-2 px-4 py-3 xl:table-row xl:p-0">
                            <td class="col-span-2 block min-w-0 xl:table-cell xl:px-4 xl:py-3 xl:align-top">
                                <span class="{{ $code }}">{{ $receipt->reference }}</span>
                                <p class="mt-1 text-xs text-slate-500">Faktur supplier: {{ $receipt->supplier_invoice_number ?: 'Belum dicatat' }}</p>
                                <div class="mt-2 flex flex-wrap gap-1.5">
                                    @foreach($receipt->items as $item)
                                        <span class="inline-flex max-w-full rounded-md border border-slate-200 bg-slate-50 px-2 py-0.5 text-[11px] font-semibold text-slate-600 [overflow-wrap:anywhere]">
                                            {{ $item->medicine->name }} · {{ $item->batch_number }} · {{ \App\Support\Quantity::format((float)$item->quantity) }} {{ $item->medicine->unit?->name }}
                                            @if($item->input_quantity && ($item->input_unit_name || (float) $item->conversion_factor !== 1.0))
                                                · faktur {{ \App\Support\Quantity::format((float)$item->input_quantity) }} {{ $item->input_unit_name ?: 'satuan' }} x {{ \App\Support\Quantity::format((float)$item->conversion_factor) }}
                                            @endif
                                        </span>
                                    @endforeach
                                </div>
                            </td>
                            <td class="block xl:table-cell xl:px-4 xl:py-3 xl:align-top"><span class="badge {{ $receipt->status === 'posted' ? 'badge-ok' : 'badge-warn' }}">{{ $receipt->status === 'posted' ? 'Stok ditambahkan' : 'Draf' }}</span></td>
                            <td class="block min-w-0 text-right xl:table-cell xl:px-4 xl:py-3 xl:text-left xl:align-top">
                                <p class="text-slate-700">{{ $receipt->counterparty ?: '-' }}</p>
                                <p class="text-xs text-slate-500">ke {{ $receipt->destinationLocation?->name ?? 'Gudang' }}</p>
                            </td>
                            <td class="block xl:table-cell xl:px-4 xl:py-3 xl:align-top">
                                <p class="font-extrabold tabular-nums text-slate-900">{{ $receipt->items->count() }} item</p>
                                <p class="whitespace-nowrap text-xs tabular-nums text-slate-500">{{ \App\Support\Quantity::format($receipt->items->sum(fn ($item) => (float) $item->quantity)) }} satuan dasar</p>
                            </td>
                            <td class="block text-right text-xs tabular-nums text-slate-500 xl:table-cell xl:px-4 xl:py-3 xl:text-left xl:align-top">
                                <p class="whitespace-nowrap">Diterima <span class="font-semibold text-slate-700">{{ $receipt->received_at->format('d/m/Y H:i') }}</span></p>
                                <p class="mt-0.5 whitespace-nowrap">@if($receipt->posted_at)Posting <span class="font-semibold text-slate-700">{{ $receipt->posted_at->format('d/m/Y H:i') }}</span>@else<span class="text-slate-400">Belum posting</span>@endif</p>
                            </td>
                            <td class="col-span-2 block xl:table-cell xl:px-4 xl:py-3 xl:align-top">
                                <div class="flex flex-wrap gap-1.5 xl:justify-end">
                                    @if($receipt->status === 'draft')
                                        @can('operate-inventory')
                                            <a class="btn-secondary {{ $small }}" href="{{ route('receipts.edit', $receipt) }}">Edit Draf</a>
                                            <form method="POST" action="{{ route('receipts.post', $receipt) }}">
                                                @csrf
                                                <button class="btn {{ $small }}">Tambah ke Stok</button>
                                            </form>
                                            <form method="POST" action="{{ route('receipts.destroy', $receipt) }}" data-confirm-receipt-draft>
                                                @csrf
                                                @method('DELETE')
                                                <button class="btn-danger {{ $small }}">Batalkan</button>
                                            </form>
                                        @endcan
                                    @else
                                        <span class="text-xs font-semibold text-slate-400">Selesai</span>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr class="block xl:table-row"><td colspan="6" class="empty-state block xl:table-cell">Belum ada dokumen penerimaan.</td></tr>
                    @endforelse
                </tbody>
            </table>
            @if($receipts->hasPages())<div class="border-t border-slate-100 px-4 py-3">{{ $receipts->links() }}</div>@endif
        </x-ui.section>

        <x-ui.section title="Item terposting terbaru" icon="in" tone="slate" flush :meta="$rows->total().' item'">
            <div class="overflow-x-auto">
                <table class="w-full whitespace-nowrap text-left text-[13px]">
                    <thead class="table-head">
                        <tr>
                            <th class="px-4 py-2.5">Waktu</th>
                            <th class="px-4 py-2.5">Referensi</th>
                            <th class="px-4 py-2.5">Obat / Batch</th>
                            <th class="px-4 py-2.5">Supplier</th>
                            <th class="px-4 py-2.5">Masuk Lokasi</th>
                            <th class="px-4 py-2.5">Petugas</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($rows as $row)
                            <tr>
                                <td class="px-4 py-2.5 tabular-nums text-slate-600">{{ $row->occurred_at->format('d/m/Y H:i') }}</td>
                                <td class="px-4 py-2.5"><span class="{{ $code }}">{{ $row->reference }}</span></td>
                                <td class="px-4 py-2.5"><b class="font-extrabold text-slate-900">{{ $row->medicine->name }}</b><p class="text-xs text-slate-500">{{ $row->batch?->number }} · ED {{ $row->batch?->expiry_date?->format('d/m/Y') }}</p></td>
                                <td class="px-4 py-2.5 text-slate-700">{{ $row->counterparty ?: '-' }}</td>
                                <td class="px-4 py-2.5"><b class="font-extrabold tabular-nums text-emerald-700">+{{ \App\Support\Quantity::format((float)$row->quantity) }} {{ $row->medicine->unit?->name }}</b><p class="text-xs text-slate-500">{{ $row->destinationLocation?->name ?? 'Lokasi tidak diketahui' }}</p></td>
                                <td class="px-4 py-2.5 text-slate-600">{{ $row->creator->name }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="empty-state">Belum ada penerimaan yang diposting.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($rows->hasPages())<div class="border-t border-slate-100 px-4 py-3">{{ $rows->links() }}</div>@endif
        </x-ui.section>
    </div>
    @endif
</x-layouts.app>
