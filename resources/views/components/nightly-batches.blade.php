@props(['item', 'batches', 'balances', 'editable'])
<details class="mt-2 min-w-64 text-xs" data-nightly-batch-details @if($errors->has('items.'.$item->id.'.batch_counts')) open @endif>
    <summary class="cursor-pointer font-semibold text-blue-700">Hasil hitung per batch</summary>
    <div class="mt-2 space-y-2 border-t border-slate-200 pt-2">
        @forelse($batches as $batch)
            @if($editable || array_key_exists($batch->id, $item->saved_batch_counts))
                <label class="flex flex-wrap items-center justify-between gap-2">
                    <span class="min-w-36">{{ $batch->number }}<span class="block text-[10px] text-slate-500">ED {{ $batch->expiry_date?->format('d/m/Y') ?? '-' }} · Sistem {{ \App\Support\Quantity::format($balances[$batch->id] ?? 0) }}</span></span>
                    @if($editable)
                        <input class="field w-24 text-xs" type="number" min="0" max="2147483647" step="1" placeholder="Hitung fisik" aria-label="Hasil hitung batch {{ $batch->number }} untuk {{ $item->name }}"
                            data-nightly-batch-count data-item-id="{{ $item->id }}" data-batch-id="{{ $batch->id }}" data-system-quantity="{{ $balances[$batch->id] ?? 0 }}"
                            value="{{ old('items.'.$item->id.'.batch_counts.'.$batch->id, $item->saved_batch_counts[$batch->id] ?? '') }}">
                    @else
                        <span class="font-semibold">{{ \App\Support\Quantity::format($item->saved_batch_counts[$batch->id]) }} {{ $item->unit }}</span>
                    @endif
                </label>
            @endif
        @empty
            <p class="text-slate-500">Belum ada batch. Jika ada stok fisik, catat obat dan batch melalui Stok Awal atau Penerimaan dahulu.</p>
        @endforelse
        @if($editable && $batches->isNotEmpty())
            <p class="text-[10px] text-slate-500">Isi semua batch yang memiliki stok; isi 0 jika kosong. Jumlah fisik otomatis dijumlahkan dari isian batch.</p>
        @elseif(!$editable && !$item->saved_batch_counts)
            <p class="text-[10px] text-slate-500">Tidak ada perubahan per batch yang dicatat.</p>
        @endif
        @error('items.'.$item->id.'.batch_counts')<p class="text-rose-700" role="alert">{{ $message }}</p>@enderror
    </div>
</details>
