<x-layouts.app title="Laporan Stok Malam">
@php($editable = auth()->user()->can('operate-inventory') && $check->status === 'draft' && $check->created_by === auth()->id())

<div class="no-print">
    <x-page-tabs :links="[
        ['route' => 'preview.nightly', 'label' => 'Harian', 'can' => 'view-nightly-checks', 'active' => ['preview.nightly', 'nightly.show']],
        ['route' => 'preview.opname', 'label' => 'Opname Periodik', 'can' => 'view-opname'],
        ['route' => 'nightly.history', 'label' => 'Riwayat', 'can' => 'view-nightly-history'],
    ]" />
</div>

<div class="mb-4">
    <a class="no-print inline-flex items-center gap-1 text-xs font-semibold text-[#3277B2] hover:underline" href="{{ route('preview.nightly') }}">
        ← Kembali ke daftar pemeriksaan
    </a>
    <div class="mt-3 flex flex-wrap items-center justify-between gap-3">
        <p class="text-xs text-slate-500">{{ $check->date }} · {{ $check->location }} · Petugas: {{ $check->creator }}</p>
        <div>
            @php($statusLabels = ['draft'=>'Draf', 'submitted'=>'Menunggu tinjauan kepala apoteker', 'approved'=>'Disetujui'])
            @php($statusStyles = ['approved' => 'bg-[#edf5fb] text-[#266194] border border-[#b2d4ee]', 'submitted' => 'bg-amber-100 text-amber-800 border border-amber-200', 'draft' => 'bg-slate-100 text-slate-700 border border-slate-200'])
            <span class="inline-flex items-center rounded-lg px-3 py-1 text-xs font-bold uppercase tracking-wider {{ $statusStyles[$check->status] ?? $statusStyles['draft'] }}">
                {{ $statusLabels[$check->status] ?? 'Draf' }}
            </span>
        </div>
    </div>
    <p class="mt-2 text-xs font-semibold text-slate-700">Daftar awal: {{ $check->scope === 'selected' ? 'Obat tertentu' : 'Semua obat' }} &middot; Diperiksa: {{ $checkedCount }} / {{ $items->count() }} obat.</p>
    <p class="mt-1 text-xs text-slate-500">Pembanding menggunakan hasil terakhir masing-masing obat yang disetujui sebelum tanggal ini. Selisih bukan jumlah pemakaian pasien.</p>
    @if($check->scope === 'selected')
        <p class="mt-1 text-xs text-slate-600">Laporan ini hanya mencakup obat yang dipilih. Obat lain tidak ikut diperiksa atau diubah stoknya.</p>
    @endif
    @if($uncheckedCount > 0)
        <p role="note" data-nightly-incomplete class="mt-3 rounded border border-amber-200 bg-amber-50 p-3 text-xs text-amber-900">{{ $uncheckedCount }} obat belum diperiksa. {{ $check->status === 'draft' ? 'Simpan draf untuk melanjutkan nanti, atau kirim hanya hasil yang sudah diperiksa setelah konfirmasi.' : 'Pemeriksaan dikirim sebagian. Persetujuan hanya mencakup '.$checkedCount.' obat yang sudah diperiksa.' }} Obat yang belum diperiksa tidak dianggap kosong dan stoknya tidak disesuaikan.</p>
    @endif
    @if($editable)
        <form method="POST" action="{{ route('nightly.destroy', $check->id) }}" class="no-print mt-3" data-confirm-cancel-draft>
            @csrf
            @method('DELETE')
            <button class="inline-flex items-center rounded-lg border border-rose-200 bg-white px-3 py-2 text-xs font-bold text-rose-600 hover:bg-rose-50">Cancel Draf</button>
        </form>
    @endif
</div>

@if(!$editable)
    <div role="note" data-nightly-readonly class="mb-4 rounded border border-slate-200 bg-slate-50 p-3 text-sm text-slate-700">
        <strong>Mode lihat saja.</strong>
        @if(!auth()->user()->can('operate-inventory'))
            Akun Anda hanya memiliki akses melihat laporan, bukan mengisi hasil hitung.
        @elseif($check->status === 'draft')
            Draf ini dibuat oleh {{ $check->creator }}. Hanya akun pembuat draf yang dapat mengisi atau menambah obat. Gunakan akun pembuat untuk melanjutkan pemeriksaan.
        @elseif($check->status === 'submitted')
            Laporan sudah dikirim untuk ditinjau. Jika perlu diperbaiki, kepala apoteker dapat mengembalikannya melalui Kembalikan untuk Koreksi agar pembuat laporan bisa mengisi lagi.
        @else
            Laporan sudah disetujui dan tidak dapat diedit. Gunakan pemeriksaan berikutnya untuk mencatat hasil hitung baru; riwayat laporan ini tetap dipertahankan.
        @endif
    </div>
@endif

@if(session('status'))
    <div role="status" class="mb-4 rounded-lg border border-[#b2d4ee] bg-[#edf5fb] p-3 text-sm font-semibold text-[#266194]">
        {{ session('status') }}
    </div>
@endif
@if($errors->any())
    <div role="alert" class="mb-4 rounded-lg border border-rose-200 bg-rose-50 p-3 text-sm font-semibold text-rose-700">
        {{ $errors->first() }}
    </div>
@endif
@if($check->review_note)
    <div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800">
        <strong>Catatan apoteker:</strong> {{ $check->review_note }}
    </div>
@endif

<div class="mb-5 rounded-lg border border-[#d6e8f6] bg-[#edf5fb] p-3 text-xs text-[#1f4f78] leading-relaxed">
    Kosongkan jumlah jika belum diperiksa; isi 0 jika stok kosong. Jika ada selisih, buka Hasil hitung per batch dan isi tiap batch sesuai fisiknya. Jumlah fisik otomatis dijumlahkan; persetujuan memperbarui total stok dan batch bersama. Jika ada transaksi setelah laporan dikirim, laporan perlu dikembalikan untuk dihitung ulang.
</div>

@if($editable)
<form method="POST" action="{{ route('nightly.save',$check->id) }}" data-nightly-count-form novalidate>
    @csrf
    <input type="hidden" name="batch_counts_payload" data-nightly-batch-payload>
    <input type="hidden" name="observed_revision" value="{{ old('observed_revision', $observedRevision) }}">
    <input type="hidden" name="confirm_partial" value="0" data-nightly-confirm-partial>
    <div class="no-print mb-3 flex justify-end">
        <button type="button" class="btn inline-flex items-center gap-2 text-xs" data-nightly-add-open aria-controls="nightly-add-dialog" @disabled($availableMedicines->isEmpty()) title="{{ $availableMedicines->isEmpty() ? 'Semua obat yang tersedia sudah masuk laporan' : 'Tambahkan obat ke draf pemeriksaan' }}">
            <span data-nightly-add-icon aria-hidden="true"></span> Tambah Obat
        </button>
    </div>
@else
<div>
@endif
    <div class="no-print card mb-4 flex flex-col gap-3 p-4 sm:flex-row sm:items-end">
        <div class="flex-1">
            <label class="label text-xs font-semibold text-slate-700" for="nightly-search">Cari obat</label>
            <input id="nightly-search" class="field w-full text-xs" type="search" placeholder="Ketik nama atau kode obat..." autocomplete="off">
        </div>
        <div>
            <label class="label text-xs font-semibold text-slate-700" for="nightly-filter">Tampilkan</label>
            <select id="nightly-filter" class="field text-xs">
                <option value="all">Semua obat</option>
                <option value="unchecked">Belum dicek</option>
                <option value="empty">Stok kosong</option>
                <option value="low">Stok menipis</option>
            </select>
        </div>
        <div>
            <label class="label text-xs font-semibold text-slate-700" for="nightly-page-size">Per halaman</label>
            <select id="nightly-page-size" class="field text-xs">
                <option value="25">25 obat</option>
                <option value="50">50 obat</option>
                <option value="100">100 obat</option>
            </select>
        </div>
    </div>

    <div class="no-print mb-4 flex flex-wrap items-center justify-between gap-3">
        <p id="nightly-result-count" class="text-xs font-medium text-slate-500" aria-live="polite">{{ $items->count() }} obat ditampilkan</p>
        <div class="flex items-center gap-2">
            <button id="nightly-prev" type="button" class="btn text-xs">Sebelumnya</button>
            <span id="nightly-page-info" class="min-w-28 text-center text-xs text-slate-500 font-medium">Halaman 1</span>
            <button id="nightly-next" type="button" class="btn text-xs">Berikutnya</button>
        </div>
    </div>

    <div id="nightly-table-scroll" class="card max-h-[calc(100vh-10rem)] overflow-auto">
        <table class="w-full text-left text-sm whitespace-nowrap">
            <thead class="sticky top-0 z-10 bg-[#edf5fb] text-[#266194] border-b border-[#d6e8f6] shadow-xs">
                <tr>
                    <th class="p-3 text-[11px] font-bold uppercase tracking-wider">Obat</th>
                    <th class="p-3 text-[11px] font-bold uppercase tracking-wider">Hasil Sebelumnya</th>
                    <th class="p-3 text-[11px] font-bold uppercase tracking-wider">Jumlah Aktual</th>
                    <th class="p-3 text-[11px] font-bold uppercase tracking-wider">Satuan Hitung</th>
                    <th class="p-3 text-[11px] font-bold uppercase tracking-wider">Selisih</th>
                    <th class="p-3 text-[11px] font-bold uppercase tracking-wider">Menipis</th>
                    <th class="p-3 text-[11px] font-bold uppercase tracking-wider">Jumlah Diminta</th>
                    <th class="p-3 text-[11px] font-bold uppercase tracking-wider">Catatan</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @foreach($items as $i)
                    @php($b=$baseline->get($i->medicine_id))
                    <tr class="hover:bg-slate-50/70 transition-colors" data-nightly-row data-search="{{ Illuminate\Support\Str::lower($i->name.' '.$i->code) }}" data-quantity="{{ $i->quantity }}" data-low="{{ $i->is_low ? '1' : '0' }}" data-system-quantity="{{ $systemBalances[$i->medicine_id] ?? 0 }}">
                        <td class="p-3">
                            <b class="font-bold text-slate-900 tracking-tight">{{ $i->name }}</b><br>
                            <span class="inline-flex rounded-md bg-slate-100 px-1.5 py-0.5 font-mono text-[10px] font-semibold text-slate-500 mt-0.5">{{ $i->code }}</span>
                            <x-nightly-batches :item="$i" :batches="$batchOptions->get($i->medicine_id, collect())" :balances="$batchBalances" :editable="$editable" />
                        </td>
                        <td class="p-3 text-xs font-medium text-slate-600 tabular-nums">{{ $b ? $b->quantity.' '.$b->unit : 'Belum tersedia' }}@if($b)<span class="mt-1 block text-[11px] text-slate-500">{{ $b->checked_date }}</span>@endif</td>
                        <td class="p-3">
                            @if($editable)
                                <input aria-label="Jumlah {{ $i->name }}" class="field w-28 text-xs font-medium" name="items[{{ $i->id }}][quantity]" type="number" min="0" max="2147483647" step="1" placeholder="Belum dicek" value="{{ old('items.'.$i->id.'.quantity',$i->quantity) }}">
                            @else
                                <span class="font-bold tabular-nums text-slate-900">{{ $i->quantity ?? 'Belum dicek' }}</span>
                            @endif
                        </td>
                        <td class="p-3">
                            @if($editable)
                                @php($masterUnit = $medicineUnits->get($i->medicine_id))
                                @php($savedUnit = old('items.'.$i->id.'.unit', $i->unit))
                                @php($savedUnit = is_scalar($savedUnit) ? trim((string) $savedUnit) : '')
                                @php($countedQuantity = old('items.'.$i->id.'.quantity', $i->quantity))
                                @php($hasCount = $countedQuantity !== null && $countedQuantity !== '')
                                @php($unitChanged = $masterUnit && $hasCount && mb_strtolower($savedUnit) !== mb_strtolower($masterUnit))
                                @php($unitOptions = $masterUnit ? collect([$masterUnit]) : $countUnits)
                                @php($selectedUnit = $masterUnit ? ($unitChanged ? '' : $masterUnit) : $savedUnit)
                                <select aria-label="Satuan {{ $i->name }}" class="field w-36 text-xs font-medium" name="items[{{ $i->id }}][unit]" data-nightly-unit>
                                    @if(!$masterUnit || $unitChanged)
                                        <option value="">Pilih satuan</option>
                                    @endif
                                    @foreach($unitOptions as $unitName)
                                        <option value="{{ $unitName }}" @selected($selectedUnit === $unitName)>{{ $unitName }}</option>
                                    @endforeach
                                </select>
                                @if($unitChanged)
                                    <p class="mt-1 max-w-40 whitespace-normal text-[11px] text-amber-800">Satuan tersimpan berbeda dari Data Obat. Periksa kembali jumlahnya lalu pilih satuan stok.</p>
                                @elseif(!$masterUnit)
                                    <p class="mt-1 max-w-40 whitespace-normal text-[11px] text-amber-800">Satuan Data Obat belum diatur. Pilih referensi sesuai fisik; hasil lebih dari 0 memerlukan satuan di Data Obat.</p>
                                @endif
                                @error('items.'.$i->id.'.unit')<p class="mt-1 max-w-40 whitespace-normal text-[11px] text-rose-700" role="alert">{{ $message }}</p>@enderror
                            @else
                                <span class="text-xs text-slate-600 font-medium">{{ $i->unit }}</span>
                            @endif
                        </td>
                        <td class="p-3 text-xs font-bold tabular-nums text-slate-700">{{ $b && $i->quantity !== null && $b->unit === $i->unit ? sprintf('%+d',$i->quantity - $b->quantity) : 'Tidak tersedia' }}</td>
                        <td class="p-3">
                            @if($editable)
                                <input aria-label="Menipis {{ $i->name }}" type="checkbox" name="items[{{ $i->id }}][is_low]" value="1" @checked(old('items.'.$i->id.'.is_low',$i->is_low))>
                            @else
                                <span class="inline-flex items-center rounded-md px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider {{ $i->quantity === null ? 'bg-slate-100 text-slate-600 border border-slate-200' : ($i->quantity === 0 ? 'bg-rose-100 text-rose-800 border border-rose-300' : ($i->is_low ? 'bg-amber-100 text-amber-800 border border-amber-300' : 'bg-[#edf5fb] text-[#266194] border border-[#b2d4ee]')) }}">
                                    {{ $i->quantity === null ? 'Belum dicek' : ($i->quantity === 0 ? 'Kosong' : ($i->is_low ? 'Menipis' : 'Cukup')) }}
                                </span>
                            @endif
                        </td>
                        <td class="p-3">
                            @if($editable)
                                <input aria-label="Permintaan {{ $i->name }}" class="field w-24 text-xs font-medium" type="number" min="0" max="2147483647" step="1" name="items[{{ $i->id }}][requested_quantity]" value="{{ old('items.'.$i->id.'.requested_quantity',$i->requested_quantity) }}">
                            @else
                                <span class="text-xs font-medium text-slate-700 tabular-nums">{{ $i->requested_quantity ?? 'Belum ditentukan' }}</span>
                            @endif
                        </td>
                        <td class="p-3">
                            @if($editable)
                                <input aria-label="Catatan {{ $i->name }}" class="field w-40 text-xs" maxlength="1000" name="items[{{ $i->id }}][note]" value="{{ old('items.'.$i->id.'.note',$i->note) }}">
                            @else
                                <span class="text-xs text-slate-500">{{ $i->note ?: '—' }}</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="no-print mt-4 flex flex-wrap items-center justify-end gap-2">
        <button id="nightly-prev-bottom" type="button" class="btn text-xs">Sebelumnya</button>
        <span id="nightly-page-info-bottom" class="min-w-28 text-center text-xs text-slate-500 font-medium">Halaman 1</span>
        <button id="nightly-next-bottom" type="button" class="btn text-xs">Berikutnya</button>
    </div>

    @if($editable)
        <div class="no-print sticky bottom-3 z-10 my-5 flex gap-3 rounded-lg bg-white/95 p-3 shadow-sm border border-slate-200/80 backdrop-blur">
            <button class="btn bg-white hover:bg-slate-50 text-slate-800 border border-slate-200 font-bold text-xs" name="action" value="save">Simpan Draf</button>
            <button class="btn bg-[#3277B2] hover:bg-[#266194] text-white font-bold text-xs" name="action" value="submit">Kirim ke Kepala Apoteker</button>
        </div>
        <dialog id="nightly-add-dialog" class="no-print m-auto w-[calc(100%_-_2rem)] max-w-xl max-h-[calc(100dvh_-_2rem)] overflow-y-auto rounded-lg border border-slate-200 bg-white p-4 shadow-lg" aria-labelledby="nightly-add-title" data-nightly-add-dialog @if(old('action') === 'add' && $errors->has('medicine_ids')) data-reopen @endif>
            <div class="mb-3 flex items-center justify-between gap-3">
                <h2 id="nightly-add-title" class="text-base font-bold text-slate-900">Tambah Obat ke Pemeriksaan</h2>
                <button type="button" class="flex h-8 w-8 shrink-0 items-center justify-center rounded hover:bg-slate-100" data-nightly-add-close aria-label="Tutup pilihan obat" title="Tutup pilihan obat"><span data-nightly-close-icon aria-hidden="true"></span></button>
            </div>
            <p class="mb-3 text-xs text-slate-600">Pilih obat tambahan. Isian pemeriksaan yang sedang dikerjakan ikut disimpan sebagai draf, tanpa mengubah stok.</p>
            @if(old('action') === 'add' && $errors->has('medicine_ids'))<p class="mb-3 text-xs text-rose-700" role="alert">{{ $errors->first('medicine_ids') }}</p>@endif
            <label class="label text-xs" for="nightly-add-search">Cari obat yang belum masuk laporan</label>
            <input id="nightly-add-search" class="field mb-2 w-full text-xs" type="search" placeholder="Ketik nama atau kode obat..." autocomplete="off">
            <p class="mb-2 text-xs text-slate-500" data-nightly-add-count aria-live="polite">0 obat dipilih</p>
            @php($addedSelection = is_array(old('medicine_ids')) ? old('medicine_ids') : [])
            <div class="max-h-[40dvh] overflow-y-auto rounded border border-slate-200">
                @foreach($availableMedicines as $medicine)
                    <label class="flex items-start gap-3 border-b border-slate-100 px-3 py-2 text-xs last:border-0 hover:bg-slate-50" data-nightly-add-option data-search="{{ Illuminate\Support\Str::lower($medicine->name.' '.$medicine->code) }}">
                        <input class="mt-0.5 shrink-0" type="checkbox" name="medicine_ids[]" value="{{ $medicine->id }}" @checked(in_array($medicine->id, $addedSelection))>
                        <span class="min-w-0 break-words"><span class="font-semibold text-slate-800">{{ $medicine->name }}</span><span class="mt-0.5 block text-slate-500">{{ $medicine->code }} &middot; {{ $medicine->unit?->name ?? 'Satuan belum diatur' }}</span></span>
                    </label>
                @endforeach
                <p class="p-3 text-xs text-slate-500" data-nightly-add-empty hidden>Tidak ada obat yang sesuai pencarian.</p>
            </div>
            <div class="mt-4 flex flex-wrap justify-end gap-2">
                <button type="button" class="btn text-xs" data-nightly-add-close>Batal</button>
                <button type="submit" class="btn bg-[#3277B2] text-xs text-white hover:bg-[#266194]" name="action" value="add">Tambahkan ke Pemeriksaan</button>
            </div>
        </dialog>
    @endif
@if($editable)
</form>
@else
</div>
@endif

@if($check->status==='submitted')
    @can('finalize-opname')
        <form class="no-print card my-6 p-4" method="POST" action="{{ route('nightly.review',$check->id) }}">
            @csrf
            <p class="mb-3 text-xs text-slate-600">Persetujuan memperbarui total dan batch hanya untuk {{ $checkedCount }} obat yang sudah diperiksa dalam laporan ini. {{ $uncheckedCount }} obat belum diperiksa dan stoknya tidak berubah. Jika stok berubah setelah laporan dikirim, kembalikan untuk koreksi dan hitung ulang.</p>
            <div class="mb-4 max-h-64 overflow-auto">
                <table class="w-full text-left text-xs">
                    <thead><tr><th class="p-2">Obat</th><th class="p-2">Stok sistem sekarang</th><th class="p-2">Hasil hitung</th><th class="p-2">Perubahan jika disetujui</th></tr></thead>
                    <tbody>
                        @foreach($items->whereNotNull('quantity') as $item)
                            @php($systemQuantity = (float) ($systemBalances[$item->medicine_id] ?? 0))
                            <tr class="border-t border-slate-100"><td class="p-2">{{ $item->name }}</td><td class="p-2">{{ \App\Support\Quantity::format($systemQuantity) }} {{ $item->unit }}</td><td class="p-2">{{ \App\Support\Quantity::format($item->quantity) }} {{ $item->unit }}</td><td class="p-2">{{ \App\Support\Quantity::format($item->quantity - $systemQuantity) }} {{ $item->unit }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <label class="label text-xs font-semibold text-slate-700" for="review">Catatan tinjauan (wajib jika dikembalikan)</label>
            <textarea class="field w-full text-xs" id="review" name="review_note" maxlength="2000" rows="3" placeholder="Tuliskan catatan arahan atau alasan koreksi..."></textarea>
            <div class="mt-4 flex gap-3">
                <button class="btn bg-[#3277B2] hover:bg-[#266194] text-white font-bold text-xs" name="action" value="approve" data-confirm-stock-review data-checked-count="{{ $checkedCount }}" data-unchecked-count="{{ $uncheckedCount }}">Setujui &amp; Sesuaikan Stok</button>
                <button class="btn bg-white hover:bg-rose-50 text-rose-600 border border-rose-200 font-bold text-xs" name="action" value="return">Kembalikan untuk Koreksi</button>
            </div>
        </form>
    @endcan
@endif

<section class="card mt-6 p-4">
    <div class="flex items-center gap-2">
        <span class="h-2 w-2 rounded-full bg-[#3277B2]"></span>
        <h3 class="font-bold text-slate-900 text-base tracking-tight">Rekap Kekurangan dan Permintaan Persediaan</h3>
    </div>
    <p class="mt-1.5 text-xs text-slate-500">{{ $check->status==='approved'?'Berdasarkan hasil pemeriksaan yang disetujui.':'Draf rekap, belum disetujui apoteker.' }} Tujuan supplier dan jumlah pesanan akhir ditentukan apoteker. Laporan ini tidak mengirim pesanan otomatis.</p>
    <pre class="mt-4 whitespace-pre-wrap rounded-xl bg-slate-50 p-4 font-mono text-xs text-slate-800 border border-slate-200">{{ $summary }}</pre>
    <div class="no-print mt-4 flex flex-wrap gap-3">
        <a class="btn bg-[#3277B2] hover:bg-[#266194] text-white font-bold text-xs" href="{{ route('nightly.print', $check->id) }}" target="_blank" rel="noopener" data-nightly-print>Cetak / Simpan PDF</a>
        <button type="button" class="btn bg-white hover:bg-slate-50 text-slate-800 border border-slate-200 font-bold text-xs" onclick="navigator.clipboard.writeText(document.getElementById('nightly-summary').value).then(()=>this.textContent='Tersalin').catch(()=>document.getElementById('nightly-summary').select())">Salin Ringkasan WAG</button>
    </div>
    <p class="no-print mt-2 text-xs text-slate-500">Laporan cetak memakai data terakhir yang disimpan. Simpan Draf terlebih dahulu jika ada perubahan.</p>
    <textarea id="nightly-summary" aria-label="Ringkasan untuk disalin" readonly class="no-print field mt-3 text-xs font-mono" rows="5">{{ $summary }}</textarea>
</section>
</x-layouts.app>
