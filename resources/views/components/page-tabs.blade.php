@props(['links' => []])

@php
    $tabs = collect($links)
        ->reject(fn ($link) => ($link['can'] ?? null) && ! auth()->user()->can($link['can']))
        ->map(fn ($link) => [
            'label' => $link['label'],
            'href' => route($link['route']),
            'active' => collect($link['active'] ?? [$link['route']])->contains(fn ($route) => request()->routeIs($route)),
            'count' => $link['count'] ?? null,
        ])->all();
@endphp
<x-ui.tabs :links="$tabs" class="mb-4" />
