<x-layouts.app title="Riwayat Pemeriksaan">
    <x-page-tabs :links="[
        ['route' => 'preview.nightly', 'label' => 'Harian', 'can' => 'view-nightly-checks', 'active' => ['preview.nightly', 'nightly.show']],
        ['route' => 'preview.opname', 'label' => 'Opname Periodik', 'can' => 'view-opname'],
        ['route' => 'nightly.history', 'label' => 'Riwayat', 'can' => 'view-nightly-history'],
    ]" />

    @if($errors->any())
        <div class="mb-4 rounded-lg border border-rose-200 bg-rose-50 p-3 text-sm font-semibold text-rose-700">
            {{ $errors->first() }}
        </div>
    @endif

    <form class="card mb-5 flex flex-wrap items-end gap-3 p-4" method="GET">
        <div class="flex-1 min-w-[200px]">
            <label class="label text-xs font-semibold text-slate-700" for="search-history">Nama / kode obat</label>
            <input class="field w-full text-xs" id="search-history" name="search" value="{{ request('search') }}" placeholder="Cari obat...">
        </div>
        <div>
            <label class="label text-xs font-semibold text-slate-700" for="history-location">Lokasi</label>
            <select class="field text-xs" id="history-location" name="location_id">
                <option value="">Semua lokasi</option>
                @foreach($locations as $l)
                    <option value="{{ $l->id }}" @selected(request('location_id')==$l->id)>{{ $l->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="label text-xs font-semibold text-slate-700" for="from">Dari tanggal</label>
            <input class="field text-xs" type="date" id="from" name="from" value="{{ request('from') }}">
        </div>
        <div>
            <label class="label text-xs font-semibold text-slate-700" for="to">Sampai tanggal</label>
            <input class="field text-xs" type="date" id="to" name="to" value="{{ request('to') }}">
        </div>
        <button class="btn bg-[#3277B2] hover:bg-[#266194] text-white font-bold text-xs">Tampilkan</button>
    </form>

    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full whitespace-nowrap text-left text-sm">
                <thead class="bg-[#edf5fb] border-b border-[#d6e8f6] text-[#266194]">
                    <tr>
                        <th class="p-4 text-[11px] font-bold uppercase tracking-wider">Tanggal</th>
                        <th class="p-4 text-[11px] font-bold uppercase tracking-wider">Lokasi</th>
                        <th class="p-4 text-[11px] font-bold uppercase tracking-wider">Obat</th>
                        <th class="p-4 text-[11px] font-bold uppercase tracking-wider">Hasil Hitung Fisik</th>
                        <th class="p-4 text-[11px] font-bold uppercase tracking-wider text-right">Laporan</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($rows as $row)
                        <tr class="hover:bg-slate-50/70 transition-colors">
                            <td class="p-4 text-xs font-medium text-slate-600 tabular-nums">{{ $row->date }}</td>
                            <td class="p-4">
                                <span class="inline-flex items-center gap-1.5 rounded-lg bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700">
                                    {{ $row->location }}
                                </span>
                            </td>
                            <td class="p-4">
                                <b class="font-bold text-slate-900 tracking-tight">{{ $row->name }}</b><br>
                                <span class="inline-flex rounded-md bg-slate-100 px-1.5 py-0.5 font-mono text-[11px] font-semibold text-slate-500 mt-0.5">{{ $row->code }}</span>
                            </td>
                            <td class="p-4 font-bold tabular-nums text-slate-900">{{ $row->quantity }} {{ $row->unit }}</td>
                            <td class="p-4 text-right">
                                <a class="inline-flex items-center rounded-lg px-2.5 py-1 text-xs font-bold text-[#3277B2] hover:bg-[#edf5fb] transition" href="{{ route('nightly.show',$row->nightly_check_id) }}">
                                    Lihat pemeriksaan →
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td class="p-8 text-center text-slate-500 font-medium" colspan="5">Belum ada hasil pemeriksaan yang disetujui untuk pilihan ini.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-4">{{ $rows->links() }}</div>
</x-layouts.app>
