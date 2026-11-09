@props(['label', 'value', 'unit' => null, 'sub' => null, 'theme' => 'neutral', 'icon' => null, 'href' => null, 'active' => false])
{{-- Number card in the Dashboard / Monitoring style: light tint, solid colour only on the icon and value. --}}
@php
    $themes = [
        'rose' => ['card' => 'border-rose-200 bg-rose-50/70', 'ring' => 'border-rose-500 ring-2 ring-rose-500/40', 'icon' => 'bg-rose-600 text-white', 'value' => 'text-rose-700'],
        'amber' => ['card' => 'border-amber-200 bg-amber-50/70', 'ring' => 'border-amber-500 ring-2 ring-amber-500/40', 'icon' => 'bg-amber-500 text-amber-950', 'value' => 'text-amber-700'],
        'violet' => ['card' => 'border-violet-200 bg-violet-50/60', 'ring' => 'border-violet-500 ring-2 ring-violet-500/40', 'icon' => 'bg-violet-600 text-white', 'value' => 'text-violet-700'],
        'green' => ['card' => 'border-emerald-200 bg-emerald-50/60', 'ring' => 'border-emerald-500 ring-2 ring-emerald-500/40', 'icon' => 'bg-emerald-700 text-white', 'value' => 'text-emerald-700'],
        'teal' => ['card' => 'border-teal-200 bg-teal-50/60', 'ring' => 'border-teal-500 ring-2 ring-teal-500/40', 'icon' => 'bg-teal-700 text-white', 'value' => 'text-teal-700'],
        'brand' => ['card' => 'border-brand-200 bg-brand-50/70', 'ring' => 'border-brand-500 ring-2 ring-brand-500/40', 'icon' => 'bg-brand-600 text-white', 'value' => 'text-brand-700'],
        'neutral' => ['card' => 'border-slate-200 bg-white', 'ring' => 'border-slate-500 ring-2 ring-slate-400/40', 'icon' => 'bg-slate-600 text-white', 'value' => 'text-slate-900'],
        'muted' => ['card' => 'border-slate-200 bg-white', 'ring' => 'border-slate-500 ring-2 ring-slate-400/40', 'icon' => 'bg-slate-400 text-white', 'value' => 'text-slate-500'],
    ];
    $tone = $themes[$theme] ?? $themes['neutral'];
    $tag = $href ? 'a' : ($attributes->has('wire:click') || $attributes->get('type') === 'button' ? 'button' : 'div');
@endphp
<{{ $tag }} @if($href) href="{{ $href }}" @endif {{ $attributes->class(['flex min-w-0 flex-col items-start rounded-2xl border p-3 text-left sm:px-4 sm:py-3.5', 'transition-shadow hover:shadow-md' => $tag !== 'div', $tone['card'], $tone['ring'] => $active]) }}>
    <span class="flex min-w-0 items-center gap-2">
        @if($icon)
            <span class="grid h-7 w-7 shrink-0 place-items-center rounded-lg {{ $tone['icon'] }}"><x-ui.icon :name="$icon" stroke-width="2.2" /></span>
        @endif
        <span class="text-[13px] font-extrabold text-slate-700">{{ $label }}</span>
    </span>
    <span class="mt-2.5 flex items-baseline gap-1.5">
        <span class="text-[26px] font-extrabold leading-none tabular-nums sm:text-[30px] {{ $tone['value'] }}" data-stat-value>{{ $value }}</span>
        @if($unit)<span class="text-xs font-semibold text-slate-500">{{ $unit }}</span>@endif
    </span>
    @if($sub || $slot->isNotEmpty())
        <span class="mt-1.5 text-xs text-slate-600">{{ $sub }}{{ $slot }}</span>
    @endif
</{{ $tag }}>
