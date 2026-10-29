<x-layouts.app title="Impor Pengeluaran Obat" :hide-description="true">
    <x-issue-tabs></x-issue-tabs>
    @can('operate-inventory')<x-issue-import-steps />@endcan
    <section class="mb-5 max-w-4xl text-sm leading-relaxed text-slate-600">
        <h2 class="text-base font-semibold text-slate-900">Pilih file pengeluaran dari Neoklinik</h2>
        <p class="mt-1">Pilih Excel dan lokasi tempat obat keluar. Anda masih bisa memeriksa isinya sebelum stok berkurang.</p>
        <p class="mt-2 text-xs text-slate-500">Format .xlsx, maksimal 4 MB dan 2.000 transaksi. File asli serta nama pasien/dokter tidak diarsipkan.</p>
    </section>
    @if(session('status'))<p role="status" class="mb-4 rounded-md border border-blue-200 bg-blue-50 p-3 text-sm text-blue-800">{{ session('status') }}</p>@endif
    @if($errors->any())<div role="alert" class="mb-4 rounded-md border border-rose-200 bg-rose-50 p-3 text-sm text-rose-800"><ul class="list-inside list-disc">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

    @can('operate-inventory')
    <form method="POST" enctype="multipart/form-data" action="{{ route('issue-imports.store') }}" class="mb-7 border-y border-slate-200 py-5" data-required-alert="Lengkapi unggahan dan batas mulai impor" novalidate>
        @csrf
        <div class="grid gap-4 md:grid-cols-2">
            <div><label class="label" for="import-file">File ekspor Neoklinik</label><input class="field text-xs" id="import-file" name="file" type="file" accept=".xlsx" required></div>
            <div><label class="label" for="import-location">Lokasi stok asal</label><select class="field text-xs" id="import-location" name="location_id" required>
                @foreach($locations as $location)<option value="{{ $location->id }}" data-start="{{ isset($policies[$location->id]) ? \Carbon\Carbon::parse($policies[$location->id]->starts_at)->format('Y-m-d\TH:i') : '' }}" @selected(old('location_id', $locations->firstWhere('code', 'APT')?->id)==$location->id)>{{ $location->name }}</option>@endforeach
            </select><p class="mt-1 text-xs text-slate-500">Pastikan penjualan pada file mengambil stok dari lokasi ini.</p></div>
            <details class="border-t border-slate-100 pt-3 md:col-span-2" data-import-period-settings open><summary class="cursor-pointer text-sm font-semibold text-slate-700" id="import-period-summary">Atur tanggal mulai untuk lokasi ini</summary><div class="mt-3 max-w-xl"><label class="label" for="import-start">Mulai pencatatan (WIB)</label><input class="field text-xs" id="import-start" type="datetime-local" name="starts_at" value="{{ old('starts_at') }}" aria-describedby="import-policy-help import-start-example" required><p class="mt-2 text-xs text-slate-600" id="import-policy-help">Isi awal periode pengeluaran yang belum dicatat di ShifaStock, bukan tanggal unggah.</p><p id="import-start-example" class="mt-2 text-xs leading-relaxed text-slate-600"><strong>Contoh:</strong> stok Apotek dihitung pukul 08.00 dan tersisa 100 tablet. Gunakan pengeluaran setelah hitungan itu; pengeluaran sebelumnya sudah termasuk dalam sisa 100 tablet.</p><p class="mt-2 text-xs text-slate-500">Tanggal ini tetap setelah penyimpanan pertama. File untuk hari-hari berikutnya tetap dapat diimpor.</p></div></details>
        </div>
        <label class="mt-4 flex items-start gap-2 text-sm text-slate-700"><input type="checkbox" name="cutoff_confirmed" value="1" required data-required-label="Pengeluaran belum dicatat atau termasuk hitung stok" class="mt-1 shrink-0"> <span class="min-w-0 flex-1">Pengeluaran pada periode ini belum dicatat manual atau termasuk dalam stok awal/hitung fisik.</span></label>
        <div class="mt-4"><button class="btn text-xs" type="submit">Baca File &amp; Lanjutkan</button></div>
    </form>
    @endcan
    <details data-import-history @if(!auth()->user()->can('operate-inventory') || session('status') || request()->has('page')) open @endif>
        <summary class="mb-3 cursor-pointer text-base font-semibold text-slate-900">Riwayat Impor ({{ $documents->total() }})</summary>
        <div class="overflow-x-auto rounded-md border border-slate-200">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-50 text-xs text-slate-600"><tr><th class="p-3">Dokumen / Periode</th><th class="p-3">Lokasi</th><th class="p-3">Hasil</th><th class="p-3">Petugas</th><th class="p-3">Aksi</th></tr></thead>
                <tbody class="divide-y divide-slate-100">@forelse($documents as $document)<tr>
                    <td class="p-3"><span class="font-semibold">IMP-{{ $document->id }}</span><br><span class="text-xs text-slate-500">{{ $document->period_start->format('d/m/Y') }} - {{ $document->period_end->format('d/m/Y') }} · {{ $document->row_count }} baris</span></td>
                    <td class="p-3">{{ $document->location->name }}</td>
                    <td class="p-3 text-xs">@if($document->status === 'posted')<span class="font-semibold text-emerald-700">Terposting</span><br>{{ $document->posted_count }} baru · {{ $document->duplicate_count }} duplikat dilewati @elseif($document->status === 'expired' || $document->created_at->lt(now()->subDays(7)))<span class="text-slate-500">Pratinjau kedaluwarsa</span>@else<span class="font-semibold text-amber-700">Draf · belum mengubah stok</span>@endif</td>
                    <td class="p-3 text-xs">Diunggah: {{ $document->creator?->name }}<br>@if($document->poster)Diposting: {{ $document->poster->name }}@endif</td>
                    <td class="p-3"><div class="flex items-center gap-2"><a href="{{ route('issue-imports.show', $document) }}" class="text-xs font-semibold text-blue-700">Lihat</a><x-issue-import-delete :document="$document" /></div></td>
                </tr>@empty<tr><td colspan="5" class="p-6 text-center text-slate-500">Belum ada file pengeluaran yang diimpor.</td></tr>@endforelse</tbody>
            </table>
        </div>
        <div class="mt-4">{{ $documents->links() }}</div>
    </details>
</x-layouts.app>
