<x-layouts.app title="Kondisi Khusus">
    @if(session('status'))
        <div class="mb-4 rounded-lg border border-[#b2d4ee] bg-[#edf5fb] p-3 text-sm font-semibold text-[#266194]">{{ session('status') }}</div>
    @endif
    @if($errors->any())
        <div class="mb-4 rounded-lg border border-rose-200 bg-rose-50 p-3 text-sm font-semibold text-rose-700">{{ $errors->first() }}</div>
    @endif

    @can('operate-inventory')
    <form method="POST" action="{{ route('special-adjustments.store') }}" class="card mb-5 grid gap-4 p-4 md:grid-cols-2 xl:grid-cols-4" data-required-alert="Lengkapi data kondisi khusus" novalidate>
        @csrf
        <div>
            <label class="label" for="adjust-reference">Referensi</label>
            <input class="field bg-slate-50 text-xs font-semibold text-slate-700" id="adjust-reference" name="reference" value="{{ old('reference', $nextReference) }}" readonly required maxlength="100">
        </div>
        <div>
            <label class="label" for="adjust-condition">Kondisi</label>
            <select class="field text-xs" id="adjust-condition" name="condition" required>
                @foreach(['rusak'=>'Obat rusak','hilang'=>'Obat hilang','terjatuh'=>'Obat terjatuh','tidak_diambil'=>'Tidak diambil pasien','expired'=>'Expired','lainnya'=>'Lainnya'] as $value => $label)
                    <option value="{{ $value }}" @selected(old('condition')===$value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="label" for="adjust-direction">Arah stok</label>
            <select class="field text-xs" id="adjust-direction" name="direction" required>
                <option value="decrease" @selected(old('direction','decrease')==='decrease')>Kurangi stok</option>
                <option value="increase" @selected(old('direction')==='increase')>Tambah stok koreksi</option>
            </select>
        </div>
        <div>
            <label class="label" for="adjust-time">Waktu kejadian</label>
            <input class="field text-xs" id="adjust-time" name="occurred_at" type="datetime-local" value="{{ old('occurred_at',now('Asia/Jakarta')->format('Y-m-d\\TH:i')) }}" required>
        </div>
        <div class="md:col-span-2">
            <label class="label" for="adjust-medicine">Obat</label>
            <select class="field text-xs" id="adjust-medicine" name="medicine_id" data-searchable-select data-search-placeholder="Cari kode atau nama obat..." required>
                <option value="">Pilih obat</option>
                @foreach($medicines as $medicine)
                    <option value="{{ $medicine->id }}" @selected(old('medicine_id')==$medicine->id)>{{ $medicine->code }} — {{ $medicine->name }} ({{ $medicine->unit?->name }})</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="label" for="adjust-location">Lokasi</label>
            <select class="field text-xs" id="adjust-location" name="location_id" required>
                @foreach($locations as $location)
                    <option value="{{ $location->id }}" @selected(old('location_id')==$location->id)>{{ $location->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="label" for="adjust-quantity">Jumlah</label>
            <input class="field text-xs" id="adjust-quantity" name="quantity" type="number" min="0.0001" step="0.0001" value="{{ old('quantity') }}" required>
        </div>
        <div class="md:col-span-2">
            <label class="label" for="adjust-batch">Batch (opsional)</label>
            <select class="field text-xs" id="adjust-batch" name="batch_id" data-searchable-select data-search-placeholder="Cari batch..." data-depends-on="#adjust-medicine,#adjust-location" data-depends-empty="Pilih obat dan lokasi dulu">
                <option value="">Tanpa rincian batch</option>
                @foreach($batchBalances as $balance)
                    <option value="{{ $balance->batch_id }}" data-depends-values="{{ $balance->medicine_id }}|{{ $balance->location_id }}" @selected(old('batch_id')==$balance->batch_id)>
                        {{ $balance->medicine->code }} · {{ $balance->batch->number }} · {{ $balance->location->name }} · {{ \App\Support\Quantity::format((float)$balance->quantity) }} {{ $balance->medicine->unit?->name }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="md:col-span-2 xl:col-span-3">
            <label class="label" for="adjust-notes">Catatan</label>
            <input class="field text-xs" id="adjust-notes" name="notes" value="{{ old('notes') }}" placeholder="Detail kejadian atau tindak lanjut" maxlength="1000">
        </div>
        <div class="flex items-end">
            <button class="btn w-full text-xs" data-adjust-save>Simpan &amp; Kurangi Stok</button>
        </div>
        <x-stock-entry-context :medicines="$medicines" :batch-balances="$batchBalances" medicine="#adjust-medicine" location="#adjust-location" quantity="#adjust-quantity" batch="#adjust-batch" />
    </form>
    @endcan

    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full whitespace-nowrap text-left text-sm">
                <thead class="bg-[#edf5fb] border-b border-[#d6e8f6] text-[#266194]">
                    <tr>
                        <th class="p-4">Waktu</th>
                        <th class="p-4">Referensi</th>
                        <th class="p-4">Obat / Batch</th>
                        <th class="p-4">Lokasi</th>
                        <th class="p-4">Dampak</th>
                        <th class="p-4">Catatan</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($rows as $row)
                        @php($decrease = filled($row->source_location_id))
                        <tr>
                            <td class="p-4 text-xs font-medium text-slate-600 tabular-nums">{{ $row->occurred_at->format('d/m/Y H:i') }}</td>
                            <td class="p-4 font-mono text-xs font-semibold text-slate-700">{{ $row->reference }}</td>
                            <td class="p-4"><b>{{ $row->medicine->name }}</b><br><span class="text-xs text-slate-500">{{ $row->batch?->number ?? 'Tanpa batch' }}</span></td>
                            <td class="p-4 text-xs font-semibold text-slate-600">{{ $row->sourceLocation?->name ?? $row->destinationLocation?->name }}</td>
                            <td class="p-4 font-bold tabular-nums {{ $decrease ? 'text-rose-700' : 'text-emerald-700' }}">{{ $decrease ? '-' : '+' }}{{ \App\Support\Quantity::format((float)$row->quantity) }} {{ $row->medicine->unit?->name }}</td>
                            <td class="p-4 text-xs text-slate-600">{{ $row->notes ?: '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="p-8 text-center text-slate-500 font-medium">Belum ada kondisi khusus.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-4">{{ $rows->links() }}</div>
</x-layouts.app>
