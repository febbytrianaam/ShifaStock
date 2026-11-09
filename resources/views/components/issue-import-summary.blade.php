@props(['preview'])

{{-- Number and label share one line ("1 siap") so the summary reads the same aloud and in innerText. --}}
<div data-import-summary>
    <div class="grid grid-cols-3 gap-2">
        <p @class(['rounded-xl px-2.5 py-2 text-xs font-bold leading-tight text-slate-600', 'bg-emerald-50' => $preview['new'] > 0, 'bg-slate-50' => $preview['new'] <= 0])>
            <b @class(['text-[22px] font-extrabold tabular-nums', 'text-emerald-700' => $preview['new'] > 0, 'text-slate-500' => $preview['new'] <= 0])>{{ $preview['new'] }}</b> siap
        </p>
        <p class="rounded-xl bg-slate-50 px-2.5 py-2 text-xs font-bold leading-tight text-slate-600">
            <b class="text-[22px] font-extrabold tabular-nums text-slate-500">{{ $preview['duplicates'] }}</b> sudah diimpor
        </p>
        <p @class(['rounded-xl px-2.5 py-2 text-xs font-bold leading-tight text-slate-600', 'bg-rose-50' => $preview['blocked'] > 0, 'bg-slate-50' => $preview['blocked'] <= 0])>
            <b @class(['text-[22px] font-extrabold tabular-nums', 'text-rose-700' => $preview['blocked'] > 0, 'text-slate-500' => $preview['blocked'] <= 0])>{{ $preview['blocked'] }}</b> perlu diperbaiki
        </p>
    </div>
    <p class="mt-2 text-xs text-slate-500">Belum mengurangi stok</p>
</div>
