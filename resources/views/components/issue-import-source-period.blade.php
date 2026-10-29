@props(['rows'])
@php
    $first = \Carbon\CarbonImmutable::parse(collect($rows)->min('occurred_at'), 'Asia/Jakarta');
    $last = \Carbon\CarbonImmutable::parse(collect($rows)->max('occurred_at'), 'Asia/Jakarta');
@endphp
<time datetime="{{ $first->toIso8601String() }}" class="block">{{ $first->format('d/m/Y') }}<span class="block text-slate-500">{{ $first->format('H:i:s') }}</span></time>
@if(!$first->equalTo($last))
    <span class="my-1 block text-slate-400">s/d</span>
    <time datetime="{{ $last->toIso8601String() }}" class="block">{{ $last->format('d/m/Y') }}<span class="block text-slate-500">{{ $last->format('H:i:s') }}</span></time>
@endif
