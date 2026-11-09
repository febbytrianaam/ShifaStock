<x-layouts.app title="Pengecekan Stok Malam" :hide-description="true">
    <x-page-tabs :links="[
        ['route' => 'preview.nightly', 'label' => 'Harian', 'can' => 'view-nightly-checks', 'active' => ['preview.nightly', 'nightly.show']],
        ['route' => 'preview.opname', 'label' => 'Opname Periodik', 'can' => 'view-opname'],
        ['route' => 'nightly.history', 'label' => 'Riwayat', 'can' => 'view-nightly-history'],
    ]" />

    @php
        $statusMeta = ['approved' => ['Disetujui', 'badge-ok'], 'submitted' => ['Menunggu tinjauan', 'badge-warn'], 'draft' => ['Draf', 'badge-muted']];
        $outline = 'inline-flex min-h-11 items-center justify-center gap-1.5 whitespace-nowrap rounded-lg border bg-white px-3 text-xs font-bold lg:min-h-[30px] lg:px-2.5';
        // Below md each row stacks into a small grid so the actions stay on screen; from md up it is a normal table.
        $stackRow = 'grid grid-cols-[minmax(0,1fr)_auto] items-start gap-x-3 gap-y-1.5 border-t border-slate-100 px-4 py-3 first:border-t-0 md:table-row md:p-0 md:first:border-t';
        $cell = 'block md:table-cell md:px-4 md:py-2.5';
    @endphp

    @if($errors->any())
        <div class="callout-danger mb-4 font-semibold" role="alert">{{ $errors->first() }}</div>
    @endif
    @if(session('status'))
        <div class="callout mb-4 font-semibold" role="status">{{ session('status') }}</div>
    @endif

    <div class="grid items-start gap-4 xl:grid-cols-[minmax(0,1fr)_360px]">
        <aside aria-label="Mulai dan panduan pemeriksaan" class="flex min-w-0 flex-col gap-4 xl:col-start-2 xl:row-start-1">
            <section class="card min-w-0" aria-labelledby="nightly-guide-title" data-nightly-start-guide>
                <div class="section-head"><div class="flex min-w-0 items-center gap-2.5">
                    <span class="icon-tile bg-teal-100 text-teal-700"><x-ui.icon name="info" /></span>
                    <span class="min-w-0">
                        <h2 id="nightly-guide-title" class="section-title">Memahami angka pemeriksaan stok</h2>
                        <span class="section-sub block">Stok awal, stok sistem, dan jumlah fisik</span>
                    </span>
                </div></div>
                <div class="px-4 py-3.5 text-[13px] leading-relaxed text-slate-600">
                    <p>Pemeriksaan membandingkan catatan stok dengan jumlah obat yang benar-benar ada di lokasi yang dipilih, baik Apotek maupun Gudang.</p>
                    <dl class="mt-3 grid gap-2 md:grid-cols-3 xl:grid-cols-1">
                        <div class="min-w-0 rounded-xl bg-slate-50 px-3 py-2.5">
                            <dt class="text-xs font-bold text-slate-800">Stok awal</dt>
                            <dd class="mt-0.5 text-xs">Persediaan saat mulai memakai sistem, dicatat per obat, batch, dan lokasi. Tidak perlu diisi ulang setiap pemeriksaan.</dd>
                        </div>
                        <div class="min-w-0 rounded-xl bg-slate-50 px-3 py-2.5">
                            <dt class="text-xs font-bold text-slate-800">Stok sistem</dt>
                            <dd class="mt-0.5 text-xs">Jumlah dari stok awal dan transaksi yang sudah tercatat, seperti penerimaan, transfer, pengeluaran, serta retur. Obat yang dipakai tidak otomatis mengurangi angka ini jika pengeluarannya belum dicatat.</dd>
                        </div>
                        <div class="min-w-0 rounded-xl bg-slate-50 px-3 py-2.5">
                            <dt class="text-xs font-bold text-slate-800">Jumlah fisik</dt>
                            <dd class="mt-0.5 text-xs">Hasil menghitung obat langsung di lokasi pemeriksaan, sesuai satuan obat. Tidak harus sama dengan stok sistem. Isi 0 jika sudah dihitung dan habis; biarkan kosong jika belum diperiksa.</dd>
                        </div>
                    </dl>
                    <div class="callout mt-3 text-xs">
                        <p><strong class="text-slate-900">Contoh:</strong> stok sistem 10 tablet, tetapi fisiknya tinggal 2 tablet. Jumlah fisik diisi <strong class="text-slate-900">2</strong>. Setelah disetujui, stok sistem menjadi 2, bukan dikurangi 2 menjadi 8.</p>
                        <p class="mt-1.5"><strong class="text-slate-900">Kapan stok berubah?</strong> Menyimpan draf atau mengirim laporan belum mengubah stok. Stok baru disesuaikan setelah kepala apoteker menyetujui hasil pemeriksaan, hanya untuk obat yang sudah diperiksa. Obat yang belum diperiksa tidak dianggap kosong.</p>
                    </div>
                </div>
            </section>

            @can('operate-inventory')
            @php($selectedMedicineIds = is_array(old('medicine_ids')) ? old('medicine_ids') : [])
            <x-ui.section title="Mulai pemeriksaan" subtitle="Satu laporan per tanggal dan lokasi" icon="check">
                <form method="POST" action="{{ route('nightly.store') }}" data-nightly-start-form data-required-alert="Lengkapi data pemeriksaan" novalidate>
                    @csrf
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div class="min-w-0">
                            <label class="label" for="date">Tanggal pemeriksaan</label>
                            <input class="field" id="date" name="date" type="date" value="{{ old('date', now('Asia/Jakarta')->toDateString()) }}" max="{{ now('Asia/Jakarta')->toDateString() }}" required>
                        </div>
                        <div class="min-w-0">
                            <label class="label" for="location">Lokasi</label>
                            <select class="field" id="location" name="location_id" required>
                                @foreach($locations as $location)
                                    <option value="{{ $location->id }}" @selected(old('location_id') == $location->id)>{{ $location->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <fieldset class="mt-3">
                        <legend class="label">Cakupan pemeriksaan</legend>
                        <div class="grid grid-cols-2 gap-2">
                            @foreach(['complete' => 'Semua obat', 'selected' => 'Obat tertentu'] as $scope => $scopeLabel)
                                <label class="flex min-h-11 items-center gap-2 rounded-lg border border-slate-300 bg-white px-3 text-[13px] font-semibold text-slate-700 hover:bg-slate-50 has-[:checked]:border-brand-500 has-[:checked]:bg-brand-50 has-[:checked]:text-brand-800 lg:min-h-9">
                                    <input type="radio" name="scope" value="{{ $scope }}" @checked(old('scope', 'complete') === $scope)> {{ $scopeLabel }}
                                </label>
                            @endforeach
                        </div>
                    </fieldset>
                    <div class="mt-3" data-nightly-medicine-picker @if(old('scope', 'complete') !== 'selected') hidden @endif>
                        <label class="label" for="nightly-medicine-search">Pilih obat yang akan diperiksa</label>
                        <x-ui.search id="nightly-medicine-search" label="Cari obat" width="w-full" placeholder="Cari nama atau kode obat..." autocomplete="off" />
                        <p class="mt-2 text-xs font-semibold text-slate-600" data-nightly-selected-count aria-live="polite">{{ count($selectedMedicineIds) }} obat dipilih</p>
                        <div class="mt-1.5 max-h-64 overflow-y-auto rounded-xl border border-slate-200">
                            @foreach($medicines as $medicine)
                                <label class="flex items-start gap-3 border-b border-slate-100 px-3 py-2 text-[13px] last:border-0 hover:bg-slate-50" data-nightly-medicine-option data-search="{{ Illuminate\Support\Str::lower($medicine->name.' '.$medicine->code) }}">
                                    <input class="mt-1 shrink-0" type="checkbox" name="medicine_ids[]" value="{{ $medicine->id }}" @checked(in_array($medicine->id, $selectedMedicineIds))>
                                    <span class="min-w-0 break-words">
                                        <span class="font-bold text-slate-900">{{ $medicine->name }}</span>
                                        <span class="mt-0.5 flex flex-wrap items-center gap-1.5 text-xs text-slate-500"><span class="rounded bg-slate-100 px-1.5 font-mono text-[11px] font-semibold text-slate-600">{{ $medicine->code }}</span>{{ $medicine->unit?->name ?? 'Satuan belum diatur' }}</span>
                                    </span>
                                </label>
                            @endforeach
                            <p class="p-3 text-xs text-slate-500" data-nightly-no-matches hidden>Tidak ada obat yang sesuai pencarian.</p>
                        </div>
                        <p class="mt-2 text-xs text-slate-500">Hanya obat yang dipilih yang masuk laporan. Stok obat lain tidak berubah saat laporan disetujui.</p>
                    </div>
                    <button class="btn mt-4 min-h-11 w-full lg:min-h-9">Buka / Mulai Pemeriksaan</button>
                    <p class="mt-2 text-xs leading-relaxed text-slate-500">Satu laporan per tanggal dan lokasi. Jika sudah ada, laporan tersebut akan dibuka. Untuk menambah obat yang tertinggal, gunakan tombol Tambah Obat pada draf tanpa membatalkannya.</p>
                </form>
            </x-ui.section>
            @endcan
        </aside>

        <x-ui.section class="xl:col-start-1 xl:row-start-1" title="Daftar pemeriksaan" subtitle="Laporan hitung fisik per tanggal dan lokasi, terbaru di atas." icon="list" flush :meta="$checks->total().' laporan'">
            <div class="overflow-x-auto">
                <table class="block w-full text-left text-[13px] md:table">
                    <thead class="table-head hidden md:table-header-group">
                        <tr>
                            <th class="px-4 py-2.5">Tanggal</th>
                            <th class="px-4 py-2.5">Lokasi</th>
                            <th class="px-4 py-2.5">Status</th>
                            <th class="px-4 py-2.5 text-right">Laporan</th>
                        </tr>
                    </thead>
                    <tbody class="block md:table-row-group">
                        @forelse($checks as $check)
                            @php($checkDate = \Carbon\Carbon::parse($check->date)->locale('id'))
                            @php($checkStatus = $statusMeta[$check->status] ?? $statusMeta['draft'])
                            <tr class="{{ $stackRow }}">
                                <td class="{{ $cell }} col-start-1 row-start-1 whitespace-nowrap">
                                    <span class="font-bold tabular-nums text-slate-900">{{ $checkDate->translatedFormat('j M Y') }}</span>
                                    <span class="text-xs text-slate-500 md:block"><span class="md:hidden">· </span>{{ $checkDate->translatedFormat('l') }}</span>
                                </td>
                                <td class="{{ $cell }} col-span-2 md:min-w-48">
                                    <span class="font-extrabold text-slate-900">{{ $check->location }}</span>
                                    <span class="mt-0.5 block text-xs text-slate-500">Daftar awal: {{ $check->scope === 'selected' ? 'Obat tertentu' : 'Semua obat' }} · Dibuat oleh: {{ $check->creator }}</span>
                                </td>
                                <td class="{{ $cell }} col-start-2 row-start-1 whitespace-nowrap"><span class="badge {{ $checkStatus[1] }}">{{ $checkStatus[0] }}</span></td>
                                <td class="{{ $cell }} col-span-2 pt-1 md:pt-2.5">
                                    <div class="flex gap-2 md:justify-end">
                                        <a class="{{ $outline }} border-brand-200 text-brand-800 hover:bg-brand-50" href="{{ route('nightly.show',$check->id) }}">Buka laporan</a>
                                        @can('operate-inventory')
                                            @if($check->status === 'draft' && $check->created_by === auth()->id())
                                                <form method="POST" action="{{ route('nightly.destroy', $check->id) }}" data-confirm-cancel-draft>
                                                    @csrf
                                                    @method('DELETE')
                                                    <input type="hidden" name="draft_revision" value="{{ $check->draft_revision }}">
                                                    <button class="{{ $outline }} border-rose-200 text-rose-700 hover:bg-rose-50">Cancel</button>
                                                </form>
                                            @endif
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr class="block md:table-row">
                                <td class="empty-state block md:table-cell" colspan="4">Belum ada hasil pemeriksaan. Mulai dari hitung fisik pertama.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($checks->hasPages())
                <div class="border-t border-slate-100 px-4 py-3">{{ $checks->links() }}</div>
            @endif
        </x-ui.section>
    </div>
</x-layouts.app>
