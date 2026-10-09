<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Masuk · Klinik Shifa Medika</title>
    <link rel="icon" type="image/png" href="{{ asset('images/shifa-medika.png') }}?v=2">
    <link rel="apple-touch-icon" href="{{ asset('images/shifa-medika.png') }}?v=2">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-50 text-slate-800 lg:grid lg:grid-cols-2">
    <section class="hidden flex-col justify-between bg-[#102a43] p-12 text-white lg:flex">
        <div class="flex items-center gap-4"><div class="rounded-xl bg-white p-2"><img src="{{ asset('images/shifa-medika.png') }}" alt="Logo Klinik Shifa Medika" class="w-24"></div><p class="text-xl font-bold">Klinik Shifa Medika</p></div>
        <div><p class="mb-4 text-sm font-semibold text-blue-200">MANAJEMEN PERSEDIAAN OBAT</p><h1 class="text-4xl leading-tight font-bold">Persediaan tertelusur,<br>pelayanan lebih terarah.</h1><p class="mt-5 max-w-md text-slate-300">Pencatatan obat berdasarkan batch dan lokasi untuk gudang dan apotek.</p></div>
        <p class="text-xs text-slate-400">Capstone Project · Fondasi aplikasi</p>
    </section>
    <main class="flex min-h-screen items-center justify-center p-6">
        <form method="POST" action="{{ route('login') }}" class="card w-full max-w-md p-8">
            @csrf
            <img src="{{ asset('images/shifa-medika.png') }}" alt="Logo Klinik Shifa Medika" class="mb-6 w-24 lg:hidden">
            <p class="text-sm font-semibold text-blue-700">SELAMAT DATANG</p><h2 class="mt-2 text-3xl font-bold">Masuk ke sistem</h2>
            <p class="mt-2 mb-6 text-sm text-slate-500">Gunakan akun individu yang diberikan administrator.</p>
            @if($errors->any())<div role="alert" class="mb-4 rounded-xl bg-rose-50 p-3 text-sm text-rose-700">{{ $errors->first() }}</div>@endif
            <label for="username" class="label">Username</label><input id="username" name="username" type="text" autocomplete="username" value="{{ old('username') }}" required autofocus class="field mb-4">
            <label for="password" class="label">Password</label><input id="password" name="password" type="password" autocomplete="current-password" required class="field mb-4">
            <label class="mb-6 flex items-center gap-2 text-sm"><input type="checkbox" name="remember" value="1">Ingat saya</label><button class="btn w-full">Masuk</button>
            <div class="mt-6 border-t border-slate-100 pt-4"><p class="text-xs font-semibold text-slate-600">Klinik Shifa Medika</p><p class="mt-1 text-xs leading-relaxed text-slate-500">{{ config('clinic.address') }}</p><details class="mt-3"><summary class="cursor-pointer text-xs font-semibold text-blue-700">Lihat lokasi klinik</summary><iframe src="{{ config('clinic.map_embed_url') }}" title="Peta lokasi Klinik Shifa Medika" width="400" height="300" style="border:0;width:100%;max-width:100%;margin-top:12px;border-radius:12px" allowfullscreen loading="lazy" referrerpolicy="strict-origin-when-cross-origin"></iframe></details></div>
        </form>
    </main>
</body>
</html>
