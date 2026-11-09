@props(['current' => 'upload', 'document' => null, 'compact' => false])
@php
    // Compact: vertical list in the side panel. Otherwise three equal columns on phones, one row from sm up.
    $item = $compact
        ? 'flex min-h-11 min-w-0 flex-1 items-center gap-2 rounded-lg px-2.5 text-[13px] font-bold lg:min-h-8'
        : 'flex min-h-11 min-w-0 flex-1 flex-col items-center justify-center gap-1 rounded-lg px-1.5 py-1.5 text-center text-[11px] font-bold leading-tight sm:flex-row sm:gap-2 sm:px-2.5 sm:py-0 sm:text-left sm:text-[13px] lg:min-h-8';
@endphp
<nav aria-label="Langkah impor" @class(['border-b border-slate-100 pb-3' => $compact])>
    <ol @class(['grid gap-1' => $compact, 'grid grid-cols-3 gap-1 sm:flex sm:flex-wrap sm:items-center' => ! $compact])>
        @foreach(['upload' => 'Pilih file', 'match' => 'Cocokkan obat', 'review' => 'Periksa & simpan'] as $key => $label)
            <li class="flex min-w-0 items-center gap-1" @if($current === $key) aria-current="step" @endif>
                @if($key === 'upload' || $document)
                    <a href="{{ $key === 'upload' ? route('issue-imports.index') : route('issue-imports.show', [$document, 'step' => $key]) }}" @class([$item, 'bg-brand-50 text-brand-800' => $current === $key, 'text-slate-600 hover:bg-slate-50 hover:text-slate-900' => $current !== $key])>
                        <span @class(['grid h-5 w-5 shrink-0 place-items-center rounded-full text-[11px] font-extrabold', 'bg-brand-600 text-white' => $current === $key, 'bg-slate-200 text-slate-600' => $current !== $key]) aria-hidden="true">{{ $loop->iteration }}</span><span class="sr-only">{{ $loop->iteration }}. </span>{{ $label }}
                    </a>
                @else
                    <span class="{{ $item }} text-slate-400">
                        <span class="grid h-5 w-5 shrink-0 place-items-center rounded-full bg-slate-100 text-[11px] font-extrabold text-slate-400" aria-hidden="true">{{ $loop->iteration }}</span><span class="sr-only">{{ $loop->iteration }}. </span>{{ $label }}
                    </span>
                @endif
                @if(! $compact && ! $loop->last)<x-ui.icon name="chevron-right" class="hidden h-3.5 w-3.5 shrink-0 text-slate-300 sm:block" />@endif
            </li>
        @endforeach
    </ol>
</nav>
