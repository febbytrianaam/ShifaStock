@props(['preview'])

<div class="border-y border-slate-200 py-3 text-xs" data-import-summary>
    <div class="flex flex-wrap gap-x-4 gap-y-2">
        <span><b class="text-emerald-700">{{ $preview['new'] }}</b> siap</span>
        <span><b class="text-slate-600">{{ $preview['duplicates'] }}</b> sudah diimpor</span>
        <span><b class="text-rose-700">{{ $preview['blocked'] }}</b> perlu diperbaiki</span>
    </div>
    <p class="mt-2 text-slate-500">Belum mengurangi stok</p>
</div>
