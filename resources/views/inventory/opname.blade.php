<x-layouts.app title="Stock Opname Periodik">
    <x-page-tabs :links="[
        ['route' => 'preview.nightly', 'label' => 'Harian', 'can' => 'view-nightly-checks', 'active' => ['preview.nightly', 'nightly.show']],
        ['route' => 'preview.opname', 'label' => 'Opname Periodik', 'can' => 'view-opname'],
        ['route' => 'nightly.history', 'label' => 'Riwayat', 'can' => 'view-nightly-history'],
    ]" />

    <form method="GET" class="card mb-5 flex flex-wrap items-end gap-3 p-4" data-required-alert="Lengkapi periode opname" novalidate>
        <div>
            <label class="label text-xs font-semibold text-slate-700" for="opname-period">Periode</label>
            <input class="field text-xs" id="opname-period" name="period" type="month" value="{{ $period }}" required>
        </div>
        <button class="btn bg-[#3277B2] hover:bg-[#266194] text-white font-bold text-xs">Tampilkan</button>
    </form>

    <div class="mb-5 grid gap-4 sm:grid-cols-3">
        <div class="card p-4">
            <span class="inline-flex rounded-lg bg-[#edf5fb] border border-[#d6e8f6] px-2 py-0.5 text-[11px] font-bold uppercase tracking-wider text-[#266194]">Periode</span>
            <p class="mt-2 text-xl font-bold text-slate-900">{{ $periodLabel }}</p>
        </div>
        <div class="card p-4">
            <span class="inline-flex rounded-lg bg-[#edf5fb] border border-[#d6e8f6] px-2 py-0.5 text-[11px] font-bold uppercase tracking-wider text-[#266194]">Baris Hasil Hitung</span>
            <p class="mt-2 text-xl font-bold tabular-nums text-slate-900">{{ $rows->total() }}</p>
        </div>
        <div class="card p-4">
            <span class="inline-flex rounded-lg bg-[#edf5fb] border border-[#d6e8f6] px-2 py-0.5 text-[11px] font-bold uppercase tracking-wider text-[#266194]">Sumber Valid</span>
            <p class="mt-2 text-lg font-bold text-[#266194]">Pemeriksaan Disetujui</p>
        </div>
    </div>

    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full whitespace-nowrap text-left text-sm">
                <thead class="sticky top-20 z-10 bg-[#edf5fb] text-[#266194] border-b border-[#d6e8f6] shadow-xs">
                    <tr>
                        <th class="p-4 text-[11px] font-bold uppercase tracking-wider">Tanggal</th>
                        <th class="p-4 text-[11px] font-bold uppercase tracking-wider">Lokasi</th>
                        <th class="p-4 text-[11px] font-bold uppercase tracking-wider">Obat</th>
                        <th class="p-4 text-[11px] font-bold uppercase tracking-wider">Hasil Fisik</th>
                        <th class="p-4 text-[11px] font-bold uppercase tracking-wider">Kondisi</th>
                        <th class="p-4 text-[11px] font-bold uppercase tracking-wider text-right">Pemeriksaan</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($rows as $row)
                        <tr class="hover:bg-slate-50/70 transition-colors">
                            <td class="p-4 text-xs font-medium text-slate-600 tabular-nums">{{ $row->date }}</td>
                            <td class="p-4">
                                <span class="inline-flex items-center gap-1.5 rounded-lg bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-700">{{ $row->location }}</span>
                            </td>
                            <td class="p-4">
                                <b class="font-bold text-slate-900 tracking-tight">{{ $row->name }}</b><br>
                                <span class="inline-flex rounded-md bg-slate-100 px-1.5 py-0.5 font-mono text-[11px] font-semibold text-slate-500 mt-0.5">{{ $row->code }}</span>
                            </td>
                            <td class="p-4 font-bold tabular-nums text-slate-900">{{ $row->quantity }} {{ $row->unit }}</td>
                            <td class="p-4">
                                <span class="inline-flex items-center rounded-md px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider {{ $row->quantity===0 ? 'bg-rose-100 text-rose-800 border border-rose-300' : ($row->is_low ? 'bg-amber-100 text-amber-800 border border-amber-300' : 'bg-[#edf5fb] text-[#266194] border border-[#b2d4ee]') }}">
                                    {{ $row->quantity===0 ? 'Kosong' : ($row->is_low ? 'Menipis' : 'Cukup') }}
                                </span>
                            </td>
                            <td class="p-4 text-right">
                                <a class="inline-flex items-center rounded-lg px-2.5 py-1 text-xs font-bold text-[#3277B2] hover:bg-[#edf5fb] transition" href="{{ route('nightly.show', $row->check_id) }}">
                                    Lihat sumber →
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="p-8 text-center text-slate-500 font-medium">Belum ada pemeriksaan yang disetujui pada {{ $periodLabel }}.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-4">{{ $rows->links() }}</div>
</x-layouts.app>
