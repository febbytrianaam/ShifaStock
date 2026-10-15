<x-layouts.app title="Dashboard">
@php
    $isDemoDashboard = ($dashboardMode ?? 'demo') === 'demo';
@endphp
@can('view-inventory')
    <div class="mb-5 border-b border-slate-200 pb-4">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <div class="flex flex-wrap items-center gap-2">
                    <span class="rounded-md border border-slate-200 bg-white px-2 py-0.5 text-[11px] font-semibold uppercase text-slate-500">
                        {{ auth()->user()->role->label() }}
                    </span>
                    <span class="rounded-md {{ $isDemoDashboard ? 'border border-amber-200 bg-amber-50 text-amber-800' : 'border border-emerald-200 bg-emerald-50 text-emerald-700' }} px-2 py-0.5 text-[11px] font-semibold uppercase">
                        {{ $isDemoDashboard ? 'Mode Demo' : 'Data Real' }}
                    </span>
                </div>
                <h1 class="mt-2 text-xl font-bold text-slate-900 sm:text-2xl">
                    Selamat Datang, {{ auth()->user()->username ?? 'Petugas Farmasi' }}
                </h1>
                <p class="mt-1 max-w-3xl text-sm leading-relaxed text-slate-500">
                    {{ $isDemoDashboard ? 'Ringkasan demo untuk kebutuhan presentasi dan simulasi alur klinik.' : 'Ringkasan persediaan dari stok operasional dan pemeriksaan fisik yang disetujui.' }}
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-2 shrink-0">
                <div class="inline-flex rounded-lg border border-slate-200 bg-white p-1 text-xs font-bold shadow-xs">
                    <a href="{{ route('dashboard', ['data' => 'demo']) }}" class="rounded-md px-3 py-1.5 transition {{ $isDemoDashboard ? 'bg-[#3277B2] text-white shadow-xs' : 'text-slate-500 hover:bg-slate-50 hover:text-slate-800' }}">
                        Demo
                    </a>
                    <a href="{{ route('dashboard', ['data' => 'real']) }}" class="rounded-md px-3 py-1.5 transition {{ ! $isDemoDashboard ? 'bg-[#3277B2] text-white shadow-xs' : 'text-slate-500 hover:bg-slate-50 hover:text-slate-800' }}">
                        Data real
                    </a>
                </div>
                @can('manage-medicines')
                <a href="{{ route('medicines.index') }}?tambah=1" class="btn">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                    Tambah Obat
                </a>
                @endcan
                <a href="{{ route('preview.receipts') }}" class="btn-secondary text-xs">
                    Obat Masuk
                </a>
                <a href="{{ route('preview.transfers') }}" class="btn-secondary text-xs">
                    Transfer Gudang–Apotek
                </a>
                <a href="{{ route('preview.nightly') }}" class="btn-secondary text-xs">
                    Pemeriksaan Harian
                </a>
            </div>
        </div>
    </div>

    <!-- KPI Stats -->
    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <!-- 1. Master Terverifikasi -->
        <a href="{{ route('medicines.index') }}" class="card card-interactive p-4 group block">
            <div class="flex items-start justify-between">
                <div>
                    <span class="inline-flex items-center rounded-md bg-[#edf5fb] border border-[#d6e8f6] px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider text-[#266194]">Master Data</span>
                    <h3 class="mt-2 text-xs font-semibold text-slate-500">Master Terverifikasi</h3>
                </div>
                <span class="grid h-8 w-8 place-items-center rounded-lg bg-brand-50 text-brand-700 group-hover:bg-brand-100 transition-colors">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                </span>
            </div>
            <p class="mt-2 text-xl font-bold tabular-nums text-slate-900">{{ \App\Support\Quantity::format($stats['verified']) }}</p>
            <div class="mt-2 flex items-center justify-between text-xs">
                <span class="text-slate-400 font-normal">Siap dipakai dalam transaksi</span>
                <span class="font-bold text-[#3277B2] group-hover:translate-x-0.5 transition-transform">Kelola →</span>
            </div>
        </a>

        <!-- 2. Obat Memiliki Stok (Calm Steel-Blue Tone) -->
        <a href="{{ route('preview.reports') }}" class="card card-interactive p-4 group block">
            <div class="flex items-start justify-between">
                <div>
                    <span class="inline-flex items-center rounded-md bg-sky-50 border border-sky-200 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider text-[#1f628d]">Persediaan</span>
                    <h3 class="mt-2 text-xs font-semibold text-slate-500">Obat Memiliki Stok</h3>
                </div>
                <span class="grid h-8 w-8 place-items-center rounded-lg bg-sky-50 text-sky-700 group-hover:bg-sky-100 transition-colors">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                    </svg>
                </span>
            </div>
            <p class="mt-2 text-xl font-bold tabular-nums text-slate-900">{{ \App\Support\Quantity::format($stats['stocked']) }}</p>
            <div class="mt-2 flex items-center justify-between text-xs">
                <span class="text-slate-400 font-normal">Stok lebih dari nol</span>
                <span class="font-bold text-[#2a759e] group-hover:translate-x-0.5 transition-transform">Laporan →</span>
            </div>
        </a>

        <!-- 3. Kosong / Menipis -->
        <a href="{{ route('shortages.index') }}" class="card card-interactive p-4 group block {{ $stats['shortages'] > 0 ? 'attention-critical' : '' }}">
            <div class="flex items-start justify-between">
                <div>
                    <span class="inline-flex items-center rounded-md {{ $stats['shortages'] > 0 ? 'attention-badge-critical bg-rose-100 text-rose-800 border border-rose-300' : 'bg-slate-100 text-slate-600' }} px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider">
                        {{ $stats['shortages'] > 0 ? 'Perlu Restock' : 'Status Aman' }}
                    </span>
                    <h3 class="mt-2 text-xs font-semibold text-slate-500">Kosong / Menipis</h3>
                </div>
                <span class="grid h-8 w-8 place-items-center rounded-lg bg-rose-50 text-rose-600 group-hover:bg-rose-100 transition-colors">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                    </svg>
                </span>
            </div>
            <p class="mt-2 text-xl font-bold tabular-nums {{ $stats['shortages'] > 0 ? 'text-rose-600' : 'text-slate-900' }}">{{ \App\Support\Quantity::format($stats['shortages']) }}</p>
            <div class="mt-2 flex items-center justify-between text-xs">
                <span class="text-slate-400 font-normal">Pemeriksaan terbaru disetujui</span>
                <span class="font-bold text-rose-600 group-hover:translate-x-0.5 transition-transform">Tindak Lanjut →</span>
            </div>
        </a>

        <!-- 4. Batch Mendekati ED -->
        <a href="{{ route('preview.expiry') }}" class="card card-interactive p-4 group block {{ $stats['expiring'] > 0 ? 'attention-warning' : '' }}">
            <div class="flex items-start justify-between">
                <div>
                    <span class="inline-flex items-center rounded-md {{ $stats['expiring'] > 0 ? 'attention-badge-warning bg-amber-100 border border-amber-300' : 'bg-amber-50 border border-amber-200' }} px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider text-amber-800">Kedaluwarsa</span>
                    <h3 class="mt-2 text-xs font-semibold text-slate-500">Batch Mendekati ED</h3>
                </div>
                <span class="grid h-8 w-8 place-items-center rounded-lg bg-amber-50 text-amber-700 group-hover:bg-amber-100 transition-colors">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </span>
            </div>
            <p class="mt-2 text-xl font-bold tabular-nums text-slate-900">{{ \App\Support\Quantity::format($stats['expiring']) }}</p>
            <div class="mt-2 flex items-center justify-between text-xs">
                <span class="text-slate-400 font-normal">Dalam {{ $expiryWarningMonths ?? 3 }} bulan</span>
                <span class="font-bold text-amber-700 group-hover:translate-x-0.5 transition-transform">Periksa →</span>
            </div>
        </a>
    </div>

    <!-- Chart & Action Required Row -->
    <div class="mt-5 grid gap-5 xl:grid-cols-3">
        <!-- Stock Activity Chart -->
        <div class="min-w-0 xl:col-span-2">
            <x-stock-chart :chart="$chart" />
        </div>

        <!-- Perlu Ditindaklanjuti Widget -->
        <div class="card flex flex-col p-4">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <div class="flex items-center gap-2.5">
                    <span class="grid h-7 w-7 place-items-center rounded-lg bg-rose-50 text-rose-600">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />
                        </svg>
                    </span>
                    <div class="flex items-center gap-2">
                        <h2 class="font-bold text-slate-900 text-sm">Perlu Ditindaklanjuti</h2>
                        @if(count($shortages) > 0)
                            <span class="inline-flex items-center rounded-full bg-rose-500 text-white text-[11px] font-bold px-2 py-0.5">{{ count($shortages) }}</span>
                        @endif
                    </div>
                </div>
                <a class="inline-flex items-center gap-1 text-xs font-bold text-[#3277B2] hover:underline tracking-tight" href="{{ route('shortages.index') }}">
                    Lihat semua
                    <span aria-hidden="true">→</span>
                </a>
            </div>
            <p class="mt-2 mb-3 text-xs font-normal text-slate-400">Dari pemeriksaan terbaru yang disetujui</p>

            <div class="flex-1 space-y-2.5 overflow-y-auto max-h-[340px] pr-1">
                @forelse($shortages as $row)
                    <a href="{{ $isDemoDashboard ? route('shortages.index') : route('nightly.show', $row->nightly_check_id) }}" class="group flex items-center justify-between gap-3 rounded-lg border {{ $row->quantity === 0 ? 'attention-critical hover:bg-rose-50/90' : 'attention-warning hover:bg-amber-50/90' }} p-3 transition-all">
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2">
                                @if($row->quantity === 0)
                                    <span class="attention-badge-critical inline-flex items-center rounded-md border border-rose-300 bg-rose-100 text-rose-800 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider">Kosong</span>
                                @else
                                    <span class="attention-badge-warning inline-flex items-center rounded-md border border-amber-300 bg-amber-100 text-amber-800 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider">Menipis</span>
                                @endif
                                <p class="truncate text-sm font-bold text-slate-900 tracking-tight group-hover:text-[#3277B2] transition-colors">{{ $row->name }}</p>
                            </div>
                            <p class="mt-1 text-xs font-medium text-slate-600 tabular-nums">{{ $row->location }} · {{ \App\Support\Quantity::format($row->quantity) }} {{ $row->unit }} · {{ $row->quantity===0?'Kosong':'Menipis' }}</p>
                        </div>
                        <span class="text-slate-400 group-hover:text-[#3277B2] group-hover:translate-x-0.5 transition-all">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
                            </svg>
                        </span>
                    </a>
                @empty
                    <div class="my-auto flex flex-col items-center justify-center rounded-lg border border-slate-200 bg-slate-50 p-6 text-center text-slate-500">
                        <div class="grid h-12 w-12 place-items-center rounded-full bg-sky-100 text-[#3277B2] mb-3">
                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <p class="text-sm font-bold text-slate-800 tracking-tight">Persediaan Aman</p>
                        <p class="mt-1 text-xs font-normal text-slate-400">Belum ada kekurangan dari pemeriksaan yang disetujui.</p>
                    </div>
                @endforelse
            </div>
        </div>
    </div>

    <!-- Stok per Lokasi Table Section -->
    <section class="mt-6">
        <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <div class="flex items-center gap-2.5">
                    <span class="grid h-7 w-7 place-items-center rounded-lg bg-brand-50 text-brand-700">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 12h16.5m-16.5 3.75h16.5M3.75 19.5h16.5M5.625 4.5h12.75a1.875 1.875 0 010 3.75H5.625a1.875 1.875 0 010-3.75z" />
                        </svg>
                    </span>
                    <h2 class="font-bold text-slate-900 text-base tracking-tight">Stok per Lokasi</h2>
                </div>
                <p class="mt-1 text-xs font-normal text-slate-500 leading-normal">Diperbarui oleh penerimaan, transfer, dan persetujuan hitung fisik</p>
            </div>
            
            <div class="flex flex-wrap items-center gap-3">
                <div class="relative w-full sm:w-56">
                    <input id="balance-search" type="search" placeholder="Cari obat di tabel..." class="w-full rounded-lg border border-slate-200 bg-white py-2 pl-9 pr-3 text-xs font-medium text-slate-800 placeholder:text-slate-400 focus:border-[#3277B2] focus:outline-none focus:ring-2 focus:ring-[#edf5fb]">
                    <svg class="absolute left-3 top-2.5 h-3.5 w-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
                    </svg>
                </div>
                <a href="{{ route('preview.reports') }}" class="inline-flex items-center gap-1.5 rounded-lg bg-[#3277B2] hover:bg-[#266194] px-3 py-2 text-xs font-bold text-white shadow-xs transition">
                    Buka laporan →
                </a>
            </div>
        </div>

        <div class="card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full whitespace-nowrap text-left text-sm">
                    <thead class="bg-[#edf5fb] border-b border-[#d6e8f6] text-[#266194]">
                        <tr>
                            <th class="p-4 text-[11px] font-bold uppercase tracking-wider">Obat</th>
                            <th class="p-4 text-[11px] font-bold uppercase tracking-wider">Lokasi</th>
                            <th class="p-4 text-[11px] font-bold uppercase tracking-wider">Jumlah</th>
                            <th class="p-4 text-[11px] font-bold uppercase tracking-wider">Terakhir Dihitung Fisik</th>
                            <th class="p-4 text-[11px] font-bold uppercase tracking-wider text-right">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($balances as $balance)
                            <tr class="hover:bg-slate-50/70 transition-colors" data-balance-row>
                                <td class="p-4">
                                    <div class="flex items-center gap-3">
                                        <div class="grid h-8 w-8 shrink-0 place-items-center rounded-lg bg-brand-50 text-xs font-bold text-brand-700">
                                            {{ strtoupper(substr($balance->medicine->name, 0, 2)) }}
                                        </div>
                                        <div>
                                            <b class="font-bold text-slate-900 tracking-tight text-sm">{{ $balance->medicine->name }}</b>
                                            <div class="mt-0.5">
                                                <span class="inline-flex rounded-md bg-slate-100 px-1.5 py-0.5 font-mono text-[11px] font-semibold text-slate-600">{{ $balance->medicine->code }}</span>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="p-4">
                                    <span class="inline-flex items-center gap-1.5 rounded-lg {{ str_contains(strtolower($balance->location->name), 'gudang') ? 'bg-[#edf5fb] text-[#1f4f78] border border-[#b2d4ee]' : 'bg-slate-100 text-slate-700 border border-slate-200' }} px-2.5 py-1 text-xs font-semibold tracking-tight">
                                        <span class="h-1.5 w-1.5 rounded-full {{ str_contains(strtolower($balance->location->name), 'gudang') ? 'bg-[#3277B2]' : 'bg-slate-400' }}"></span>
                                        {{ $balance->location->name }}
                                    </span>
                                </td>
                                <td class="p-4">
                                    <span class="tabular-nums font-bold text-slate-900 text-sm">{{ \App\Support\Quantity::format((float)$balance->quantity) }}</span> <span class="text-xs font-medium text-slate-500">{{ $balance->medicine->unit?->name }}</span>
                                </td>
                                <td class="p-4 text-xs font-medium text-slate-600">
                                    @if($balance->last_counted_at)
                                        <span class="inline-flex items-center gap-1.5 tabular-nums text-slate-600">
                                            <svg class="h-3.5 w-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                                            </svg>
                                            {{ $balance->last_counted_at->format('d/m/Y H:i') }}
                                        </span>
                                    @else
                                        <span class="text-slate-400 italic">Belum dihitung fisik</span>
                                    @endif
                                </td>
                                <td class="p-4 text-right">
                                    <span class="inline-flex items-center rounded-lg bg-sky-50 px-2.5 py-1 text-[11px] font-bold tracking-tight text-[#1f628d] border border-sky-200">
                                        ● Tersedia
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="p-10 text-center text-slate-500">
                                    <div class="grid h-12 w-12 place-items-center rounded-full bg-slate-100 text-slate-400 mx-auto mb-3">
                                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                                        </svg>
                                    </div>
                                    <p class="font-bold text-slate-800 tracking-tight text-sm">Belum ada stok. Catat penerimaan atau setujui pemeriksaan fisik pertama.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </section>
@else
    <div class="mb-5 border-b border-slate-200 pb-4">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <div class="flex flex-wrap items-center gap-2">
                    <span class="inline-flex items-center gap-1.5 rounded-md border border-brand-100 bg-brand-50 px-2 py-0.5 text-[11px] font-semibold text-brand-700">
                        <span class="h-1.5 w-1.5 rounded-full bg-brand-600"></span>
                        Portal Administrator
                    </span>
                    <span class="rounded-md {{ $isDemoDashboard ? 'border border-amber-200 bg-amber-50 text-amber-800' : 'border border-emerald-200 bg-emerald-50 text-emerald-700' }} px-2 py-0.5 text-[11px] font-semibold uppercase">
                        {{ $isDemoDashboard ? 'Mode Demo' : 'Data Real' }}
                    </span>
                </div>
                <h1 class="mt-2 text-xl font-bold text-slate-900 sm:text-2xl">Manajemen Akses & Konfigurasi Sistem</h1>
                <p class="mt-1 text-sm font-normal leading-relaxed text-slate-500">
                    {{ $isDemoDashboard ? 'Ringkasan demo untuk menggambarkan kondisi akun sistem.' : 'Kelola akses pengguna dan referensi sistem Klinik Shifa Medika.' }}
                </p>
            </div>

            <div class="inline-flex rounded-lg border border-slate-200 bg-white p-1 text-xs font-bold shadow-xs">
                <a href="{{ route('dashboard', ['data' => 'demo']) }}" class="rounded-md px-3 py-1.5 transition {{ $isDemoDashboard ? 'bg-[#3277B2] text-white shadow-xs' : 'text-slate-500 hover:bg-slate-50 hover:text-slate-800' }}">
                    Demo
                </a>
                <a href="{{ route('dashboard', ['data' => 'real']) }}" class="rounded-md px-3 py-1.5 transition {{ ! $isDemoDashboard ? 'bg-[#3277B2] text-white shadow-xs' : 'text-slate-500 hover:bg-slate-50 hover:text-slate-800' }}">
                    Data real
                </a>
            </div>
        </div>
    </div>

    <div class="grid gap-3 sm:grid-cols-3">
        @foreach([
            ['Pengguna Terdaftar', $adminStats['users'], 'Akun individu', 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z', '#3277B2', '#edf5fb', '#d6e8f6', 'border-t-[#3277B2]', 'bg-[#3277B2] text-white'],
            ['Akun Aktif', $adminStats['active_users'], 'Dapat mengakses sistem', 'M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z', '#2a759e', '#f0f7fb', '#d2e7f3', 'border-t-[#2a759e]', 'bg-[#2a759e] text-white'],
            ['Role Sistem', $adminStats['roles'], 'Admin, petugas, kepala, guest', 'M9 12.75L11.25 15 15 9.75m-2.164-2.078a3 3 0 01.07-4.242 3 3 0 014.243.07m-4.243 4.172l4.243 4.243m0-8.486a3 3 0 014.242 4.243 3 3 0 01-4.242 4.243', '#5a6b82', '#f1f5f9', '#e2e8f0', 'border-t-slate-500', 'bg-slate-600 text-white'],
        ] as [$label, $value, $hint, $svgPath, $textColor, $bgColor, $borderColor, $topBorder, $iconStyle])
            <div class="card card-interactive p-4">
                <div class="flex items-center justify-between">
                    <p class="text-[11px] font-semibold text-slate-500 uppercase tracking-wider">{{ $label }}</p>
                    <span class="grid h-9 w-9 place-items-center rounded-lg {{ $iconStyle }} shadow-xs">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="{{ $svgPath }}" />
                        </svg>
                    </span>
                </div>
                <p class="mt-3 text-2xl font-bold tracking-tight tabular-nums text-slate-900">{{ $value }}</p>
                <p class="mt-1 text-xs font-normal text-slate-400">{{ $hint }}</p>
            </div>
        @endforeach
    </div>
@endcan
</x-layouts.app>
