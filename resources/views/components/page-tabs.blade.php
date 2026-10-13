@props(['links' => []])

<nav class="mb-5 flex flex-wrap gap-2" aria-label="Submenu halaman">
    @foreach($links as $link)
        @continue(($link['can'] ?? null) && ! auth()->user()->can($link['can']))
        @php($activeRoutes = $link['active'] ?? [$link['route']])
        @php($isActive = collect($activeRoutes)->contains(fn ($route) => request()->routeIs($route)))
        <a
            href="{{ route($link['route']) }}"
            class="inline-flex items-center rounded-md border px-3 py-1.5 text-xs font-bold {{ $isActive ? 'border-[#b2d4ee] bg-[#edf5fb] text-[#266194]' : 'border-slate-200 bg-white text-slate-500 hover:bg-slate-50 hover:text-slate-700' }}"
            @if($isActive) aria-current="page" @endif
        >
            {{ $link['label'] }}
        </a>
    @endforeach
</nav>
