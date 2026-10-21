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
            ['dashboard', '▦', 'Dashboard', null, 'view-dashboard'],
        ]],
        ['label' => 'ADMINISTRASI', 'inventory' => false, 'anyCan' => ['manage-users', 'view-audit-logs', 'manage-system-references', 'manage-system-settings'], 'items' => [
            ['admin.users.index', '◉', 'Pengguna', null, 'manage-users'],
            ['admin.audit-logs.index', '◌', 'Audit Log', null, 'view-audit-logs'],
            ['admin.references.index', '◇', 'Referensi Sistem', null, 'manage-system-references'],
            ['admin.settings.index', '⚙', 'Pengaturan Sistem', null, 'manage-system-settings'],
        ]],
        ['label' => 'DATA & PERGERAKAN', 'inventory' => false, 'anyCan' => ['view-medicines', 'view-opening-balances', 'view-receipts', 'view-transfers', 'view-issues', 'view-returns'], 'items' => [
            ['medicines.index', '▤', 'Data Obat', null, 'view-medicines'],
            ['opening-balances.index', '+', 'Stok Awal', null, 'view-opening-balances'],
            ['preview.receipts', '↓', 'Penerimaan Obat', null, 'view-receipts', ['preview.receipts', 'receipts.edit']],
            ['preview.transfers', '⇄', 'Transfer Gudang–Apotek', null, 'view-transfers'],
            ['preview.issues', '↑', 'Pengeluaran Obat', null, 'view-issues'],
            ['preview.returns', '↩', 'Retur PBF', null, 'view-returns'],
        ]],
        ['label' => 'PEMERIKSAAN STOK', 'inventory' => false, 'anyCan' => ['view-nightly-checks', 'view-opname', 'view-nightly-history'], 'items' => [
            ['preview.nightly', '✓', 'Pemeriksaan Stok', null, 'view-nightly-checks', ['preview.nightly', 'nightly.show', 'preview.opname', 'nightly.history']],
        ]],
        ['label' => 'TINDAK LANJUT', 'inventory' => false, 'anyCan' => ['view-shortages', 'view-expiry', 'view-special-adjustments', 'view-reports', 'view-stock-card', 'view-monitoring-parameters'], 'items' => [
            ['shortages.index', '!', 'Monitoring', null, 'view-shortages', ['shortages.index', 'preview.expiry']],
            ['monitoring-parameters.index', '⚙', 'Parameter Monitoring', null, 'view-monitoring-parameters'],
            ['special-adjustments.index', '◇', 'Kondisi Khusus', null, 'view-special-adjustments'],
            ['preview.reports', '▥', 'Laporan', null, 'view-reports', ['preview.reports', 'stock-card.index']],
        ]],
    ];
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
        'preview.transfers' => 'Pindahkan stok dari satu lokasi ke lokasi lain, misalnya Gudang ke Apotek. Jika batch dikosongkan, sistem otomatis memakai FEFO dari batch dengan kedaluwarsa paling dekat dan bisa membagi jumlah ke beberapa batch.',
        'preview.issues' => 'Catat obat keluar dari persediaan berdasarkan referensi layanan, tindakan, atau catatan internal klinik. Jika batch dikosongkan, sistem memakai FEFO dan menolak batch yang sudah kedaluwarsa.',
        'preview.returns' => 'Catat obat yang dikembalikan ke PBF, misalnya karena rusak, recall, atau salah kirim. Setelah diposting, stok di lokasi sumber dan stok batch akan berkurang.',
        'preview.nightly' => 'Mulai pemeriksaan fisik stok harian per lokasi. Petugas dapat menyimpan draft, mengirim untuk ditinjau, lalu kepala apoteker dapat menyetujui atau mengembalikan laporan.',
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
                $currentGroupLabel = match ($group['label']) {
                    'MENU UTAMA' => null,
                    'ADMINISTRASI' => 'Administrasi',
                    'DATA & PERGERAKAN' => 'Data dan Pergerakan',
                    'PEMERIKSAAN STOK' => 'Pemeriksaan Stok',
                    'TINDAK LANJUT' => 'Tindak Lanjut',
                    default => ucwords(strtolower($group['label'])),
                };
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
    $restoredInputFields = array_keys(session()->getOldInput());
    $onboardingConfig = ['autoStart' => auth()->user()->onboarding_seen_at === null,
        'endpoint' => route('onboarding.finish'), 'token' => csrf_token(),
        'steps' => \App\Support\Onboarding::steps(auth()->user())];
@endphp
<script type="application/json" data-restored-input-fields>@json($restoredInputFields)</script>
<script type="application/json" data-onboarding-config>@json($onboardingConfig)</script>
<div class="min-h-screen">
    <button type="button" id="sidebar-backdrop" hidden class="fixed inset-0 z-30 bg-slate-950/40 md:hidden" aria-label="Tutup menu"></button>
    <aside id="sidebar" class="fixed inset-y-0 left-0 z-40 w-60 -translate-x-full overflow-y-auto border-r border-slate-200 bg-white p-4 transition-transform md:translate-x-0" aria-label="Navigasi aplikasi">
        <a href="{{ route('dashboard') }}" class="mb-6 flex items-center gap-3">
            <img src="{{ asset('images/shifa-medika.png') }}" alt="Logo Klinik Shifa Medika" class="h-auto w-12 shrink-0">
            <div><p class="text-sm font-bold text-slate-900">Monitoring Obat</p><p class="text-xs text-slate-400">Klinik Shifa Medika</p></div>
        </a>
        <nav aria-label="Menu utama">
            @foreach($navigationGroups as $group)
                @if((!($group['inventory'] ?? false) || auth()->user()->can('view-inventory')) && (!isset($group['can']) || auth()->user()->can($group['can'])) && (!isset($group['anyCan']) || collect($group['anyCan'])->contains(fn ($ability) => auth()->user()->can($ability))))
                    <p class="mb-2 mt-5 text-[10px] font-bold uppercase tracking-wider text-slate-400 first:mt-0">{{ $group['label'] }}</p>
                    <div class="space-y-1">
                        @foreach($group['items'] as $item)
                            @php([$route, $icon, $label, $badge, $can, $activeRoutes] = array_pad($item, 6, []))
                            @php($activeRoutes = $activeRoutes ?: [$route])
                            @php($isActive = collect($activeRoutes)->contains(fn ($activeRoute) => request()->routeIs($activeRoute)))
                            @continue($can && !auth()->user()->can($can))
                            <a data-tour-menu="{{ $route }}" class="nav-link {{ $isActive ? 'nav-active' : '' }}" href="{{ route($route) }}" @if($isActive) aria-current="page" @endif><span class="w-5 shrink-0 text-center text-base" aria-hidden="true">{{ $icon }}</span><span class="min-w-0 flex-1">{{ $label }}</span>@if($badge)<span class="text-[9px] font-semibold uppercase text-slate-400">{{ $badge }}</span>@endif</a>
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
                <button type="button" data-tour-restart class="grid h-8 w-8 place-items-center rounded-md text-slate-500 hover:bg-slate-50" aria-label="Buka panduan" title="Buka panduan"><span data-tour-help-icon aria-hidden="true">?</span></button>
                <span class="hidden text-xs text-slate-400 lg:inline">{{ now()->locale('id')->translatedFormat('d M Y') }}</span>
                <div class="relative">
                    <button id="user-menu-button" data-tour-profile type="button" class="flex max-w-[150px] items-center gap-2 rounded-md px-1.5 py-1 text-sm font-semibold text-slate-600 hover:bg-slate-50 sm:max-w-[220px] sm:px-2" aria-expanded="false" aria-controls="user-menu" data-user-menu-toggle>
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
        <x-uat-notice />
        <div class="p-4 md:p-6" data-tour-main>
            @if($pageDescription)
                <div class="mb-5 rounded-lg border border-slate-200 bg-white px-4 py-3 text-sm font-medium leading-6 text-slate-600">
                    {{ $pageDescription }}
                </div>
            @endif
            {{ $slot }}
        </div>
    </main>
</div>
@livewireScripts
</body>
</html>
