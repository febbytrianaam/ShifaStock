<x-layouts.app title="Laporan Stok Malam">
@php($editable = auth()->user()->can('operate-inventory') && $check->status === 'draft' && $check->created_by === auth()->id())
<style>@media print {aside,header,nav,.no-print{display:none!important}main{margin:0!important;padding:0!important}body{background:white!important}.card{box-shadow:none!important}input,textarea{border:0!important}table{font-size:10px}tr{break-inside:avoid}}</style>

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
    <p class="mt-2 text-xs text-slate-500">Diperiksa: {{ $items->whereNotNull('quantity')->count() }} / {{ $items->count() }} obat. Pembanding: {{ $previous ? $previous->date : 'Belum ada pemeriksaan terdahulu yang disetujui' }}. Selisih bukan jumlah pemakaian pasien.</p>
    @if($editable)
        <form method="POST" action="{{ route('nightly.destroy', $check->id) }}" class="no-print mt-3" data-confirm-cancel-draft>
            @csrf
            @method('DELETE')
            <button class="inline-flex items-center rounded-lg border border-rose-200 bg-white px-3 py-2 text-xs font-bold text-rose-600 hover:bg-rose-50">Cancel Draf</button>
        </form>
    @endif
</div>

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
    Kosongkan jumlah jika belum diperiksa; isi 0 jika stok kosong. Satuan harus sesuai hitungan fisik. Jika berbeda satuan dari pemeriksaan sebelumnya, selisih tidak dihitung. Tandai menipis berdasarkan pengecekan petugas; batas minimum otomatis belum ditetapkan.
</div>

<form method="POST" action="{{ route('nightly.save',$check->id) }}">
    @csrf
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
                    <tr class="hover:bg-slate-50/70 transition-colors" data-nightly-row data-search="{{ Illuminate\Support\Str::lower($i->name.' '.$i->code) }}" data-quantity="{{ $i->quantity }}" data-low="{{ $i->is_low ? '1' : '0' }}">
                        <td class="p-3">
                            <b class="font-bold text-slate-900 tracking-tight">{{ $i->name }}</b><br>
                            <span class="inline-flex rounded-md bg-slate-100 px-1.5 py-0.5 font-mono text-[10px] font-semibold text-slate-500 mt-0.5">{{ $i->code }}</span>
                        </td>
                        <td class="p-3 text-xs font-medium text-slate-600 tabular-nums">{{ $b ? $b->quantity.' '.$b->unit : 'Belum tersedia' }}</td>
                        <td class="p-3">
                            @if($editable)
                                <input aria-label="Jumlah {{ $i->name }}" class="field w-28 text-xs font-medium" name="items[{{ $i->id }}][quantity]" type="number" min="0" max="2147483647" step="1" placeholder="Belum dicek" value="{{ old('items.'.$i->id.'.quantity',$i->quantity) }}">
                            @else
                                <span class="font-bold tabular-nums text-slate-900">{{ $i->quantity ?? 'Belum dicek' }}</span>
                            @endif
                        </td>
                        <td class="p-3">
                            @if($editable)
                                <input aria-label="Satuan {{ $i->name }}" class="field w-28 text-xs font-medium" maxlength="100" name="items[{{ $i->id }}][unit]" placeholder="Tablet / botol" value="{{ old('items.'.$i->id.'.unit',$i->unit) }}">
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
    @endif
</form>

@if($check->status==='submitted')
    @can('finalize-opname')
        <form class="no-print card my-6 p-4" method="POST" action="{{ route('nightly.review',$check->id) }}">
            @csrf
            <label class="label text-xs font-semibold text-slate-700" for="review">Catatan tinjauan (wajib jika dikembalikan)</label>
            <textarea class="field w-full text-xs" id="review" name="review_note" maxlength="2000" rows="3" placeholder="Tuliskan catatan arahan atau alasan koreksi..."></textarea>
            <div class="mt-4 flex gap-3">
                <button class="btn bg-[#3277B2] hover:bg-[#266194] text-white font-bold text-xs" name="action" value="approve">Setujui Laporan</button>
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
        <button type="button" class="btn bg-[#3277B2] hover:bg-[#266194] text-white font-bold text-xs" onclick="window.print()">Cetak / Simpan PDF</button>
        <button type="button" class="btn bg-white hover:bg-slate-50 text-slate-800 border border-slate-200 font-bold text-xs" onclick="navigator.clipboard.writeText(document.getElementById('nightly-summary').value).then(()=>this.textContent='Tersalin').catch(()=>document.getElementById('nightly-summary').select())">Salin Ringkasan WAG</button>
    </div>
    <textarea id="nightly-summary" aria-label="Ringkasan untuk disalin" readonly class="no-print field mt-3 text-xs font-mono" rows="5">{{ $summary }}</textarea>
</section>
</x-layouts.app>
