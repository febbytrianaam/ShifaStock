@php
    $canManage = auth()->user()->can('manage-medicines');
    $control = 'min-h-11 lg:min-h-[34px] lg:py-1.5';
    $rowAction = 'inline-flex min-h-11 items-center rounded-lg border px-2.5 text-xs font-bold lg:min-h-8';
    $tiles = [
        ['label' => 'Total obat', 'value' => $summary['total'], 'theme' => 'neutral', 'icon' => 'medicine', 'sub' => 'Seluruh master obat'],
        ['label' => 'Aktif', 'value' => $summary['active'], 'theme' => 'brand', 'icon' => 'ok', 'sub' => \App\Support\Quantity::format($summary['total'] - $summary['active']).' nonaktif',
            'property' => 'operationalFilter', 'filter' => 'active', 'current' => $operationalFilter],
        ['label' => 'Terverifikasi', 'value' => $summary['verified'], 'theme' => $summary['verified'] > 0 ? 'green' : 'muted', 'icon' => 'shield',
            'sub' => $summary['total'] > 0 ? round($summary['verified'] / $summary['total'] * 100).'% dari total' : 'Belum ada obat',
            'property' => 'verificationFilter', 'filter' => 'terverifikasi', 'current' => $verificationFilter],
        ['label' => 'Perlu revisi', 'value' => $summary['revision'], 'theme' => $summary['revision'] > 0 ? 'amber' : 'muted', 'icon' => 'alert',
            'sub' => $summary['revision'] > 0 ? 'Periksa catatan verifikasi' : 'Tidak ada yang perlu direvisi',
            'property' => 'verificationFilter', 'filter' => 'perlu_revisi', 'current' => $verificationFilter],
        ['label' => 'Belum verifikasi', 'value' => $summary['pending'], 'theme' => $summary['pending'] > 0 ? 'violet' : 'muted', 'icon' => 'clock',
            'sub' => $summary['pending'] > 0 ? 'Menunggu ditinjau' : 'Semua sudah ditinjau',
            'property' => 'verificationFilter', 'filter' => 'belum_diverifikasi', 'current' => $verificationFilter],
    ];
@endphp
<div>
    @if(session('status'))
        <div role="status" class="callout mb-4 font-semibold">{{ session('status') }}</div>
    @endif

    @can('manage-medicines')
        <dialog id="medicine-dialog" wire:ignore.self class="w-[calc(100%-2rem)] max-w-4xl rounded-2xl border border-slate-200 bg-white p-0 text-[13px] text-slate-800" aria-labelledby="medicine-dialog-title">
            <div class="flex items-start justify-between gap-3 border-b border-slate-100 px-5 py-4">
                <div class="flex items-center gap-2.5">
                    <span class="icon-tile bg-brand-100 text-brand-700"><x-ui.icon name="medicine" /></span>
                    <div>
                        <span class="text-xs font-bold text-slate-500">Master Obat</span>
                        <h2 id="medicine-dialog-title" class="section-title">{{ $editingId ? 'Edit Obat' : 'Tambah Obat' }}</h2>
                    </div>
                </div>
                <button type="button" data-close-dialog aria-label="Tutup form" class="grid h-11 w-11 place-items-center rounded-lg text-xl leading-none text-slate-400 hover:bg-slate-100 lg:h-9 lg:w-9">&times;</button>
            </div>

            <form wire:submit="save" class="p-5">
                <div class="grid gap-4 lg:grid-cols-2">
                    <div>
                        <label for="code" class="label">Kode klinik</label>
                        <input id="code" wire:model="code" class="field" required maxlength="50" placeholder="ID dari klinik">
                        @error('code')<p class="error">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="original_name" class="label">Nama asli klinik</label>
                        <input id="original_name" wire:model="original_name" class="field" maxlength="255" placeholder="Nama sesuai data klinik">
                        @error('original_name')<p class="error">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="name" class="label">Nama final</label>
                        <input id="name" wire:model="name" class="field" required maxlength="255" placeholder="Nama kandidat / final">
                        @error('name')<p class="error">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="generic_name" class="label">Kandungan / keterangan</label>
                        <input id="generic_name" wire:model="generic_name" class="field" maxlength="255" placeholder="Kandungan obat">
                        @error('generic_name')<p class="error">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="strength" class="label">Kekuatan</label>
                        <input id="strength" wire:model="strength" class="field" maxlength="100" placeholder="500 mg / 5 ml">
                        @error('strength')<p class="error">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="dosage_form" class="label">Bentuk sediaan</label>
                        <input id="dosage_form" wire:model="dosage_form" class="field" maxlength="100" placeholder="Tablet / Sirup / Kapsul">
                        @error('dosage_form')<p class="error">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="unit_id" class="label">Satuan stok</label>
                        <select id="unit_id" wire:model="unit_id" class="field">
                            <option value="">Belum dipilih</option>
                            @foreach($units as $unit)
                                <option value="{{ $unit->id }}">{{ $unit->name }}</option>
                            @endforeach
                        </select>
                        @error('unit_id')<p class="error">{{ $message }}</p>@enderror
                        <p class="mt-1 text-xs text-slate-500">Satuan stok tidak dapat diganti setelah obat digunakan pada stok atau transaksi. Kemasan pembelian tetap bisa disesuaikan.</p>
                    </div>
                    <div>
                        <label for="package_name" class="label">Kemasan pembelian</label>
                        <input id="package_name" wire:model="package_name" class="field" maxlength="100" placeholder="Nama kemasan">
                        @error('package_name')<p class="error">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="package_conversion" class="label">Jumlah satuan stok dalam 1 kemasan</label>
                        <input id="package_conversion" wire:model="package_conversion" class="field" type="number" step="0.01" min="0.01" placeholder="Contoh: 10">
                        @error('package_conversion')<p class="error">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="verification_status" class="label">Status verifikasi</label>
                        <select id="verification_status" wire:model="verification_status" class="field">
                            <option value="belum_diverifikasi">Belum Diverifikasi</option>
                            <option value="perlu_revisi">Perlu Revisi</option>
                            <option value="terverifikasi">Terverifikasi</option>
                        </select>
                        @error('verification_status')<p class="error">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="package_reference" class="label">Referensi kemasan</label>
                        <textarea id="package_reference" wire:model="package_reference" class="field min-h-20" maxlength="2000" placeholder="Catatan kemasan dari referensi"></textarea>
                        @error('package_reference')<p class="error">{{ $message }}</p>@enderror
                    </div>
                    <details class="rounded-xl border border-slate-200 px-3.5 py-2.5 lg:col-span-2" @if($errors->has('stock_unit_suggestion') || $errors->has('purchase_unit_name') || $errors->has('conversion_candidate')) open @endif>
                        <summary class="flex min-h-8 cursor-pointer items-center text-xs font-bold text-slate-600">Data usulan dari impor / riset</summary>
                        <div class="mt-3 grid gap-4 pb-1 lg:grid-cols-3">
                            <div>
                                <label for="stock_unit_suggestion" class="label">Satuan stok usulan</label>
                                <input id="stock_unit_suggestion" wire:model="stock_unit_suggestion" class="field" maxlength="100" placeholder="Satuan dari riset">
                                @error('stock_unit_suggestion')<p class="error">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label for="purchase_unit_name" class="label">Satuan beli</label>
                                <input id="purchase_unit_name" wire:model="purchase_unit_name" class="field" maxlength="100" placeholder="Box / Strip / Botol">
                                @error('purchase_unit_name')<p class="error">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label for="conversion_candidate" class="label">Konversi kandidat</label>
                                <input id="conversion_candidate" wire:model="conversion_candidate" class="field" type="number" step="0.01" min="0.01" placeholder="Isi kalau sudah pasti">
                                @error('conversion_candidate')<p class="error">{{ $message }}</p>@enderror
                            </div>
                        </div>
                    </details>
                    <div class="lg:col-span-2">
                        <label for="verification_note" class="label">Catatan verifikasi</label>
                        <textarea id="verification_note" wire:model="verification_note" class="field min-h-20" maxlength="4000" placeholder="Catatan bila perlu revisi atau klarifikasi"></textarea>
                        @error('verification_note')<p class="error">{{ $message }}</p>@enderror
                    </div>
                </div>
                <div class="mt-5 flex justify-end gap-2 border-t border-slate-100 pt-4">
                    <button type="button" class="btn-secondary min-h-11 lg:min-h-9" data-close-dialog>Batal</button>
                    <button class="btn min-h-11 lg:min-h-9" wire:loading.attr="disabled" wire:target="save">Simpan</button>
                </div>
            </form>
        </dialog>
    @endcan

    @can('manage-medicines')
        @if($showImportPreview)
            <x-ui.section title="Preview import riset Master Obat" :subtitle="count($importPreview).' baris dibaca. Data yang sudah punya kode sama akan dilewati, tidak ditimpa otomatis.'" icon="upload" tone="amber" flush class="mb-4">
                <x-slot:actions>
                    <button type="button" class="btn-secondary min-h-11 lg:min-h-[34px] lg:py-1.5" wire:click="$set('showImportPreview', false)">Tutup</button>
                    <button type="button" class="btn min-h-11 lg:min-h-[34px] lg:py-1.5" wire:click="runImport" wire:loading.attr="disabled" wire:target="runImport">Import yang Baru</button>
                </x-slot:actions>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[900px] text-left text-[13px]">
                        <thead class="table-head">
                            <tr>
                                <th class="px-4 py-2.5">Kode</th>
                                <th class="px-4 py-2.5">Nama Klinik</th>
                                <th class="px-4 py-2.5">Nama Kandidat</th>
                                <th class="px-4 py-2.5">Satuan</th>
                                <th class="px-4 py-2.5">Status</th>
                                <th class="px-4 py-2.5">Import</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach(collect($importPreview)->take(20) as $row)
                                <tr>
                                    <td class="px-4 py-2.5"><span class="rounded bg-slate-100 px-1.5 font-mono text-[11px] font-semibold text-slate-600">{{ $row['code'] }}</span></td>
                                    <td class="px-4 py-2.5 text-slate-600">{{ $row['original_name'] }}</td>
                                    <td class="px-4 py-2.5 font-bold text-slate-900">{{ $row['name'] }}</td>
                                    <td class="px-4 py-2.5 text-slate-600">{{ $row['stock_unit_suggestion'] ?: '-' }}</td>
                                    <td class="px-4 py-2.5">
                                        <span @class(['badge', 'badge-warn' => $row['verification_status'] === 'perlu_revisi', 'badge-muted' => $row['verification_status'] !== 'perlu_revisi'])>
                                            {{ $row['verification_status'] === 'perlu_revisi' ? 'Perlu Revisi' : 'Belum Diverifikasi' }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-2.5">
                                        <span @class(['badge', 'badge-brand' => $row['import_status'] === 'new', 'badge-muted' => $row['import_status'] !== 'new'])>
                                            {{ $row['import_status'] === 'new' ? 'Baru' : 'Sudah ada' }}
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @if(count($importPreview) > 20)
                    <p class="border-t border-slate-100 px-4 py-2.5 text-xs text-slate-500">Pratinjau menampilkan 20 baris pertama dari {{ count($importPreview) }} baris.</p>
                @endif
            </x-ui.section>
        @endif
    @endcan

    <section aria-label="Rekap master obat" class="grid grid-cols-2 gap-3 lg:grid-cols-5 xl:gap-4">
        @foreach($tiles as $tile)
            @isset($tile['filter'])
                @php
                    $active = $tile['current'] === $tile['filter'];
                @endphp
                <x-ui.stat :label="$tile['label']" :value="\App\Support\Quantity::format($tile['value'])" :sub="$tile['sub']" :theme="$tile['theme']" :icon="$tile['icon']" :active="$active"
                    wire:click="$set('{{ $tile['property'] }}', '{{ $active ? '' : $tile['filter'] }}')" aria-pressed="{{ $active ? 'true' : 'false' }}" title="{{ $active ? 'Hapus saringan' : 'Saring daftar: '.$tile['label'] }}" />
            @else
                <x-ui.stat :label="$tile['label']" :value="\App\Support\Quantity::format($tile['value'])" :sub="$tile['sub']" :theme="$tile['theme']" :icon="$tile['icon']" class="col-span-2 lg:col-span-1" />
            @endisset
        @endforeach
    </section>

    <div class="mt-4 flex flex-wrap items-center justify-between gap-2.5">
        <div class="flex w-full flex-wrap items-center gap-2 xl:w-auto">
            <x-ui.search id="search" label="Cari obat" aria-label="Cari obat" wire:model.live.debounce.300ms="search" placeholder="Cari kode, nama, kandungan..." width="sm:w-72" />
            <select wire:model.live="verificationFilter" class="field {{ $control }} min-w-[150px] flex-1 sm:w-auto sm:flex-none" aria-label="Filter verifikasi">
                <option value="">Semua verifikasi</option>
                <option value="belum_diverifikasi">Belum Diverifikasi</option>
                <option value="perlu_revisi">Perlu Revisi</option>
                <option value="terverifikasi">Terverifikasi</option>
            </select>
            <select wire:model.live="operationalFilter" class="field {{ $control }} min-w-[140px] flex-1 sm:w-auto sm:flex-none" aria-label="Filter aktif">
                <option value="">Semua status</option>
                <option value="active">Aktif</option>
                <option value="inactive">Nonaktif</option>
            </select>
            <label class="flex min-w-[150px] flex-1 items-center gap-2 sm:flex-none">
                <span class="shrink-0 text-xs font-bold text-slate-600">Urutkan</span>
                <select wire:model.live="sort" class="field {{ $control }} sm:w-auto" aria-label="Urutkan">
                    <option value="code">Kode</option>
                    <option value="name">Nama</option>
                    <option value="verification_status">Verifikasi</option>
                    <option value="is_active">Aktif</option>
                </select>
            </label>
        </div>
        <div class="flex w-full flex-wrap gap-2 sm:w-auto">
            <a href="{{ route('medicines.export-template') }}" class="btn-secondary {{ $control }} flex-1 sm:flex-none" title="Unduh daftar master obat ke Excel"><x-ui.icon name="download" />Unduh Template Excel</a>
            @can('manage-medicines')
            <button type="button" class="btn-secondary {{ $control }} flex-1 sm:flex-none" wire:click="previewImport" wire:loading.attr="disabled" wire:target="previewImport"><x-ui.icon name="upload" />Preview Import 160</button>
            <button type="button" class="btn {{ $control }} flex-1 sm:flex-none" wire:click="create"><x-ui.icon name="plus" />Tambah Obat</button>
            @endcan
        </div>
    </div>

    <x-ui.section title="Daftar obat" icon="list" tone="brand" flush class="mt-3.5"
        subtitle="Data impor masuk sebagai nonaktif sampai diverifikasi. Template Excel: isi kolom kuning, lalu serahkan ke kepala apoteker untuk ditinjau; unduhan tidak mengubah data ShifaStock."
        :meta="\App\Support\Quantity::format($medicines->total()).' obat'">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[1180px] text-left text-[13px]">
                <thead class="table-head">
                    <tr>
                        <th class="px-4 py-2.5">Obat</th>
                        <th class="px-4 py-2.5">Sediaan</th>
                        <th class="px-4 py-2.5">Kemasan</th>
                        <th class="px-4 py-2.5">Lokasi</th>
                        <th class="px-4 py-2.5">Stok</th>
                        <th class="px-4 py-2.5">Verifikasi</th>
                        <th class="px-4 py-2.5">Status</th>
                        <th class="px-4 py-2.5">Audit</th>
                        @can('manage-medicines')
                            <th class="px-4 py-2.5 text-right">Aksi</th>
                        @endcan
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($medicines as $medicine)
                        @php
                            $stock = (float) $medicine->balances->sum('quantity');
                            $locations = $medicine->balances
                                ->filter(fn($balance) => (float) $balance->quantity > 0)
                                ->sortBy(fn($balance) => $balance->location?->name)
                                ->values();
                            $parameters = $medicine->stockParameters->whereNotNull('minimum_quantity');
                            $low = $parameters->contains(fn($parameter) => (float) ($medicine->balances->firstWhere('location_id', $parameter->location_id)?->quantity ?? 0) <= (float) $parameter->minimum_quantity);
                            $package = $medicine->packages->first();
                            $audit = $medicine->audits->sortByDesc('created_at')->first();
                            $statusClass = match ($medicine->verification_status) {
                                'terverifikasi' => 'badge-ok',
                                'perlu_revisi' => 'badge-warn',
                                default => 'badge-muted',
                            };
                            $statusLabel = match ($medicine->verification_status) {
                                'terverifikasi' => 'Terverifikasi',
                                'perlu_revisi' => 'Perlu Revisi',
                                default => 'Belum Diverifikasi',
                            };
                        @endphp
                        <tr wire:key="medicine-{{ $medicine->id }}" class="align-top">
                            <td class="max-w-[340px] px-4 py-3">
                                <p class="font-extrabold text-slate-900">{{ $medicine->name }}</p>
                                <p class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-slate-500">
                                    <span class="rounded bg-slate-100 px-1.5 font-mono text-[11px] font-semibold text-slate-600">{{ $medicine->code }}</span>
                                    <span>{{ $medicine->original_name ?: 'Nama klinik belum ada' }}</span>
                                </p>
                                @if($medicine->generic_name)
                                    <p class="mt-0.5 text-xs text-slate-400">{{ $medicine->generic_name }}</p>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-slate-700">
                                <p class="font-semibold">{{ $medicine->dosage_form ?: 'Belum diisi' }}</p>
                                <p class="mt-0.5 text-xs text-slate-400">{{ $medicine->strength ?: 'Kekuatan belum diisi' }}</p>
                            </td>
                            <td class="px-4 py-3 text-slate-700">
                                <p class="font-semibold">{{ $package?->name ?: ($medicine->purchase_unit_name ?: 'Belum diatur') }}</p>
                                <p class="mt-0.5 text-xs text-slate-400">
                                    {{ $package ? 'Isi '.\App\Support\Quantity::format($package->conversion_factor).' '.$medicine->unit?->name : ($medicine->conversion_candidate ? 'Kandidat isi '.\App\Support\Quantity::format($medicine->conversion_candidate) : 'Konversi belum pasti') }}
                                </p>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex max-w-[240px] flex-wrap gap-1.5">
                                    @forelse($locations as $balance)
                                        <span class="badge">
                                            <span class="h-1.5 w-1.5 rounded-full {{ str_contains(strtolower($balance->location?->name ?? ''), 'gudang') ? 'bg-brand-600' : 'bg-slate-400' }}"></span>
                                            {{ $balance->location?->name ?? 'Lokasi tidak diketahui' }}
                                        </span>
                                    @empty
                                        <span class="text-xs text-slate-400">Belum ada posisi</span>
                                    @endforelse
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <p class="font-extrabold tabular-nums text-slate-900">{{ $medicine->balances->isEmpty() ? 'Belum ada stok' : \App\Support\Quantity::format($stock).' '.$medicine->unit?->name }}</p>
                                @if($low)
                                    <span class="badge badge-warn mt-1">Stok menipis</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <span class="badge {{ $statusClass }}">{{ $statusLabel }}</span>
                                @if($medicine->verification_note)
                                    <p class="mt-1.5 max-w-[240px] text-xs text-slate-500">{{ $medicine->verification_note }}</p>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <span class="badge {{ $medicine->is_active ? 'badge-brand' : 'badge-muted' }}">
                                    {{ $medicine->is_active ? 'Aktif' : 'Nonaktif' }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-xs text-slate-500">
                                @if($audit)
                                    <p class="font-bold text-slate-700">{{ ucfirst(str_replace('_', ' ', $audit->action)) }}</p>
                                    <p>{{ $audit->user?->name ?: 'Sistem' }}</p>
                                    <p class="tabular-nums">{{ $audit->created_at?->format('d/m/Y H:i') }}</p>
                                @else
                                    Belum ada audit
                                @endif
                            </td>
                            @can('manage-medicines')
                                <td class="px-4 py-3 text-right">
                                    <div class="flex flex-wrap justify-end gap-1.5">
                                        <button type="button" wire:click="edit({{ $medicine->id }})" class="{{ $rowAction }} border-brand-200 bg-white text-brand-800 hover:bg-brand-50">Edit</button>
                                        @if($medicine->verification_status !== 'terverifikasi')
                                            <button type="button" wire:click="verify({{ $medicine->id }})" class="{{ $rowAction }} border-emerald-200 bg-white text-emerald-700 hover:bg-emerald-50">Verifikasi</button>
                                        @endif
                                        <button type="button" wire:click="toggleActive({{ $medicine->id }})" class="{{ $rowAction }} border-slate-300 bg-white text-slate-700 hover:bg-slate-50">
                                            {{ $medicine->is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                                        </button>
                                    </div>
                                </td>
                            @endcan
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $canManage ? 9 : 8 }}" class="empty-state">Belum ada obat yang sesuai.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-ui.section>

    <div class="mt-4">{{ $medicines->links() }}</div>
</div>
