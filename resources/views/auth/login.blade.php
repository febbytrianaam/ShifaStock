<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Masuk · Klinik Shifa Medika</title>
    <link rel="icon" type="image/png" href="{{ asset('images/shifa-medika.png') }}?v=2">
    <link rel="apple-touch-icon" href="{{ asset('images/shifa-medika.png') }}?v=2">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-[#f8fafc] text-slate-800">
    <x-uat-notice />
    <main class="flex min-h-screen items-center justify-center px-4 py-8 sm:p-6">
        <div class="w-full max-w-sm md:max-w-[920px]">
            <div class="grid overflow-hidden rounded-2xl border border-slate-200 bg-white md:grid-cols-[minmax(0,1fr)_minmax(0,1.08fr)]">
                {{-- Same quiet palette as the Dashboard: a light panel with tinted icon tiles, no colour band. Below md only the name stays. --}}
                <section aria-labelledby="login-app-title" class="flex flex-col border-b border-slate-200 bg-slate-50/70 px-6 py-5 md:border-b-0 md:border-r md:p-8">
                    <div class="flex items-center gap-3">
                        <img src="{{ asset('images/shifa-medika.png') }}" alt="Logo Klinik Shifa Medika" class="h-11 w-11 shrink-0 object-contain md:h-12 md:w-12">
                        <div class="min-w-0">
                            <h1 id="login-app-title" class="text-[17px] font-extrabold text-slate-900">Monitoring Obat</h1>
                            <p class="text-xs text-slate-500">Klinik Shifa Medika</p>
                        </div>
                    </div>
                    <div class="hidden md:block">
                        <p class="mt-9 text-[22px] font-extrabold leading-snug text-slate-900">Stok obat Gudang dan Apotek, terpantau setiap hari.</p>
                        <ul class="mt-6 space-y-4">
                            <li class="flex gap-3">
                                <span class="icon-tile bg-teal-100 text-teal-700"><x-ui.icon name="check" /></span>
                                <div class="min-w-0">
                                    <p class="text-[13px] font-extrabold text-slate-800">Pemeriksaan stok harian</p>
                                    <p class="text-xs leading-relaxed text-slate-500">Hitung fisik per lokasi, lalu kirim untuk disetujui kepala apoteker.</p>
                                </div>
                            </li>
                            <li class="flex gap-3">
                                <span class="icon-tile bg-violet-100 text-violet-700"><x-ui.icon name="clock" /></span>
                                <div class="min-w-0">
                                    <p class="text-[13px] font-extrabold text-slate-800">Stok minimum &amp; kedaluwarsa</p>
                                    <p class="text-xs leading-relaxed text-slate-500">Obat yang menipis dan batch yang mendekati ED langsung terlihat.</p>
                                </div>
                            </li>
                            <li class="flex gap-3">
                                <span class="icon-tile bg-brand-100 text-brand-700"><x-ui.icon name="transfer" /></span>
                                <div class="min-w-0">
                                    <p class="text-[13px] font-extrabold text-slate-800">Setiap mutasi tercatat</p>
                                    <p class="text-xs leading-relaxed text-slate-500">Penerimaan, transfer, pengeluaran, dan impor masuk ke kartu stok.</p>
                                </div>
                            </li>
                        </ul>
                        <p class="mt-9 flex items-start gap-2 text-xs leading-relaxed text-slate-500"><x-ui.icon name="location" class="mt-0.5 h-3.5 w-3.5 shrink-0 text-slate-400" />{{ config('clinic.address') }}</p>
                    </div>
                </section>

                <form method="POST" action="{{ route('login') }}" class="flex flex-col justify-center px-6 py-6 sm:px-8 md:px-10 md:py-10">
                    @csrf
                    <h2 class="text-[22px] font-extrabold text-slate-900">Masuk</h2>
                    <p class="mt-1 text-[13px] text-slate-500">Gunakan akun yang diberikan administrator klinik.</p>
                    @if($errors->any())<div role="alert" class="callout-danger mt-5 font-semibold">{{ $errors->first() }}</div>@endif

                    <label for="username" class="label mt-6">Username</label>
                    <div class="relative">
                        <x-ui.icon name="user" class="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                        <input id="username" name="username" type="text" autocomplete="username" value="{{ old('username') }}" required autofocus class="field min-h-11 pl-10">
                    </div>

                    <label for="password" class="label mt-4">Password</label>
                    <div class="relative">
                        <x-ui.icon name="lock" class="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                        <input id="password" name="password" type="password" autocomplete="current-password" required class="field min-h-11 pl-10 pr-12">
                        <button type="button" hidden data-password-toggle aria-controls="password" aria-pressed="false" aria-label="Tampilkan password" title="Tampilkan password" class="absolute inset-y-0 right-0 flex w-11 items-center justify-center rounded-r-lg text-slate-500 hover:text-brand-700"></button>
                    </div>

                    <div class="mt-3 flex flex-wrap items-center justify-between gap-x-3">
                        <label class="flex min-h-11 items-center gap-2 text-[13px] font-semibold text-slate-700"><input type="checkbox" name="remember" value="1" class="h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-600">Ingat saya</label>
                        <span class="text-xs text-slate-500">Lupa password? Hubungi administrator.</span>
                    </div>
                    <button class="btn mt-3 min-h-11 w-full">Masuk</button>

                    <p class="mt-6 flex items-start gap-2 border-t border-slate-100 pt-4 text-xs leading-relaxed text-slate-500"><x-ui.icon name="shield" class="mt-0.5 h-3.5 w-3.5 shrink-0 text-slate-400" />Menu yang tampil setelah masuk mengikuti peran akun Anda.</p>
                </form>
            </div>
            <p class="mt-4 text-center text-xs leading-relaxed text-slate-500 md:hidden">{{ config('clinic.address') }}</p>
        </div>
    </main>
</body>
</html>
