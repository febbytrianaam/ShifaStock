<x-layouts.app title="Monitoring Kedaluwarsa">
    <div class="mb-5 grid gap-3 sm:grid-cols-3">
        <!-- Kedaluwarsa -->
        <div class="card p-4">
            <span class="inline-flex rounded-md bg-rose-50 border border-rose-200 px-2 py-0.5 text-[11px] font-bold uppercase tracking-wider text-rose-700">Kedaluwarsa</span>
            <p class="mt-2 text-xl font-bold tabular-nums text-rose-600">{{ $counts['expired'] }}</p>
            <p class="mt-1 text-xs text-slate-400 font-normal">Sudah melewati tanggal batas</p>
        </div>
        <!-- Dalam 3 Bulan -->
        <div class="card p-4">
            <span class="inline-flex rounded-md bg-amber-50 border border-amber-200 px-2 py-0.5 text-[11px] font-bold uppercase tracking-wider text-amber-800">Dalam 3 Bulan</span>
            <p class="mt-2 text-xl font-bold tabular-nums text-amber-700">{{ $counts['near'] }}</p>
            <p class="mt-1 text-xs text-slate-400 font-normal">Perlu prioritas penggunaan (FEFO)</p>
        </div>
        <!-- Di Luar 3 Bulan -->
        <div class="card p-4">
            <span class="inline-flex rounded-md bg-[#edf5fb] border border-[#d6e8f6] px-2 py-0.5 text-[11px] font-bold uppercase tracking-wider text-[#266194]">Di Luar 3 Bulan</span>
            <p class="mt-2 text-xl font-bold tabular-nums text-[#266194]">{{ $counts['safe'] }}</p>
            <p class="mt-1 text-xs text-slate-400 font-normal">Masa simpan masih aman</p>
        </div>
    </div>

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
                            $locations = $batch->medicine->balances
                                ->filter(fn($balance) => (float) $balance->quantity > 0)
                                ->sortBy(fn($balance) => $balance->location?->name)
                                ->values();
                        @endphp
                        <tr class="hover:bg-slate-50/70 transition-colors">
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
                                        <span class="text-xs text-slate-400">Belum ada saldo lokasi</span>
                                    @endforelse
                                </div>
                            </td>
                            <td class="p-4 font-bold tabular-nums text-slate-900">
                                {{ number_format((float)$batch->medicine->balances->sum('quantity'),0,',','.') }} {{ $batch->medicine->unit?->name }} <span class="text-xs font-normal text-slate-400">(total obat)</span>
                            </td>
                            <td class="p-4 text-right">
                                <span class="inline-flex items-center rounded-md px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider {{ $days<0 ? 'bg-rose-100 text-rose-800 border border-rose-300' : ($days<=90 ? 'bg-amber-100 text-amber-800 border border-amber-300' : 'bg-[#edf5fb] text-[#266194] border border-[#b2d4ee]') }}">
                                    {{ $days<0 ? 'Kedaluwarsa' : ($days<=90 ? $days.' hari lagi' : 'Aman') }}
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
