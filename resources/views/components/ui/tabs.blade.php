@props(['links' => [], 'label' => 'Submenu halaman'])
{{-- Segmented page tabs. Each link: ['label' => ..., 'href' => ..., 'active' => bool, 'count' => ?int, 'attributes' => ?array]. --}}
<nav {{ $attributes->class(['segmented']) }} aria-label="{{ $label }}">
    @foreach($links as $link)
        <a href="{{ $link['href'] }}" @class(['segmented-item', 'segmented-active' => $link['active']]) @if($link['active']) aria-current="page" @endif
            @foreach($link['attributes'] ?? [] as $name => $value) {{ $name }}="{{ $value }}" @endforeach>
            {{ $link['label'] }}
            @isset($link['count'])<span class="segmented-count">{{ $link['count'] }}</span>@endisset
        </a>
    @endforeach
</nav>
