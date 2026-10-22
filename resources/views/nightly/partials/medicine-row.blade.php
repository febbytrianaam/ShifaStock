@php($b = $baseline->get($i->medicine_id))
<article class="nightly-row" data-nightly-row data-search="{{ Illuminate\Support\Str::lower($i->name.' '.$i->code) }}" data-quantity="{{ $i->quantity }}" data-low="{{ $i->is_low ? '1' : '0' }}" data-system-quantity="{{ $systemBalances[$i->medicine_id] ?? 0 }}">
    <div class="nightly-line">
        <div class="nightly-medicine"><b>{{ $i->name }}</b><small>{{ $i->code }}</small></div>
        <div class="nightly-system">{{ \App\Support\Quantity::format($systemBalances[$i->medicine_id] ?? 0) }}<small>{{ $medicineUnits->get($i->medicine_id) ?: $i->unit }} &middot; sistem</small></div>
        <div class="nightly-quantity">
            <span class="nightly-quantity-label" aria-hidden="true">Jumlah fisik / satuan</span>
            @if($editable)
                <input aria-label="Jumlah {{ $i->name }}" class="field" name="items[{{ $i->id }}][quantity]" type="number" min="0" max="2147483647" step="1" placeholder="-" value="{{ old('items.'.$i->id.'.quantity', $i->quantity) }}">
                @php($masterUnit = $medicineUnits->get($i->medicine_id))
                @php($savedUnit = old('items.'.$i->id.'.unit', $i->unit))
                @php($savedUnit = is_scalar($savedUnit) ? trim((string) $savedUnit) : '')
                @php($countedQuantity = old('items.'.$i->id.'.quantity', $i->quantity))
                @php($hasCount = $countedQuantity !== null && $countedQuantity !== '')
                @php($unitChanged = $masterUnit && $hasCount && mb_strtolower($savedUnit) !== mb_strtolower($masterUnit))
                @php($unitOptions = $masterUnit ? collect([$masterUnit]) : $countUnits)
                @php($selectedUnit = $masterUnit ? ($unitChanged ? '' : $masterUnit) : $savedUnit)
                <select aria-label="Satuan {{ $i->name }}" class="field" name="items[{{ $i->id }}][unit]" data-nightly-unit>
                    @if(!$masterUnit || $unitChanged)<option value="">Pilih satuan</option>@endif
                    @foreach($unitOptions as $unitName)<option value="{{ $unitName }}" @selected($selectedUnit === $unitName)>{{ $unitName }}</option>@endforeach
                </select>
            @else
                <span class="text-xs font-semibold">{{ $i->quantity ?? 'Belum dicek' }}</span><span class="text-xs">{{ $i->unit }}</span>
            @endif
        </div>
        <div class="nightly-row-actions"><span class="nightly-state" data-nightly-state>{{ $i->quantity === null ? 'Belum dicek' : ($i->quantity === 0 ? 'Kosong' : ($i->is_low ? 'Menipis' : 'Diperiksa')) }}</span><button type="button" class="nightly-row-toggle" data-nightly-row-toggle aria-expanded="false" aria-controls="nightly-details-{{ $i->id }}" aria-label="Rincian {{ $i->name }}">Rincian <span data-nightly-icon="down" aria-hidden="true"></span></button></div>
    </div>
    <div id="nightly-details-{{ $i->id }}" class="nightly-details" data-nightly-row-details hidden>
        <div><x-nightly-batches :item="$i" :batches="$batchOptions->get($i->medicine_id, collect())" :balances="$batchBalances" :editable="$editable" /></div>
        <div class="nightly-extra">
            <p class="text-[11px] text-slate-500" data-nightly-item-worker>Pembaruan terakhir: {{ $i->last_edited_name ?: 'Belum tercatat' }}@if($i->last_edited_at) &middot; {{ \Carbon\Carbon::parse($i->last_edited_at, config('app.timezone'))->timezone('Asia/Jakarta')->format('d/m/Y H:i') }} WIB @endif</p>
            <dl><div><dt>Hasil sebelumnya</dt><dd>{{ $b ? $b->quantity.' '.$b->unit : 'Belum tersedia' }}@if($b) ({{ $b->checked_date }})@endif</dd></div><div><dt>Selisih dari hasil sebelumnya</dt><dd>{{ $b && $i->quantity !== null && $b->unit === $i->unit ? sprintf('%+d', $i->quantity - $b->quantity) : 'Tidak tersedia' }} (tersimpan)</dd></div></dl>
            <p class="text-[11px] text-slate-500">Pembanding adalah hasil terakhir yang disetujui sebelum tanggal ini. Selisih bukan jumlah pemakaian pasien.</p>
            @if($editable)
                @if($unitChanged)<p class="text-xs text-amber-800">Satuan tersimpan berbeda dari Data Obat. Periksa kembali jumlahnya lalu pilih satuan stok.</p>
                @elseif(!$masterUnit)<p class="text-xs text-amber-800">Satuan Data Obat belum diatur. Pilih referensi sesuai fisik; hasil lebih dari 0 memerlukan satuan di Data Obat.</p>@endif
                @error('items.'.$i->id.'.unit')<p class="text-xs text-rose-700" role="alert">{{ $message }}</p>@enderror
                <label class="flex items-center gap-2"><input aria-label="Menipis {{ $i->name }}" type="checkbox" name="items[{{ $i->id }}][is_low]" value="1" @checked(old('items.'.$i->id.'.is_low', $i->is_low))> Tandai stok menipis</label>
                <label><span class="label">Jumlah diminta ({{ $masterUnit ?: $i->unit }})</span><input aria-label="Permintaan {{ $i->name }}" class="field" type="number" min="0" max="2147483647" step="1" name="items[{{ $i->id }}][requested_quantity]" value="{{ old('items.'.$i->id.'.requested_quantity', $i->requested_quantity) }}"></label>
                <label><span class="label">Catatan</span><input aria-label="Catatan {{ $i->name }}" class="field" maxlength="1000" name="items[{{ $i->id }}][note]" value="{{ old('items.'.$i->id.'.note', $i->note) }}"></label>
            @else
                <p>Jumlah diminta: {{ $i->requested_quantity ?? 'Belum ditentukan' }} {{ $i->requested_quantity !== null ? $i->unit : '' }}</p><p>Catatan: {{ $i->note ?: '-' }}</p>
            @endif
        </div>
    </div>
</article>
