<x-ui.tabs label="Pengeluaran obat" class="mb-4" :links="[
    ['label' => 'Input Manual', 'href' => route('preview.issues'), 'active' => request()->routeIs('preview.issues')],
    ['label' => 'Impor & Riwayat Excel', 'href' => $importUrl, 'active' => request()->routeIs('issue-imports.*')],
]" />
@if($resumeDocument && request()->routeIs('preview.issues', 'issue-imports.index'))
    <section class="callout mb-4 flex flex-wrap items-center justify-between gap-3" aria-label="Impor yang belum selesai" data-import-resume>
        <div class="min-w-0"><p class="font-bold text-slate-900">Pemeriksaan IMP-{{ $resumeDocument->id }} · {{ $resumeDocument->location->name }} belum selesai</p><p class="mt-1 text-xs leading-relaxed text-slate-600">File dan pilihan yang sudah disimpan masih tersedia. Tidak perlu unggah ulang. Isian yang belum disimpan tidak ikut tersimpan saat berpindah halaman.</p></div>
        <a class="btn shrink-0" href="{{ route('issue-imports.show', [$resumeDocument, 'step' => $resumeStep]) }}">Lanjutkan pemeriksaan</a>
    </section>
@endif
