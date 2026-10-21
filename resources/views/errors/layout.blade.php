<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('code') - @yield('title') | ShifaStock</title>
    <link rel="icon" type="image/png" href="{{ asset('images/shifa-medika.png') }}">
    {{-- Error pages must not depend on Vite, JavaScript, sessions, or database access. --}}
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; background: #f8fafb; color: #182632; font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; font-size: 14px; letter-spacing: 0; line-height: 1.65; }
        .brand { display: flex; align-items: center; gap: 12px; max-width: 1120px; margin: auto; padding: 24px 32px; }
        .brand img { width: 48px; height: 48px; object-fit: contain; }
        .brand strong, .brand span { display: block; }
        .brand strong { font-size: 15px; }
        .brand span { color: #64748b; font-size: 12px; }
        main { display: flex; align-items: center; min-height: calc(100svh - 128px); padding: 40px 24px 88px; }
        .content { width: 100%; max-width: 560px; margin: 0 auto; }
        .status { display: inline-flex; align-items: center; gap: 8px; margin: 0 0 20px; color: #526471; font-size: 12px; font-weight: 600; }
        .status::before { content: ""; width: 8px; height: 8px; border-radius: 50%; background: @yield('accent', '#3277b2'); }
        h1 { margin: 0 0 14px; font-size: 30px; font-weight: 700; letter-spacing: 0; line-height: 1.3; overflow-wrap: anywhere; }
        .description { margin: 0; color: #526471; font-size: 15px; }
        .guidance { margin: 24px 0; padding: 0 0 0 16px; border-left: 3px solid @yield('accent', '#3277b2'); color: #405362; }
        .guidance strong { display: block; margin-bottom: 4px; color: #243746; font-size: 13px; }
        .guidance p { margin: 0; }
        .actions { display: flex; flex-wrap: wrap; gap: 12px; margin-top: 28px; }
        .button { display: inline-flex; align-items: center; justify-content: center; min-height: 42px; padding: 9px 18px; border: 1px solid #3277b2; border-radius: 6px; background: #3277b2; color: #fff; font-size: 14px; font-weight: 600; text-align: center; text-decoration: none; }
        .button:hover { border-color: #266194; background: #266194; }
        a:focus-visible { outline: 3px solid #74aede; outline-offset: 4px; }
        .help { margin: 28px 0 0; padding-top: 20px; border-top: 1px solid #dfe7ec; color: #64748b; font-size: 12px; }
        [data-uat-notice] { padding: 8px 20px; border-bottom: 1px solid #f4dfa1; background: #fff9e8; color: #765412; text-align: center; font-size: 12px; }
        @media (max-width: 480px) {
            .brand { padding: 20px 24px; }
            main { align-items: flex-start; min-height: 0; padding: 56px 24px; }
            h1 { font-size: 26px; }
            .button { width: 100%; }
        }
    </style>
</head>
<body>
    <x-uat-notice />
    <header class="brand" aria-label="Identitas aplikasi">
        <img src="{{ asset('images/shifa-medika.png') }}" width="48" height="48" alt="Logo Klinik Shifa Medika">
        <div><strong>ShifaStock</strong><span>Klinik Shifa Medika</span></div>
    </header>
    <main>
        <section class="content" aria-labelledby="error-title">
            <p class="status">@yield('code') &middot; @yield('status')</p>
            <h1 id="error-title">@yield('title')</h1>
            <p class="description">@yield('description')</p>
            <div class="guidance">
                <strong>@yield('guidance-title')</strong>
                <p>@yield('guidance')</p>
            </div>
            <nav class="actions" aria-label="Navigasi pemulihan">
                <a class="button" href="{{ url('/') }}">Kembali ke aplikasi</a>
            </nav>
            <p class="help">@yield('help')</p>
        </section>
    </main>
</body>
</html>
