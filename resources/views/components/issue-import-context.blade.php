@props(['document', 'step'])

<section aria-label="Informasi dokumen impor" class="space-y-3" data-import-context>
    <div class="flex items-center justify-between gap-2">
        <h2 class="min-w-0 break-words text-sm font-semibold">IMP-{{ $document->id }} · {{ $document->location->name }}</h2>
        <x-issue-import-delete :document="$document" />
    </div>
    <details class="text-xs text-slate-600" data-import-file-details>
        <summary class="cursor-pointer font-medium">Detail file · {{ $document->row_count }} baris</summary>
        <dl class="mt-3 space-y-2 leading-relaxed">
            <div><dt class="font-medium">Periode pada Excel</dt><dd>{{ $document->period_start->format('d/m/Y H:i') }} - {{ $document->period_end->format('d/m/Y H:i') }} WIB</dd></div>
            <div><dt class="font-medium">Isi file</dt><dd>{{ $document->sale_count }} penjualan · {{ $document->compound_count }} baris racikan</dd></div>
        </dl>
    </details>
    <x-issue-import-steps :current="$step" :document="$document" :compact="true" />
    <a class="inline-block text-xs font-medium text-slate-500" href="{{ route('issue-imports.index') }}">Kembali ke riwayat</a>
    @if(session('status'))<p role="status" class="border-l-2 border-blue-300 bg-blue-50 p-3 text-xs leading-relaxed text-blue-800">{{ session('status') }}</p>@endif
    @if($errors->any())<div role="alert" class="border-l-2 border-rose-300 bg-rose-50 p-3 text-xs leading-relaxed text-rose-800"><ul class="list-inside list-disc space-y-1">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
</section>
