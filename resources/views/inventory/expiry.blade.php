<x-layouts.app title="Monitoring Kedaluwarsa">
    <div class="mb-5">
        <h2 class="text-xl font-bold tracking-tight text-slate-900">Monitoring Kedaluwarsa</h2>
        <p class="mt-1 text-xs text-slate-500">Tanggal berasal dari batch yang dicatat saat penerimaan obat.</p>
    </div>

    <div class="mb-5 grid gap-4 sm:grid-cols-3">
        <!-- Kedaluwarsa -->
        <div class="card p-5 border border-slate-200/90 border-t-4 border-t-rose-500 bg-white shadow-sm">
            <span class="inline-flex rounded-lg bg-rose-100 border border-rose-200 px-2 py-0.5 text-[11px] font-bold uppercase tracking-wider text-rose-800">Kedaluwarsa</span>
            <p class="mt-2 text-3xl font-extrabold tracking-tight tabular-nums text-rose-600">{{ $counts['expired'] }}</p>
            <p class="mt-1 text-xs text-slate-400 font-normal">Sudah melewati tanggal batas</p>
        </div>
        <!-- Dalam 3 Bulan -->
        <div class="card p-5 border border-slate-200/90 border-t-4 border-t-amber-500 bg-white shadow-sm">
            <span class="inline-flex rounded-lg bg-amber-100 border border-amber-200 px-2 py-0.5 text-[11px] font-bold uppercase tracking-wider text-amber-800">Dalam 3 Bulan</span>
            <p class="mt-2 text-3xl font-extrabold tracking-tight tabular-nums text-amber-700">{{ $counts['near'] }}</p>
            <p class="mt-1 text-xs text-slate-400 font-normal">Perlu prioritas penggunaan (FEFO)</p>
        </div>
        <!-- Di Luar 3 Bulan -->
        <div class="card p-5 border border-slate-200/90 border-t-4 border-t-[#3277B2] bg-white shadow-sm">
            <span class="inline-flex rounded-lg bg-[#edf5fb] border border-[#d6e8f6] px-2 py-0.5 text-[11px] font-bold uppercase tracking-wider text-[#266194]">Di Luar 3 Bulan</span>
            <p class="mt-2 text-3xl font-extrabold tracking-tight tabular-nums text-[#266194]">{{ $counts['safe'] }}</p>
            <p class="mt-1 text-xs text-slate-400 font-normal">Masa simpan masih aman</p>
        </div>
    </div>

    <div class="card overflow-hidden border border-slate-200/90 border-t-4 border-t-amber-500 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full whitespace-nowrap text-left text-sm">
                <thead class="bg-amber-50/70 border-b border-amber-200 text-amber-900">
                    <tr>
                        <th class="p-4 text-[11px] font-bold uppercase tracking-wider">Obat</th>
                        <th class="p-4 text-[11px] font-bold uppercase tracking-wider">Batch</th>
                        <th class="p-4 text-[11px] font-bold uppercase tracking-wider">Kedaluwarsa</th>
                        <th class="p-4 text-[11px] font-bold uppercase tracking-wider">Stok Saat Ini</th>
                        <th class="p-4 text-[11px] font-bold uppercase tracking-wider text-right">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($batches as $batch)
                        @php($days=now('Asia/Jakarta')->startOfDay()->diffInDays($batch->expiry_date,false))
                        <tr class="hover:bg-slate-50/70 transition-colors">
                            <td class="p-4">
                                <b class="font-bold text-slate-900 tracking-tight">{{ $batch->medicine->name }}</b><br>
                                <span class="inline-flex rounded-md bg-slate-100 px-1.5 py-0.5 font-mono text-[11px] font-semibold text-slate-500 mt-0.5">{{ $batch->medicine->code }}</span>
                            </td>
                            <td class="p-4 font-mono text-xs font-semibold text-slate-700">{{ $batch->number }}</td>
                            <td class="p-4 font-medium text-slate-800 tabular-nums">{{ $batch->expiry_date->format('d/m/Y') }}</td>
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
                            <td colspan="5" class="p-8 text-center text-slate-500 font-medium">Belum ada batch dari penerimaan obat.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-4">{{ $batches->links() }}</div>
</x-layouts.app>
