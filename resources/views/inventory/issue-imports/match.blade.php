<section aria-labelledby="import-match-title" data-import-match>
    <div class="issue-import-workspace">
        <form id="import-mapping-form" method="POST" action="{{ route('issue-imports.mappings', $document) }}" class="card min-w-0 overflow-hidden" data-import-mapping-form data-import-list>
            @csrf
            <input type="hidden" name="mappings_payload" value="" data-import-mappings-payload>
            <div class="section-head">
                <div class="flex min-w-0 items-center gap-2.5">
                    <span class="icon-tile bg-brand-100 text-brand-700"><x-ui.icon name="list" /></span>
                    <div class="min-w-0">
                        <h3 id="import-match-title" class="section-title">Cocokkan obat dari Excel</h3>
                        <p class="section-sub">{{ $needsMapping }} obat/satuan perlu dicocokkan · {{ count($preview['groups']) - $needsMapping }} tidak perlu dicocokkan ulang</p>
                    </div>
                </div>
            </div>
            <noscript><p class="callout-warn m-4">Aktifkan JavaScript lalu muat ulang halaman untuk menyimpan pilihan obat dengan aman.</p></noscript>
            <div class="flex flex-wrap items-center justify-between gap-2.5 border-b border-slate-100 px-4 py-3">
                <x-ui.search label="Cari obat pada Excel" width="sm:w-80" aria-label="Cari obat pada Excel" placeholder="Cari kode atau nama obat pada Excel..." maxlength="200" data-import-match-search />
                <label class="flex min-h-11 items-center gap-2 text-xs font-semibold text-slate-600 lg:min-h-8"><input type="checkbox" class="h-4 w-4 shrink-0" data-import-show-saved @checked($needsMapping === 0)>Tampilkan juga pilihan tersimpan</label>
            </div>
            <div class="issue-import-list-scroll relative overflow-auto" tabindex="0" role="region" aria-label="Daftar pencocokan obat">
                <table class="issue-import-mapping issue-import-match-table w-full table-fixed text-left text-xs" data-import-mappings>
                    <thead class="table-head sticky top-0 z-10"><tr><th class="w-[44px] px-3 py-2.5">No.</th><th class="w-[15%] px-3 py-2.5">Tanggal Jual (WIB)</th><th class="px-3 py-2.5">Obat pada Excel</th><th class="w-[30%] px-3 py-2.5">Obat di ShifaStock</th><th class="w-[19%] px-3 py-2.5">Samakan satuannya</th><th class="w-[84px] px-3 py-2.5">Sudah benar</th></tr></thead>
                    <tbody class="divide-y divide-slate-100">@foreach($preview['groups'] as $key => $group)
                        @php($selectedId = old('mappings.'.$key.'.medicine_id', $group['mapping']?->medicine_id ?? $group['suggestion']?->id))
                        @php($selectedMedicine = $medicines->firstWhere('id', $selectedId))
                        <tr data-import-match-row data-import-mapping-key="{{ $key }}" data-import-search-text="{{ $group['code'].' '.$group['name'] }}" data-needs-mapping="{{ $group['needs_mapping'] ? '1' : '0' }}" @if($needsMapping > 0 && !$group['needs_mapping']) hidden @endif>
                            <td class="px-3 py-3 align-top tabular-nums text-slate-500" data-label="No.">{{ $loop->iteration }}</td>
                            <td class="px-3 py-3 align-top text-slate-700" data-label="Tanggal Jual (WIB)"><x-issue-import-source-period :rows="$group['details']" /></td>
                            <td class="px-3 py-3 align-top" data-label="Obat pada Excel"><b class="break-words text-[13px] font-extrabold text-slate-900">{{ $group['name'] }}</b><p class="mt-1 flex flex-wrap items-center gap-x-1.5 gap-y-1 text-slate-500"><span class="rounded bg-slate-100 px-1.5 font-mono text-[11px] font-semibold text-slate-600">{{ $group['code'] }}</span><span class="tabular-nums">{{ \App\Support\Quantity::format($group['quantity']) }} {{ $group['unit'] }}</span></p>@if($group['mapping_valid'])<p class="mt-1.5"><span class="badge badge-ok">Pilihan tersimpan</span></p>@elseif($group['mapping'])<p class="mt-1.5"><span class="badge badge-warn">Pilihan lama perlu diperiksa ulang</span></p>@elseif($group['suggestion'])<p class="mt-1.5"><span class="badge badge-warn">Saran otomatis; belum dikonfirmasi</span></p>@endif</td>
                            <td class="px-3 py-3 align-top" data-label="Obat di ShifaStock"><label class="sr-only" for="map-{{ $key }}">Obat ShifaStock untuk {{ $group['code'] }} satuan {{ $group['unit'] }}</label><select id="map-{{ $key }}" class="field text-xs" name="mappings[{{ $key }}][medicine_id]" data-searchable-select data-search-placeholder="Cari kode atau nama obat..." data-import-medicine>
                                <option value="">Cari obat yang sama</option>@foreach($medicines as $medicine)<option value="{{ $medicine->id }}" data-unit="{{ $medicine->unit?->name }}" @selected($selectedId==$medicine->id)>{{ $medicine->code }} - {{ $medicine->name }} ({{ $medicine->unit?->name }})</option>@endforeach
                            </select></td>
                            <td class="px-3 py-3 align-top text-slate-600" data-label="Samakan satuannya"><label for="factor-{{ $key }}" class="mb-1.5 block">1 {{ $group['unit'] }} pada Excel berisi:</label><div class="flex min-w-0 flex-wrap items-center gap-2"><input id="factor-{{ $key }}" class="field w-20 text-xs tabular-nums" type="number" min="0.0001" max="1000000" step="0.0001" name="mappings[{{ $key }}][factor]" value="{{ old('mappings.'.$key.'.factor', \App\Support\Quantity::input($group['mapping']?->factor ?? 1)) }}" required><span class="min-w-0 break-words font-semibold text-slate-700" data-import-target-unit>{{ $selectedMedicine?->unit?->name ?? 'pilih obat dahulu' }}</span></div>@if($group['unit'] === '-')<p class="mt-2 text-amber-800">Satuan Excel kosong. Tanyakan satuannya sebelum menyimpan.</p>@endif</td>
                            <td class="px-3 py-3 align-top" data-label="Sudah benar"><label class="flex min-h-11 items-center gap-2 whitespace-nowrap font-semibold text-slate-700 [overflow-wrap:normal] lg:min-h-0"><input type="checkbox" class="h-4 w-4 shrink-0" name="mappings[{{ $key }}][confirmed]" value="1" data-import-map-confirm @checked(old('mappings.'.$key.'.confirmed'))> Benar</label></td>
                        </tr>
                        <tr data-import-match-details @if($needsMapping > 0 && !$group['needs_mapping']) hidden @endif><td colspan="6" class="!p-0"><x-issue-import-source-details :rows="$group['details']" :document="$document" /></td></tr>
                    @endforeach</tbody>
                </table>
            </div>
            <p class="border-t border-slate-100 px-4 py-2.5 text-xs text-slate-500" data-import-match-result aria-live="polite"></p>
        </form>
        <aside class="issue-import-info" aria-labelledby="import-match-guide-title" data-import-info>
            <x-issue-import-context :document="$document" :step="$step" />
            <x-issue-import-summary :preview="$preview" />
            <details class="rounded-xl border border-slate-200 text-xs text-slate-600" data-import-matching-guide>
                <summary id="import-match-guide-title" class="cursor-pointer px-3 py-2.5 font-bold text-slate-700">Petunjuk pencocokan</summary>
                <ol class="list-decimal space-y-2.5 border-t border-slate-100 py-3 pl-7 pr-3 leading-relaxed">
                    <li>Pilih obat yang sama di ShifaStock. Pastikan nama, bentuk, dan kekuatannya sesuai, misalnya tablet bukan sirup.</li>
                    <li>Periksa isi per satuan. Contoh: 1 BUTIR berisi 1 Tablet; untuk BOX, isi sesuai kemasan obat tersebut.</li>
                    <li>Tandai <b>Benar</b> setelah diperiksa. Pilihan tersimpan akan dipakai lagi pada impor berikutnya.</li>
                    <li>Daftar diringkas per obat dan satuan. Tanggal yang berbeda ditulis sebagai rentang; buka rincian untuk melihat waktu dan jumlah setiap baris Excel.</li>
                </ol>
            </details>
            <div class="space-y-2.5 border-t border-slate-100 pt-3">
                @if($needsMapping > 0)<p class="text-xs leading-relaxed text-slate-600" data-import-confirmation-help>Pilih obat yang sesuai, periksa isi per satuan, lalu centang <b>Benar</b> dan simpan pilihan. Isian otomatis masih berupa saran.</p>@endif
                <label class="flex items-start gap-2 text-xs leading-relaxed text-slate-700"><input type="checkbox" class="mt-0.5 h-4 w-4 shrink-0" data-import-select-visible><span>Tandai pilihan yang tampil dan sudah saya periksa</span></label>
                <button class="btn min-h-11 w-full lg:min-h-9" type="submit" form="import-mapping-form">Simpan Pilihan &amp; Lanjutkan</button>
                <p class="flex flex-wrap items-center justify-between gap-x-3 gap-y-1 text-xs leading-relaxed text-slate-500">
                    <span>Hanya pilihan yang ditandai Benar yang disimpan. Stok belum berkurang.</span>
                    <a class="link inline-flex min-h-11 items-center lg:min-h-0" href="{{ route('issue-imports.show', [$document, 'step' => 'review']) }}">Lihat hasil pemeriksaan →</a>
                </p>
            </div>
        </aside>
    </div>
</section>
