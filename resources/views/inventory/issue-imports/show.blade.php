<x-layouts.app title="Rincian Impor Pengeluaran" :hide-description="true">
    <x-issue-tabs></x-issue-tabs>
    @if(!$preview)
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div><h2 class="text-base font-semibold">IMP-{{ $document->id }} · {{ $document->location->name }}</h2><p class="mt-1 text-xs text-slate-500">{{ $document->period_start->format('d/m/Y H:i') }} - {{ $document->period_end->format('d/m/Y H:i') }} WIB · {{ $document->row_count }} baris · {{ $document->sale_count }} penjualan · {{ $document->compound_count }} baris racikan</p></div>
        <div class="flex items-center gap-3"><a href="{{ route('issue-imports.index') }}" class="text-sm font-medium text-blue-700">Kembali ke riwayat</a><x-issue-import-delete :document="$document" /></div>
    </div>
    @if(session('status'))<p role="status" class="mb-4 rounded-md border border-blue-200 bg-blue-50 p-3 text-sm text-blue-800">{{ session('status') }}</p>@endif
    @if($errors->any())<div role="alert" class="mb-4 rounded-md border border-rose-200 bg-rose-50 p-3 text-sm text-rose-800"><ul class="list-inside list-disc">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    @if($expired)<p class="border-y border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">Pratinjau kedaluwarsa setelah 7 hari. Unggah ulang file untuk memeriksa stok dan pemetaan terbaru. Belum ada stok yang dikurangi dari draf ini.</p>@endif
    @endif
    @if($preview)
        @if($step === 'match')
            @can('operate-inventory')
                @include('inventory.issue-imports.match')
            @endcan
        @else
        <div class="issue-import-workspace">
        <section class="min-w-0" aria-labelledby="import-preview-title" data-import-list>
            <div class="mb-3 flex flex-wrap items-end justify-between gap-3"><div><h3 id="import-preview-title" class="text-sm font-semibold">Obat yang akan dicatat</h3><p class="mt-1 text-xs text-slate-500">Pengeluaran sejak {{ $document->starts_at->format('d/m/Y H:i') }} WIB. Obat yang sudah pernah dicatat tidak dipotong lagi.</p></div>
                <div><label class="label" for="import-review-filter">Tampilkan</label><select id="import-review-filter" class="field text-xs" data-import-review-filter><option value="all">Semua obat</option><option value="blocked" @selected($preview['blocked'] > 0)>Perlu diperiksa</option><option value="ready">Siap disimpan</option><option value="duplicate">Sudah pernah dicatat</option></select></div>
            </div>
            <div class="issue-import-list-scroll relative overflow-auto rounded-md border border-slate-200 bg-white" tabindex="0" role="region" aria-label="Daftar pemeriksaan pengeluaran">
                <table class="issue-import-review-table w-full table-fixed text-left text-xs" data-import-preview>
                    <thead class="sticky top-0 z-10 bg-slate-50 text-slate-600"><tr><th class="w-[5%] p-2">No.</th><th class="w-[17%] p-3">Tanggal Jual (WIB)</th><th class="w-[28%] p-3">Obat pada Excel</th><th class="w-[12%] p-3">Jumlah di Excel</th><th class="w-[18%] p-3">Stok yang akan berkurang</th><th class="w-[20%] p-3">Status</th></tr></thead>
                    <tbody class="divide-y divide-slate-100">@foreach($preview['groups'] as $group)<tr data-import-review-row data-ready="{{ $group['ready'] }}" data-blocked="{{ $group['blocked'] }}" data-duplicate="{{ $group['duplicate'] }}" @if($preview['blocked'] > 0 && !$group['blocked']) hidden @endif>
                        <td class="p-2 align-top text-slate-500" data-label="No.">{{ $loop->iteration }}</td>
                        <td class="p-3 align-top" data-label="Tanggal Jual (WIB)"><x-issue-import-source-period :rows="$group['details']" /></td>
                        <td class="p-3 align-top" data-label="Obat"><b>{{ $group['name'] }}</b><br><span class="text-slate-500">{{ $group['code'] }}</span>
                        </td>
                        <td class="p-3 align-top" data-label="Jumlah pada Excel">{{ \App\Support\Quantity::format((float)$group['quantity']) }} {{ $group['unit'] }}</td>
                        <td class="p-3 align-top" data-label="Stok yang akan berkurang">@if($group['medicine']){{ \App\Support\Quantity::format((float)$group['new_quantity']) }} {{ $group['medicine']->unit?->name }}<br><span class="text-slate-500">{{ $group['medicine']->name }}</span>@else Pilih obat dahulu @endif</td>
                        <td class="p-3 align-top" data-label="Hasil pemeriksaan"><span class="text-emerald-700">{{ $group['ready'] }} siap</span> · <span class="text-slate-500">{{ $group['duplicate'] }} sudah diimpor</span>@if($group['blocked'])<br><b class="text-rose-700">{{ $group['blocked'] }} perlu diperbaiki</b>@foreach(array_unique($group['problems']) as $problem)<p class="mt-1 text-rose-700">{{ \App\Support\IssueImportGuidance::forMessage($problem, $document)['title'] }}</p>@endforeach @endif</td>
                    </tr><tr data-import-review-details @if($preview['blocked'] > 0 && !$group['blocked']) hidden @endif><td colspan="6" class="!p-0"><x-issue-import-source-details :rows="$group['details']" :document="$document" /></td></tr>@endforeach</tbody>
                </table>
            </div>
            <p class="mt-2 text-xs text-slate-500" data-import-review-count aria-live="polite"></p>
        </section>
        <aside class="issue-import-info" aria-labelledby="import-review-guide-title" data-import-info>
        <x-issue-import-context :document="$document" :step="$step" />
        <h3 id="import-review-guide-title" class="text-sm font-semibold">Ringkasan &amp; konfirmasi</h3>
        <x-issue-import-summary :preview="$preview" />
        @if($preview['blocked'])
            <section class="border-l-4 border-amber-400 bg-amber-50 p-3 text-xs" aria-labelledby="import-problems-title" data-import-problems>
                <h3 id="import-problems-title" class="font-semibold text-slate-900">Selesaikan bagian berikut sebelum menyimpan</h3>
                <ul class="mt-3 space-y-3">
                    @foreach(collect($preview['details'])->where('status', 'blocked')->groupBy('message') as $message => $problemRows)
                        @php($help = \App\Support\IssueImportGuidance::forMessage($message, $document))
                        <li><b>{{ $help['title'] }} ({{ $problemRows->count() }} transaksi)</b><p class="mt-1 leading-relaxed text-slate-700">{{ $help['action'] }}</p></li>
                    @endforeach
                </ul>
                @can('operate-inventory')<a class="mt-3 inline-block font-semibold text-blue-700" href="{{ route('issue-imports.show', [$document, 'step' => 'match']) }}">Kembali ke pencocokan obat</a>@endcan
            </section>
        @else
            <p class="text-xs leading-relaxed text-slate-600">Periksa daftar obat dan jumlah yang akan keluar. Stok baru berkurang setelah Anda menekan Simpan Pengeluaran dan mengonfirmasinya.</p>
        @endif
        @can('operate-inventory')
        <form method="POST" action="{{ route('issue-imports.post', $document) }}" class="border-t border-slate-200 pt-4" data-import-post data-required-alert="Lengkapi konfirmasi sebelum posting" novalidate>
            @csrf<input type="hidden" name="preview_token" value="{{ $preview['token'] }}">
            @if(!$preview['blocked'])
            <h3 class="mb-3 text-sm font-semibold">Pastikan sebelum menyimpan</h3>
            <div class="space-y-3 text-xs leading-relaxed text-slate-700">
                <label class="flex items-start gap-2"><input type="checkbox" name="delivered_confirmed" value="1" required data-required-label="Obat benar-benar diserahkan" class="mt-0.5 shrink-0"><span class="min-w-0 flex-1">Obat benar-benar sudah diserahkan; transaksi batal atau retur sudah diperiksa.</span></label>
                <label class="flex items-start gap-2"><input type="checkbox" name="units_confirmed" value="1" required data-required-label="Produk, satuan, dan arti kuantitas racikan" class="mt-0.5 shrink-0"><span class="min-w-0 flex-1">Obat dan satuan benar. Jumlah racikan berarti total bahan terpakai, bukan jumlah bungkus.</span></label>
                <label class="flex items-start gap-2"><input type="checkbox" name="fefo_confirmed" value="1" required data-required-label="Lokasi asal dan persetujuan FEFO" class="mt-0.5 shrink-0"><span class="min-w-0 flex-1">Stok diambil dari {{ $document->location->name }}. Saya setuju batch dipilih otomatis dari kedaluwarsa terdekat; Excel tidak memuat batch aslinya.</span></label>
                <label class="flex items-start gap-2"><input type="checkbox" name="period_confirmed" value="1" required data-required-label="Periode belum tercatat dan zona waktu WIB" class="mt-0.5 shrink-0"><span class="min-w-0 flex-1">Pengeluaran belum dicatat manual atau termasuk hasil hitung stok. Waktu pada Excel menggunakan WIB.</span></label>
                <label class="flex items-start gap-2"><input type="checkbox" name="identity_confirmed" value="1" required data-required-label="Identitas transaksi sumber stabil" class="mt-0.5 shrink-0"><span class="min-w-0 flex-1">Nomor penjualan, kode obat, dan kelompok racikan tetap sama pada ekspor ulang. Koreksi transaksi lama diperiksa terpisah.</span></label>
            </div>
            @endif
            @if($preview['blocked'])<p class="mt-4 text-sm font-semibold text-rose-700">{{ $preview['blocked'] }} baris perlu diperbaiki. Belum ada stok yang dikurangi.</p>@endif
            <div class="mt-4 flex flex-col items-start gap-3"><button class="btn w-full text-xs disabled:cursor-not-allowed disabled:opacity-50" type="submit" @disabled($preview['blocked'] > 0)>{{ $preview['new'] ? 'Simpan Pengeluaran & Kurangi Stok' : 'Selesaikan Pemeriksaan' }}</button><a class="text-xs font-semibold text-slate-600" href="{{ route('issue-imports.show', [$document, 'step' => 'match']) }}">Kembali ke pencocokan</a></div>
        </form>
        @endcan
        </aside>
        </div>
        @endif
    @endif
    @if($document->status === 'posted')
        <p class="mb-4 border-y border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-900">{{ $document->posted_count }} transaksi baru diposting oleh {{ $document->poster?->name }} · {{ $document->duplicate_count }} duplikat tidak dikurangi lagi. Data terposting tidak dapat diedit dari impor.</p>
        @if($document->payload)
        <details class="mb-4 border-b border-slate-200 pb-4"><summary class="cursor-pointer text-sm font-semibold text-slate-700">Rincian konversi saat posting</summary><div class="mt-3 max-h-64 overflow-auto"><table class="w-full min-w-[540px] text-left text-xs"><thead class="bg-slate-50"><tr><th class="p-3">Obat sumber</th><th class="p-3">Jumlah sumber</th><th class="p-3">Isi per satuan</th><th class="p-3">Jumlah stok keluar</th></tr></thead><tbody class="divide-y divide-slate-100">@foreach($document->payload as $source)<tr><td class="p-3">{{ $source['source_name'] }}<br><span class="text-slate-500">Baris {{ $source['source_row'] }}</span></td><td class="p-3">{{ \App\Support\Quantity::format($source['source_quantity']) }} {{ $source['source_unit'] }}</td><td class="p-3">{{ \App\Support\Quantity::format($source['conversion_factor']) }}</td><td class="p-3">{{ \App\Support\Quantity::format($source['quantity']) }} {{ $source['target_unit'] }}<br><span class="text-slate-500">{{ $source['target_name'] }}</span></td></tr>@endforeach</tbody></table></div></details>
        @endif
        <div class="overflow-x-auto rounded-md border border-slate-200"><table class="w-full text-left text-xs"><thead class="bg-slate-50"><tr><th class="p-3">Referensi / Waktu</th><th class="p-3">Obat / Batch FEFO</th><th class="p-3">Jumlah keluar</th><th class="p-3">Kartu stok</th></tr></thead><tbody class="divide-y divide-slate-100">
            @forelse($movements as $movement)<tr><td class="p-3">{{ $movement->reference }}<br><span class="text-slate-500">{{ $movement->occurred_at->format('d/m/Y H:i') }}</span></td><td class="p-3">{{ $movement->medicine->name }}<br><span class="text-slate-500">{{ $movement->batch?->number }}</span></td><td class="p-3">{{ \App\Support\Quantity::format((float)$movement->quantity) }} {{ $movement->medicine->unit?->name }}</td><td class="p-3">@can('view-stock-card')<a class="font-semibold text-blue-700" href="{{ route('stock-card.index', ['medicine_id' => $movement->medicine_id, 'location_id' => $document->location_id, 'start' => $document->period_start->format('Y-m-d'), 'end' => $document->period_end->format('Y-m-d')]) }}">Lihat mutasi</a>@endcan</td></tr>@empty<tr><td colspan="4" class="p-5 text-slate-500">Tidak ada mutasi baru dari dokumen ini; seluruh transaksi sudah diimpor sebelumnya.</td></tr>@endforelse
        </tbody></table></div><div class="mt-4">{{ $movements->links() }}</div>
    @endif
</x-layouts.app>
