<x-layouts.app title="Kondisi Khusus">
    @if(session('status'))
        <div role="status" class="callout-ok mb-4 font-semibold">{{ session('status') }}</div>
    @endif
    @if($errors->any())
        <div role="alert" class="callout-danger mb-4 font-semibold">{{ $errors->first() }}</div>
    @endif

    @can('operate-inventory')
    <form method="POST" action="{{ route('special-adjustments.store') }}" class="mb-4 flex flex-col gap-4" data-required-alert="Lengkapi data kondisi khusus" novalidate>
        @csrf
        <x-ui.section title="Data kondisi khusus" icon="special">
            <div class="grid gap-x-4 gap-y-3.5 sm:grid-cols-2 xl:grid-cols-4">
                <div>
                    <label class="label" for="adjust-reference">Referensi</label>
                    <input class="field min-h-11 bg-slate-50 font-mono text-xs font-semibold text-slate-700 lg:min-h-9" id="adjust-reference" name="reference" value="{{ old('reference', $nextReference) }}" readonly required maxlength="100">
                </div>
                <div>
                    <label class="label" for="adjust-condition">Kondisi</label>
                    <select class="field min-h-11 text-xs lg:min-h-9" id="adjust-condition" name="condition" required>
                        @foreach(['rusak'=>'Obat rusak','hilang'=>'Obat hilang','terjatuh'=>'Obat terjatuh','tidak_diambil'=>'Tidak diambil pasien','expired'=>'Expired','lainnya'=>'Lainnya'] as $value => $label)
                            <option value="{{ $value }}" @selected(old('condition', $prefill['condition'] ?? null)===$value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="label" for="adjust-direction">Arah stok</label>
                    <select class="field min-h-11 text-xs lg:min-h-9" id="adjust-direction" name="direction" required>
                        <option value="decrease" @selected(old('direction','decrease')==='decrease')>Kurangi stok</option>
                        <option value="increase" @selected(old('direction')==='increase')>Tambah stok koreksi</option>
                    </select>
                </div>
                <div>
                    <label class="label" for="adjust-time">Waktu kejadian</label>
                    <input class="field min-h-11 text-xs lg:min-h-9" id="adjust-time" name="occurred_at" type="datetime-local" value="{{ old('occurred_at',now('Asia/Jakarta')->format('Y-m-d\\TH:i')) }}" required>
                </div>
                <div class="sm:col-span-2">
                    <label class="label" for="adjust-medicine">Obat</label>
                    <select class="field text-xs" id="adjust-medicine" name="medicine_id" data-searchable-select data-search-placeholder="Cari kode atau nama obat..." required>
                        <option value="">Pilih obat</option>
                        @foreach($medicines as $medicine)
                            <option value="{{ $medicine->id }}" @selected(old('medicine_id', $prefill['medicine_id'] ?? null)==$medicine->id)>{{ $medicine->code }} — {{ $medicine->name }} ({{ $medicine->unit?->name }})</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="label" for="adjust-location">Lokasi</label>
                    <select class="field min-h-11 text-xs lg:min-h-9" id="adjust-location" name="location_id" required>
                        @foreach($locations as $location)
                            <option value="{{ $location->id }}" @selected(old('location_id', $prefill['location_id'] ?? null)==$location->id)>{{ $location->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="label" for="adjust-quantity">Jumlah</label>
                    <input class="field min-h-11 text-xs tabular-nums lg:min-h-9" id="adjust-quantity" name="quantity" type="number" min="0.0001" step="0.0001" value="{{ old('quantity') }}" required>
                </div>
                <div class="sm:col-span-2">
                    <label class="label" for="adjust-batch">Batch</label>
                    <select class="field text-xs" id="adjust-batch" name="batch_id" required data-searchable-select data-search-placeholder="Cari batch..." data-depends-on="#adjust-medicine,#adjust-location,#adjust-direction" data-depends-empty="Pilih obat dan lokasi dulu">
                        <option value="">Pilih batch yang diperiksa</option>
                        @foreach($batchBalances as $balance)
                            <option value="{{ $balance->batch_id }}" data-depends-values="{{ $balance->medicine_id }}|{{ $balance->location_id }}|{{ (float) $balance->quantity > 0 ? '' : 'increase' }}" @selected(old('batch_id', $prefill['batch_id'] ?? null)==$balance->batch_id)>
                                {{ $balance->medicine->code }} · {{ $balance->batch->number }} · {{ $balance->location->name }} · {{ \App\Support\Quantity::format((float)$balance->quantity) }} {{ $balance->medicine->unit?->name }}
                            </option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-xs text-slate-500">Pilih batch sesuai obat dan lokasi. Batch dengan stok kosong hanya dapat ditambah melalui koreksi; batch baru dicatat melalui penerimaan atau stok awal.</p>
                </div>
                <div class="sm:col-span-2">
                    <label class="label" for="adjust-notes">Catatan</label>
                    <input class="field min-h-11 text-xs lg:min-h-9" id="adjust-notes" name="notes" value="{{ old('notes') }}" placeholder="Detail kejadian atau tindak lanjut" maxlength="1000">
                </div>
                <x-stock-entry-context :medicines="$medicines" :batch-balances="$batchBalances" medicine="#adjust-medicine" location="#adjust-location" quantity="#adjust-quantity" batch="#adjust-batch" />
            </div>
        </x-ui.section>
        <div class="flex justify-end">
            <button class="btn min-h-11 w-full sm:w-auto lg:min-h-9" data-adjust-save>Simpan &amp; Kurangi Stok</button>
        </div>
    </form>
    @endcan

    <x-ui.section title="Riwayat kondisi khusus" icon="history" tone="slate" flush :meta="$rows->total().' mutasi'">
        <div class="overflow-x-auto">
            <table class="w-full whitespace-nowrap text-left text-[13px]">
                <thead class="table-head">
                    <tr>
                        <th class="px-4 py-2.5">Waktu</th>
                        <th class="px-4 py-2.5">Referensi</th>
                        <th class="px-4 py-2.5">Obat / Batch</th>
                        <th class="px-4 py-2.5">Lokasi</th>
                        <th class="px-4 py-2.5">Dampak</th>
                        <th class="px-4 py-2.5">Catatan</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($rows as $row)
                        @php($decrease = filled($row->source_location_id))
                        <tr>
                            <td class="px-4 py-2.5 tabular-nums text-slate-600">{{ $row->occurred_at->format('d/m/Y H:i') }}</td>
                            <td class="px-4 py-2.5"><span class="inline-block rounded bg-slate-100 px-1.5 font-mono text-[11px] font-semibold text-slate-600">{{ $row->reference }}</span></td>
                            <td class="px-4 py-2.5"><b class="font-extrabold text-slate-900">{{ $row->medicine->name }}</b><p class="text-xs text-slate-500">{{ $row->batch?->number ?? 'Tanpa batch' }}</p></td>
                            <td class="px-4 py-2.5 text-slate-700">{{ $row->sourceLocation?->name ?? $row->destinationLocation?->name }}</td>
                            <td class="px-4 py-2.5 font-extrabold tabular-nums {{ $decrease ? 'text-rose-700' : 'text-emerald-700' }}">{{ $decrease ? '-' : '+' }}{{ \App\Support\Quantity::format((float)$row->quantity) }} {{ $row->medicine->unit?->name }}</td>
                            <td class="px-4 py-2.5 text-slate-600">{{ $row->notes ?: '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="empty-state">Belum ada kondisi khusus.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($rows->hasPages())<div class="border-t border-slate-100 px-4 py-3">{{ $rows->links() }}</div>@endif
    </x-ui.section>
</x-layouts.app>
