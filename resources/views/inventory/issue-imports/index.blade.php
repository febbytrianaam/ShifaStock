<x-layouts.app title="Impor Pengeluaran Obat" :hide-description="true">
    <x-issue-tabs></x-issue-tabs>
    @if(session('status'))<p role="status" class="callout-ok mb-4">{{ session('status') }}</p>@endif
    @if($errors->any())<div role="alert" class="callout-danger mb-4"><ul class="list-inside list-disc">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

    @can('operate-inventory')
    <form method="POST" enctype="multipart/form-data" action="{{ route('issue-imports.store') }}" class="mb-4 flex flex-col gap-4" data-required-alert="Lengkapi unggahan dan batas mulai impor" novalidate>
        @csrf
        <x-ui.section title="Pilih file pengeluaran dari Neoklinik" subtitle="Pilih Excel dan lokasi tempat obat keluar. Anda masih bisa memeriksa isinya sebelum stok berkurang." icon="upload">
            <x-slot:actions><x-issue-import-steps /></x-slot:actions>
            <div class="grid gap-x-4 gap-y-3.5 lg:grid-cols-2">
                <div>
                    <label class="label" for="import-file">File ekspor Neoklinik</label>
                    <input class="field min-h-11 py-1.5 text-xs file:mr-3 file:rounded-md file:border-0 file:bg-slate-100 file:px-2.5 file:py-1 file:text-xs file:font-bold file:text-slate-700 lg:min-h-9" id="import-file" name="file" type="file" accept=".xlsx" required>
                    <p class="mt-1 text-xs text-slate-500">Format .xlsx, maksimal 4 MB dan 2.000 transaksi. File asli serta nama pasien/dokter tidak diarsipkan.</p>
                </div>
                <div>
                    <label class="label" for="import-location">Lokasi stok asal</label>
                    <select class="field min-h-11 text-xs lg:min-h-9" id="import-location" name="location_id" required>
                        @foreach($locations as $location)<option value="{{ $location->id }}" data-start="{{ isset($policies[$location->id]) ? \Carbon\Carbon::parse($policies[$location->id]->starts_at)->format('Y-m-d\TH:i') : '' }}" @selected(old('location_id', $locations->firstWhere('code', 'APT')?->id)==$location->id)>{{ $location->name }}</option>@endforeach
                    </select>
                    <p class="mt-1 text-xs text-slate-500">Pastikan penjualan pada file mengambil stok dari lokasi ini.</p>
                </div>
                <details class="rounded-xl border border-slate-200 lg:col-span-2" data-import-period-settings open>
                    <summary class="cursor-pointer px-3.5 py-3 text-[13px] font-bold text-slate-700 lg:py-2.5" id="import-period-summary">Atur tanggal mulai untuk lokasi ini</summary>
                    <div class="grid gap-x-6 gap-y-3 border-t border-slate-100 px-3.5 py-3 lg:grid-cols-2">
                        <div>
                            <label class="label" for="import-start">Mulai pencatatan (WIB)</label>
                            <input class="field min-h-11 text-xs lg:min-h-9" id="import-start" type="datetime-local" name="starts_at" value="{{ old('starts_at') }}" aria-describedby="import-policy-help import-start-example" required>
                            <p class="mt-1.5 text-xs text-slate-600" id="import-policy-help">Isi awal periode pengeluaran yang belum dicatat di ShifaStock, bukan tanggal unggah.</p>
                        </div>
                        <div class="text-xs leading-relaxed text-slate-600">
                            <p id="import-start-example"><strong class="text-slate-800">Contoh:</strong> stok Apotek dihitung pukul 08.00 dan tersisa 100 tablet. Gunakan pengeluaran setelah hitungan itu; pengeluaran sebelumnya sudah termasuk dalam sisa 100 tablet.</p>
                            <p class="mt-2 text-slate-500">Tanggal ini tetap setelah penyimpanan pertama. File untuk hari-hari berikutnya tetap dapat diimpor.</p>
                        </div>
                    </div>
                </details>
            </div>
            <label class="mt-4 flex items-start gap-2.5 text-[13px] text-slate-700"><input type="checkbox" name="cutoff_confirmed" value="1" required data-required-label="Pengeluaran belum dicatat atau termasuk hitung stok" class="mt-0.5 h-4 w-4 shrink-0"> <span class="min-w-0 flex-1">Pengeluaran pada periode ini belum dicatat manual atau termasuk dalam stok awal/hitung fisik.</span></label>
        </x-ui.section>
        <div class="flex justify-end">
            <button class="btn min-h-11 w-full sm:w-auto lg:min-h-9" type="submit">Baca File &amp; Lanjutkan</button>
        </div>
    </form>
    @endcan
    <details class="group card overflow-hidden" data-import-history @if(!auth()->user()->can('operate-inventory') || session('status') || request()->has('page')) open @endif>
        <summary class="flex min-h-11 cursor-pointer list-none items-center gap-2.5 px-4 py-3.5 [&::-webkit-details-marker]:hidden">
            <span class="icon-tile bg-slate-100 text-slate-600"><x-ui.icon name="history" /></span>
            <span class="section-title min-w-0 flex-1">Riwayat Impor</span>
            <span class="text-xs text-slate-500">{{ $documents->total() }} dokumen</span>
            <x-ui.icon name="chevron-right" class="h-4 w-4 shrink-0 text-slate-400 transition-transform group-open:rotate-90" />
        </summary>
        <div class="overflow-x-auto border-t border-slate-100">
            <table class="w-full text-left text-[13px]">
                <thead class="table-head"><tr><th class="px-4 py-2.5">Dokumen / Periode</th><th class="px-4 py-2.5">Lokasi</th><th class="px-4 py-2.5">Hasil</th><th class="px-4 py-2.5">Petugas</th><th class="px-4 py-2.5 text-right">Aksi</th></tr></thead>
                <tbody class="divide-y divide-slate-100">@forelse($documents as $document)<tr>
                    <td class="px-4 py-2.5 align-top"><span class="inline-block rounded bg-slate-100 px-1.5 font-mono text-[11px] font-semibold text-slate-600">IMP-{{ $document->id }}</span><p class="mt-1 text-xs tabular-nums text-slate-500">{{ $document->period_start->format('d/m/Y') }} - {{ $document->period_end->format('d/m/Y') }} · {{ $document->row_count }} baris</p></td>
                    <td class="px-4 py-2.5 align-top text-slate-700">{{ $document->location->name }}</td>
                    <td class="px-4 py-2.5 align-top">@if($document->status === 'posted')<span class="badge badge-ok">Terposting</span><p class="mt-1 text-xs text-slate-500">{{ $document->posted_count }} baru · {{ $document->duplicate_count }} duplikat dilewati</p>@elseif($document->status === 'expired' || $document->created_at->lt(now()->subDays(7)))<span class="badge badge-muted">Pratinjau kedaluwarsa</span>@else<span class="badge badge-warn">Draf · belum mengubah stok</span>@endif</td>
                    <td class="px-4 py-2.5 align-top text-xs text-slate-600">Diunggah: {{ $document->creator?->name }}@if($document->poster)<br>Diposting: {{ $document->poster->name }}@endif</td>
                    <td class="px-4 py-2.5 align-top"><div class="flex items-center justify-end gap-1.5"><a href="{{ route('issue-imports.show', $document) }}" class="btn-secondary min-h-11 px-2.5 py-1 text-xs lg:min-h-8">Lihat</a><x-issue-import-delete :document="$document" /></div></td>
                </tr>@empty<tr><td colspan="5" class="empty-state">Belum ada file pengeluaran yang diimpor.</td></tr>@endforelse</tbody>
            </table>
        </div>
        @if($documents->hasPages())<div class="border-t border-slate-100 px-4 py-3">{{ $documents->links() }}</div>@endif
    </details>
</x-layouts.app>
