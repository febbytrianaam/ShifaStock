@props(['medicines', 'batchBalances', 'medicine', 'location', 'quantity', 'batch', 'fefo' => false])
@php
    $contextData = [
        'units' => $medicines->mapWithKeys(fn ($item) => [$item->id => $item->unit?->name]),
        'balances' => $batchBalances->map(fn ($item) => [
            'medicine' => $item->medicine_id, 'location' => $item->location_id,
            'batch' => $item->batch_id, 'number' => $item->batch?->number,
            'expiry' => $item->batch?->expiry_date?->toDateString(),
            'quantity' => (float) $item->quantity, 'id' => $item->id,
        ])->values(),
    ];
@endphp
<div class="col-span-full border-t border-slate-200 pt-3 text-xs text-slate-600" data-stock-entry-context
     data-medicine="{{ $medicine }}" data-location="{{ $location }}" data-quantity="{{ $quantity }}" data-batch="{{ $batch }}" data-fefo="{{ $fefo ? '1' : '0' }}">
    <script type="application/json" data-stock-context-data>@json($contextData)</script>
    <p data-stock-context-summary aria-live="polite">Pilih obat dan lokasi untuk melihat stok tersedia.</p>
    <p class="mt-1" data-stock-context-plan aria-live="polite"></p>
</div>
