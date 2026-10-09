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
<body class="bg-[#f6f8fb] text-slate-800 antialiased">
@php
    $navigationGroups = [
        ['label' => 'MENU UTAMA', 'inventory' => false, 'items' => [
            ['dashboard', '▦', 'Dashboard', null],
        ]],
        ['label' => 'DATA & PERGERAKAN', 'inventory' => true, 'items' => [
            ['medicines.index', '▤', 'Data Obat', null],
            ['preview.receipts', '↓', 'Penerimaan Obat', null],
            ['preview.transfers', '⇄', 'Transfer Gudang–Apotek', null],
        ]],
        ['label' => 'PEMERIKSAAN STOK', 'inventory' => true, 'items' => [
            ['preview.nightly', '✓', 'Pemeriksaan Harian', null],
            ['preview.opname', '◫', 'Stock Opname Periodik', null],
            ['nightly.history', '▧', 'Riwayat Pemeriksaan', null],
        ]],
        ['label' => 'TINDAK LANJ', 'inventory' => true, 'items' => [
            ['shortages.index', '!', 'Kekurangan & Permintaan', null],
            ['preview.expiry', '◷', 'Monitoring Kedaluwarsa', null],
            ['preview.reports', '▥', 'Laporan', null],
        ]],
    ];
@endphp
<div class="min-h-screen">
    <button type="button" id="sidebar-backdrop" hidden class="fixed inset-0 z-30 bg-slate-950/40 md:hidden" aria-label="Tutup menu"></button>
    <aside id="sidebar" class="fixed inset-y-0 left-0 z-40 w-64 -translate-x-full overflow-y-auto border-r border-slate-200/80 bg-white p-5 transition-transform md:translate-x-0" aria-label="Navigasi aplikasi">
        <a href="{{ route('dashboard') }}" class="mb-8 flex items-center gap-3">
            <img src="{{ asset('images/shifa-medika.png') }}" alt="Logo Klinik Shifa Medika" class="h-auto w-16 shrink-0">
            <div><p class="font-bold">Monitoring Obat</p><p class="text-xs text-slate-400">Klinik Shifa Medika</p></div>
        </a>
        <nav aria-label="Menu utama">
            @foreach($navigationGroups as $group)
                @if(!$group['inventory'] || auth()->user()->can('view-inventory'))
                    <p class="mb-2 mt-5 text-[11px] font-bold tracking-widest text-slate-400 first:mt-0">{{ $group['label'] }}</p>
                    <div class="space-y-1">
                        @foreach($group['items'] as [$route, $icon, $label, $badge])
                            <a class="nav-link {{ request()->routeIs($route) ? 'nav-active' : '' }}" href="{{ route($route) }}" @if(request()->routeIs($route)) aria-current="page" @endif><span class="w-5 shrink-0 text-center text-base" aria-hidden="true">{{ $icon }}</span><span class="min-w-0 flex-1">{{ $label }}</span>@if($badge)<span class="text-[9px] font-semibold uppercase text-slate-400">{{ $badge }}</span>@endif</a>
                        @endforeach
                    </div>
                @endif
            @endforeach
        </nav>
        <div class="mt-8 border-t border-slate-100 pt-5">
            <div class="rounded-2xl bg-slate-50 p-4"><p class="text-xs text-slate-400">Masuk sebagai</p><p class="mt-1 font-semibold">{{ auth()->user()->role->label() }}</p><p class="mt-1 text-xs text-[#3277B2] font-medium">● Akun aktif</p></div>
            <form method="POST" action="{{ route('logout') }}" class="mt-3">@csrf<button class="px-3 py-2 text-sm font-semibold text-rose-600">Keluar</button></form>
        </div>
    </aside>
    <main class="min-h-screen min-w-0 md:ml-64">
        <header class="sticky top-0 z-20 flex min-h-20 items-center justify-between gap-3 border-b border-slate-200/80 bg-white/95 px-5 py-3 backdrop-blur md:px-8">
            <div class="flex min-w-0 items-center gap-3"><button id="sidebar-toggle" type="button" class="rounded-lg border border-slate-200 p-2 md:hidden" aria-label="Buka menu" aria-expanded="false" aria-controls="sidebar">☰</button><div><p class="text-xs text-slate-400">Monitoring Obat / {{ $title ?? 'Dashboard' }}</p><h1 class="text-xl font-bold">{{ $title ?? 'Dashboard' }}</h1></div></div>
            <div class="flex shrink-0 items-center gap-3"><span class="hidden text-sm text-slate-500 lg:inline">{{ now()->locale('id')->translatedFormat('d F Y') }}</span>@can('manage-medicines')<a href="{{ route('medicines.index') }}?tambah=1" class="btn"><span aria-hidden="true">+</span> Tambah Obat</a>@endcan</div>
        </header>
        <div class="p-5 md:p-8">{{ $slot }}</div>
    </main>
</div>
@livewireScripts
</body>
</html>
