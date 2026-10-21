<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Masuk · Klinik Shifa Medika</title>
    <link rel="icon" type="image/png" href="{{ asset('images/shifa-medika.png') }}?v=2">
    <link rel="apple-touch-icon" href="{{ asset('images/shifa-medika.png') }}?v=2">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-[#f6f8fb] text-slate-800">
    <x-uat-notice />
    <main class="flex min-h-screen items-center justify-center p-6">
        <form method="POST" action="{{ route('login') }}" class="w-full max-w-sm rounded-xl border border-slate-200 bg-white p-7 shadow-soft">
            @csrf
            <div class="mb-7 text-center">
                <img src="{{ asset('images/shifa-medika.png') }}" alt="Logo Klinik Shifa Medika" class="mx-auto h-auto w-20">
                <h1 class="mt-4 text-xl font-bold text-slate-900">Klinik Shifa Medika</h1>
                <p class="mt-1 text-xs font-semibold uppercase tracking-wide text-brand-600">Monitoring Persediaan Obat</p>
                <p class="mt-3 text-sm text-slate-500">Masuk dengan akun yang diberikan administrator.</p>
            </div>
            @if($errors->any())<div role="alert" class="mb-4 rounded-xl bg-rose-50 p-3 text-sm text-rose-700">{{ $errors->first() }}</div>@endif
            <label for="username" class="label">Username</label><input id="username" name="username" type="text" autocomplete="username" value="{{ old('username') }}" required autofocus class="field mb-4">
            <label for="password" class="label">Password</label>
            <div class="relative mb-4">
                <input id="password" name="password" type="password" autocomplete="current-password" required class="field pr-12">
                <button type="button" hidden data-password-toggle aria-controls="password" aria-pressed="false" aria-label="Tampilkan password" title="Tampilkan password" class="absolute inset-y-0 right-0 flex w-10 items-center justify-center rounded-r-lg text-slate-500 hover:text-brand-700"></button>
            </div>
            <label class="mb-6 flex items-center gap-2 text-sm"><input type="checkbox" name="remember" value="1">Ingat saya</label><button class="btn w-full">Masuk</button>
            <p class="mt-6 border-t border-slate-100 pt-4 text-center text-xs leading-relaxed text-slate-500">{{ config('clinic.address') }}</p>
        </form>
    </main>
</body>
</html>
