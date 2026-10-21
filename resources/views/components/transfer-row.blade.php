@props(['medicines', 'batchBalances', 'index', 'item' => []])
@php
    $medicineId = $index === 0 ? 'transfer-medicine' : 'transfer-medicine-'.$index;
    $batchId = $index === 0 ? 'transfer-batch' : 'transfer-batch-'.$index;
    $quantityId = $index === 0 ? 'transfer-quantity' : 'transfer-quantity-'.$index;
@endphp
<tr data-transfer-row data-transfer-index="{{ $index }}">
    <td class="relative align-top">
        <span class="block px-3 pt-2 text-[10px] text-slate-500" data-transfer-row-label>{{ is_int($index) ? 'Baris '.($index + 1) : '' }}</span>
        <label for="{{ $medicineId }}" class="sr-only">Obat</label>
        <select class="field text-xs" id="{{ $medicineId }}" name="items[{{ $index }}][medicine_id]" data-transfer-medicine data-searchable-select data-search-placeholder="Cari kode atau nama obat...">
            <option value="">Pilih obat</option>
            @foreach($medicines as $medicine)
                <option value="{{ $medicine->id }}" data-stock-unit="{{ $medicine->unit?->name }}" @selected(($item['medicine_id'] ?? '') == $medicine->id)>{{ $medicine->code }} - {{ $medicine->name }} ({{ $medicine->unit?->name }})</option>
            @endforeach
        </select>
        @error('items.'.$index.'.medicine_id')<p class="error px-3 pb-2" role="alert">{{ $message }}</p>@enderror
        <div class="break-words px-3 pb-3 text-xs text-slate-500" data-stock-entry-context data-stock-context-source="#transfer-stock-context"
             data-medicine="#{{ $medicineId }}" data-location="#transfer-source" data-quantity="#{{ $quantityId }}" data-batch="#{{ $batchId }}" data-fefo="1">
            <p data-stock-context-summary aria-live="polite">Pilih obat untuk melihat stok sumber.</p>
            <p class="mt-1" data-stock-context-plan aria-live="polite"></p>
        </div>
    </td>
    <td class="relative align-top">
        <label for="{{ $batchId }}" class="sr-only">Batch</label>
        <select class="field text-xs" id="{{ $batchId }}" name="items[{{ $index }}][batch_id]" data-transfer-batch data-searchable-select
                data-empty-label="Otomatis: kedaluwarsa terdekat" data-search-placeholder="Cari batch..."
                data-depends-on="#{{ $medicineId }},#transfer-source" data-depends-empty="Pilih obat dan lokasi sumber dulu">
            <option value="">Otomatis: kedaluwarsa terdekat</option>
            @foreach($batchBalances as $balance)
                <option value="{{ $balance->batch_id }}" data-depends-values="{{ $balance->medicine_id }}|{{ $balance->location_id }}" @selected(($item['batch_id'] ?? '') == $balance->batch_id)>{{ $balance->batch->number }} - {{ $balance->location->name }} - ED {{ $balance->batch->expiry_date?->format('d/m/Y') ?? '-' }} - {{ \App\Support\Quantity::format((float) $balance->quantity) }} {{ $balance->medicine->unit?->name }}</option>
            @endforeach
        </select>
        @error('items.'.$index.'.batch_id')<p class="error px-3 pb-2" role="alert">{{ $message }}</p>@enderror
    </td>
    <td class="relative align-top">
        <label for="{{ $quantityId }}" class="sr-only">Jumlah yang dipindahkan</label>
        <input class="field text-xs" id="{{ $quantityId }}" name="items[{{ $index }}][quantity]" data-transfer-quantity type="number" min="0.0001" max="999999999999.9999" step="0.0001" placeholder="0" value="{{ $item['quantity'] ?? '' }}">
        <span class="block px-3 pb-2 text-xs text-slate-500" data-transfer-unit></span>
        @error('items.'.$index.'.quantity')<p class="error px-3 pb-2" role="alert">{{ $message }}</p>@enderror
    </td>
    <td class="text-center align-top">
        <button type="button" class="m-2 inline-flex h-8 w-8 items-center justify-center rounded-md text-slate-500 hover:bg-rose-50 hover:text-rose-700 disabled:cursor-not-allowed disabled:opacity-30" data-transfer-remove-row title="Hapus baris" aria-label="Hapus baris"><span data-transfer-remove-icon aria-hidden="true">&times;</span></button>
    </td>
</tr>
