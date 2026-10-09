@props(['chart'])
@php
    $totalReceipts = array_sum($chart['receipts'] ?? []);
    $totalTransfers = array_sum($chart['transfers'] ?? []);
    $totalMovements = $totalReceipts + $totalTransfers;
@endphp
<div class="card min-w-0 p-4">
    <div class="mb-3 flex flex-col gap-3 border-b border-slate-100 pb-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <div class="flex items-center gap-2.5">
                <span class="grid h-7 w-7 place-items-center rounded-lg bg-brand-50 text-brand-700">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625z" />
                    </svg>
                </span>
                <h2 class="text-sm font-bold text-slate-900">Aktivitas Persediaan</h2>
            </div>
            <p class="mt-1 text-xs text-slate-500 font-normal leading-normal">Jumlah transaksi penerimaan dan transfer dalam 7 hari terakhir</p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <span class="inline-flex items-center gap-1.5 rounded-md border border-slate-200 bg-slate-50 px-2 py-1 text-xs font-semibold text-slate-600">
                <svg class="h-3.5 w-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 9v7.5" />
                </svg>
                7 Hari Terakhir
            </span>
        </div>
    </div>

    <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 mb-4">
        <div class="rounded-lg border border-slate-200 bg-slate-50/70 p-3">
            <div class="flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-wider text-emerald-700">
                <span class="h-2 w-2 rounded-full bg-emerald-500"></span>
                Penerimaan
            </div>
            <p class="mt-1 text-xl font-extrabold tracking-tight tabular-nums text-slate-900">{{ $totalReceipts }} <span class="text-xs font-medium text-slate-500 tracking-normal">transaksi</span></p>
        </div>
        <div class="rounded-lg border border-slate-200 bg-slate-50/70 p-3">
            <div class="flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-wider text-[#266194]">
                <span class="h-2 w-2 rounded-full bg-[#3277B2]"></span>
                Transfer
            </div>
            <p class="mt-1 text-xl font-extrabold tracking-tight tabular-nums text-slate-900">{{ $totalTransfers }} <span class="text-xs font-medium text-slate-500 tracking-normal">transaksi</span></p>
        </div>
        <div class="col-span-2 rounded-lg border border-slate-200 bg-slate-50/70 p-3 sm:col-span-1">
            <div class="flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-wider text-slate-700">
                <span class="h-2 w-2 rounded-full bg-slate-400"></span>
                Total Aktivitas
            </div>
            <p class="mt-1 text-xl font-extrabold tracking-tight tabular-nums text-slate-900">{{ $totalMovements }} <span class="text-xs font-medium text-slate-500 tracking-normal">pergerakan</span></p>
        </div>
    </div>

    <!-- Canvas -->
    <div class="relative h-52 min-w-0 w-full sm:h-56">
        <canvas id="stockChart" height="105" class="max-w-full" role="img" aria-label="Grafik transaksi persediaan tujuh hari terakhir" data-chart='@json($chart)'></canvas>
    </div>
</div>
