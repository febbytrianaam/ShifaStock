<div>
    <div class="mb-5 rounded-lg border border-[#d6e8f6] bg-[#edf5fb] p-3 text-sm leading-relaxed text-[#1f4f78]">
        Master obat menampung nama asli klinik, nama hasil riset, satuan, kemasan, status verifikasi, dan status aktif. Data impor masuk sebagai nonaktif sampai diverifikasi.
    </div>

    @if(session('status'))
        <div role="status" class="mb-5 rounded-lg border border-[#b2d4ee] bg-[#edf5fb] p-3 text-sm font-semibold text-[#266194]">
            {{ session('status') }}
        </div>
    @endif

    @can('manage-medicines')
        <dialog id="medicine-dialog" wire:ignore.self class="w-[calc(100%-2rem)] max-w-4xl rounded-lg border border-slate-200 bg-white p-0 text-slate-800 shadow-soft" aria-labelledby="medicine-dialog-title">
            <div class="flex items-start justify-between border-b border-slate-100 px-5 py-4">
                <div>
                    <span class="text-[11px] font-bold uppercase tracking-wider text-[#3277B2]">Master Obat</span>
                    <h2 id="medicine-dialog-title" class="mt-1 text-lg font-bold tracking-tight text-slate-900">{{ $editingId ? 'Edit Obat' : 'Tambah Obat' }}</h2>
                </div>
                <button type="button" data-close-dialog aria-label="Tutup form" class="rounded-lg p-1 text-xl leading-none text-slate-400 hover:bg-slate-100">&times;</button>
            </div>

            <form wire:submit="save" class="p-5">
                <div class="grid gap-4 lg:grid-cols-2">
                    <div>
                        <label for="code" class="label">Kode klinik</label>
                        <input id="code" wire:model="code" class="field" required maxlength="50" placeholder="ID dari klinik">
                        @error('code')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="original_name" class="label">Nama asli klinik</label>
                        <input id="original_name" wire:model="original_name" class="field" maxlength="255" placeholder="Nama sesuai data klinik">
                        @error('original_name')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="name" class="label">Nama final</label>
                        <input id="name" wire:model="name" class="field" required maxlength="255" placeholder="Nama kandidat / final">
                        @error('name')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="generic_name" class="label">Kandungan / keterangan</label>
                        <input id="generic_name" wire:model="generic_name" class="field" maxlength="255" placeholder="Kandungan obat">
                        @error('generic_name')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="strength" class="label">Kekuatan</label>
                        <input id="strength" wire:model="strength" class="field" maxlength="100" placeholder="500 mg / 5 ml">
                        @error('strength')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="dosage_form" class="label">Bentuk sediaan</label>
                        <input id="dosage_form" wire:model="dosage_form" class="field" maxlength="100" placeholder="Tablet / Sirup / Kapsul">
                        @error('dosage_form')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="unit_id" class="label">Satuan stok</label>
                        <select id="unit_id" wire:model="unit_id" class="field">
                            <option value="">Belum dipilih</option>
                            @foreach($units as $unit)
                                <option value="{{ $unit->id }}">{{ $unit->name }}</option>
                            @endforeach
                        </select>
                        @error('unit_id')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                        <p class="mt-1 text-xs text-slate-500">Satuan stok tidak dapat diganti setelah obat digunakan pada stok atau transaksi. Kemasan pembelian tetap bisa disesuaikan.</p>
                    </div>
                    <div>
                        <label for="package_name" class="label">Kemasan pembelian</label>
                        <input id="package_name" wire:model="package_name" class="field" maxlength="100" placeholder="Nama kemasan">
                        @error('package_name')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="package_conversion" class="label">Jumlah satuan stok dalam 1 kemasan</label>
                        <input id="package_conversion" wire:model="package_conversion" class="field" type="number" step="0.01" min="0.01" placeholder="Contoh: 10">
                        @error('package_conversion')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="verification_status" class="label">Status verifikasi</label>
                        <select id="verification_status" wire:model="verification_status" class="field">
                            <option value="belum_diverifikasi">Belum Diverifikasi</option>
                            <option value="perlu_revisi">Perlu Revisi</option>
                            <option value="terverifikasi">Terverifikasi</option>
                        </select>
                        @error('verification_status')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="package_reference" class="label">Referensi kemasan</label>
                        <textarea id="package_reference" wire:model="package_reference" class="field min-h-20" maxlength="2000" placeholder="Catatan kemasan dari referensi"></textarea>
                        @error('package_reference')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                    </div>
                    <details class="lg:col-span-2 border-t border-slate-200 pt-3" @if($errors->has('stock_unit_suggestion') || $errors->has('purchase_unit_name') || $errors->has('conversion_candidate')) open @endif>
                        <summary class="cursor-pointer text-xs font-semibold text-slate-500">Data usulan dari impor / riset</summary>
                        <div class="mt-3 grid gap-4 lg:grid-cols-2">
                    <div>
                        <label for="stock_unit_suggestion" class="label">Satuan stok usulan</label>
                        <input id="stock_unit_suggestion" wire:model="stock_unit_suggestion" class="field" maxlength="100" placeholder="Satuan dari riset">
                        @error('stock_unit_suggestion')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="purchase_unit_name" class="label">Satuan beli</label>
                        <input id="purchase_unit_name" wire:model="purchase_unit_name" class="field" maxlength="100" placeholder="Box / Strip / Botol">
                        @error('purchase_unit_name')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="conversion_candidate" class="label">Konversi kandidat</label>
                        <input id="conversion_candidate" wire:model="conversion_candidate" class="field" type="number" step="0.01" min="0.01" placeholder="Isi kalau sudah pasti">
                        @error('conversion_candidate')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                    </div>
                        </div>
                    </details>
                    <div class="lg:col-span-2">
                        <label for="verification_note" class="label">Catatan verifikasi</label>
                        <textarea id="verification_note" wire:model="verification_note" class="field min-h-20" maxlength="4000" placeholder="Catatan bila perlu revisi atau klarifikasi"></textarea>
                        @error('verification_note')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                    </div>
                </div>
                <div class="mt-5 flex justify-end gap-2 border-t border-slate-100 pt-4">
                    <button type="button" class="btn border border-slate-200 bg-white text-slate-600 hover:bg-slate-50" data-close-dialog>Batal</button>
                    <button class="btn bg-[#3277B2] font-bold text-white hover:bg-[#266194]" wire:loading.attr="disabled" wire:target="save">Simpan</button>
                </div>
            </form>
        </dialog>
    @endcan

    @can('manage-medicines')
        <div class="mb-5 flex flex-wrap justify-end gap-2">
            <button type="button" class="btn border border-[#b2d4ee] bg-white text-xs font-bold text-[#3277B2] hover:bg-[#edf5fb]" wire:click="previewImport" wire:loading.attr="disabled" wire:target="previewImport">Preview Import 160</button>
            <button type="button" class="btn bg-[#3277B2] text-xs font-bold text-white hover:bg-[#266194]" wire:click="create">Tambah Obat</button>
        </div>
    @endcan

    @can('manage-medicines')
        @if($showImportPreview)
            <div class="mb-5 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
                <div class="mb-3 flex flex-col justify-between gap-2 md:flex-row md:items-center">
                    <div>
                        <p class="font-bold">Preview import riset Master Obat</p>
                        <p class="text-xs text-amber-800">{{ count($importPreview) }} baris dibaca. Data yang sudah punya kode sama akan dilewati, tidak ditimpa otomatis.</p>
                    </div>
                    <div class="flex gap-2">
                        <button type="button" class="btn border border-amber-300 bg-white text-xs font-bold text-amber-800 hover:bg-amber-100" wire:click="$set('showImportPreview', false)">Tutup</button>
                        <button type="button" class="btn bg-amber-600 text-xs font-bold text-white hover:bg-amber-700" wire:click="runImport" wire:loading.attr="disabled" wire:target="runImport">Import yang Baru</button>
                    </div>
                </div>
                <div class="overflow-x-auto rounded-lg border border-amber-200 bg-white">
                    <table class="w-full min-w-[900px] text-left text-xs">
                        <thead class="bg-amber-100 text-amber-900">
                            <tr>
                                <th class="p-3 font-bold uppercase tracking-wider">Kode</th>
                                <th class="p-3 font-bold uppercase tracking-wider">Nama Klinik</th>
                                <th class="p-3 font-bold uppercase tracking-wider">Nama Kandidat</th>
                                <th class="p-3 font-bold uppercase tracking-wider">Satuan</th>
                                <th class="p-3 font-bold uppercase tracking-wider">Status</th>
                                <th class="p-3 font-bold uppercase tracking-wider">Import</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-amber-100">
                            @foreach(collect($importPreview)->take(20) as $row)
                                <tr>
                                    <td class="p-3 font-mono font-semibold text-slate-700">{{ $row['code'] }}</td>
                                    <td class="p-3 text-slate-600">{{ $row['original_name'] }}</td>
                                    <td class="p-3 font-semibold text-slate-900">{{ $row['name'] }}</td>
                                    <td class="p-3 text-slate-600">{{ $row['stock_unit_suggestion'] ?: '-' }}</td>
                                    <td class="p-3">
                                        <span class="badge {{ $row['verification_status'] === 'perlu_revisi' ? 'border border-amber-300 bg-amber-100 text-amber-800' : 'border border-slate-200 bg-slate-100 text-slate-600' }}">
                                            {{ $row['verification_status'] === 'perlu_revisi' ? 'Perlu Revisi' : 'Belum Diverifikasi' }}
                                        </span>
                                    </td>
                                    <td class="p-3">
                                        <span class="badge {{ $row['import_status'] === 'new' ? 'border border-[#b2d4ee] bg-[#edf5fb] text-[#266194]' : 'border border-slate-200 bg-slate-100 text-slate-600' }}">
                                            {{ $row['import_status'] === 'new' ? 'Baru' : 'Sudah ada' }}
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    @endcan

    <div class="mb-5 grid grid-cols-2 gap-3 lg:grid-cols-5" aria-label="Rekap master obat">
        @foreach([
            ['label' => 'Total', 'value' => $summary['total'], 'tone' => 'text-slate-900'],
            ['label' => 'Aktif', 'value' => $summary['active'], 'tone' => 'text-[#266194]'],
            ['label' => 'Terverifikasi', 'value' => $summary['verified'], 'tone' => 'text-emerald-700'],
            ['label' => 'Perlu Revisi', 'value' => $summary['revision'], 'tone' => 'text-amber-700'],
            ['label' => 'Belum Verifikasi', 'value' => $summary['pending'], 'tone' => 'text-slate-700'],
        ] as $stat)
            <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-xs">
                <p class="text-[11px] font-bold uppercase tracking-wider text-slate-400">{{ $stat['label'] }}</p>
                <p class="mt-1 text-2xl font-extrabold tabular-nums {{ $stat['tone'] }}">{{ \App\Support\Quantity::format($stat['value']) }}</p>
            </div>
        @endforeach
    </div>

    <div class="mb-4 grid gap-3 lg:grid-cols-[1fr_180px_180px_160px]">
        <input id="search" aria-label="Cari obat" wire:model.live.debounce.300ms="search" class="field text-sm" type="search" placeholder="Cari kode, nama, kandungan...">
        <select wire:model.live="verificationFilter" class="field text-sm" aria-label="Filter verifikasi">
            <option value="">Semua verifikasi</option>
            <option value="belum_diverifikasi">Belum Diverifikasi</option>
            <option value="perlu_revisi">Perlu Revisi</option>
            <option value="terverifikasi">Terverifikasi</option>
        </select>
        <select wire:model.live="operationalFilter" class="field text-sm" aria-label="Filter aktif">
            <option value="">Semua status</option>
            <option value="active">Aktif</option>
            <option value="inactive">Nonaktif</option>
        </select>
        <select wire:model.live="sort" class="field text-sm" aria-label="Urutkan">
            <option value="code">Kode</option>
            <option value="name">Nama</option>
            <option value="verification_status">Verifikasi</option>
            <option value="is_active">Aktif</option>
        </select>
    </div>

    <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-xs">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[1320px] text-left text-sm">
                <thead class="border-b border-[#d6e8f6] bg-[#edf5fb] text-[#266194]">
                    <tr>
                        <th class="p-4 text-[11px] font-bold uppercase tracking-wider">Kode</th>
                        <th class="p-4 text-[11px] font-bold uppercase tracking-wider">Obat</th>
                        <th class="p-4 text-[11px] font-bold uppercase tracking-wider">Sediaan</th>
                        <th class="p-4 text-[11px] font-bold uppercase tracking-wider">Kemasan</th>
                        <th class="p-4 text-[11px] font-bold uppercase tracking-wider">Lokasi</th>
                        <th class="p-4 text-[11px] font-bold uppercase tracking-wider">Stok</th>
                        <th class="p-4 text-[11px] font-bold uppercase tracking-wider">Verifikasi</th>
                        <th class="p-4 text-[11px] font-bold uppercase tracking-wider">Status</th>
                        <th class="p-4 text-[11px] font-bold uppercase tracking-wider">Audit</th>
                        @can('manage-medicines')
                            <th class="p-4 text-right text-[11px] font-bold uppercase tracking-wider">Aksi</th>
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
                                'terverifikasi' => 'border border-emerald-200 bg-emerald-50 text-emerald-700',
                                'perlu_revisi' => 'border border-amber-300 bg-amber-100 text-amber-800',
                                default => 'border border-slate-200 bg-slate-100 text-slate-600',
                            };
                            $statusLabel = match ($medicine->verification_status) {
                                'terverifikasi' => 'Terverifikasi',
                                'perlu_revisi' => 'Perlu Revisi',
                                default => 'Belum Diverifikasi',
                            };
                        @endphp
                        <tr wire:key="medicine-{{ $medicine->id }}" class="align-top transition-colors hover:bg-slate-50/70">
                            <td class="p-4">
                                <span class="inline-flex rounded-md bg-slate-100 px-2 py-0.5 font-mono text-[11px] font-semibold text-slate-600">{{ $medicine->code }}</span>
                            </td>
                            <td class="max-w-[320px] p-4">
                                <p class="font-bold text-slate-900">{{ $medicine->name }}</p>
                                <p class="mt-1 text-xs text-slate-500">{{ $medicine->original_name ?: 'Nama klinik belum ada' }}</p>
                                @if($medicine->generic_name)
                                    <p class="mt-1 text-xs text-slate-400">{{ $medicine->generic_name }}</p>
                                @endif
                            </td>
                            <td class="p-4 text-slate-600">
                                <p class="font-semibold">{{ $medicine->dosage_form ?: 'Belum diisi' }}</p>
                                <p class="mt-1 text-xs text-slate-400">{{ $medicine->strength ?: 'Kekuatan belum diisi' }}</p>
                            </td>
                            <td class="p-4 text-slate-600">
                                <p class="font-semibold">{{ $package?->name ?: ($medicine->purchase_unit_name ?: 'Belum diatur') }}</p>
                                <p class="mt-1 text-xs text-slate-400">
                                    {{ $package ? 'Isi '.\App\Support\Quantity::format($package->conversion_factor).' '.$medicine->unit?->name : ($medicine->conversion_candidate ? 'Kandidat isi '.\App\Support\Quantity::format($medicine->conversion_candidate) : 'Konversi belum pasti') }}
                                </p>
                            </td>
                            <td class="p-4">
                                <div class="flex max-w-[240px] flex-wrap gap-1.5">
                                    @forelse($locations as $balance)
                                        <span class="inline-flex items-center gap-1.5 rounded-md border border-slate-200 bg-slate-50 px-2 py-0.5 text-xs font-semibold text-slate-700">
                                            <span class="h-1.5 w-1.5 rounded-full {{ str_contains(strtolower($balance->location?->name ?? ''), 'gudang') ? 'bg-[#3277B2]' : 'bg-slate-400' }}"></span>
                                            {{ $balance->location?->name ?? 'Lokasi tidak diketahui' }}
                                        </span>
                                    @empty
                                        <span class="text-xs text-slate-400">Belum ada posisi</span>
                                    @endforelse
                                </div>
                            </td>
                            <td class="p-4 font-bold tabular-nums text-slate-900">
                                {{ $medicine->balances->isEmpty() ? 'Belum ada stok' : \App\Support\Quantity::format($stock).' '.$medicine->unit?->name }}
                                @if($low)
                                    <p class="mt-1 text-xs font-semibold text-rose-600">Stok menipis</p>
                                @endif
                            </td>
                            <td class="p-4">
                                <span class="badge {{ $statusClass }}">{{ $statusLabel }}</span>
                                @if($medicine->verification_note)
                                    <p class="mt-2 max-w-[240px] text-xs text-slate-500">{{ $medicine->verification_note }}</p>
                                @endif
                            </td>
                            <td class="p-4">
                                <span class="badge {{ $medicine->is_active ? 'border border-[#b2d4ee] bg-[#edf5fb] text-[#266194]' : 'border border-slate-200 bg-slate-100 text-slate-600' }}">
                                    {{ $medicine->is_active ? 'Aktif' : 'Nonaktif' }}
                                </span>
                            </td>
                            <td class="p-4 text-xs text-slate-500">
                                @if($audit)
                                    <p class="font-semibold text-slate-700">{{ ucfirst(str_replace('_', ' ', $audit->action)) }}</p>
                                    <p>{{ $audit->user?->name ?: 'Sistem' }}</p>
                                    <p>{{ $audit->created_at?->format('d/m/Y H:i') }}</p>
                                @else
                                    Belum ada audit
                                @endif
                            </td>
                            @can('manage-medicines')
                                <td class="p-4 text-right">
                                    <div class="flex justify-end gap-2">
                                        <button type="button" wire:click="edit({{ $medicine->id }})" class="rounded-lg px-2.5 py-1 text-xs font-bold text-[#3277B2] hover:bg-[#edf5fb]">Edit</button>
                                        @if($medicine->verification_status !== 'terverifikasi')
                                            <button type="button" wire:click="verify({{ $medicine->id }})" class="rounded-lg px-2.5 py-1 text-xs font-bold text-emerald-700 hover:bg-emerald-50">Verifikasi</button>
                                        @endif
                                        <button type="button" wire:click="toggleActive({{ $medicine->id }})" class="rounded-lg px-2.5 py-1 text-xs font-bold text-slate-600 hover:bg-slate-100">
                                            {{ $medicine->is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                                        </button>
                                    </div>
                                </td>
                            @endcan
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ auth()->user()->can('manage-medicines') ? 10 : 9 }}" class="p-10 text-center text-slate-400">Belum ada obat yang sesuai.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-4">{{ $medicines->links() }}</div>
</div>
