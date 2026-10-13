<x-layouts.app title="Pengecekan Stok Malam">
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
    @if(session('status'))
        <div class="mb-4 rounded-lg border border-[#b2d4ee] bg-[#edf5fb] p-3 text-sm font-semibold text-[#266194]">
            {{ session('status') }}
        </div>
    @endif

    @can('operate-inventory')
    <form method="POST" action="{{ route('nightly.store') }}" class="card mb-5 flex flex-wrap items-end gap-4 p-4" data-required-alert="Lengkapi data pemeriksaan" novalidate>
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
    @endcan

    <div class="card overflow-hidden">
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
                            <td class="p-4">
                                <div class="flex justify-end gap-2">
                                    <a class="inline-flex items-center rounded-lg px-2.5 py-1 text-xs font-bold text-[#3277B2] hover:bg-[#edf5fb] transition" href="{{ route('nightly.show',$check->id) }}">
                                        Buka laporan →
                                    </a>
                                    @can('operate-inventory')
                                        @if($check->status === 'draft' && $check->created_by === auth()->id())
                                            <form method="POST" action="{{ route('nightly.destroy', $check->id) }}" data-confirm-cancel-draft>
                                                @csrf
                                                @method('DELETE')
                                                <button class="inline-flex items-center rounded-lg px-2.5 py-1 text-xs font-bold text-rose-600 hover:bg-rose-50 transition">Cancel</button>
                                            </form>
                                        @endif
                                    @endcan
                                </div>
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
