@props(['current' => 'upload', 'document' => null, 'compact' => false])
<nav aria-label="Langkah impor" class="{{ $compact ? 'border-b border-slate-200 pb-3' : 'mb-5 border-b border-slate-200 pb-4' }}">
    <ol class="{{ $compact ? 'grid gap-2 text-xs' : 'grid grid-cols-3 gap-2 text-xs sm:text-sm' }}">
        @foreach(['upload' => 'Pilih file', 'match' => 'Cocokkan obat', 'review' => 'Periksa & simpan'] as $key => $label)
            <li class="min-w-0 {{ $current === $key ? 'font-semibold text-blue-700' : 'text-slate-500' }}" @if($current === $key) aria-current="step" @endif>
                @if($key === 'upload')<a href="{{ route('issue-imports.index') }}">{{ $loop->iteration }}. {{ $label }}</a>
                @elseif($document)<a href="{{ route('issue-imports.show', [$document, 'step' => $key]) }}">{{ $loop->iteration }}. {{ $label }}</a>
                @else<span>{{ $loop->iteration }}. {{ $label }}</span>@endif
            </li>
        @endforeach
    </ol>
</nav>
