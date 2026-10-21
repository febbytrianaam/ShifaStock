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
    @php
        $targetId = old('destination_location_id', $prefill['destination_location_id'] ?? $locations->firstWhere('code', 'APT')?->id ?? $locations->last()?->id);
        $sourceId = old('source_location_id', $prefill['source_location_id'] ?? $locations->first(fn ($location) => $location->code === 'GDG' && $location->id != $targetId)?->id ?? $locations->first(fn ($location) => $location->id != $targetId)?->id);
        $items = old('items', [
            ['medicine_id' => old('medicine_id', $prefill['medicine_id'] ?? ''), 'batch_id' => old('batch_id', $prefill['batch_id'] ?? ''), 'quantity' => old('quantity', '')],
            ['medicine_id' => '', 'batch_id' => '', 'quantity' => ''],
        ]);
        $items = collect(is_array($items) ? $items : [])->filter(fn ($item) => is_array($item))->take(50)
            ->map(fn ($item) => array_map(fn ($value) => is_scalar($value) ? $value : '', array_intersect_key($item, array_flip(['medicine_id', 'batch_id', 'quantity']))))->values()->all() ?: [[]];
    @endphp
    <form method="POST" action="{{ route('transfers.store') }}" class="transfer-entry mb-6" data-transfer-form data-required-alert="Lengkapi data transfer" novalidate>
        @csrf
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        <div>
            <label class="label text-xs font-semibold text-slate-700" for="transfer-reference">Referensi</label>
            <input class="field w-full bg-slate-50 text-xs font-semibold text-slate-700" id="transfer-reference" name="reference" value="{{ old('reference', $nextReference) }}" readonly required maxlength="100">
        </div>
        <div>
            <label class="label text-xs font-semibold text-slate-700" for="transfer-source">Lokasi sumber</label>
            <select class="field w-full text-xs" id="transfer-source" name="source_location_id" required>
                @foreach($locations as $location)
                    <option value="{{ $location->id }}" @selected((string) $sourceId === (string) $location->id)>{{ $location->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="label text-xs font-semibold text-slate-700" for="transfer-target">Lokasi tujuan</label>
            <select class="field w-full text-xs" id="transfer-target" name="destination_location_id" required>
                @foreach($locations as $location)
                    <option value="{{ $location->id }}" @selected((string) $targetId === (string) $location->id)>{{ $location->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="label text-xs font-semibold text-slate-700" for="transfer-time">Waktu transfer</label>
            <input class="field w-full text-xs" id="transfer-time" name="occurred_at" type="datetime-local" value="{{ old('occurred_at',now('Asia/Jakarta')->format('Y-m-d\\TH:i')) }}" required>
        </div>
        <div class="md:col-span-2 xl:col-span-4">
            <label class="label text-xs font-semibold text-slate-700" for="transfer-notes">Catatan</label>
            <input class="field w-full text-xs" id="transfer-notes" name="notes" value="{{ old('notes') }}" placeholder="Catatan mutasi antar unit (opsional)" maxlength="1000">
        </div>
        </div>
        <div class="mt-5 overflow-hidden rounded-lg border border-slate-200">
            <div class="flex items-center justify-between gap-3 border-b border-slate-200 bg-slate-50 px-3 py-2">
                <h2 class="text-xs font-semibold text-slate-600">Item Transfer</h2>
                <button type="button" class="btn-secondary px-2.5 py-1 text-xs" data-transfer-add-row><span data-transfer-add-icon aria-hidden="true">+</span> Baris</button>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[760px] table-fixed text-left text-sm">
                    <colgroup><col class="w-[40%]"><col class="w-[34%]"><col class="w-[19%]"><col class="w-[7%]"></colgroup>
                    <thead class="bg-brand-50 text-brand-700"><tr><th scope="col">Obat</th><th scope="col">Batch (opsional)</th><th scope="col">Jumlah dipindahkan</th><th scope="col" class="text-center">Aksi</th></tr></thead>
                    <tbody data-transfer-items>
                        @foreach($items as $index => $item)
                            <x-transfer-row :medicines="$medicines" :batch-balances="$batchBalances" :index="$index" :item="$item" />
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @error('items')<p class="error" role="alert">{{ $message }}</p>@enderror
        <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
            <p class="text-xs text-slate-500">Pilih batch untuk menentukan pengambilan, atau biarkan otomatis sesuai kedaluwarsa terdekat.</p>
            <button class="btn text-xs" type="submit">Simpan Transfer</button>
        </div>
        @php
            $contextData = [
                'units' => $medicines->mapWithKeys(fn ($medicine) => [$medicine->id => $medicine->unit?->name]),
                'balances' => $batchBalances->map(fn ($balance) => [
                    'medicine' => $balance->medicine_id, 'location' => $balance->location_id, 'batch' => $balance->batch_id,
                    'number' => $balance->batch?->number, 'expiry' => $balance->batch?->expiry_date?->toDateString(),
                    'quantity' => (float) $balance->quantity, 'id' => $balance->id,
                ])->values(),
            ];
        @endphp
        <script type="application/json" id="transfer-stock-context">@json($contextData)</script>
        <template data-transfer-template>
            <x-transfer-row :medicines="$medicines" :batch-balances="$batchBalances" index="__INDEX__" />
        </template>
    </form>
    @endcan

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
                        <th class="p-4 text-right text-[11px] font-bold uppercase tracking-wider">Kartu Stok</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($rows as $row)
                        @php
                            $cardStart = $row->occurred_at->toDateString();
                            $cardEnd = now('Asia/Jakarta')->toDateString();
                            $baseCardParams = [
                                'medicine_id' => $row->medicine_id,
                                'batch_id' => $row->batch_id,
                                'start' => $cardStart,
                                'end' => $cardEnd,
                            ];
                        @endphp
                        <tr class="hover:bg-slate-50/70 transition-colors">
                            <td class="p-4 text-xs font-medium text-slate-600 tabular-nums">{{ $row->occurred_at->format('d/m/Y H:i') }}</td>
                            <td class="p-4">
                                <span class="inline-flex rounded-md bg-slate-100 px-2 py-0.5 font-mono text-[11px] font-semibold text-slate-700">{{ $row->reference }}</span>
                            </td>
                            <td class="p-4 font-bold text-slate-900 tracking-tight">{{ $row->medicine->name }}<span class="mt-1 block text-xs font-normal text-slate-500">Batch: {{ $row->batch?->number ?? '-' }}</span></td>
                            <td class="p-4 text-slate-700 font-medium">
                                <span class="inline-flex items-center gap-1.5 rounded-lg bg-[#edf5fb] text-[#266194] px-2.5 py-1 text-xs font-semibold">
                                    {{ $row->sourceLocation->name }} → {{ $row->destinationLocation->name }}
                                </span>
                            </td>
                            <td class="p-4 font-bold tabular-nums text-slate-900">
                                {{ \App\Support\Quantity::format((float)$row->quantity) }} {{ $row->medicine->unit?->name }}
                            </td>
                            <td class="p-4 text-slate-600 font-medium text-xs">{{ $row->creator->name }}</td>
                            <td class="p-4">
                                @can('view-stock-card')
                                    <div class="flex justify-end gap-2">
                                        <a class="rounded-lg border border-slate-200 px-2.5 py-1 text-xs font-bold text-slate-600 hover:bg-slate-50" href="{{ route('stock-card.index', $baseCardParams + ['location_id' => $row->source_location_id]) }}">Asal</a>
                                        <a class="rounded-lg border border-[#b2d4ee] bg-[#edf5fb] px-2.5 py-1 text-xs font-bold text-[#266194] hover:bg-[#dcecf8]" href="{{ route('stock-card.index', $baseCardParams + ['location_id' => $row->destination_location_id]) }}">Tujuan</a>
                                    </div>
                                @else
                                    <span class="block text-right text-xs font-medium text-slate-400">-</span>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="p-8 text-center text-slate-500 font-medium">Belum ada transfer yang diposting.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-4">{{ $rows->links() }}</div>
</x-layouts.app>
