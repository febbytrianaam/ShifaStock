<dialog id="nightly-add-dialog" class="no-print m-auto w-[calc(100%_-_2rem)] max-w-xl max-h-[calc(100dvh_-_2rem)] overflow-y-auto rounded-lg border border-slate-200 bg-white p-4 shadow-lg" aria-labelledby="nightly-add-title" data-nightly-add-dialog @if(old('action') === 'add' && $errors->has('medicine_ids')) data-reopen @endif>
    <div class="mb-3 flex items-center justify-between gap-3"><h2 id="nightly-add-title" class="text-base font-bold">Tambah Obat ke Pemeriksaan</h2><button type="button" class="nightly-icon" data-nightly-add-close aria-label="Tutup pilihan obat" title="Tutup pilihan obat"><span data-nightly-close-icon aria-hidden="true"></span></button></div>
    <p class="mb-3 text-xs text-slate-600">Pilih obat tambahan. Isian pemeriksaan yang sedang dikerjakan ikut disimpan sebagai draf, tanpa mengubah stok.</p>
    @if(old('action') === 'add' && $errors->has('medicine_ids'))<p class="mb-3 text-xs text-rose-700" role="alert">{{ $errors->first('medicine_ids') }}</p>@endif
    <label class="label" for="nightly-add-search">Cari obat yang belum masuk laporan</label><input id="nightly-add-search" class="field mb-2" type="search" placeholder="Nama atau kode obat..." autocomplete="off">
    <p class="mb-2 text-xs text-slate-500" data-nightly-add-count aria-live="polite">0 obat dipilih</p>
    @php($addedSelection = is_array(old('medicine_ids')) ? old('medicine_ids') : [])
    <div class="max-h-[40dvh] overflow-y-auto rounded border border-slate-200">
        @foreach($availableMedicines as $medicine)
            <label class="flex items-start gap-3 border-b border-slate-100 px-3 py-2 text-xs last:border-0 hover:bg-slate-50" data-nightly-add-option data-search="{{ Illuminate\Support\Str::lower($medicine->name.' '.$medicine->code) }}">
                <input class="mt-0.5 shrink-0" type="checkbox" name="medicine_ids[]" value="{{ $medicine->id }}" @checked(in_array($medicine->id, $addedSelection))>
                <span class="min-w-0 break-words"><span class="font-semibold">{{ $medicine->name }}</span><span class="mt-0.5 block text-slate-500">{{ $medicine->code }} &middot; {{ $medicine->unit?->name ?? 'Satuan belum diatur' }}</span></span>
            </label>
        @endforeach
        <p class="p-3 text-xs text-slate-500" data-nightly-add-empty hidden>Tidak ada obat yang sesuai pencarian.</p>
    </div>
    <div class="mt-4 flex justify-end gap-2"><button type="button" class="btn-secondary" data-nightly-add-close>Batal</button><button type="submit" class="btn" name="action" value="add">Tambahkan ke Pemeriksaan</button></div>
</dialog>
