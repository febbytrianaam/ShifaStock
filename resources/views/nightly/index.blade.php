<x-layouts.app title="Pengecekan Stok Malam">
    <div class="mb-5">
        <h2 class="text-xl font-bold tracking-tight text-slate-900">Pengecekan Stok Malam</h2>
        <p class="mt-1 text-xs text-slate-500">Hasil hitung fisik per tanggal dan lokasi. Tidak menghitung obat keluar secara otomatis.</p>
    </div>

    @if($errors->any())
        <div class="mb-4 rounded-2xl border border-rose-200 border-l-4 border-l-rose-500 bg-rose-50 p-4 text-sm font-semibold text-rose-700 shadow-xs">
            {{ $errors->first() }}
        </div>
    @endif

    <form method="POST" action="{{ route('nightly.store') }}" class="card mb-6 flex flex-wrap items-end gap-4 p-5 border border-slate-200/90 border-t-4 border-t-[#3277B2] bg-white shadow-sm">
        @csrf
        <div>
            <label class="label text-xs font-semibold text-slate-700" for="date">Tanggal pemeriksaan</label>
            <input class="field text-xs" id="date" name="date" type="date" value="{{ old('date', now('Asia/Jakarta')->toDateString()) }}" max="{{ now('Asia/Jakarta')->toDateString() }}" required>
        </div>
        <div>
            <label class="label text-xs font-semibold text-slate-700" for="location">Lokasi</label>
            <select class="field text-xs" id="location" name="location_id" required>
                @foreach($locations as $location)
                    <option value="{{ $location->id }}">{{ $location->name }}</option>
                @endforeach
            </select>
        </div>
        <button class="btn bg-[#3277B2] hover:bg-[#266194] text-white font-bold text-xs">Buka / Mulai Pemeriksaan</button>
    </form>

    <div class="card overflow-hidden border border-slate-200/90 border-t-4 border-t-[#3277B2] bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full whitespace-nowrap text-left text-sm">
                <thead class="bg-[#edf5fb] border-b border-[#d6e8f6] text-[#266194]">
                    <tr>
                        <th class="p-4 text-[11px] font-bold uppercase tracking-wider">Tanggal</th>
                        <th class="p-4 text-[11px] font-bold uppercase tracking-wider">Lokasi</th>
                        <th class="p-4 text-[11px] font-bold uppercase tracking-wider">Status</th>
                        <th class="p-4 text-[11px] font-bold uppercase tracking-wider text-right">Laporan</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($checks as $check)
                        <tr class="hover:bg-slate-50/70 transition-colors">
                            <td class="p-4 text-xs font-medium text-slate-600 tabular-nums">{{ $check->date }}</td>
                            <td class="p-4">
                                <span class="inline-flex items-center gap-1.5 rounded-lg bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700">
                                    {{ $check->location }}
                                </span>
                            </td>
                            <td class="p-4">
                                @php($statusStyle = ['approved' => 'bg-[#edf5fb] text-[#266194] border border-[#b2d4ee]', 'submitted' => 'bg-amber-100 text-amber-800 border border-amber-200', 'draft' => 'bg-slate-100 text-slate-700 border border-slate-200'][$check->status] ?? 'bg-slate-100 text-slate-700 border border-slate-200')
                                @php($statusLabel = ['approved' => 'Disetujui', 'submitted' => 'Menunggu tinjauan', 'draft' => 'Draf'][$check->status] ?? 'Draf')
                                <span class="inline-flex items-center rounded-md px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider {{ $statusStyle }}">
                                    {{ $statusLabel }}
                                </span>
                            </td>
                            <td class="p-4 text-right">
                                <a class="inline-flex items-center rounded-lg px-2.5 py-1 text-xs font-bold text-[#3277B2] hover:bg-[#edf5fb] transition" href="{{ route('nightly.show',$check->id) }}">
                                    Buka laporan →
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td class="p-8 text-center text-slate-500 font-medium" colspan="4">Belum ada hasil pemeriksaan. Mulai dari hitung fisik pertama.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-4">{{ $checks->links() }}</div>
</x-layouts.app>
