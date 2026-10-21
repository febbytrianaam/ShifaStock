<x-layouts.app title="Stock Opname Periodik">
    <x-page-tabs :links="[
        ['route' => 'preview.nightly', 'label' => 'Harian', 'can' => 'view-nightly-checks', 'active' => ['preview.nightly', 'nightly.show']],
        ['route' => 'preview.opname', 'label' => 'Opname Periodik', 'can' => 'view-opname'],
        ['route' => 'nightly.history', 'label' => 'Riwayat', 'can' => 'view-nightly-history'],
    ]" />

    @if(session('status'))
        <div class="mb-4 rounded-lg border border-[#b2d4ee] bg-[#edf5fb] p-3 text-sm font-semibold text-[#266194]">{{ session('status') }}</div>
    @endif
    @if($errors->any())
        <div class="mb-4 rounded-lg border border-rose-200 bg-rose-50 p-3 text-sm font-semibold text-rose-700">{{ $errors->first() }}</div>
    @endif

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
            <span class="inline-flex rounded-lg bg-[#edf5fb] border border-[#d6e8f6] px-2 py-0.5 text-[11px] font-bold uppercase tracking-wider text-[#266194]">Sesi Bulanan</span>
            <p class="mt-2 text-xl font-bold tabular-nums text-slate-900">{{ $sessions->count() }}</p>
        </div>
        <div class="card p-4">
            <span class="inline-flex rounded-lg bg-[#edf5fb] border border-[#d6e8f6] px-2 py-0.5 text-[11px] font-bold uppercase tracking-wider text-[#266194]">Rekap Harian</span>
            <p class="mt-2 text-xl font-bold tabular-nums text-slate-900">{{ $rows->total() }}</p>
        </div>
    </div>

    <p class="mb-4 text-xs text-slate-600">Opname bulanan membandingkan hitungan fisik dengan stok saat snapshot dibuat. Simpan isian sebelum finalisasi. Jika ada transaksi atau perubahan satuan setelah snapshot, pilih Mulai Hitung Ulang dan periksa kembali seluruh stok. Rekap harian di bawah terpisah dari isian sesi bulanan.</p>
    @can('operate-inventory')
    <form method="POST" action="{{ route('opname.store') }}" class="card mb-5 grid gap-4 p-4 md:grid-cols-2 xl:grid-cols-5" data-required-alert="Lengkapi sesi stock opname" novalidate>
        @csrf
        <div>
            <label class="label" for="session-period">Periode</label>
            <input class="field text-xs" id="session-period" name="period" type="month" value="{{ old('period', $period) }}" required>
        </div>
        <div>
            <label class="label" for="session-location">Lokasi</label>
            <select class="field text-xs" id="session-location" name="location_id" required>
                @foreach($locations as $location)
                    <option value="{{ $location->id }}" @selected(old('location_id')==$location->id)>{{ $location->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="label" for="session-cutoff">Batas waktu pencatatan stok</label>
            <input class="field text-xs" id="session-cutoff" name="cutoff_at" type="datetime-local" value="{{ old('cutoff_at',now('Asia/Jakarta')->format('Y-m-d\\TH:i')) }}" required>
        </div>
        <div class="xl:col-span-2">
            <label class="label" for="session-notes">Catatan</label>
            <input class="field text-xs" id="session-notes" name="notes" value="{{ old('notes') }}" placeholder="Catatan sesi opname" maxlength="1000">
        </div>
        <div class="md:col-span-2 xl:col-span-5 flex justify-end">
            <button class="btn text-xs">Buat Sesi Bulanan</button>
        </div>
    </form>
    @endcan

    @if($sessions->isNotEmpty())
        <div class="mb-5 flex flex-wrap gap-2">
            @foreach($sessions as $session)
                <a href="{{ route('preview.opname', ['period' => $period, 'session' => $session->id]) }}" class="rounded-lg border px-3 py-2 text-xs font-bold {{ $selectedSession?->id === $session->id ? 'border-[#3277B2] bg-[#edf5fb] text-[#266194]' : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50' }}">
                    {{ $session->location->name }} · {{ $session->status === 'finalized' ? 'Final' : 'Draft' }}
                </a>
            @endforeach
        </div>
    @endif

    @if($selectedSession)
        <section class="card mb-8 overflow-hidden">
            <div class="flex flex-col gap-3 border-b border-slate-100 p-4 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <h2 class="text-sm font-bold text-slate-900">Sesi {{ $selectedSession->reference }} · {{ $selectedSession->location->name }}</h2>
                    <p class="mt-1 text-xs text-slate-500">Cut-off {{ $selectedSession->cutoff_at->format('d/m/Y H:i') }} · Status {{ $selectedSession->status === 'finalized' ? 'Final' : 'Draft' }}</p>
                </div>
                @if($selectedSession->status === 'finalized')
                    <span class="inline-flex rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-1 text-xs font-bold text-emerald-700">Difinalisasi {{ $selectedSession->finalized_at?->format('d/m/Y H:i') }}</span>
                @endif
            </div>

            @can('operate-inventory')
            <form method="POST" action="{{ route('opname.save', $selectedSession) }}" data-required-alert="Periksa isian stock opname" novalidate>
                @csrf
            @else
            <div>
            @endcan
                <div class="overflow-x-auto">
                    <table class="w-full whitespace-nowrap text-left text-sm">
                        <thead class="bg-[#edf5fb] border-b border-[#d6e8f6] text-[#266194]">
                            <tr>
                                <th class="p-4 text-[11px] font-bold uppercase tracking-wider">Obat / Batch</th>
                                <th class="p-4 text-[11px] font-bold uppercase tracking-wider">Stok Sistem</th>
                                <th class="p-4 text-[11px] font-bold uppercase tracking-wider">Fisik</th>
                                <th class="p-4 text-[11px] font-bold uppercase tracking-wider">Selisih</th>
                                <th class="p-4 text-[11px] font-bold uppercase tracking-wider">Alasan</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse($selectedSession->items as $item)
                                @php($difference = $item->physical_quantity === null ? null : (float)$item->physical_quantity - (float)$item->system_quantity)
                                <tr>
                                    <td class="p-4">
                                        <b class="font-bold text-slate-900">{{ $item->medicine->name }}</b><br>
                                        <span class="text-xs text-slate-500">{{ $item->batch?->number ?? 'Tanpa batch' }} · {{ $item->unit_name }}</span>
                                    </td>
                                    <td class="p-4 font-bold tabular-nums text-slate-900">{{ \App\Support\Quantity::format((float)$item->system_quantity) }}</td>
                                    <td class="p-4">
                                        @can('operate-inventory')
                                        <input class="field w-32 text-xs" name="items[{{ $item->id }}][physical_quantity]" type="number" min="0" step="0.0001" value="{{ old('items.'.$item->id.'.physical_quantity', $item->physical_quantity !== null ? rtrim(rtrim((string)$item->physical_quantity, '0'), '.') : '') }}" @disabled($selectedSession->status === 'finalized')>
                                        @else
                                        <span class="tabular-nums">{{ $item->physical_quantity !== null ? \App\Support\Quantity::format($item->physical_quantity) : 'Belum diisi' }}</span>
                                        @endcan
                                    </td>
                                    <td class="p-4 font-bold tabular-nums {{ $difference === null ? 'text-slate-400' : ($difference < 0 ? 'text-rose-700' : ($difference > 0 ? 'text-emerald-700' : 'text-slate-700')) }}">
                                        {{ $difference === null ? 'Belum diisi' : ($difference > 0 ? '+' : '').\App\Support\Quantity::format($difference) }}
                                    </td>
                                    <td class="p-4">
                                        @can('operate-inventory')
                                        <input class="field w-72 text-xs" name="items[{{ $item->id }}][reason]" value="{{ old('items.'.$item->id.'.reason', $item->reason) }}" placeholder="Penyebab selisih jika ada" maxlength="1000" @disabled($selectedSession->status === 'finalized')>
                                        @else
                                        <span>{{ $item->reason ?: '-' }}</span>
                                        @endcan
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="p-8 text-center text-slate-500 font-medium">Belum ada stok untuk snapshot sesi ini.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if($selectedSession->status === 'draft' && auth()->user()->can('operate-inventory'))
                    <div class="flex flex-wrap justify-end gap-2 border-t border-slate-100 p-4">
                        @can('operate-inventory')
                            <button class="btn-secondary text-xs">Simpan Isian</button>
                        @endcan
                    </div>
                @endif
            @can('operate-inventory')
            </form>
            @else
            </div>
            @endcan
            @if($selectedSession->status === 'draft')
                @can('operate-inventory')
                    <form method="POST" action="{{ route('opname.recount', $selectedSession) }}" class="flex justify-end border-t border-slate-100 p-4" data-confirm-action data-confirm-title="Mulai hitung ulang?" data-confirm-text="Snapshot akan mengikuti stok saat ini dan semua isian fisik dikosongkan. Isian sebelumnya tetap tercatat di audit log. Hitung ulang seluruh batch sebelum finalisasi." data-confirm-button="Ya, mulai hitung ulang">
                        @csrf
                        <button class="btn-secondary text-xs">Mulai Hitung Ulang</button>
                    </form>
                @endcan
                @can('finalize-opname')
                    <form method="POST" action="{{ route('opname.finalize', $selectedSession) }}" class="flex justify-end border-t border-slate-100 p-4" data-confirm-action data-confirm-title="Finalisasi stock opname?" data-confirm-text="Selisih akan diposting menjadi mutasi penyesuaian dan sesi tidak bisa diedit lagi." data-confirm-button="Ya, finalisasi">
                        @csrf
                        <button class="btn text-xs">Finalisasi Kepala Apoteker</button>
                    </form>
                @endcan
            @endif
        </section>
    @endif

    <section>
        <h2 class="mb-3 text-sm font-bold text-slate-900">Rekap Pemeriksaan Harian Disetujui</h2>
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
                                <td class="p-4"><span class="inline-flex items-center gap-1.5 rounded-lg bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-700">{{ $row->location }}</span></td>
                                <td class="p-4"><b class="font-bold text-slate-900 tracking-tight">{{ $row->name }}</b><br><span class="inline-flex rounded-md bg-slate-100 px-1.5 py-0.5 font-mono text-[11px] font-semibold text-slate-500 mt-0.5">{{ $row->code }}</span></td>
                                <td class="p-4 font-bold tabular-nums text-slate-900">{{ \App\Support\Quantity::format($row->quantity) }} {{ $row->unit }}</td>
                                <td class="p-4"><span class="inline-flex items-center rounded-md px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider {{ $row->quantity===0 ? 'bg-rose-100 text-rose-800 border border-rose-300' : ($row->is_low ? 'bg-amber-100 text-amber-800 border border-amber-300' : 'bg-[#edf5fb] text-[#266194] border border-[#b2d4ee]') }}">{{ $row->quantity===0 ? 'Kosong' : ($row->is_low ? 'Menipis' : 'Cukup') }}</span></td>
                                <td class="p-4 text-right"><a class="inline-flex items-center rounded-lg px-2.5 py-1 text-xs font-bold text-[#3277B2] hover:bg-[#edf5fb] transition" href="{{ route('nightly.show', $row->check_id) }}">Lihat sumber →</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="p-8 text-center text-slate-500 font-medium">Belum ada pemeriksaan yang disetujui pada {{ $periodLabel }}.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="mt-4">{{ $rows->links() }}</div>
    </section>
</x-layouts.app>
