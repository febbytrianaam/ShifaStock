<div>
    <div class="mb-5 rounded-2xl border border-[#d6e8f6] border-l-4 border-l-[#3277B2] bg-[#edf5fb] p-4 text-sm text-[#1f4f78] leading-relaxed shadow-xs">
        Daftar obat mencakup hasil impor Excel. Stok berasal dari saldo persediaan yang sama dengan penerimaan, transfer, dan pemeriksaan fisik. Entri bertanda <strong>Perlu verifikasi</strong> belum dapat dipakai dalam transaksi.
    </div>

    @if(session('status'))
        <div role="status" class="mb-5 rounded-2xl border border-[#b2d4ee] border-l-4 border-l-[#3277B2] bg-[#edf5fb] p-4 text-sm font-semibold text-[#266194] shadow-xs">
            {{ session('status') }}
        </div>
    @endif

    @can('manage-medicines')
        <dialog id="medicine-dialog" wire:ignore.self class="w-[calc(100%-2rem)] max-w-lg rounded-2xl border border-slate-200 bg-white p-6 text-slate-800 shadow-soft" aria-labelledby="medicine-dialog-title">
            <div class="mb-5 flex items-start justify-between border-b border-slate-100 pb-3">
                <div>
                    <span class="inline-flex rounded-lg bg-[#edf5fb] px-2 py-0.5 text-[11px] font-bold uppercase tracking-wider text-[#3277B2]">DATA MASTER</span>
                    <h2 id="medicine-dialog-title" class="mt-1 text-xl font-bold tracking-tight text-slate-900">Tambah Obat Baru</h2>
                </div>
                <button type="button" data-close-dialog aria-label="Tutup form" class="rounded-lg p-1 text-slate-400 hover:bg-slate-100 text-lg leading-none">&times;</button>
            </div>
            <form wire:submit="save">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="code" class="label text-xs font-semibold text-slate-700">Kode obat</label>
                        <input id="code" wire:model="code" class="field" required maxlength="50" placeholder="Contoh: OBT-001">
                        @error('code')<p class="error text-xs text-rose-600 mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="name" class="label text-xs font-semibold text-slate-700">Nama obat</label>
                        <input id="name" wire:model="name" class="field" required maxlength="255" placeholder="Nama obat & dosis">
                        @error('name')<p class="error text-xs text-rose-600 mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="dosage_form" class="label text-xs font-semibold text-slate-700">Bentuk sediaan</label>
                        <input id="dosage_form" wire:model="dosage_form" class="field" required maxlength="100" placeholder="Tablet / Sirup / Kapsul">
                        @error('dosage_form')<p class="error text-xs text-rose-600 mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="unit_id" class="label text-xs font-semibold text-slate-700">Satuan dasar</label>
                        <select id="unit_id" wire:model="unit_id" class="field" required>
                            <option value="">Pilih satuan</option>
                            @foreach($units as $unit)
                                <option value="{{ $unit->id }}">{{ $unit->name }}</option>
                            @endforeach
                        </select>
                        @error('unit_id')<p class="error text-xs text-rose-600 mt-1">{{ $message }}</p>@enderror
                    </div>
                </div>
                <button class="btn mt-5 w-full bg-[#3277B2] hover:bg-[#266194] text-white font-bold" wire:loading.attr="disabled" wire:target="save">Simpan obat</button>
            </form>
        </dialog>
    @endcan

    <div class="mb-5 flex flex-col justify-between gap-3 sm:flex-row sm:items-end">
        <div>
            <h2 class="text-xl font-bold tracking-tight text-slate-900">Master Data Obat</h2>
            <p class="mt-1 text-xs text-slate-500">Daftar obat dan kondisi stok saat ini.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <input id="search" aria-label="Cari nama atau kode obat" wire:model.live.debounce.300ms="search" class="field w-full sm:w-64 text-xs font-medium focus:border-[#3277B2]" type="search" placeholder="Cari nama / kode obat...">
            @can('manage-medicines')
                <button type="button" class="btn bg-[#3277B2] hover:bg-[#266194] text-white font-bold text-xs" data-open-medicine>+ Tambah Obat</button>
            @endcan
        </div>
    </div>

    <!-- 4 Summary Stat Cards -->
    <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-label="Rekap master obat">
        <!-- 1. Total Obat -->
        <div class="card card-interactive p-5 border border-slate-200/90 border-t-4 border-t-[#3277B2] bg-white shadow-sm">
            <div class="flex items-center justify-between">
                <span class="inline-flex rounded-lg bg-[#edf5fb] border border-[#d6e8f6] px-2 py-0.5 text-[11px] font-bold uppercase tracking-wider text-[#266194]">Total Master</span>
                <span class="grid h-9 w-9 place-items-center rounded-xl bg-[#3277B2] text-white shadow-xs">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                </span>
            </div>
            <p class="mt-2 text-3xl font-extrabold tracking-tight tabular-nums text-slate-900">{{ number_format($summary['total'], 0, ',', '.') }}</p>
            <p class="mt-1 text-xs text-slate-400 font-normal">Seluruh master obat</p>
        </div>

        <!-- 2. Obat Aktif -->
        <div class="card card-interactive p-5 border border-slate-200/90 border-t-4 border-t-[#5ba4d6] bg-white shadow-sm">
            <div class="flex items-center justify-between">
                <span class="inline-flex rounded-lg bg-[#edf5fb] border border-[#d6e8f6] px-2 py-0.5 text-[11px] font-bold uppercase tracking-wider text-[#266194]">Siap Pakai</span>
                <span class="grid h-9 w-9 place-items-center rounded-xl bg-[#5ba4d6] text-white shadow-xs">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                </span>
            </div>
            <p class="mt-2 text-3xl font-extrabold tracking-tight tabular-nums text-[#266194]">{{ number_format($summary['active'], 0, ',', '.') }}</p>
            <p class="mt-1 text-xs text-slate-400 font-normal">Sediaan dan satuan sudah terisi</p>
        </div>

        <!-- 3. Obat Nonaktif -->
        <div class="card card-interactive p-5 border border-slate-200/90 border-t-4 border-t-slate-400 bg-white shadow-sm">
            <div class="flex items-center justify-between">
                <span class="inline-flex rounded-lg bg-slate-100 border border-slate-200 px-2 py-0.5 text-[11px] font-bold uppercase tracking-wider text-slate-600">Nonaktif</span>
                <span class="grid h-9 w-9 place-items-center rounded-xl bg-slate-500 text-white shadow-xs">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" /></svg>
                </span>
            </div>
            <p class="mt-2 text-3xl font-extrabold tracking-tight tabular-nums text-slate-700">{{ number_format($summary['inactive'], 0, ',', '.') }}</p>
            <p class="mt-1 text-xs text-slate-400 font-normal">Data lengkap, status nonaktif</p>
        </div>

        <!-- 4. Perlu Verifikasi -->
        <div class="card card-interactive p-5 border border-slate-200/90 border-t-4 border-t-amber-500 bg-white shadow-sm">
            <div class="flex items-center justify-between">
                <span class="inline-flex rounded-lg bg-amber-100 border border-amber-300 px-2 py-0.5 text-[11px] font-bold uppercase tracking-wider text-amber-800">Verifikasi</span>
                <span class="grid h-9 w-9 place-items-center rounded-xl bg-amber-500 text-white shadow-xs">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" /></svg>
                </span>
            </div>
            <p class="mt-2 text-3xl font-extrabold tracking-tight tabular-nums text-amber-700">{{ number_format($summary['pending'], 0, ',', '.') }}</p>
            <p class="mt-1 text-xs text-slate-400 font-normal">Sediaan atau satuan belum lengkap</p>
        </div>
    </div>

    <!-- Data Table -->
    <div class="card overflow-hidden border border-slate-200/90 border-t-4 border-t-[#3277B2] bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full whitespace-nowrap text-left text-sm">
                <thead class="bg-[#edf5fb] border-b border-[#d6e8f6] text-[#266194]">
                    <tr>
                        <th class="p-4 text-[11px] font-bold uppercase tracking-wider">Kode</th>
                        <th class="p-4 text-[11px] font-bold uppercase tracking-wider">Nama Obat</th>
                        <th class="p-4 text-[11px] font-bold uppercase tracking-wider">Kategori</th>
                        <th class="p-4 text-[11px] font-bold uppercase tracking-wider">Stok</th>
                        <th class="p-4 text-[11px] font-bold uppercase tracking-wider">Minimum</th>
                        <th class="p-4 text-[11px] font-bold uppercase tracking-wider">Kedaluwarsa</th>
                        <th class="p-4 text-[11px] font-bold uppercase tracking-wider">Status</th>
                        @can('manage-medicines')
                            <th class="p-4 text-[11px] font-bold uppercase tracking-wider text-right">Aksi</th>
                        @endcan
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($medicines as $medicine)
                        @php
                            $stock = (float) $medicine->balances->sum('quantity');
                            $parameters = $medicine->stockParameters->whereNotNull('minimum_quantity');
                            $minimum = (float) $parameters->sum('minimum_quantity');
                            $low = $parameters->contains(fn($parameter) => (float) ($medicine->balances->firstWhere('location_id', $parameter->location_id)?->quantity ?? 0) <= (float) $parameter->minimum_quantity);
                            $expiry = $medicine->batches->whereNotNull('expiry_date')->sortBy('expiry_date')->first()?->expiry_date;
                            $pending = !$medicine->unit_id || !$medicine->dosage_form;
                        @endphp
                        <tr wire:key="medicine-{{ $medicine->id }}" class="hover:bg-slate-50/70 transition-colors">
                            <td class="p-4">
                                <span class="inline-flex rounded-md bg-slate-100 px-2 py-0.5 font-mono text-[11px] font-semibold text-slate-600">{{ $medicine->code }}</span>
                            </td>
                            <td class="p-4">
                                <span class="font-bold text-slate-900 tracking-tight">{{ $medicine->name }}</span>
                            </td>
                            <td class="p-4 text-slate-600 font-medium">{{ $medicine->dosage_form ?? 'Belum dikonfirmasi' }}</td>
                            <td class="p-4 font-bold tabular-nums text-slate-900">
                                {{ $medicine->balances->isEmpty() ? 'Belum ada saldo' : number_format($stock, 0, ',', '.').' '.$medicine->unit?->name }}
                            </td>
                            <td class="p-4 text-slate-600 tabular-nums font-medium">
                                {{ $parameters->isEmpty() ? 'Belum diatur' : number_format($minimum, 0, ',', '.').' '.$medicine->unit?->name }}
                            </td>
                            <td class="p-4 text-slate-600 tabular-nums text-xs font-medium">
                                {{ $expiry?->format('d/m/Y') ?? 'Belum tersedia' }}
                            </td>
                            <td class="p-4">
                                <span class="badge {{ $pending || !$medicine->is_active ? 'bg-slate-100 text-slate-600 border border-slate-200' : ($low ? 'bg-rose-100 text-rose-800 border border-rose-300' : ($medicine->balances->isEmpty() ? 'bg-amber-100 text-amber-800 border border-amber-300' : 'bg-[#edf5fb] text-[#266194] border border-[#b2d4ee]')) }}">
                                    {{ $pending ? 'Perlu verifikasi' : (!$medicine->is_active ? 'Nonaktif' : ($low ? 'Stok Menipis' : ($medicine->balances->isEmpty() ? 'Belum ada stok' : 'Aktif'))) }}
                                </span>
                            </td>
                            @can('manage-medicines')
                                <td class="p-4 text-right">
                                    <button wire:click="toggleActive({{ $medicine->id }})" wire:confirm="Ubah status obat ini?" class="inline-flex items-center rounded-lg px-2.5 py-1 text-xs font-bold text-[#3277B2] hover:bg-[#edf5fb] transition">
                                        {{ $medicine->is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                                    </button>
                                </td>
                            @endcan
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="p-10 text-center text-slate-400 font-medium">Belum ada obat yang sesuai.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-4">{{ $medicines->links() }}</div>
</div>
