@props(['title' => 'Dashboard', 'hideDescription' => false])
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Dashboard' }} · Klinik Shifa Medika</title>
    <link rel="icon" type="image/png" href="{{ asset('images/shifa-medika.png') }}?v=2">
    <link rel="apple-touch-icon" href="{{ asset('images/shifa-medika.png') }}?v=2">
    {{-- Apply the remembered collapsed sidebar before first paint so the page does not jump. --}}
    <script>try { if (localStorage.getItem('shifastock.sidebar') === 'collapsed') document.documentElement.dataset.sidebar = 'collapsed'; } catch (error) {}</script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="bg-[#f8fafc] text-slate-800 antialiased">
@php
    $navigationGroups = [
        ['label' => null, 'inventory' => false, 'items' => [
            ['dashboard', 'dashboard', 'Dashboard', null, 'view-dashboard'],
        ]],
        ['label' => 'KERJA HARIAN', 'inventory' => false, 'anyCan' => ['view-nightly-checks', 'view-shortages'], 'items' => [
            ['preview.nightly', 'check', 'Pemeriksaan Stok', null, 'view-nightly-checks', ['preview.nightly', 'nightly.show', 'preview.opname', 'nightly.history']],
            ['shortages.index', 'monitor', 'Monitoring Stok', null, 'view-shortages', ['shortages.index', 'preview.expiry']],
        ]],
        ['label' => 'TRANSAKSI', 'inventory' => false, 'anyCan' => ['view-receipts', 'view-transfers', 'view-issues', 'view-returns', 'view-special-adjustments'], 'items' => [
            ['preview.receipts', 'in', 'Penerimaan Obat', null, 'view-receipts', ['preview.receipts', 'receipts.edit']],
            ['preview.transfers', 'transfer', 'Transfer Stok', null, 'view-transfers'],
            ['preview.issues', 'out', 'Pengeluaran & Impor', null, 'view-issues', ['preview.issues', 'issue-imports.*']],
            ['preview.returns', 'return', 'Retur PBF', null, 'view-returns'],
            ['special-adjustments.index', 'special', 'Kondisi Khusus', null, 'view-special-adjustments'],
        ]],
        ['label' => 'DATA & LAPORAN', 'inventory' => false, 'anyCan' => ['view-medicines', 'view-opening-balances', 'view-monitoring-parameters', 'view-reports'], 'items' => [
            ['medicines.index', 'medicine', 'Data Obat', null, 'view-medicines'],
            ['opening-balances.index', 'opening', 'Stok Awal', null, 'view-opening-balances'],
            ['monitoring-parameters.index', 'parameter', 'Parameter Monitoring', null, 'view-monitoring-parameters'],
            ['preview.reports', 'report', 'Laporan & Kartu Stok', null, 'view-reports', ['preview.reports', 'stock-card.index']],
        ]],
        ['label' => 'ADMINISTRASI', 'inventory' => false, 'anyCan' => ['manage-users', 'manage-permissions', 'view-audit-logs', 'manage-system-references', 'manage-system-settings'], 'items' => [
            ['admin.users.index', 'users', auth()->user()->can('manage-users') ? 'Pengguna & Hak Akses' : 'Hak Akses', null, ['manage-users', 'manage-permissions']],
            ['admin.audit-logs.index', 'audit', 'Audit Log', null, 'view-audit-logs'],
            ['admin.references.index', 'reference', 'Referensi Sistem', null, 'manage-system-references'],
            ['admin.settings.index', 'settings', 'Pengaturan Sistem', null, 'manage-system-settings'],
        ]],
    ];
    $navigationIcons = \App\Support\Icons::PATHS;
    $currentPageLabel = $title ?? 'Dashboard';
    $currentGroupLabel = null;
    $pageDescription = null;
    $pageDescriptions = [
        'dashboard' => 'Pantau stok, aktivitas penerimaan dan transfer, serta hasil pemeriksaan terbaru yang memerlukan tindak lanjut.',
        'admin.users.index' => 'Kelola akun pengguna, role, dan hak akses menu dari satu tempat. Password hanya bisa diubah oleh superadmin/admin yang memiliki akses kelola pengguna, sehingga akun petugas tidak mengubah password sendiri.',
        'admin.users.create' => 'Buat akun baru untuk petugas, admin, kepala apoteker, atau guest. Pastikan role sesuai tugasnya karena role menentukan menu dan aksi yang bisa dilakukan.',
        'admin.users.edit' => 'Perbarui nama, username, role, status aktif, atau password pengguna. Nonaktifkan akun jika petugas tidak lagi boleh login, tanpa menghapus riwayat aktivitasnya.',
        'admin.audit-logs.index' => 'Lihat riwayat aktivitas penting seperti login, perubahan pengguna, hak akses, dan transaksi stok. Halaman ini membantu menelusuri siapa melakukan perubahan dan kapan perubahan terjadi.',
        'admin.references.index' => 'Kelola referensi dasar yang dipakai transaksi stok, seperti lokasi dan satuan obat. Nonaktifkan data yang tidak dipakai lagi agar riwayat lama tetap aman.',
        'admin.settings.index' => 'Atur preferensi ringan untuk operasional sistem, seperti default lokasi penerimaan dan identitas klinik yang muncul pada laporan.',
        'medicines.index' => 'Kelola master obat yang menjadi dasar semua transaksi stok. Obat perlu aktif, punya satuan, dan bentuk sediaan agar bisa dipakai pada penerimaan, transfer, pemeriksaan, monitoring, dan laporan.',
        'opening-balances.index' => 'Catat stok awal per obat, batch, dan lokasi ketika data awal persediaan mulai dimasukkan ke sistem. Setiap stok awal dibuat sebagai mutasi agar bisa ditelusuri di kartu stok.',
        'preview.receipts' => 'Catat obat masuk dari PBF berdasarkan faktur. Pilih lokasi tujuan, isi batch dan konversi satuan jika obat diterima per box/strip, lalu posting saat data sudah benar karena stok lokasi dan batch akan langsung bertambah.',
        'receipts.edit' => 'Perbaiki draf penerimaan sebelum menambah stok. Simpan draf untuk melanjutkan nanti, atau simpan dan tambah stok setelah faktur, batch, serta jumlahnya sudah sesuai.',
        'preview.transfers' => 'Pindahkan beberapa obat sekaligus dari satu lokasi ke lokasi lain, misalnya Gudang ke Apotek. Isi obat dan jumlah per baris. Batch kosong memakai kedaluwarsa terdekat secara otomatis. Seluruh item disimpan bersama; jika ada yang gagal, stok tidak dipindahkan.',
        'preview.issues' => 'Catat obat keluar dari persediaan berdasarkan referensi layanan, tindakan, atau catatan internal klinik. Jika batch dikosongkan, sistem memakai FEFO dan menolak batch yang sudah kedaluwarsa.',
        'preview.returns' => 'Catat obat yang dikembalikan ke PBF, misalnya karena rusak, recall, atau salah kirim. Setelah diposting, stok di lokasi sumber dan stok batch akan berkurang.',
        'preview.nightly' => 'Pilih lokasi dan cakupan: semua obat atau obat tertentu. Isi hasil hitung fisik obat dalam laporan, simpan draf atau kirim ke kepala apoteker. Persetujuan hanya menyesuaikan stok obat yang diperiksa.',
        'nightly.show' => 'Isi atau tinjau rincian pemeriksaan fisik stok. Saat laporan disetujui, hasil hitung dapat menjadi dasar penyesuaian stok dan riwayat pemeriksaan.',
        'preview.opname' => 'Lihat rekap stock opname periodik dari pemeriksaan yang sudah disetujui. Gunakan filter periode untuk mengecek hasil fisik per bulan dan sumber pemeriksaannya.',
        'nightly.history' => 'Cari riwayat hasil hitung fisik berdasarkan obat, lokasi, dan rentang tanggal. Cocok untuk mengecek perubahan stok fisik dari pemeriksaan sebelumnya.',
        'shortages.index' => 'Pantau daftar obat yang kosong atau menipis berdasarkan pemeriksaan yang sudah disetujui. Gunakan halaman ini untuk menentukan obat yang perlu diminta atau diprioritaskan.',
        'preview.expiry' => 'Pantau batch obat yang sudah kedaluwarsa atau mendekati kedaluwarsa. Lokasi stok ikut ditampilkan agar petugas tahu batch mana yang perlu diprioritaskan atau ditindaklanjuti.',
        'monitoring-parameters.index' => auth()->user()->can('manage-monitoring-parameters')
            ? 'Atur batas minimum stok per obat dan lokasi, serta periode peringatan kedaluwarsa. Parameter ini membantu monitoring menandai stok yang perlu perhatian dengan aturan yang jelas.'
            : 'Lihat batas minimum stok per obat dan lokasi, serta periode peringatan kedaluwarsa. Nilai di halaman ini hanya dapat diubah oleh pengguna yang memiliki hak kelola parameter.',
        'special-adjustments.index' => 'Catat kejadian khusus di luar penerimaan dan transfer, seperti obat rusak, hilang, terjatuh, tidak diambil pasien, expired, atau koreksi stok. Posting akan menyesuaikan stok sesuai arah yang dipilih.',
        'preview.reports' => 'Lihat ringkasan stok persediaan per lokasi, mutasi terbaru, dan pemeriksaan yang sudah disetujui. Halaman ini membantu mengecek kondisi stok tanpa membuka transaksi satu per satu.',
        'stock-card.index' => 'Telusuri pergerakan stok secara detail per obat, lokasi, batch, dan periode. Jika lokasi dipilih, sistem menampilkan stok awal periode, mutasi masuk-keluar, dan stok berjalan.',
    ];
    foreach ($navigationGroups as $group) {
        foreach ($group['items'] as $item) {
            [$route, $icon, $label, $badge, $can, $activeRoutes] = array_pad($item, 6, []);
            $activeRoutes = $activeRoutes ?: [$route];
            if (collect($activeRoutes)->contains(fn ($activeRoute) => request()->routeIs($activeRoute))) {
                $currentPageLabel = $label;
                $currentGroupLabel = $group['label'] ? ucwords(strtolower($group['label'])) : null;
                break 2;
            }
        }
    }
    foreach ($pageDescriptions as $route => $description) {
        if (request()->routeIs($route)) {
            $pageDescription = $description;
            break;
        }
    }
@endphp
@php
    // Live counts beside menu items that need attention; kept to two cheap counts per page load.
    $navigationCounts = [];
    if (auth()->user()->can('view-inventory')) {
        if (auth()->user()->can('view-shortages')) {
            $navigationCounts['shortages.index'] = ['count' => \App\Queries\MinimumStockAlerts::query()->count(), 'class' => 'bg-rose-50 text-rose-700'];
        }
        if (auth()->user()->can('view-nightly-checks')) {
            $navigationCounts['preview.nightly'] = ['count' => \Illuminate\Support\Facades\DB::table('nightly_checks')->where('status', 'submitted')->count(), 'class' => 'bg-amber-50 text-amber-700'];
        }
    }
    $accountName = auth()->user()->name ?: auth()->user()->username;
    $accountInitials = collect(preg_split('/\s+/', trim($accountName)))->filter()->take(2)->map(fn ($word) => mb_strtoupper(mb_substr($word, 0, 1)))->implode('') ?: 'U';
    $restoredInputFields = array_keys(session()->getOldInput());
    $onboardingConfig = ['autoStart' => auth()->user()->onboarding_seen_at === null,
        'endpoint' => route('onboarding.finish', [], false), 'token' => csrf_token(),
        'steps' => \App\Support\Onboarding::steps(auth()->user())];
@endphp
<script type="application/json" data-restored-input-fields>@json($restoredInputFields)</script>
<script type="application/json" data-onboarding-config>@json($onboardingConfig)</script>
<div class="min-h-screen">
    <button type="button" id="sidebar-backdrop" hidden class="fixed inset-0 z-30 bg-slate-950/45 md:hidden" aria-label="Tutup menu"></button>
    <aside id="sidebar" class="fixed inset-y-0 left-0 z-40 flex w-72 max-w-[86vw] -translate-x-full flex-col border-r border-slate-200 bg-white px-3.5 py-4 shadow-xl transition-transform md:w-64 md:max-w-none md:translate-x-0 md:shadow-none" aria-label="Navigasi aplikasi">
        <div class="flex items-center gap-2.5 pb-3" data-sidebar-brand>
            <a href="{{ route('dashboard') }}" class="flex min-w-0 flex-1 items-center gap-2.5" data-sidebar-home title="Dashboard">
                <img src="{{ asset('images/shifa-medika.png') }}" alt="Logo Klinik Shifa Medika" class="h-9 w-9 shrink-0 object-contain">
                <span class="min-w-0" data-sidebar-text><span class="block text-[15px] font-extrabold text-slate-900">Monitoring Obat</span><span class="block text-xs text-slate-500">Klinik Shifa Medika</span></span>
            </a>
            <button type="button" data-sidebar-collapse class="hidden h-8 w-8 shrink-0 place-items-center rounded-lg border border-slate-200 text-slate-500 hover:bg-slate-50 hover:text-slate-700 md:grid" aria-label="Ciutkan menu" title="Ciutkan menu" aria-pressed="false">
                <svg class="h-[18px] w-[18px]" data-sidebar-collapse-icon fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 3h18v18H3zM9 3v18M17 15l-3-3 3-3" /></svg>
                <svg class="h-[18px] w-[18px]" data-sidebar-expand-icon fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 3h18v18H3zM9 3v18M14 9l3 3-3 3" /></svg>
            </button>
            <button type="button" data-sidebar-close class="grid h-11 w-11 shrink-0 place-items-center rounded-lg border border-slate-200 text-slate-600 md:hidden" aria-label="Tutup menu">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12" /></svg>
            </button>
        </div>
        <nav class="mt-1 flex min-h-0 flex-1 flex-col gap-0.5 overflow-y-auto" aria-label="Menu utama">
            @foreach($navigationGroups as $group)
                @if((!($group['inventory'] ?? false) || auth()->user()->can('view-inventory')) && (!isset($group['can']) || auth()->user()->can($group['can'])) && (!isset($group['anyCan']) || collect($group['anyCan'])->contains(fn ($ability) => auth()->user()->can($ability))))
                    @if($group['label'])
                        <p class="mb-1.5 mt-4 px-2.5 text-[11px] font-bold uppercase tracking-wider text-slate-400" data-sidebar-text>{{ $group['label'] }}</p>
                        <span class="sidebar-divider" aria-hidden="true"></span>
                    @endif
                    @foreach($group['items'] as $item)
                        @php([$route, $icon, $label, $badge, $can, $activeRoutes] = array_pad($item, 6, []))
                        @php($activeRoutes = $activeRoutes ?: [$route])
                        @php($isActive = collect($activeRoutes)->contains(fn ($activeRoute) => request()->routeIs($activeRoute)))
                        @continue($can && !collect((array) $can)->contains(fn ($ability) => auth()->user()->can($ability)))
                        <a data-tour-menu="{{ $route }}" class="nav-link {{ $isActive ? 'nav-active' : '' }}" href="{{ route($route) }}" title="{{ $label }}" @if($isActive) aria-current="page" @endif>
                            <svg class="nav-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="{{ $navigationIcons[$icon] }}" /></svg>
                            <span class="min-w-0 flex-1 truncate" data-sidebar-text>{{ $label }}</span>
                            @if(($navigationCounts[$route]['count'] ?? 0) > 0)<span class="nav-count {{ $navigationCounts[$route]['class'] }}" data-nav-count="{{ $route }}">{{ $navigationCounts[$route]['count'] }}</span>@endif
                        </a>
                    @endforeach
                @endif
            @endforeach
        </nav>
    </aside>
    <main class="min-h-screen min-w-0 md:ml-64" data-app-main>
        <header class="sticky top-0 z-20 flex min-h-14 items-center justify-between gap-3 border-b border-slate-200 bg-white/95 px-4 py-2 backdrop-blur md:px-6">
            <div class="flex min-w-0 items-center gap-3">
                <button id="sidebar-toggle" type="button" class="grid h-11 w-11 shrink-0 place-items-center rounded-lg border border-slate-200 text-slate-600 md:hidden" aria-label="Buka menu" aria-expanded="false" aria-controls="sidebar">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h16" /></svg>
                </button>
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
            <div class="flex shrink-0 items-center gap-2.5">
                @isset($headerActions)
                    <div class="hidden items-center gap-2 border-r border-slate-200 pr-3 xl:flex" data-header-actions>{{ $headerActions }}</div>
                @endisset
                <span class="hidden text-xs text-slate-400 lg:inline">{{ now()->locale('id')->translatedFormat('d M Y') }}</span>
                <button type="button" data-tour-restart class="grid h-9 w-9 place-items-center rounded-lg border border-slate-200 text-sm font-bold text-slate-500 hover:bg-slate-50" aria-label="Buka panduan" title="Buka panduan"><span data-tour-help-icon aria-hidden="true">?</span></button>
                <div class="relative">
                    <button id="user-menu-button" data-tour-profile type="button" class="flex items-center gap-2 rounded-full text-left hover:bg-slate-50 sm:border sm:border-slate-200 sm:py-[3px] sm:pl-[3px] sm:pr-2.5" aria-expanded="false" aria-controls="user-menu" aria-haspopup="true" data-user-menu-toggle>
                        <span class="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-brand-50 text-xs font-extrabold text-brand-700 sm:h-8 sm:w-8" aria-hidden="true">{{ $accountInitials }}</span>
                        <span class="hidden min-w-0 flex-col leading-tight sm:flex">
                            <span class="max-w-[160px] truncate text-[13px] font-bold text-slate-900">{{ $accountName }}</span>
                            <span class="text-[11px] text-slate-500">{{ auth()->user()->role->label() }}</span>
                        </span>
                        <svg class="hidden h-3.5 w-3.5 text-slate-400 sm:block" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 0 1 1.06.02L10 11.17l3.71-3.94a.75.75 0 1 1 1.08 1.04l-4.25 4.5a.75.75 0 0 1-1.08 0l-4.25-4.5a.75.75 0 0 1 .02-1.06Z" clip-rule="evenodd" />
                        </svg>
                        <span class="sr-only">Menu akun</span>
                    </button>
                    <div id="user-menu" hidden class="absolute right-0 z-30 mt-2 w-56 rounded-xl border border-slate-200 bg-white p-1.5 text-slate-700 shadow-lg">
                        <div class="mb-1 border-b border-slate-100 px-2.5 pb-2.5 pt-2">
                            <p class="truncate text-[13px] font-bold text-slate-900">{{ $accountName }}</p>
                            <p class="text-xs text-slate-500">{{ auth()->user()->role->label() }}</p>
                        </div>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button class="flex min-h-10 w-full items-center gap-2.5 rounded-lg px-2.5 text-left text-sm font-bold text-rose-700 hover:bg-rose-50">
                                <svg class="h-[18px] w-[18px]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9" /></svg>
                                Keluar
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </header>
        <x-uat-notice />
        <div class="p-4 md:p-6" data-tour-main>
            @if($pageDescription && !$hideDescription)
                <p class="page-intro" data-page-intro>{{ $pageDescription }}</p>
            @endif
            {{ $slot }}
        </div>
    </main>
</div>
@livewireScripts
</body>
</html>
