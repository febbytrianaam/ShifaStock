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
        /* Mirrors the app theme (white rounded panel, slate text, brand button) without Vite. */
        body { margin: 0; background: #f8fafc; color: #0f172a; font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; font-size: 14px; letter-spacing: 0; line-height: 1.6; }
        .brand { display: flex; align-items: center; gap: 10px; max-width: 1120px; margin: auto; padding: 20px 24px; }
        .brand img { width: 40px; height: 40px; object-fit: contain; }
        .brand strong, .brand span { display: block; }
        .brand strong { font-size: 15px; font-weight: 800; }
        .brand span { color: #64748b; font-size: 12px; }
        main { display: flex; align-items: center; min-height: calc(100svh - 120px); padding: 32px 16px 80px; }
        .content { width: 100%; max-width: 560px; margin: 0 auto; padding: 28px; border: 1px solid #e2e8f0; border-radius: 16px; background: #fff; }
        .status { display: inline-flex; align-items: center; gap: 8px; margin: 0 0 16px; padding: 1px 8px; border: 1px solid #e2e8f0; border-radius: 6px; background: #f1f5f9; color: #475569; font-size: 11px; font-weight: 700; line-height: 18px; }
        .status::before { content: ""; width: 7px; height: 7px; border-radius: 50%; background: @yield('accent', '#3277b2'); }
        h1 { margin: 0 0 10px; font-size: 24px; font-weight: 800; letter-spacing: 0; line-height: 1.3; overflow-wrap: anywhere; }
        .description { margin: 0; color: #475569; font-size: 14px; }
        .guidance { margin: 20px 0; padding: 12px 14px; border: 1px solid #d6e8f6; border-radius: 12px; background: #f4f9fd; color: #334155; font-size: 13px; }
        .guidance strong { display: block; margin-bottom: 2px; color: #0f172a; font-size: 13px; font-weight: 800; }
        .guidance p { margin: 0; }
        .actions { display: flex; flex-wrap: wrap; gap: 12px; margin-top: 20px; }
        .button { display: inline-flex; align-items: center; justify-content: center; min-height: 44px; padding: 8px 16px; border: 1px solid #3277b2; border-radius: 8px; background: #3277b2; color: #fff; font-size: 13px; font-weight: 700; text-align: center; text-decoration: none; }
        .button:hover { border-color: #266194; background: #266194; }
        a:focus-visible { outline: 3px solid #74aede; outline-offset: 4px; }
        .help { margin: 20px 0 0; padding-top: 16px; border-top: 1px solid #f1f5f9; color: #64748b; font-size: 12px; }
        [data-uat-notice] { padding: 8px 20px; border-bottom: 1px solid #fde68a; background: #fffbeb; color: #78350f; text-align: center; font-size: 12px; }
        @media (max-width: 480px) {
            main { align-items: flex-start; min-height: 0; padding: 24px 16px; }
            .content { padding: 20px; }
            h1 { font-size: 22px; }
            .button { width: 100%; }
        }
    </style>
</head>
<body>
    <x-uat-notice />
    <header class="brand" aria-label="Identitas aplikasi">
        <img src="{{ asset('images/shifa-medika.png') }}" width="48" height="48" alt="Logo Klinik Shifa Medika">
        <div><strong>Monitoring Obat</strong><span>Klinik Shifa Medika</span></div>
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
