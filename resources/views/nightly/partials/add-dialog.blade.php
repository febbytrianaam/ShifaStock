<dialog id="nightly-add-dialog" class="no-print m-auto w-[calc(100%_-_2rem)] max-w-xl max-h-[calc(100dvh_-_2rem)] overflow-y-auto rounded-2xl border border-slate-200 bg-white p-0 shadow-lg" aria-labelledby="nightly-add-title" data-nightly-add-dialog @if(old('action') === 'add' && $errors->has('medicine_ids')) data-reopen @endif>
    <div class="section-head"><div class="flex min-w-0 items-center gap-2.5"><span class="icon-tile bg-brand-100 text-brand-700" aria-hidden="true"><x-ui.icon name="plus" /></span><h2 id="nightly-add-title" class="section-title">Tambah Obat ke Pemeriksaan</h2></div><button type="button" class="nightly-icon" data-nightly-add-close aria-label="Tutup pilihan obat" title="Tutup pilihan obat"><span data-nightly-close-icon aria-hidden="true"></span></button></div>
    <div class="p-4">
    <p class="mb-3 text-[13px] text-slate-600">Pilih obat tambahan. Isian pemeriksaan yang sedang dikerjakan ikut disimpan sebagai draf, tanpa mengubah stok.</p>
    @if(old('action') === 'add' && $errors->has('medicine_ids'))<p class="callout-danger mb-3 text-xs" role="alert">{{ $errors->first('medicine_ids') }}</p>@endif
    <label class="label" for="nightly-add-search">Cari obat yang belum masuk laporan</label><x-ui.search id="nightly-add-search" label="Cari obat" width="w-full" placeholder="Nama atau kode obat..." autocomplete="off" />
    <p class="mb-1.5 mt-2 text-xs font-semibold text-slate-600" data-nightly-add-count aria-live="polite">0 obat dipilih</p>
    @php($addedSelection = is_array(old('medicine_ids')) ? old('medicine_ids') : [])
    <div class="max-h-[40dvh] overflow-y-auto rounded-xl border border-slate-200">
        @foreach($availableMedicines as $medicine)
            <label class="flex items-start gap-3 border-b border-slate-100 px-3 py-2 text-[13px] last:border-0 hover:bg-slate-50" data-nightly-add-option data-search="{{ Illuminate\Support\Str::lower($medicine->name.' '.$medicine->code) }}">
                <input class="mt-1 shrink-0" type="checkbox" name="medicine_ids[]" value="{{ $medicine->id }}" @checked(in_array($medicine->id, $addedSelection))>
                <span class="min-w-0 break-words"><span class="font-bold text-slate-900">{{ $medicine->name }}</span><span class="mt-0.5 flex flex-wrap items-center gap-1.5 text-xs text-slate-500"><span class="rounded bg-slate-100 px-1.5 font-mono text-[11px] font-semibold text-slate-600">{{ $medicine->code }}</span>{{ $medicine->unit?->name ?? 'Satuan belum diatur' }}</span></span>
            </label>
        @endforeach
        <p class="p-3 text-xs text-slate-500" data-nightly-add-empty hidden>Tidak ada obat yang sesuai pencarian.</p>
    </div>
    <div class="mt-4 flex justify-end gap-2"><button type="button" class="btn-secondary" data-nightly-add-close>Batal</button><button type="submit" class="btn" name="action" value="add">Tambahkan ke Pemeriksaan</button></div>
    </div>
</dialog>
