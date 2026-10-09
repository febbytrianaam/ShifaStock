<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Dashboard' }} · Klinik Shifa Medika</title>
    <link rel="icon" type="image/png" href="{{ asset('images/shifa-medika.png') }}?v=2">
    <link rel="apple-touch-icon" href="{{ asset('images/shifa-medika.png') }}?v=2">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="bg-[#f8fafc] text-slate-800 antialiased">
@php
    $navigationGroups = [
        ['label' => 'MENU UTAMA', 'inventory' => false, 'items' => [
            ['dashboard', '▦', 'Dashboard', null, null],
        ]],
        ['label' => 'ADMINISTRASI', 'can' => 'manage-users', 'items' => [
            ['admin.users.index', '◉', 'Pengguna', null, null],
        ]],
        ['label' => 'DATA & PERGERAKAN', 'inventory' => true, 'items' => [
            ['medicines.index', '▤', 'Data Obat', null, null],
            ['preview.receipts', '↓', 'Penerimaan Obat', null, null],
            ['preview.transfers', '⇄', 'Transfer Gudang–Apotek', null, null],
        ]],
        ['label' => 'PEMERIKSAAN STOK', 'inventory' => true, 'items' => [
            ['preview.nightly', '✓', 'Pemeriksaan Harian', null, null],
            ['preview.opname', '◫', 'Stock Opname Periodik', null, null],
            ['nightly.history', '▧', 'Riwayat Pemeriksaan', null, null],
        ]],
        ['label' => 'TINDAK LANJ', 'inventory' => true, 'items' => [
            ['shortages.index', '!', 'Kekurangan & Permintaan', null, null],
            ['preview.expiry', '◷', 'Monitoring Kedaluwarsa', null, null],
            ['preview.reports', '▥', 'Laporan', null, null],
        ]],
    ];
    $currentPageLabel = $title ?? 'Dashboard';
    $currentGroupLabel = null;
    foreach ($navigationGroups as $group) {
        foreach ($group['items'] as [$route, $icon, $label]) {
            if (request()->routeIs($route)) {
                $currentPageLabel = $label;
                $currentGroupLabel = match ($group['label']) {
                    'MENU UTAMA' => null,
                    'ADMINISTRASI' => 'Administrasi',
                    'DATA & PERGERAKAN' => 'Data dan Pergerakan',
                    'PEMERIKSAAN STOK' => 'Pemeriksaan Stok',
                    'TINDAK LANJ' => 'Tindak Lanjut',
                    default => ucwords(strtolower($group['label'])),
                };
                break 2;
            }
        }
    }
@endphp
<div class="min-h-screen">
    <button type="button" id="sidebar-backdrop" hidden class="fixed inset-0 z-30 bg-slate-950/40 md:hidden" aria-label="Tutup menu"></button>
    <aside id="sidebar" class="fixed inset-y-0 left-0 z-40 w-60 -translate-x-full overflow-y-auto border-r border-slate-200 bg-white p-4 transition-transform md:translate-x-0" aria-label="Navigasi aplikasi">
        <a href="{{ route('dashboard') }}" class="mb-6 flex items-center gap-3">
            <img src="{{ asset('images/shifa-medika.png') }}" alt="Logo Klinik Shifa Medika" class="h-auto w-12 shrink-0">
            <div><p class="text-sm font-bold text-slate-900">Monitoring Obat</p><p class="text-xs text-slate-400">Klinik Shifa Medika</p></div>
        </a>
        <nav aria-label="Menu utama">
            @foreach($navigationGroups as $group)
                @if((!($group['inventory'] ?? false) || auth()->user()->can('view-inventory')) && (!isset($group['can']) || auth()->user()->can($group['can'])))
                    <p class="mb-2 mt-5 text-[10px] font-bold uppercase tracking-wider text-slate-400 first:mt-0">{{ $group['label'] }}</p>
                    <div class="space-y-1">
                        @foreach($group['items'] as [$route, $icon, $label, $badge, $can])
                            @continue($can && !auth()->user()->can($can))
                            <a class="nav-link {{ request()->routeIs($route) ? 'nav-active' : '' }}" href="{{ route($route) }}" @if(request()->routeIs($route)) aria-current="page" @endif><span class="w-5 shrink-0 text-center text-base" aria-hidden="true">{{ $icon }}</span><span class="min-w-0 flex-1">{{ $label }}</span>@if($badge)<span class="text-[9px] font-semibold uppercase text-slate-400">{{ $badge }}</span>@endif</a>
                        @endforeach
                    </div>
                @endif
            @endforeach
        </nav>
    </aside>
    <main class="min-h-screen min-w-0 md:ml-60">
        <header class="sticky top-0 z-20 flex min-h-14 items-center justify-between gap-3 border-b border-slate-200 bg-white/95 px-4 py-2 backdrop-blur md:px-6">
            <div class="flex min-w-0 items-center gap-3">
                <button id="sidebar-toggle" type="button" class="rounded-md border border-slate-200 px-2 py-1.5 text-sm text-slate-600 md:hidden" aria-label="Buka menu" aria-expanded="false" aria-controls="sidebar">☰</button>
                <div class="min-w-0">
                    <nav class="flex min-w-0 items-center gap-1 text-[11px] font-medium text-slate-400" aria-label="Breadcrumb">
                        <a href="{{ route('dashboard') }}" class="shrink-0 hover:text-slate-600">Monitoring Obat</a>
                        @if($currentGroupLabel)
                            <span aria-hidden="true">/</span>
                            <span class="hidden truncate sm:inline">{{ $currentGroupLabel }}</span>
                        @endif
                    </nav>
                    <h1 class="truncate text-sm font-semibold text-slate-900 sm:text-base">{{ $currentPageLabel }}</h1>
                </div>
            </div>
            <div class="flex shrink-0 items-center gap-3">
                <span class="hidden text-xs text-slate-400 lg:inline">{{ now()->locale('id')->translatedFormat('d M Y') }}</span>
                <div class="relative">
                    <button id="user-menu-button" type="button" class="flex max-w-[150px] items-center gap-2 rounded-md px-1.5 py-1 text-sm font-semibold text-slate-600 hover:bg-slate-50 sm:max-w-[220px] sm:px-2" aria-expanded="false" aria-controls="user-menu" data-user-menu-toggle>
                        <span class="hidden min-w-0 truncate text-right text-xs sm:inline">{{ auth()->user()->name ?: auth()->user()->username }}</span>
                        <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-brand-50 text-brand-700" aria-hidden="true">
                            <svg viewBox="0 0 24 24" class="h-3.5 w-3.5" fill="currentColor">
                                <path d="M12 12a4 4 0 1 0-4-4 4 4 0 0 0 4 4Zm0 2c-4.42 0-8 2.24-8 5v1h16v-1c0-2.76-3.58-5-8-5Z" />
                            </svg>
                        </span>
                        <svg class="hidden h-3.5 w-3.5 text-slate-400 sm:block" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 0 1 1.06.02L10 11.17l3.71-3.94a.75.75 0 1 1 1.08 1.04l-4.25 4.5a.75.75 0 0 1-1.08 0l-4.25-4.5a.75.75 0 0 1 .02-1.06Z" clip-rule="evenodd" />
                        </svg>
                    </button>
                    <div id="user-menu" hidden class="absolute right-0 z-30 mt-2 w-44 rounded-lg border border-slate-200 bg-white p-1.5 text-slate-700 shadow-lg">
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button class="w-full rounded-md px-3 py-2 text-left text-sm font-semibold hover:bg-slate-50">Keluar</button>
                        </form>
                    </div>
                </div>
            </div>
        </header>
        <div class="p-4 md:p-6">{{ $slot }}</div>
    </main>
</div>
@livewireScripts
</body>
</html>
