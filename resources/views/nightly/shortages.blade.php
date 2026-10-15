<x-layouts.app title="Kekurangan & Permintaan">
    <x-page-tabs :links="[
        ['route' => 'shortages.index', 'label' => 'Kekurangan Stok', 'can' => 'view-shortages'],
        ['route' => 'preview.expiry', 'label' => 'Kedaluwarsa', 'can' => 'view-expiry'],
    ]" />

    <p class="mb-4 text-xs text-slate-600">Sumber ringkasan dan tabel pertama: pemeriksaan fisik terakhir yang disetujui per lokasi. Tanggal hitung tercantum pada kolom Diperiksa. Stok sistem berdasarkan transaksi ditampilkan terpisah pada bagian batas minimum.</p>
    <div class="mb-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <div class="card p-4 {{ $summary['total'] > 0 ? 'attention-warning' : '' }}">
            <span class="inline-flex rounded-md bg-[#edf5fb] border border-[#d6e8f6] px-2 py-0.5 text-[11px] font-bold uppercase tracking-wider text-[#266194]">Perlu Tindak Lanjut</span>
            <p class="mt-2 text-xl font-bold tabular-nums text-[#3277B2]">{{ \App\Support\Quantity::format($summary['total']) }}</p>
            <p class="mt-1 text-xs text-slate-400 font-normal">Obat kosong atau menipis</p>
        </div>
        <div class="card p-4 {{ $summary['empty'] > 0 ? 'attention-critical' : '' }}">
            <span class="inline-flex rounded-md {{ $summary['empty'] > 0 ? 'attention-badge-critical bg-rose-100 border border-rose-300 text-rose-800' : 'bg-rose-50 border border-rose-200 text-rose-700' }} px-2 py-0.5 text-[11px] font-bold uppercase tracking-wider">Stok Kosong</span>
            <p class="mt-2 text-xl font-bold tabular-nums text-rose-600">{{ \App\Support\Quantity::format($summary['empty']) }}</p>
            <p class="mt-1 text-xs text-slate-400 font-normal">Fisik 0 pada pemeriksaan</p>
        </div>
        <div class="card p-4 {{ $summary['low'] > 0 ? 'attention-warning' : '' }}">
            <span class="inline-flex rounded-md {{ $summary['low'] > 0 ? 'attention-badge-warning bg-amber-100 border border-amber-300' : 'bg-amber-50 border border-amber-200' }} px-2 py-0.5 text-[11px] font-bold uppercase tracking-wider text-amber-800">Stok Menipis</span>
            <p class="mt-2 text-xl font-bold tabular-nums text-amber-700">{{ \App\Support\Quantity::format($summary['low']) }}</p>
            <p class="mt-1 text-xs text-slate-400 font-normal">Di bawah ambang batas</p>
        </div>
        <div class="card p-4">
            <span class="inline-flex rounded-md bg-[#edf5fb] border border-[#d6e8f6] px-2 py-0.5 text-[11px] font-bold uppercase tracking-wider text-[#266194]">Permintaan Diisi</span>
            <p class="mt-2 text-xl font-bold tabular-nums text-[#266194]">{{ \App\Support\Quantity::format($summary['requested']) }}</p>
            <p class="mt-1 text-xs text-slate-400 font-normal">Telah dicatat jumlah usulan</p>
        </div>
    </div>

    <form method="GET" class="card mb-5 flex flex-wrap items-end gap-3 p-4">
        <div class="flex-1 min-w-[200px]">
            <label class="label text-xs font-semibold text-slate-700" for="shortage-search">Nama / kode obat</label>
            <input class="field w-full text-xs" id="shortage-search" name="search" value="{{ request('search') }}" placeholder="Cari obat...">
        </div>
        <div>
            <label class="label text-xs font-semibold text-slate-700" for="shortage-location">Lokasi</label>
            <select class="field text-xs" id="shortage-location" name="location_id">
                <option value="">Semua lokasi</option>
                @foreach($locations as $location)
                    <option value="{{ $location->id }}" @selected(request('location_id')==$location->id)>{{ $location->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="label text-xs font-semibold text-slate-700" for="shortage-status">Kondisi</label>
            <select class="field text-xs" id="shortage-status" name="status">
                <option value="all">Kosong dan menipis</option>
                <option value="empty" @selected(request('status')==='empty')>Kosong</option>
                <option value="low" @selected(request('status')==='low')>Menipis</option>
            </select>
        </div>
        <button class="btn bg-[#3277B2] hover:bg-[#266194] text-white font-bold text-xs">Tampilkan</button>
    </form>

    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full whitespace-nowrap text-left text-sm">
                <thead class="bg-[#edf5fb] border-b border-[#d6e8f6] text-[#266194]">
                    <tr>
                        <th class="p-4 text-[11px] font-bold uppercase tracking-wider">Obat</th>
                        <th class="p-4 text-[11px] font-bold uppercase tracking-wider">Lokasi</th>
                        <th class="p-4 text-[11px] font-bold uppercase tracking-wider">Diperiksa</th>
                        <th class="p-4 text-[11px] font-bold uppercase tracking-wider">Stok Fisik</th>
                        <th class="p-4 text-[11px] font-bold uppercase tracking-wider">Kondisi</th>
                        <th class="p-4 text-[11px] font-bold uppercase tracking-wider">Jumlah Diminta</th>
                        <th class="p-4 text-[11px] font-bold uppercase tracking-wider text-right">Sumber</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($rows as $row)
                        <tr class="{{ $row->quantity===0 ? 'attention-critical' : 'attention-warning' }} hover:bg-slate-50/70 transition-colors">
                            <td class="p-4">
                                <b class="font-bold text-slate-900 tracking-tight">{{ $row->name }}</b><br>
                                <span class="inline-flex rounded-md bg-slate-100 px-1.5 py-0.5 font-mono text-[11px] font-semibold text-slate-500 mt-0.5">{{ $row->code }}</span>
                            </td>
                            <td class="p-4 font-medium text-slate-700">{{ $row->location }}</td>
                            <td class="p-4 text-xs font-medium text-slate-600 tabular-nums">{{ $row->date }}</td>
                            <td class="p-4 font-bold tabular-nums text-slate-900">{{ \App\Support\Quantity::format($row->quantity) }} {{ $row->unit }}</td>
                            <td class="p-4">
                                <span class="inline-flex items-center rounded-md px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider {{ $row->quantity===0 ? 'attention-badge-critical bg-rose-100 text-rose-800 border border-rose-300' : 'attention-badge-warning bg-amber-100 text-amber-800 border border-amber-300' }}">
                                    {{ $row->quantity===0 ? 'Kosong' : 'Menipis' }}
                                </span>
                            </td>
                            <td class="p-4 text-slate-700 font-medium tabular-nums">{{ $row->requested_quantity===null ? 'Belum ditentukan' : $row->requested_quantity.' '.$row->unit }}</td>
                            <td class="p-4 text-right">
                                <a class="inline-flex items-center rounded-lg px-2.5 py-1 text-xs font-bold text-[#3277B2] hover:bg-[#edf5fb] transition" href="{{ route('nightly.show',$row->nightly_check_id) }}">
                                    Lihat pemeriksaan →
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="p-8 text-center text-slate-500 font-medium">Belum ada obat kosong atau menipis dari pemeriksaan yang disetujui.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-4">{{ $rows->links() }}</div>

    <section class="mt-8">
        <div class="mb-3 flex items-center gap-2">
            <span class="h-2 w-2 rounded-full bg-[#3277B2]"></span>
            <h3 class="font-bold text-slate-900 text-base tracking-tight">Stok Sistem pada atau di Bawah Minimum</h3>
        </div>
        <div class="card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full whitespace-nowrap text-left text-sm">
                    <thead class="bg-[#edf5fb] border-b border-[#d6e8f6] text-[#266194]">
                        <tr>
                            <th class="p-4 text-[11px] font-bold uppercase tracking-wider">Obat</th>
                            <th class="p-4 text-[11px] font-bold uppercase tracking-wider">Lokasi</th>
                            <th class="p-4 text-[11px] font-bold uppercase tracking-wider">Stok Saat Ini</th>
                            <th class="p-4 text-[11px] font-bold uppercase tracking-wider">Minimum</th>
                            <th class="p-4 text-[11px] font-bold uppercase tracking-wider">Stok diperbarui</th>
                            <th class="p-4 text-[11px] font-bold uppercase tracking-wider">Kondisi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($parameterRows as $row)
                            <tr class="{{ (float)$row->quantity <= 0 ? 'attention-critical' : 'attention-warning' }}">
                                <td class="p-4"><b class="font-bold text-slate-900">{{ $row->name }}</b><br><span class="font-mono text-[11px] text-slate-500">{{ $row->code }}</span></td>
                                <td class="p-4 text-xs font-semibold text-slate-600">{{ $row->location }}</td>
                                <td class="p-4 font-bold tabular-nums text-slate-900">{{ \App\Support\Quantity::format((float)$row->quantity) }} {{ $row->unit }}</td>
                                <td class="p-4 font-bold tabular-nums text-[#266194]">{{ \App\Support\Quantity::format((float)$row->minimum_quantity) }} {{ $row->unit }}</td>
                                <td class="p-4 text-xs">{{ $row->stock_updated_at ? \Carbon\Carbon::parse($row->stock_updated_at)->timezone('Asia/Jakarta')->format('d/m/Y H:i') : 'Belum ada transaksi stok' }}</td>
                                <td class="p-4"><span class="inline-flex items-center rounded-md px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider {{ (float)$row->quantity <= 0 ? 'attention-badge-critical bg-rose-100 text-rose-800 border border-rose-300' : 'attention-badge-warning bg-amber-100 text-amber-800 border border-amber-300' }}">{{ (float)$row->quantity <= 0 ? 'Kosong' : 'Di bawah minimum' }}</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="p-8 text-center text-slate-500 font-medium">Tidak ada stok pada atau di bawah batas minimum yang diatur.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </section>
</x-layouts.app>
