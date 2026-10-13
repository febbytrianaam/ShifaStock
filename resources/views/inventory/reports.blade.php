<x-layouts.app title="Laporan Persediaan">
    <x-page-tabs :links="[
        ['route' => 'preview.reports', 'label' => 'Ringkasan', 'can' => 'view-reports'],
        ['route' => 'stock-card.index', 'label' => 'Kartu Stok', 'can' => 'view-stock-card'],
    ]" />

    <!-- 1. Stok Saat Ini per Lokasi -->
    <section class="mb-8">
        <div class="mb-3 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <span class="h-2 w-2 rounded-full bg-[#3277B2]"></span>
                <h3 class="font-bold text-slate-900 text-base tracking-tight">Stok Saat Ini per Lokasi</h3>
            </div>
            <a class="text-xs font-bold text-[#3277B2] hover:underline" href="{{ route('nightly.history') }}">Riwayat pemeriksaan →</a>
        </div>
        <div class="card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full whitespace-nowrap text-left text-sm">
                    <thead class="bg-[#edf5fb] border-b border-[#d6e8f6] text-[#266194]">
                        <tr>
                            <th class="p-4 text-[11px] font-bold uppercase tracking-wider">Obat</th>
                            <th class="p-4 text-[11px] font-bold uppercase tracking-wider">Lokasi</th>
                            <th class="p-4 text-[11px] font-bold uppercase tracking-wider">Jumlah</th>
                            <th class="p-4 text-[11px] font-bold uppercase tracking-wider">Terakhir Dihitung</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($balances as $balance)
                            <tr class="hover:bg-slate-50/70 transition-colors">
                                <td class="p-4 font-bold text-slate-900 tracking-tight">{{ $balance->medicine->name }}</td>
                                <td class="p-4">
                                    <span class="inline-flex items-center gap-1.5 rounded-lg {{ str_contains(strtolower($balance->location->name), 'gudang') ? 'bg-[#edf5fb] text-[#266194] border border-[#b2d4ee]' : 'bg-slate-100 text-slate-700 border border-slate-200' }} px-2.5 py-1 text-xs font-semibold">
                                        {{ $balance->location->name }}
                                    </span>
                                </td>
                                <td class="p-4 font-bold tabular-nums text-slate-900">{{ number_format((float)$balance->quantity,0,',','.') }} {{ $balance->medicine->unit?->name }}</td>
                                <td class="p-4 text-xs font-medium text-slate-600 tabular-nums">{{ $balance->last_counted_at?->format('d/m/Y H:i') ?? 'Belum dihitung fisik' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="p-8 text-center text-slate-500 font-medium">Belum ada saldo persediaan.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <!-- 2. 50 Pergerakan Terbaru -->
    <section class="mb-8">
        <div class="mb-3 flex items-center gap-2">
            <span class="h-2 w-2 rounded-full bg-[#3277B2]"></span>
            <h3 class="font-bold text-slate-900 text-base tracking-tight">50 Pergerakan Terbaru</h3>
        </div>
        <div class="card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full whitespace-nowrap text-left text-sm">
                    <thead class="bg-[#edf5fb] border-b border-[#d6e8f6] text-[#266194]">
                        <tr>
                            <th class="p-4 text-[11px] font-bold uppercase tracking-wider">Waktu</th>
                            <th class="p-4 text-[11px] font-bold uppercase tracking-wider">Jenis</th>
                            <th class="p-4 text-[11px] font-bold uppercase tracking-wider">Referensi</th>
                            <th class="p-4 text-[11px] font-bold uppercase tracking-wider">Obat</th>
                            <th class="p-4 text-[11px] font-bold uppercase tracking-wider">Dari</th>
                            <th class="p-4 text-[11px] font-bold uppercase tracking-wider">Ke</th>
                            <th class="p-4 text-[11px] font-bold uppercase tracking-wider">Jumlah</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($movements as $movement)
                            <tr class="hover:bg-slate-50/70 transition-colors">
                                <td class="p-4 text-xs font-medium text-slate-600 tabular-nums">{{ $movement->occurred_at->format('d/m/Y H:i') }}</td>
                                <td class="p-4">
                                    <span class="inline-flex items-center rounded-md px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider {{ $movement->type==='receipt' ? 'bg-[#edf5fb] text-[#266194] border border-[#b2d4ee]' : ($movement->type==='transfer' ? 'bg-sky-50 text-sky-800 border border-sky-200' : (in_array($movement->type, ['supplier_return','special_adjustment'], true) ? 'bg-rose-50 text-rose-700 border border-rose-200' : 'bg-amber-100 text-amber-800 border border-amber-300')) }}">
                                        {{ ['receipt'=>'Penerimaan','transfer'=>'Transfer','physical_count'=>'Penyesuaian Fisik','supplier_return'=>'Retur PBF','special_adjustment'=>'Kondisi Khusus'][$movement->type] ?? $movement->type }}
                                    </span>
                                </td>
                                <td class="p-4 font-mono text-xs font-semibold text-slate-700">{{ $movement->reference }}</td>
                                <td class="p-4 font-bold text-slate-900 tracking-tight">{{ $movement->medicine->name }}</td>
                                <td class="p-4 text-slate-600 font-medium text-xs">{{ $movement->sourceLocation?->name ?? '—' }}</td>
                                <td class="p-4 text-slate-600 font-medium text-xs">{{ $movement->destinationLocation?->name ?? '—' }}</td>
                                <td class="p-4 font-bold tabular-nums text-slate-900">{{ number_format((float)$movement->quantity,0,',','.') }} {{ $movement->medicine->unit?->name }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="p-8 text-center text-slate-500 font-medium">Belum ada pergerakan stok.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <!-- 3. Pemeriksaan Disetujui -->
    <section>
        <div class="mb-3 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <span class="h-2 w-2 rounded-full bg-[#3277B2]"></span>
                <h3 class="font-bold text-slate-900 text-base tracking-tight">Pemeriksaan Disetujui</h3>
            </div>
            <a class="text-xs font-bold text-[#3277B2] hover:underline" href="{{ route('preview.opname') }}">Buka rekap periodik →</a>
        </div>
        <div class="card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full whitespace-nowrap text-left text-sm">
                    <thead class="bg-[#edf5fb] border-b border-[#d6e8f6] text-[#266194]">
                        <tr>
                            <th class="p-4 text-[11px] font-bold uppercase tracking-wider">Tanggal</th>
                            <th class="p-4 text-[11px] font-bold uppercase tracking-wider">Lokasi</th>
                            <th class="p-4 text-[11px] font-bold uppercase tracking-wider">Disetujui</th>
                            <th class="p-4 text-[11px] font-bold uppercase tracking-wider text-right">Detail</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($checks as $check)
                            <tr class="hover:bg-slate-50/70 transition-colors">
                                <td class="p-4 text-xs font-medium text-slate-700 tabular-nums">{{ $check->date }}</td>
                                <td class="p-4 font-semibold text-slate-800">{{ $check->location }}</td>
                                <td class="p-4 text-xs font-medium text-slate-600 tabular-nums">{{ $check->approved_at }}</td>
                                <td class="p-4 text-right">
                                    <a class="inline-flex items-center rounded-lg px-2.5 py-1 text-xs font-bold text-[#3277B2] hover:bg-[#edf5fb] transition" href="{{ route('nightly.show',$check->id) }}">
                                        Lihat laporan →
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="p-8 text-center text-slate-500 font-medium">Belum ada pemeriksaan disetujui.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </section>
</x-layouts.app>
