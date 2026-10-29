<nav class="mb-5 flex flex-wrap gap-4 border-b border-slate-200 text-sm" aria-label="Pengeluaran obat">
    <a href="{{ route('preview.issues') }}" class="border-b-2 px-1 pb-3 {{ request()->routeIs('preview.issues') ? 'border-blue-600 font-semibold text-blue-700' : 'border-transparent text-slate-600' }}">Input Manual</a>
    <a href="{{ $importUrl }}" class="border-b-2 px-1 pb-3 {{ request()->routeIs('issue-imports.*') ? 'border-blue-600 font-semibold text-blue-700' : 'border-transparent text-slate-600' }}">Impor &amp; Riwayat Excel</a>
</nav>
@if($resumeDocument && request()->routeIs('preview.issues', 'issue-imports.index'))
    <section class="mb-5 flex flex-wrap items-center justify-between gap-3 border-l-2 border-blue-400 bg-blue-50 p-3 text-sm" aria-label="Impor yang belum selesai" data-import-resume>
        <div class="min-w-0"><p class="font-semibold text-slate-800">Pemeriksaan IMP-{{ $resumeDocument->id }} · {{ $resumeDocument->location->name }} belum selesai</p><p class="mt-1 text-xs leading-relaxed text-slate-600">File dan pilihan yang sudah disimpan masih tersedia. Tidak perlu unggah ulang. Isian yang belum disimpan tidak ikut tersimpan saat berpindah halaman.</p></div>
        <a class="btn shrink-0 text-xs" href="{{ route('issue-imports.show', [$resumeDocument, 'step' => $resumeStep]) }}">Lanjutkan pemeriksaan</a>
    </section>
@endif
