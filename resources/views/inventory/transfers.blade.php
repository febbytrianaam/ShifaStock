<x-layouts.app title="Transfer Gudang–Apotek">
    @if(session('status'))
        <div role="status" class="callout-ok mb-4 font-semibold">{{ session('status') }}</div>
    @endif
    @if($errors->any())
        <div role="alert" class="callout-danger mb-4 font-semibold">{{ $errors->first() }}</div>
    @endif

    @can('operate-inventory')
    @php
        $targetId = old('destination_location_id', $prefill['destination_location_id'] ?? $locations->firstWhere('code', 'APT')?->id ?? $locations->last()?->id);
        $sourceId = old('source_location_id', $prefill['source_location_id'] ?? $locations->first(fn ($location) => $location->code === 'GDG' && $location->id != $targetId)?->id ?? $locations->first(fn ($location) => $location->id != $targetId)?->id);
        $items = old('items', $prefillItems ?: [
            ['medicine_id' => old('medicine_id', $prefill['medicine_id'] ?? ''), 'batch_id' => old('batch_id', $prefill['batch_id'] ?? ''), 'quantity' => old('quantity', '')],
            ['medicine_id' => '', 'batch_id' => '', 'quantity' => ''],
        ]);
        $items = collect(is_array($items) ? $items : [])->filter(fn ($item) => is_array($item))->take(50)
            ->map(fn ($item) => array_map(fn ($value) => is_scalar($value) ? $value : '', array_intersect_key($item, array_flip(['medicine_id', 'batch_id', 'quantity']))))->values()->all() ?: [[]];
        $headerField = 'field min-h-11 text-xs lg:min-h-9';
    @endphp
    <form method="POST" action="{{ route('transfers.store') }}" class="transfer-entry mb-4 flex flex-col gap-4" data-transfer-form data-required-alert="Lengkapi data transfer" novalidate>
        @csrf
        <x-ui.section title="Dokumen transfer" icon="transfer">
            <div class="grid gap-x-4 gap-y-3.5 sm:grid-cols-2 xl:grid-cols-4 2xl:grid-cols-5">
                <div>
                    <label class="label" for="transfer-reference">Referensi</label>
                    <input class="{{ $headerField }} bg-slate-50 font-mono font-semibold text-slate-700" id="transfer-reference" name="reference" value="{{ old('reference', $nextReference) }}" readonly required maxlength="100">
                </div>
                <div>
                    <label class="label" for="transfer-source">Lokasi sumber</label>
                    <select class="{{ $headerField }}" id="transfer-source" name="source_location_id" required>
                        @foreach($locations as $location)
                            <option value="{{ $location->id }}" @selected((string) $sourceId === (string) $location->id)>{{ $location->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="label" for="transfer-target">Lokasi tujuan</label>
                    <select class="{{ $headerField }}" id="transfer-target" name="destination_location_id" required>
                        @foreach($locations as $location)
                            <option value="{{ $location->id }}" @selected((string) $targetId === (string) $location->id)>{{ $location->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="label" for="transfer-time">Waktu transfer</label>
                    <input class="{{ $headerField }}" id="transfer-time" name="occurred_at" type="datetime-local" value="{{ old('occurred_at',now('Asia/Jakarta')->format('Y-m-d\\TH:i')) }}" required>
                </div>
                <div class="sm:col-span-2 xl:col-span-4 2xl:col-span-1">
                    <label class="label" for="transfer-notes">Catatan</label>
                    <input class="{{ $headerField }}" id="transfer-notes" name="notes" value="{{ old('notes') }}" placeholder="Catatan mutasi antar unit (opsional)" maxlength="1000">
                </div>
            </div>
        </x-ui.section>

        <x-ui.section title="Item transfer" subtitle="Maksimal 50 baris. Batch kosong memakai kedaluwarsa terdekat." icon="list" tone="teal" flush>
            <x-slot:actions>
                <button type="button" class="btn-secondary min-h-11 px-2.5 py-1 text-xs lg:min-h-8" data-transfer-add-row><span data-transfer-add-icon aria-hidden="true">+</span> Baris</button>
            </x-slot:actions>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[760px] table-fixed text-left text-[13px]">
                    <colgroup><col class="w-[40%]"><col class="w-[34%]"><col class="w-[19%]"><col class="w-[7%]"></colgroup>
                    <thead class="table-head"><tr><th scope="col">Obat</th><th scope="col">Batch (opsional)</th><th scope="col">Jumlah dipindahkan</th><th scope="col" class="text-center">Aksi</th></tr></thead>
                    <tbody data-transfer-items>
                        @foreach($items as $index => $item)
                            <x-transfer-row :medicines="$medicines" :batch-balances="$batchBalances" :index="$index" :item="$item" />
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-ui.section>
        @error('items')<p class="callout-danger" role="alert">{{ $message }}</p>@enderror
        <div class="flex flex-wrap items-center justify-end gap-x-3 gap-y-2">
            <p class="w-full text-xs text-slate-500 sm:mr-auto sm:w-auto">Pilih batch untuk menentukan pengambilan, atau biarkan otomatis sesuai kedaluwarsa terdekat.</p>
            <button class="btn min-h-11 w-full sm:w-auto lg:min-h-9" type="submit">Simpan Transfer</button>
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

    <x-ui.section title="Riwayat transfer" icon="history" tone="slate" flush :meta="$rows->total().' mutasi'">
        <div class="overflow-x-auto">
            <table class="w-full whitespace-nowrap text-left text-[13px]">
                <thead class="table-head">
                    <tr>
                        <th class="px-4 py-2.5">Waktu</th>
                        <th class="px-4 py-2.5">Referensi</th>
                        <th class="px-4 py-2.5">Obat</th>
                        <th class="px-4 py-2.5">Perpindahan</th>
                        <th class="px-4 py-2.5">Jumlah</th>
                        <th class="px-4 py-2.5">Petugas</th>
                        <th class="px-4 py-2.5 text-right">Kartu Stok</th>
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
                        <tr>
                            <td class="px-4 py-2.5 tabular-nums text-slate-600">{{ $row->occurred_at->format('d/m/Y H:i') }}</td>
                            <td class="px-4 py-2.5"><span class="inline-block rounded bg-slate-100 px-1.5 font-mono text-[11px] font-semibold text-slate-600">{{ $row->reference }}</span></td>
                            <td class="px-4 py-2.5"><b class="font-extrabold text-slate-900">{{ $row->medicine->name }}</b><p class="text-xs text-slate-500">Batch: {{ $row->batch?->number ?? '-' }}</p></td>
                            <td class="px-4 py-2.5"><span class="badge badge-brand">{{ $row->sourceLocation->name }} → {{ $row->destinationLocation->name }}</span></td>
                            <td class="px-4 py-2.5 font-extrabold tabular-nums text-slate-900">{{ \App\Support\Quantity::format((float)$row->quantity) }} {{ $row->medicine->unit?->name }}</td>
                            <td class="px-4 py-2.5 text-slate-600">{{ $row->creator->name }}</td>
                            <td class="px-4 py-2.5">
                                @can('view-stock-card')
                                    <div class="flex justify-end gap-1.5">
                                        <a class="btn-secondary min-h-11 px-2.5 py-1 text-xs lg:min-h-8" href="{{ route('stock-card.index', $baseCardParams + ['location_id' => $row->source_location_id]) }}">Asal</a>
                                        <a class="btn-secondary min-h-11 px-2.5 py-1 text-xs lg:min-h-8" href="{{ route('stock-card.index', $baseCardParams + ['location_id' => $row->destination_location_id]) }}">Tujuan</a>
                                    </div>
                                @else
                                    <span class="block text-right text-xs text-slate-400">-</span>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="empty-state">Belum ada transfer yang diposting.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($rows->hasPages())<div class="border-t border-slate-100 px-4 py-3">{{ $rows->links() }}</div>@endif
    </x-ui.section>
</x-layouts.app>
