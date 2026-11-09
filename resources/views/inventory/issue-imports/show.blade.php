<x-layouts.app title="Rincian Impor Pengeluaran" :hide-description="true">
    <x-issue-tabs></x-issue-tabs>
    @if(!$preview)
    @if(session('status'))<p role="status" class="callout-ok mb-4">{{ session('status') }}</p>@endif
    @if($errors->any())<div role="alert" class="callout-danger mb-4"><ul class="list-inside list-disc">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <x-ui.section :title="'IMP-'.$document->id.' · '.$document->location->name" icon="file" class="mb-4">
        <x-slot:actions>
            <a href="{{ route('issue-imports.index') }}" class="btn-secondary min-h-11 px-2.5 py-1 text-xs lg:min-h-8">Kembali ke riwayat</a>
            <x-issue-import-delete :document="$document" />
        </x-slot:actions>
        <dl class="grid gap-x-6 gap-y-3 text-[13px] sm:grid-cols-2 xl:grid-cols-4">
            <div><dt class="text-xs font-bold text-slate-500">Periode pada Excel</dt><dd class="mt-0.5 tabular-nums text-slate-900">{{ $document->period_start->format('d/m/Y H:i') }} - {{ $document->period_end->format('d/m/Y H:i') }} WIB</dd></div>
            <div><dt class="text-xs font-bold text-slate-500">Isi file</dt><dd class="mt-0.5 text-slate-900">{{ $document->row_count }} baris · {{ $document->sale_count }} penjualan · {{ $document->compound_count }} baris racikan</dd></div>
            <div><dt class="text-xs font-bold text-slate-500">Diunggah</dt><dd class="mt-0.5 text-slate-900">{{ $document->creator?->name ?? '-' }}</dd></div>
            <div><dt class="text-xs font-bold text-slate-500">Status</dt><dd class="mt-1">@if($document->status === 'posted')<span class="badge badge-ok">Terposting</span>@elseif($expired)<span class="badge badge-muted">Pratinjau kedaluwarsa</span>@else<span class="badge badge-warn">Draf · belum mengubah stok</span>@endif</dd></div>
        </dl>
        @if($expired)<p class="callout-warn mt-4">Pratinjau kedaluwarsa setelah 7 hari. Unggah ulang file untuk memeriksa stok dan pemetaan terbaru. Belum ada stok yang dikurangi dari draf ini.</p>@endif
        @if($document->status === 'posted')<p class="callout-ok mt-4">{{ $document->posted_count }} transaksi baru diposting oleh {{ $document->poster?->name }} · {{ $document->duplicate_count }} duplikat tidak dikurangi lagi. Data terposting tidak dapat diedit dari impor.</p>@endif
    </x-ui.section>
    @endif
    @if($preview)
        @if($step === 'match')
            @can('operate-inventory')
                @include('inventory.issue-imports.match')
            @endcan
        @else
        <div class="issue-import-workspace">
        <section class="card min-w-0 overflow-hidden" aria-labelledby="import-preview-title" data-import-list>
            <div class="section-head">
                <div class="flex min-w-0 items-center gap-2.5">
                    <span class="icon-tile bg-brand-100 text-brand-700"><x-ui.icon name="list" /></span>
                    <div class="min-w-0"><h3 id="import-preview-title" class="section-title">Obat yang akan dicatat</h3><p class="section-sub">Pengeluaran sejak {{ $document->starts_at->format('d/m/Y H:i') }} WIB. Obat yang sudah pernah dicatat tidak dipotong lagi.</p></div>
                </div>
                <div class="flex items-center gap-2"><label class="text-xs font-bold text-slate-600" for="import-review-filter">Tampilkan</label><select id="import-review-filter" class="field min-h-11 w-auto py-1 text-xs lg:min-h-8" data-import-review-filter><option value="all">Semua obat</option><option value="blocked" @selected($preview['blocked'] > 0)>Perlu diperiksa</option><option value="ready">Siap disimpan</option><option value="duplicate">Sudah pernah dicatat</option></select></div>
            </div>
            <div class="issue-import-list-scroll relative overflow-auto" tabindex="0" role="region" aria-label="Daftar pemeriksaan pengeluaran">
                <table class="issue-import-review-table w-full table-fixed text-left text-xs" data-import-preview>
                    <thead class="table-head sticky top-0 z-10"><tr><th class="w-[5%] px-3 py-2.5">No.</th><th class="w-[17%] px-3 py-2.5">Tanggal Jual (WIB)</th><th class="w-[28%] px-3 py-2.5">Obat pada Excel</th><th class="w-[12%] px-3 py-2.5">Jumlah di Excel</th><th class="w-[18%] px-3 py-2.5">Stok yang akan berkurang</th><th class="w-[20%] px-3 py-2.5">Status</th></tr></thead>
                    <tbody class="divide-y divide-slate-100">@foreach($preview['groups'] as $group)<tr data-import-review-row data-ready="{{ $group['ready'] }}" data-blocked="{{ $group['blocked'] }}" data-duplicate="{{ $group['duplicate'] }}" @if($preview['blocked'] > 0 && !$group['blocked']) hidden @endif>
                        <td class="px-3 py-3 align-top tabular-nums text-slate-500" data-label="No.">{{ $loop->iteration }}</td>
                        <td class="px-3 py-3 align-top text-slate-700" data-label="Tanggal Jual (WIB)"><x-issue-import-source-period :rows="$group['details']" /></td>
                        <td class="px-3 py-3 align-top" data-label="Obat"><b class="text-[13px] font-extrabold text-slate-900">{{ $group['name'] }}</b><br><span class="mt-1 inline-block rounded bg-slate-100 px-1.5 font-mono text-[11px] font-semibold text-slate-600">{{ $group['code'] }}</span>
                        </td>
                        <td class="px-3 py-3 align-top tabular-nums text-slate-700" data-label="Jumlah pada Excel">{{ \App\Support\Quantity::format((float)$group['quantity']) }} {{ $group['unit'] }}</td>
                        <td class="px-3 py-3 align-top" data-label="Stok yang akan berkurang">@if($group['medicine'])<b class="font-extrabold tabular-nums text-slate-900">{{ \App\Support\Quantity::format((float)$group['new_quantity']) }} {{ $group['medicine']->unit?->name }}</b><br><span class="text-slate-500">{{ $group['medicine']->name }}</span>@else<span class="text-slate-500">Pilih obat dahulu</span>@endif</td>
                        <td class="px-3 py-3 align-top" data-label="Hasil pemeriksaan"><div class="flex flex-wrap gap-1"><span class="badge badge-ok">{{ $group['ready'] }} siap</span><span class="badge badge-muted">{{ $group['duplicate'] }} sudah diimpor</span>@if($group['blocked'])<span class="badge badge-danger">{{ $group['blocked'] }} perlu diperbaiki</span>@endif</div>@if($group['blocked'])@foreach(array_unique($group['problems']) as $problem)<p class="mt-1.5 font-semibold text-rose-700">{{ \App\Support\IssueImportGuidance::forMessage($problem, $document)['title'] }}</p>@endforeach @endif</td>
                    </tr><tr data-import-review-details @if($preview['blocked'] > 0 && !$group['blocked']) hidden @endif><td colspan="6" class="!p-0"><x-issue-import-source-details :rows="$group['details']" :document="$document" /></td></tr>@endforeach</tbody>
                </table>
            </div>
            <p class="border-t border-slate-100 px-4 py-2.5 text-xs text-slate-500" data-import-review-count aria-live="polite"></p>
        </section>
        <aside class="issue-import-info" aria-labelledby="import-review-guide-title" data-import-info>
        <x-issue-import-context :document="$document" :step="$step" />
        <h3 id="import-review-guide-title" class="text-[15px] font-extrabold text-slate-900">Ringkasan &amp; konfirmasi</h3>
        <x-issue-import-summary :preview="$preview" />
        @if($preview['blocked'])
            <section class="callout-warn text-xs" aria-labelledby="import-problems-title" data-import-problems>
                <h3 id="import-problems-title" class="text-[13px] font-extrabold text-slate-900">Selesaikan bagian berikut sebelum menyimpan</h3>
                <ul class="mt-2.5 space-y-2.5">
                    @foreach(collect($preview['details'])->where('status', 'blocked')->groupBy('message') as $message => $problemRows)
                        @php($help = \App\Support\IssueImportGuidance::forMessage($message, $document))
                        <li><b class="text-slate-900">{{ $help['title'] }} ({{ $problemRows->count() }} transaksi)</b><p class="mt-1 leading-relaxed text-slate-700">{{ $help['action'] }}</p></li>
                    @endforeach
                </ul>
                @can('operate-inventory')<a class="link mt-3 inline-flex min-h-11 items-center lg:min-h-0" href="{{ route('issue-imports.show', [$document, 'step' => 'match']) }}">Kembali ke pencocokan obat</a>@endcan
            </section>
        @else
            <p class="text-xs leading-relaxed text-slate-600">Periksa daftar obat dan jumlah yang akan keluar. Stok baru berkurang setelah Anda menekan Simpan Pengeluaran dan mengonfirmasinya.</p>
        @endif
        @can('operate-inventory')
        <form method="POST" action="{{ route('issue-imports.post', $document) }}" class="border-t border-slate-100 pt-4" data-import-post data-required-alert="Lengkapi konfirmasi sebelum posting" novalidate>
            @csrf<input type="hidden" name="preview_token" value="{{ $preview['token'] }}">
            @if(!$preview['blocked'])
            <h3 class="mb-3 text-[13px] font-extrabold text-slate-900">Pastikan sebelum menyimpan</h3>
            <div class="space-y-3 text-xs leading-relaxed text-slate-700">
                <label class="flex items-start gap-2"><input type="checkbox" name="delivered_confirmed" value="1" required data-required-label="Obat benar-benar diserahkan" class="mt-0.5 h-4 w-4 shrink-0"><span class="min-w-0 flex-1">Obat benar-benar sudah diserahkan; transaksi batal atau retur sudah diperiksa.</span></label>
                <label class="flex items-start gap-2"><input type="checkbox" name="units_confirmed" value="1" required data-required-label="Produk, satuan, dan arti kuantitas racikan" class="mt-0.5 h-4 w-4 shrink-0"><span class="min-w-0 flex-1">Obat dan satuan benar. Jumlah racikan berarti total bahan terpakai, bukan jumlah bungkus.</span></label>
                <label class="flex items-start gap-2"><input type="checkbox" name="fefo_confirmed" value="1" required data-required-label="Lokasi asal dan persetujuan FEFO" class="mt-0.5 h-4 w-4 shrink-0"><span class="min-w-0 flex-1">Stok diambil dari {{ $document->location->name }}. Saya setuju batch dipilih otomatis dari kedaluwarsa terdekat; Excel tidak memuat batch aslinya.</span></label>
                <label class="flex items-start gap-2"><input type="checkbox" name="period_confirmed" value="1" required data-required-label="Periode belum tercatat dan zona waktu WIB" class="mt-0.5 h-4 w-4 shrink-0"><span class="min-w-0 flex-1">Pengeluaran belum dicatat manual atau termasuk hasil hitung stok. Waktu pada Excel menggunakan WIB.</span></label>
                <label class="flex items-start gap-2"><input type="checkbox" name="identity_confirmed" value="1" required data-required-label="Identitas transaksi sumber stabil" class="mt-0.5 h-4 w-4 shrink-0"><span class="min-w-0 flex-1">Nomor penjualan, kode obat, dan kelompok racikan tetap sama pada ekspor ulang. Koreksi transaksi lama diperiksa terpisah.</span></label>
            </div>
            @endif
            @if($preview['blocked'])<p class="text-[13px] font-semibold text-rose-700">{{ $preview['blocked'] }} baris perlu diperbaiki. Belum ada stok yang dikurangi.</p>@endif
            <div class="mt-4 flex flex-col items-stretch gap-2"><button class="btn min-h-11 w-full lg:min-h-9" type="submit" @disabled($preview['blocked'] > 0)>{{ $preview['new'] ? 'Simpan Pengeluaran & Kurangi Stok' : 'Selesaikan Pemeriksaan' }}</button><a class="inline-flex min-h-11 items-center justify-center text-xs font-bold text-slate-600 hover:text-slate-900 lg:min-h-8" href="{{ route('issue-imports.show', [$document, 'step' => 'match']) }}">Kembali ke pencocokan</a></div>
        </form>
        @endcan
        </aside>
        </div>
        @endif
    @endif
    @if($document->status === 'posted')
        @if($document->payload)
        <details class="group card mb-4 overflow-hidden">
            <summary class="flex min-h-11 cursor-pointer list-none items-center gap-2.5 px-4 py-3 [&::-webkit-details-marker]:hidden">
                <span class="icon-tile bg-slate-100 text-slate-600"><x-ui.icon name="parameter" /></span>
                <span class="min-w-0 flex-1 text-[15px] font-extrabold text-slate-900">Rincian konversi saat posting</span>
                <x-ui.icon name="chevron-right" class="h-4 w-4 shrink-0 text-slate-400 transition-transform group-open:rotate-90" />
            </summary>
            <div class="max-h-64 overflow-auto border-t border-slate-100"><table class="w-full min-w-[540px] text-left text-[13px]"><thead class="table-head sticky top-0"><tr><th class="px-4 py-2.5">Obat sumber</th><th class="px-4 py-2.5">Jumlah sumber</th><th class="px-4 py-2.5">Isi per satuan</th><th class="px-4 py-2.5">Jumlah stok keluar</th></tr></thead><tbody class="divide-y divide-slate-100">@foreach($document->payload as $source)<tr><td class="px-4 py-2.5"><span class="text-slate-900">{{ $source['source_name'] }}</span><br><span class="text-xs text-slate-500">Baris {{ $source['source_row'] }}</span></td><td class="px-4 py-2.5 tabular-nums">{{ \App\Support\Quantity::format($source['source_quantity']) }} {{ $source['source_unit'] }}</td><td class="px-4 py-2.5 tabular-nums">{{ \App\Support\Quantity::format($source['conversion_factor']) }}</td><td class="px-4 py-2.5"><b class="font-extrabold tabular-nums text-slate-900">{{ \App\Support\Quantity::format($source['quantity']) }} {{ $source['target_unit'] }}</b><br><span class="text-xs text-slate-500">{{ $source['target_name'] }}</span></td></tr>@endforeach</tbody></table></div>
        </details>
        @endif
        <x-ui.section title="Mutasi stok dari impor ini" icon="out" tone="slate" flush :meta="$movements->total().' mutasi'">
            <div class="overflow-x-auto"><table class="w-full text-left text-[13px]"><thead class="table-head"><tr><th class="px-4 py-2.5">Referensi / Waktu</th><th class="px-4 py-2.5">Obat / Batch FEFO</th><th class="px-4 py-2.5">Jumlah keluar</th><th class="px-4 py-2.5">Kartu stok</th></tr></thead><tbody class="divide-y divide-slate-100">
                @forelse($movements as $movement)<tr><td class="px-4 py-2.5"><span class="inline-block rounded bg-slate-100 px-1.5 font-mono text-[11px] font-semibold text-slate-600">{{ $movement->reference }}</span><br><span class="text-xs tabular-nums text-slate-500">{{ $movement->occurred_at->format('d/m/Y H:i') }}</span></td><td class="px-4 py-2.5"><b class="font-extrabold text-slate-900">{{ $movement->medicine->name }}</b><br><span class="text-xs text-slate-500">{{ $movement->batch?->number }}</span></td><td class="px-4 py-2.5 font-extrabold tabular-nums text-slate-900">{{ \App\Support\Quantity::format((float)$movement->quantity) }} {{ $movement->medicine->unit?->name }}</td><td class="px-4 py-2.5">@can('view-stock-card')<a class="link text-xs" href="{{ route('stock-card.index', ['medicine_id' => $movement->medicine_id, 'location_id' => $document->location_id, 'start' => $document->period_start->format('Y-m-d'), 'end' => $document->period_end->format('Y-m-d')]) }}">Lihat mutasi</a>@endcan</td></tr>@empty<tr><td colspan="4" class="empty-state">Tidak ada mutasi baru dari dokumen ini; seluruh transaksi sudah diimpor sebelumnya.</td></tr>@endforelse
            </tbody></table></div>
            @if($movements->hasPages())<div class="border-t border-slate-100 px-4 py-3">{{ $movements->links() }}</div>@endif
        </x-ui.section>
    @endif
</x-layouts.app>
