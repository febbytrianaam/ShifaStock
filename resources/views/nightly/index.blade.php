<x-layouts.app title="Pengecekan Stok Malam" :hide-description="true">
    <x-page-tabs :links="[
        ['route' => 'preview.nightly', 'label' => 'Harian', 'can' => 'view-nightly-checks', 'active' => ['preview.nightly', 'nightly.show']],
        ['route' => 'preview.opname', 'label' => 'Opname Periodik', 'can' => 'view-opname'],
        ['route' => 'nightly.history', 'label' => 'Riwayat', 'can' => 'view-nightly-history'],
    ]" />

    <section aria-labelledby="nightly-guide-title" data-nightly-start-guide class="mb-5 border-b border-slate-200 pb-5 text-sm leading-relaxed text-slate-600">
        <h2 id="nightly-guide-title" class="text-base font-semibold text-slate-900">Memahami angka pemeriksaan stok</h2>
        <p class="mt-1">Pemeriksaan membandingkan catatan stok dengan jumlah obat yang benar-benar ada di lokasi yang dipilih, baik Apotek maupun Gudang.</p>
        <dl class="mt-4 grid gap-4 md:grid-cols-3">
            <div class="min-w-0">
                <dt class="font-semibold text-slate-800">Stok awal</dt>
                <dd class="mt-1">Persediaan saat mulai memakai sistem, dicatat per obat, batch, dan lokasi. Tidak perlu diisi ulang setiap pemeriksaan.</dd>
            </div>
            <div class="min-w-0">
                <dt class="font-semibold text-slate-800">Stok sistem</dt>
                <dd class="mt-1">Jumlah dari stok awal dan transaksi yang sudah tercatat, seperti penerimaan, transfer, pengeluaran, serta retur. Obat yang dipakai tidak otomatis mengurangi angka ini jika pengeluarannya belum dicatat.</dd>
            </div>
            <div class="min-w-0">
                <dt class="font-semibold text-slate-800">Jumlah fisik</dt>
                <dd class="mt-1">Hasil menghitung obat langsung di lokasi pemeriksaan, sesuai satuan obat. Tidak harus sama dengan stok sistem. Isi 0 jika sudah dihitung dan habis; biarkan kosong jika belum diperiksa.</dd>
            </div>
        </dl>
        <p class="mt-4"><strong class="text-slate-800">Contoh:</strong> stok sistem 10 tablet, tetapi fisiknya tinggal 2 tablet. Jumlah fisik diisi <strong class="text-slate-800">2</strong>. Setelah disetujui, stok sistem menjadi 2, bukan dikurangi 2 menjadi 8.</p>
        <p class="mt-2"><strong class="text-slate-800">Kapan stok berubah?</strong> Menyimpan draf atau mengirim laporan belum mengubah stok. Stok baru disesuaikan setelah kepala apoteker menyetujui hasil pemeriksaan, hanya untuk obat yang sudah diperiksa. Obat yang belum diperiksa tidak dianggap kosong.</p>
    </section>

    @if($errors->any())
        <div class="mb-4 rounded-lg border border-rose-200 bg-rose-50 p-3 text-sm font-semibold text-rose-700">
            {{ $errors->first() }}
        </div>
    @endif
    @if(session('status'))
        <div class="mb-4 rounded-lg border border-[#b2d4ee] bg-[#edf5fb] p-3 text-sm font-semibold text-[#266194]">
            {{ session('status') }}
        </div>
    @endif

    @can('operate-inventory')
    @php($selectedMedicineIds = is_array(old('medicine_ids')) ? old('medicine_ids') : [])
    <form method="POST" action="{{ route('nightly.store') }}" class="mb-5 border-b border-slate-200 pb-5" data-nightly-start-form data-required-alert="Lengkapi data pemeriksaan" novalidate>
        @csrf
        <div class="flex flex-wrap items-end gap-4">
        <div>
            <label class="label text-xs font-semibold text-slate-700" for="date">Tanggal pemeriksaan</label>
            <input class="field text-xs" id="date" name="date" type="date" value="{{ old('date', now('Asia/Jakarta')->toDateString()) }}" max="{{ now('Asia/Jakarta')->toDateString() }}" required>
        </div>
        <div>
            <label class="label text-xs font-semibold text-slate-700" for="location">Lokasi</label>
            <select class="field text-xs" id="location" name="location_id" required>
                @foreach($locations as $location)
                    <option value="{{ $location->id }}" @selected(old('location_id') == $location->id)>{{ $location->name }}</option>
                @endforeach
            </select>
        </div>
        <fieldset>
            <legend class="label text-xs font-semibold text-slate-700">Cakupan pemeriksaan</legend>
            <div class="flex flex-wrap gap-4 py-2 text-xs">
                <label class="flex items-center gap-2"><input type="radio" name="scope" value="complete" @checked(old('scope', 'complete') === 'complete')> Semua obat</label>
                <label class="flex items-center gap-2"><input type="radio" name="scope" value="selected" @checked(old('scope') === 'selected')> Obat tertentu</label>
            </div>
        </fieldset>
        </div>
        <div class="mt-4" data-nightly-medicine-picker @if(old('scope', 'complete') !== 'selected') hidden @endif>
            <label class="label text-xs font-semibold text-slate-700" for="nightly-medicine-search">Pilih obat yang akan diperiksa</label>
            <input id="nightly-medicine-search" type="search" class="field mb-2 w-full max-w-lg text-xs" placeholder="Cari nama atau kode obat..." autocomplete="off">
            <p class="mb-2 text-xs text-slate-500" data-nightly-selected-count aria-live="polite">{{ count($selectedMedicineIds) }} obat dipilih</p>
            <div class="max-h-64 overflow-y-auto rounded border border-slate-200">
                @foreach($medicines as $medicine)
                    <label class="flex items-start gap-3 border-b border-slate-100 px-3 py-2 text-xs last:border-0 hover:bg-slate-50" data-nightly-medicine-option data-search="{{ Illuminate\Support\Str::lower($medicine->name.' '.$medicine->code) }}">
                        <input class="mt-0.5 shrink-0" type="checkbox" name="medicine_ids[]" value="{{ $medicine->id }}" @checked(in_array($medicine->id, $selectedMedicineIds))>
                        <span class="min-w-0 break-words"><span class="font-semibold text-slate-800">{{ $medicine->name }}</span><span class="mt-0.5 block text-slate-500">{{ $medicine->code }} &middot; {{ $medicine->unit?->name ?? 'Satuan belum diatur' }}</span></span>
                    </label>
                @endforeach
                <p class="p-3 text-xs text-slate-500" data-nightly-no-matches hidden>Tidak ada obat yang sesuai pencarian.</p>
            </div>
            <p class="mt-2 text-xs text-slate-500">Hanya obat yang dipilih yang masuk laporan. Stok obat lain tidak berubah saat laporan disetujui.</p>
        </div>
        <div class="mt-4 flex flex-wrap items-center gap-3">
            <button class="btn bg-[#3277B2] hover:bg-[#266194] text-white font-bold text-xs">Buka / Mulai Pemeriksaan</button>
            <p class="max-w-xl text-xs text-slate-500">Satu laporan per tanggal dan lokasi. Jika sudah ada, laporan tersebut akan dibuka. Untuk menambah obat yang tertinggal, gunakan tombol Tambah Obat pada draf tanpa membatalkannya.</p>
        </div>
    </form>
    @endcan

    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full whitespace-nowrap text-left text-sm">
                <thead class="bg-[#edf5fb] border-b border-[#d6e8f6] text-[#266194]">
                    <tr>
                        <th class="p-4 text-[11px] font-bold uppercase tracking-wider">Tanggal</th>
                        <th class="p-4 text-[11px] font-bold uppercase tracking-wider">Lokasi</th>
                        <th class="p-4 text-[11px] font-bold uppercase tracking-wider">Status</th>
                        <th class="p-4 text-[11px] font-bold uppercase tracking-wider text-right">Laporan</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($checks as $check)
                        <tr class="hover:bg-slate-50/70 transition-colors">
                            <td class="p-4 text-xs font-medium text-slate-600 tabular-nums">{{ $check->date }}</td>
                            <td class="p-4">
                                <span class="inline-flex items-center gap-1.5 rounded-lg bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700">
                                    {{ $check->location }}
                                </span>
                                <span class="mt-1 block text-[11px] text-slate-500">Daftar awal: {{ $check->scope === 'selected' ? 'Obat tertentu' : 'Semua obat' }}</span>
                                <span class="mt-1 block text-[11px] text-slate-500">Dibuat oleh: {{ $check->creator }}</span>
                            </td>
                            <td class="p-4">
                                @php($statusStyle = ['approved' => 'bg-[#edf5fb] text-[#266194] border border-[#b2d4ee]', 'submitted' => 'bg-amber-100 text-amber-800 border border-amber-200', 'draft' => 'bg-slate-100 text-slate-700 border border-slate-200'][$check->status] ?? 'bg-slate-100 text-slate-700 border border-slate-200')
                                @php($statusLabel = ['approved' => 'Disetujui', 'submitted' => 'Menunggu tinjauan', 'draft' => 'Draf'][$check->status] ?? 'Draf')
                                <span class="inline-flex items-center rounded-md px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider {{ $statusStyle }}">
                                    {{ $statusLabel }}
                                </span>
                            </td>
                            <td class="p-4">
                                <div class="flex justify-end gap-2">
                                    <a class="inline-flex items-center rounded-lg px-2.5 py-1 text-xs font-bold text-[#3277B2] hover:bg-[#edf5fb] transition" href="{{ route('nightly.show',$check->id) }}">
                                        Buka laporan →
                                    </a>
                                    @can('operate-inventory')
                                        @if($check->status === 'draft' && $check->created_by === auth()->id())
                                            <form method="POST" action="{{ route('nightly.destroy', $check->id) }}" data-confirm-cancel-draft>
                                                @csrf
                                                @method('DELETE')
                                                <input type="hidden" name="draft_revision" value="{{ $check->draft_revision }}">
                                                <button class="inline-flex items-center rounded-lg px-2.5 py-1 text-xs font-bold text-rose-600 hover:bg-rose-50 transition">Cancel</button>
                                            </form>
                                        @endif
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td class="p-8 text-center text-slate-500 font-medium" colspan="4">Belum ada hasil pemeriksaan. Mulai dari hitung fisik pertama.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-4">{{ $checks->links() }}</div>
</x-layouts.app>
