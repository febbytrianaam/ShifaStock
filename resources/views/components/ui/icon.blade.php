@props(['name'])
{{-- A name from App\Support\Icons::PATHS, or a raw path for one-off icons. --}}
<svg {{ $attributes->merge(['class' => 'h-4 w-4', 'fill' => 'none', 'viewBox' => '0 0 24 24', 'stroke' => 'currentColor', 'stroke-width' => '2', 'stroke-linecap' => 'round', 'stroke-linejoin' => 'round', 'aria-hidden' => 'true']) }}><path d="{{ \App\Support\Icons::PATHS[$name] ?? $name }}" /></svg>
