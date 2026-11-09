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
    <main class="flex min-h-screen items-center justify-center p-4 sm:p-6">
        <form method="POST" action="{{ route('login') }}" class="w-full max-w-sm rounded-2xl border border-slate-200 bg-white p-6 sm:p-7">
            @csrf
            <div class="mb-6 flex items-center gap-3 border-b border-slate-100 pb-5">
                <img src="{{ asset('images/shifa-medika.png') }}" alt="Logo Klinik Shifa Medika" class="h-12 w-12 shrink-0 object-contain">
                <div class="min-w-0">
                    <h1 class="text-[17px] font-extrabold text-slate-900">Monitoring Obat</h1>
                    <p class="text-xs text-slate-500">Klinik Shifa Medika</p>
                </div>
            </div>
            <p class="mb-4 text-[13px] text-slate-500">Masuk dengan akun yang diberikan administrator.</p>
            @if($errors->any())<div role="alert" class="callout-danger mb-4 font-semibold">{{ $errors->first() }}</div>@endif
            <label for="username" class="label">Username</label><input id="username" name="username" type="text" autocomplete="username" value="{{ old('username') }}" required autofocus class="field mb-4 min-h-11">
            <label for="password" class="label">Password</label>
            <div class="relative mb-4">
                <input id="password" name="password" type="password" autocomplete="current-password" required class="field min-h-11 pr-12">
                <button type="button" hidden data-password-toggle aria-controls="password" aria-pressed="false" aria-label="Tampilkan password" title="Tampilkan password" class="absolute inset-y-0 right-0 flex w-11 items-center justify-center rounded-r-lg text-slate-500 hover:text-brand-700"></button>
            </div>
            <label class="mb-5 flex min-h-11 items-center gap-2 text-[13px] font-semibold text-slate-700"><input type="checkbox" name="remember" value="1" class="h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-600">Ingat saya</label><button class="btn min-h-11 w-full">Masuk</button>
            <p class="mt-6 border-t border-slate-100 pt-4 text-center text-xs leading-relaxed text-slate-500">{{ config('clinic.address') }}</p>
        </form>
    </main>
</body>
</html>
