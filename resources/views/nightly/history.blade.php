<x-layouts.app title="Riwayat Pemeriksaan">
    @php
        $outline = 'inline-flex min-h-11 items-center justify-center whitespace-nowrap rounded-lg border bg-white px-3 text-xs font-bold lg:min-h-[30px] lg:px-2.5';
        $control = 'field w-full min-h-11 py-1.5 sm:w-auto lg:min-h-[34px] lg:py-1';
        // Below md each row stacks into a small grid so the action stays on screen; from md up it is a normal table.
        $stackRow = 'grid grid-cols-[auto_minmax(0,1fr)_auto] items-start gap-x-2 gap-y-1 border-t border-slate-100 px-4 py-3 first:border-t-0 md:table-row md:p-0 md:first:border-t';
        $cell = 'block md:table-cell md:px-4 md:py-2.5';
    @endphp

    <div class="mb-4 flex flex-wrap items-center justify-between gap-2.5 [&>nav]:mb-0">
        <x-page-tabs :links="[
            ['route' => 'preview.nightly', 'label' => 'Harian', 'can' => 'view-nightly-checks', 'active' => ['preview.nightly', 'nightly.show']],
            ['route' => 'preview.opname', 'label' => 'Opname Periodik', 'can' => 'view-opname'],
            ['route' => 'nightly.history', 'label' => 'Riwayat', 'can' => 'view-nightly-history'],
        ]" />

        <form class="grid w-full grid-cols-2 gap-2 sm:flex sm:flex-wrap sm:items-center xl:w-auto" method="GET" role="search" aria-label="Saring riwayat pemeriksaan">
            <x-ui.search id="search-history" name="search" value="{{ request('search') }}" label="Nama / kode obat" placeholder="Cari nama atau kode obat" width="col-span-2 sm:w-64" />
            <label class="sr-only" for="history-location">Lokasi</label>
            <select class="{{ $control }} col-span-2" id="history-location" name="location_id">
                <option value="">Semua lokasi</option>
                @foreach($locations as $l)
                    <option value="{{ $l->id }}" @selected(request('location_id')==$l->id)>{{ $l->name }}</option>
                @endforeach
            </select>
            <span class="flex min-w-0 flex-col gap-1 sm:flex-row sm:items-center sm:gap-1.5">
                <label class="text-xs font-bold text-slate-600" for="from">Dari tanggal</label>
                <input class="{{ $control }}" type="date" id="from" name="from" value="{{ request('from') }}">
            </span>
            <span class="flex min-w-0 flex-col gap-1 sm:flex-row sm:items-center sm:gap-1.5">
                <label class="text-xs font-bold text-slate-600" for="to">Sampai tanggal</label>
                <input class="{{ $control }}" type="date" id="to" name="to" value="{{ request('to') }}">
            </span>
            <button class="btn col-span-2 min-h-11 lg:min-h-[34px] lg:py-1">Tampilkan</button>
        </form>
    </div>

    @if($errors->any())
        <div class="callout-danger mb-4 font-semibold" role="alert">{{ $errors->first() }}</div>
    @endif

    <x-ui.section title="Hasil hitung fisik disetujui" subtitle="Satu baris per obat dari setiap pemeriksaan yang sudah disetujui, terbaru di atas." icon="history" flush :meta="$rows->total().' hasil'">
        <div class="overflow-x-auto">
            <table class="block w-full text-left text-[13px] md:table">
                <thead class="table-head hidden md:table-header-group">
                    <tr>
                        <th class="px-4 py-2.5">Tanggal</th>
                        <th class="px-4 py-2.5">Lokasi</th>
                        <th class="px-4 py-2.5">Obat</th>
                        <th class="px-4 py-2.5">Hasil Hitung Fisik</th>
                        <th class="px-4 py-2.5 text-right">Laporan</th>
                    </tr>
                </thead>
                <tbody class="block md:table-row-group">
                    @forelse($rows as $row)
                        <tr class="{{ $stackRow }}">
                            <td class="{{ $cell }} col-start-1 row-start-2 whitespace-nowrap text-xs font-semibold tabular-nums text-slate-600 md:text-[13px] md:text-slate-700">{{ \Carbon\Carbon::parse($row->date)->locale('id')->translatedFormat('j M Y') }}</td>
                            <td class="{{ $cell }} col-start-2 row-start-2 whitespace-nowrap text-xs font-semibold text-slate-600 md:text-[13px] md:text-slate-700"><span class="md:hidden">· </span>{{ $row->location }}</td>
                            <td class="{{ $cell }} col-span-2 col-start-1 row-start-1 min-w-0 md:min-w-52">
                                <span class="font-extrabold text-slate-900">{{ $row->name }}</span>
                                <span class="mt-1 block"><span class="rounded bg-slate-100 px-1.5 font-mono text-[11px] font-semibold text-slate-600">{{ $row->code }}</span></span>
                            </td>
                            <td class="{{ $cell }} col-start-3 row-start-1 whitespace-nowrap text-right font-extrabold tabular-nums text-slate-900 md:text-left">{{ \App\Support\Quantity::format($row->quantity) }} <span class="font-semibold text-slate-500">{{ $row->unit }}</span></td>
                            <td class="{{ $cell }} col-span-3 row-start-3 pt-1 md:pt-2.5 md:text-right">
                                <a class="{{ $outline }} border-brand-200 text-brand-800 hover:bg-brand-50" href="{{ route('nightly.show',$row->nightly_check_id) }}">Lihat pemeriksaan</a>
                            </td>
                        </tr>
                    @empty
                        <tr class="block md:table-row">
                            <td class="empty-state block md:table-cell" colspan="5">Belum ada hasil pemeriksaan yang disetujui untuk pilihan ini.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($rows->hasPages())
            <div class="border-t border-slate-100 px-4 py-3">{{ $rows->links() }}</div>
        @endif
    </x-ui.section>
</x-layouts.app>
