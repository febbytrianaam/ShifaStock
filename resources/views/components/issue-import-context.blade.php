@props(['document', 'step'])

<section aria-label="Informasi dokumen impor" class="space-y-3" data-import-context>
    <div class="flex items-center justify-between gap-2">
        <div class="flex min-w-0 items-center gap-2.5">
            <span class="icon-tile bg-brand-100 text-brand-700"><x-ui.icon name="file" /></span>
            <h2 class="min-w-0 break-words text-base font-extrabold text-slate-900">IMP-{{ $document->id }} · {{ $document->location->name }}</h2>
        </div>
        <x-issue-import-delete :document="$document" />
    </div>
    <details class="rounded-xl border border-slate-200 text-xs text-slate-600" data-import-file-details>
        <summary class="cursor-pointer px-3 py-2.5 font-bold text-slate-700">Detail file · {{ $document->row_count }} baris</summary>
        <dl class="space-y-2 border-t border-slate-100 px-3 py-2.5 leading-relaxed">
            <div><dt class="font-bold text-slate-700">Periode pada Excel</dt><dd class="tabular-nums">{{ $document->period_start->format('d/m/Y H:i') }} - {{ $document->period_end->format('d/m/Y H:i') }} WIB</dd></div>
            <div><dt class="font-bold text-slate-700">Isi file</dt><dd>{{ $document->sale_count }} penjualan · {{ $document->compound_count }} baris racikan</dd></div>
        </dl>
    </details>
    <x-issue-import-steps :current="$step" :document="$document" :compact="true" />
    <a class="link inline-flex min-h-11 items-center text-xs lg:min-h-0" href="{{ route('issue-imports.index') }}">Kembali ke riwayat</a>
    @if(session('status'))<p role="status" class="callout-ok text-xs">{{ session('status') }}</p>@endif
    @if($errors->any())<div role="alert" class="callout-danger text-xs"><ul class="list-inside list-disc space-y-1">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
</section>
