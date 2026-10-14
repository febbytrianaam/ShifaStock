<x-layouts.app title="Monitoring Kedaluwarsa">
    <x-page-tabs :links="[
        ['route' => 'shortages.index', 'label' => 'Kekurangan Stok', 'can' => 'view-shortages'],
        ['route' => 'preview.expiry', 'label' => 'Kedaluwarsa', 'can' => 'view-expiry'],
    ]" />

    @if($errors->any())
        <div class="mb-4 rounded-lg border border-rose-200 bg-rose-50 p-3 text-sm font-semibold text-rose-700">
            {{ $errors->first() }}
        </div>
    @endif

    <div class="mb-5 grid gap-3 sm:grid-cols-3">
        <!-- Kedaluwarsa -->
        <div class="card p-4 {{ $counts['expired'] > 0 ? 'attention-critical' : '' }}">
            <span class="inline-flex rounded-md {{ $counts['expired'] > 0 ? 'attention-badge-critical bg-rose-100 border border-rose-300 text-rose-800' : 'bg-rose-50 border border-rose-200 text-rose-700' }} px-2 py-0.5 text-[11px] font-bold uppercase tracking-wider">Kedaluwarsa</span>
            <p class="mt-2 text-xl font-bold tabular-nums text-rose-600">{{ $counts['expired'] }}</p>
            <p class="mt-1 text-xs text-slate-400 font-normal">Sudah melewati tanggal batas</p>
        </div>
        <!-- Dalam batas parameter -->
        <div class="card p-4 {{ $counts['near'] > 0 ? 'attention-warning' : '' }}">
            <span class="inline-flex rounded-md {{ $counts['near'] > 0 ? 'attention-badge-warning bg-amber-100 border border-amber-300' : 'bg-amber-50 border border-amber-200' }} px-2 py-0.5 text-[11px] font-bold uppercase tracking-wider text-amber-800">Dalam {{ $warningMonths }} Bulan</span>
            <p class="mt-2 text-xl font-bold tabular-nums text-amber-700">{{ $counts['near'] }}</p>
            <p class="mt-1 text-xs text-slate-400 font-normal">Perlu prioritas penggunaan (FEFO)</p>
        </div>
        <!-- Di luar batas parameter -->
        <div class="card p-4">
            <span class="inline-flex rounded-md bg-[#edf5fb] border border-[#d6e8f6] px-2 py-0.5 text-[11px] font-bold uppercase tracking-wider text-[#266194]">Di Luar {{ $warningMonths }} Bulan</span>
            <p class="mt-2 text-xl font-bold tabular-nums text-[#266194]">{{ $counts['safe'] }}</p>
            <p class="mt-1 text-xs text-slate-400 font-normal">Masa simpan masih aman</p>
        </div>
    </div>

    <form method="GET" class="card mb-5 grid gap-4 p-4 md:grid-cols-2 xl:grid-cols-[1fr_180px_170px_160px_160px_auto]">
        <div>
            <label class="label" for="expiry-search">Obat / batch</label>
            <input class="field text-xs" id="expiry-search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Cari nama, kode, atau batch">
        </div>
        <div>
            <label class="label" for="expiry-location">Lokasi stok</label>
            <select class="field text-xs" id="expiry-location" name="location_id">
                <option value="">Semua lokasi</option>
                @foreach($locations as $location)
                    <option value="{{ $location->id }}" @selected(($filters['location_id'] ?? null)==$location->id)>{{ $location->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="label" for="expiry-status">Status</label>
            <select class="field text-xs" id="expiry-status" name="status">
                <option value="all" @selected(($filters['status'] ?? 'all')==='all')>Semua status</option>
                <option value="expired" @selected(($filters['status'] ?? 'all')==='expired')>Kedaluwarsa</option>
                <option value="near" @selected(($filters['status'] ?? 'all')==='near')>Dalam {{ $warningMonths }} bulan</option>
                <option value="safe" @selected(($filters['status'] ?? 'all')==='safe')>Aman</option>
            </select>
        </div>
        <div>
            <label class="label" for="expiry-from">ED dari</label>
            <input class="field text-xs" id="expiry-from" name="from" type="date" value="{{ $filters['from'] ?? '' }}">
        </div>
        <div>
            <label class="label" for="expiry-to">ED sampai</label>
            <input class="field text-xs" id="expiry-to" name="to" type="date" value="{{ $filters['to'] ?? '' }}">
        </div>
        <div class="flex items-end gap-2">
            <button class="btn text-xs">Tampilkan</button>
            <a class="btn-secondary text-xs" href="{{ route('preview.expiry') }}">Reset</a>
        </div>
    </form>

    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full whitespace-nowrap text-left text-sm">
                <thead class="bg-amber-50/70 border-b border-amber-200 text-amber-900">
                    <tr>
                        <th class="p-4 text-[11px] font-bold uppercase tracking-wider">Obat</th>
                        <th class="p-4 text-[11px] font-bold uppercase tracking-wider">Batch</th>
                        <th class="p-4 text-[11px] font-bold uppercase tracking-wider">Kedaluwarsa</th>
                        <th class="p-4 text-[11px] font-bold uppercase tracking-wider">Lokasi Stok</th>
                        <th class="p-4 text-[11px] font-bold uppercase tracking-wider">Stok Saat Ini</th>
                        <th class="p-4 text-[11px] font-bold uppercase tracking-wider text-right">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($batches as $batch)
                        @php
                            $days=now('Asia/Jakarta')->startOfDay()->diffInDays($batch->expiry_date,false);
                            $locations = $batch->balances
                                ->filter(fn($balance) => (float) $balance->quantity > 0)
                                ->sortBy(fn($balance) => $balance->location?->name)
                                ->values();
                        @endphp
                        <tr class="{{ $days<0 ? 'attention-critical' : ($days<=($warningMonths * 31) ? 'attention-warning' : '') }} hover:bg-slate-50/70 transition-colors">
                            <td class="p-4">
                                <b class="font-bold text-slate-900 tracking-tight">{{ $batch->medicine->name }}</b><br>
                                <span class="inline-flex rounded-md bg-slate-100 px-1.5 py-0.5 font-mono text-[11px] font-semibold text-slate-500 mt-0.5">{{ $batch->medicine->code }}</span>
                            </td>
                            <td class="p-4 font-mono text-xs font-semibold text-slate-700">{{ $batch->number }}</td>
                            <td class="p-4 font-medium text-slate-800 tabular-nums">{{ $batch->expiry_date->format('d/m/Y') }}</td>
                            <td class="p-4">
                                <div class="flex max-w-sm flex-wrap gap-1.5">
                                    @forelse($locations as $balance)
                                        <span class="inline-flex items-center gap-1.5 rounded-md border border-slate-200 bg-slate-50 px-2 py-0.5 text-xs font-semibold text-slate-700">
                                            <span class="h-1.5 w-1.5 rounded-full {{ str_contains(strtolower($balance->location?->name ?? ''), 'gudang') ? 'bg-[#3277B2]' : 'bg-slate-400' }}"></span>
                                            {{ $balance->location?->name ?? 'Lokasi tidak diketahui' }}
                                            <span class="font-mono text-[11px] text-slate-400">{{ number_format((float)$balance->quantity,0,',','.') }}</span>
                                        </span>
                                    @empty
                                        <span class="text-xs text-slate-400">Belum ada stok lokasi</span>
                                    @endforelse
                                </div>
                            </td>
                            <td class="p-4 font-bold tabular-nums text-slate-900">
                                {{ number_format((float)$batch->balances->sum('quantity'),0,',','.') }} {{ $batch->medicine->unit?->name }} <span class="text-xs font-normal text-slate-400">(batch ini)</span>
                            </td>
                            <td class="p-4 text-right">
                                <span class="inline-flex items-center rounded-md px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider {{ $days<0 ? 'attention-badge-critical bg-rose-100 text-rose-800 border border-rose-300' : ($days<=($warningMonths * 31) ? 'attention-badge-warning bg-amber-100 text-amber-800 border border-amber-300' : 'bg-[#edf5fb] text-[#266194] border border-[#b2d4ee]') }}">
                                    {{ $days<0 ? 'Kedaluwarsa' : ($days<=($warningMonths * 31) ? $days.' hari lagi' : 'Aman') }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="p-8 text-center text-slate-500 font-medium">Belum ada batch dari penerimaan obat.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-4">{{ $batches->links() }}</div>
</x-layouts.app>
