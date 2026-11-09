@props(['title' => null, 'subtitle' => null, 'icon' => null, 'tone' => 'brand', 'meta' => null, 'flush' => false, 'headingId' => null, 'heading' => 'h2'])
{{-- White panel with an optional tinted-icon header, as on the Dashboard. "flush" drops the body padding for tables and lists. --}}
@php
    $tones = [
        'brand' => 'bg-brand-100 text-brand-700', 'amber' => 'bg-amber-100 text-amber-700', 'rose' => 'bg-rose-100 text-rose-700',
        'violet' => 'bg-violet-100 text-violet-700', 'green' => 'bg-emerald-100 text-emerald-700', 'teal' => 'bg-teal-100 text-teal-700', 'slate' => 'bg-slate-100 text-slate-600',
    ];
    $headingId ??= $title ? 'judul-'.\Illuminate\Support\Str::slug($title) : null;
@endphp
<section {{ $attributes->class(['card min-w-0', 'overflow-hidden' => $flush]) }} @if($headingId) aria-labelledby="{{ $headingId }}" @endif>
    @if($title || isset($actions))
        <div class="section-head">
            <div class="flex min-w-0 items-center gap-2.5">
                @if($icon)<span class="icon-tile {{ $tones[$tone] ?? $tones['brand'] }}"><x-ui.icon :name="$icon" /></span>@endif
                <div class="min-w-0">
                    @if($title)<{{ $heading }} @if($headingId) id="{{ $headingId }}" @endif class="section-title">{{ $title }}</{{ $heading }}>@endif
                    @if($subtitle)<p class="section-sub">{{ $subtitle }}</p>@endif
                </div>
            </div>
            @if($meta || isset($actions))
                <div class="flex flex-wrap items-center gap-2">
                    @if($meta)<span class="text-xs text-slate-500">{{ $meta }}</span>@endif
                    {{ $actions ?? '' }}
                </div>
            @endif
        </div>
    @endif
    <div @class(['p-4' => ! $flush])>{{ $slot }}</div>
</section>
