<section aria-labelledby="import-match-title" data-import-match>
    <div class="issue-import-workspace">
        <form id="import-mapping-form" method="POST" action="{{ route('issue-imports.mappings', $document) }}" class="min-w-0" data-import-mapping-form data-import-list>
            @csrf
            <h3 id="import-match-title" class="mb-2 text-sm font-semibold">Cocokkan obat dari Excel</h3>
            <div class="mb-3 flex flex-wrap items-center justify-between gap-3 text-xs">
                <span class="text-slate-600">{{ $needsMapping }} obat/satuan perlu dicocokkan · {{ count($preview['groups']) - $needsMapping }} tidak perlu dicocokkan ulang</span>
            </div>
            <div class="mb-3 flex flex-wrap items-center justify-between gap-3 text-xs">
                <input type="search" class="field max-w-xs text-xs" aria-label="Cari obat pada Excel" placeholder="Cari kode atau nama obat pada Excel..." maxlength="200" data-import-match-search>
                <label class="flex items-center gap-2"><input type="checkbox" data-import-show-saved @checked($needsMapping === 0)>Tampilkan juga pilihan tersimpan</label>
            </div>
        <div class="issue-import-list-scroll relative overflow-auto rounded-md border border-slate-200 bg-white" tabindex="0" role="region" aria-label="Daftar pencocokan obat">
            <table class="issue-import-mapping issue-import-match-table w-full table-fixed text-left text-xs" data-import-mappings>
                <thead class="sticky top-0 z-10 bg-slate-50 text-slate-600"><tr><th class="w-[5%] p-2">No.</th><th class="w-[16%] p-3">Tanggal Jual (WIB)</th><th class="w-[23%] p-3">Obat pada Excel</th><th class="w-[26%] p-3">Obat di ShifaStock</th><th class="w-[20%] p-3">Samakan satuannya</th><th class="w-[10%] p-3">Sudah benar</th></tr></thead>
                <tbody class="divide-y divide-slate-100">@foreach($preview['groups'] as $key => $group)
                    @php($selectedId = old('mappings.'.$key.'.medicine_id', $group['mapping']?->medicine_id ?? $group['suggestion']?->id))
                    @php($selectedMedicine = $medicines->firstWhere('id', $selectedId))
                    <tr data-import-match-row data-import-search-text="{{ $group['code'].' '.$group['name'] }}" data-needs-mapping="{{ $group['needs_mapping'] ? '1' : '0' }}" @if($needsMapping > 0 && !$group['needs_mapping']) hidden @endif>
                        <td class="p-2 align-top text-slate-500" data-label="No.">{{ $loop->iteration }}</td>
                        <td class="p-3 align-top" data-label="Tanggal Jual (WIB)"><x-issue-import-source-period :rows="$group['details']" /></td>
                        <td class="p-3 align-top" data-label="Obat pada Excel"><b class="break-words">{{ $group['name'] }}</b><p class="mt-1 text-slate-500">{{ $group['code'] }} · {{ \App\Support\Quantity::format($group['quantity']) }} {{ $group['unit'] }}</p>@if($group['mapping_valid'])<p class="mt-1 text-emerald-700">Pilihan tersimpan</p>@elseif($group['mapping'])<p class="mt-1 text-amber-700">Pilihan lama perlu diperiksa ulang</p>@elseif($group['suggestion'])<p class="mt-1 text-amber-700">Saran otomatis; belum dikonfirmasi</p>@endif</td>
                        <td class="p-3 align-top" data-label="Obat di ShifaStock"><label class="sr-only" for="map-{{ $key }}">Obat ShifaStock untuk {{ $group['code'] }} satuan {{ $group['unit'] }}</label><select id="map-{{ $key }}" class="field text-xs" name="mappings[{{ $key }}][medicine_id]" data-searchable-select data-search-placeholder="Cari kode atau nama obat..." data-import-medicine>
                            <option value="">Cari obat yang sama</option>@foreach($medicines as $medicine)<option value="{{ $medicine->id }}" data-unit="{{ $medicine->unit?->name }}" @selected($selectedId==$medicine->id)>{{ $medicine->code }} - {{ $medicine->name }} ({{ $medicine->unit?->name }})</option>@endforeach
                        </select></td>
                        <td class="p-3 align-top" data-label="Samakan satuannya"><label for="factor-{{ $key }}" class="mb-2 block">1 {{ $group['unit'] }} pada Excel berisi:</label><div class="flex min-w-0 flex-wrap items-center gap-2"><input id="factor-{{ $key }}" class="field w-20 text-xs" type="number" min="0.0001" max="1000000" step="0.0001" name="mappings[{{ $key }}][factor]" value="{{ old('mappings.'.$key.'.factor', \App\Support\Quantity::input($group['mapping']?->factor ?? 1)) }}" required><span class="min-w-0 break-words" data-import-target-unit>{{ $selectedMedicine?->unit?->name ?? 'pilih obat dahulu' }}</span></div>@if($group['unit'] === '-')<p class="mt-2 text-amber-800">Satuan Excel kosong. Tanyakan satuannya sebelum menyimpan.</p>@endif</td>
                        <td class="p-3 align-top" data-label="Sudah benar"><label class="flex items-center gap-2"><input type="checkbox" name="mappings[{{ $key }}][confirmed]" value="1" data-import-map-confirm @checked(old('mappings.'.$key.'.confirmed'))> Benar</label></td>
                    </tr>
                    <tr data-import-match-details @if($needsMapping > 0 && !$group['needs_mapping']) hidden @endif><td colspan="6" class="!p-0"><x-issue-import-source-details :rows="$group['details']" :document="$document" /></td></tr>
                @endforeach</tbody>
            </table>
        </div>
        <p class="mt-2 text-xs text-slate-500" data-import-match-result aria-live="polite"></p>
        </form>
        <aside class="issue-import-info" aria-labelledby="import-match-guide-title" data-import-info>
            <x-issue-import-context :document="$document" :step="$step" />
            <x-issue-import-summary :preview="$preview" />
            <details class="text-xs text-slate-600" data-import-matching-guide>
            <summary id="import-match-guide-title" class="cursor-pointer font-semibold">Petunjuk pencocokan</summary>
            <ol class="mt-3 list-decimal space-y-3 pl-4 leading-relaxed">
                <li>Pilih obat yang sama di ShifaStock. Pastikan nama, bentuk, dan kekuatannya sesuai, misalnya tablet bukan sirup.</li>
                <li>Periksa isi per satuan. Contoh: 1 BUTIR berisi 1 Tablet; untuk BOX, isi sesuai kemasan obat tersebut.</li>
                <li>Tandai <b>Benar</b> setelah diperiksa. Pilihan tersimpan akan dipakai lagi pada impor berikutnya.</li>
                <li>Daftar diringkas per obat dan satuan. Tanggal yang berbeda ditulis sebagai rentang; buka rincian untuk melihat waktu dan jumlah setiap baris Excel.</li>
            </ol>
            </details>
            <div class="space-y-3 border-t border-slate-200 pt-4">
                @if($needsMapping > 0)<p class="text-xs leading-relaxed text-slate-600" data-import-confirmation-help>Pilih obat yang sesuai, periksa isi per satuan, lalu centang <b>Benar</b> dan simpan pilihan. Isian otomatis masih berupa saran.</p>@endif
                <label class="flex items-start gap-2 text-xs leading-relaxed text-slate-600"><input type="checkbox" class="mt-0.5 shrink-0" data-import-select-visible><span>Tandai pilihan yang tampil dan sudah saya periksa</span></label>
                <button class="btn w-full text-xs" type="submit" form="import-mapping-form">Simpan Pilihan &amp; Lanjutkan</button>
                <p class="text-xs leading-relaxed text-slate-500">Hanya pilihan yang ditandai Benar yang disimpan. Stok belum berkurang.</p>
                <a class="inline-block text-xs font-medium text-blue-700" href="{{ route('issue-imports.show', [$document, 'step' => 'review']) }}">Lihat hasil pemeriksaan</a>
            </div>
        </aside>
    </div>
</section>
