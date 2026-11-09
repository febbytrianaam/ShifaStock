<x-layouts.app title="Stock Opname Periodik">
    @php
        $outline = 'inline-flex min-h-11 items-center justify-center whitespace-nowrap rounded-lg border bg-white px-3 text-xs font-bold lg:min-h-[30px] lg:px-2.5';
        $draftSessions = $sessions->where('status', '!=', 'finalized')->count();
        $sessionItems = $selectedSession?->items ?? collect();
        $filledItems = $sessionItems->whereNotNull('physical_quantity')->count();
        $sessionStat = match (true) {
            $selectedSession === null => ['value' => '–', 'theme' => 'muted', 'sub' => 'Belum ada sesi bulanan dipilih'],
            $selectedSession->status === 'finalized' => ['value' => $filledItems.' / '.$sessionItems->count(), 'theme' => 'green', 'sub' => 'Sesi '.$selectedSession->location->name.' sudah difinalisasi'],
            default => ['value' => $filledItems.' / '.$sessionItems->count(), 'theme' => $sessionItems->isNotEmpty() && $filledItems === $sessionItems->count() ? 'green' : 'amber', 'sub' => 'Sesi '.$selectedSession->location->name.' · '.($sessionItems->count() - $filledItems).' batch belum diisi'],
        };
        $hasSessionBlock = $sessions->isNotEmpty() || $selectedSession;
        // Below md each recap row stacks into a small grid so the link stays on screen; from md up it is a normal table.
        $stackRow = 'grid grid-cols-[auto_minmax(0,1fr)_auto] items-start gap-x-2 gap-y-1 border-t border-slate-100 px-4 py-3 first:border-t-0 md:table-row md:p-0 md:first:border-t';
        $cell = 'block md:table-cell md:px-4 md:py-2.5';
        $entryRow = 'grid grid-cols-[minmax(0,2fr)_minmax(0,3fr)] items-start gap-x-3 gap-y-2 border-t border-slate-100 px-4 py-3 first:border-t-0 md:table-row md:p-0 md:first:border-t';
    @endphp

    <div class="mb-4 flex flex-wrap items-center justify-between gap-2.5 [&>nav]:mb-0">
        <x-page-tabs :links="[
            ['route' => 'preview.nightly', 'label' => 'Harian', 'can' => 'view-nightly-checks', 'active' => ['preview.nightly', 'nightly.show']],
            ['route' => 'preview.opname', 'label' => 'Opname Periodik', 'can' => 'view-opname'],
            ['route' => 'nightly.history', 'label' => 'Riwayat', 'can' => 'view-nightly-history'],
        ]" />
        <form method="GET" class="flex flex-wrap items-center gap-2" data-required-alert="Lengkapi periode opname" novalidate>
            <label class="text-xs font-bold text-slate-600" for="opname-period">Periode</label>
            <input class="field w-auto min-h-11 py-1.5 lg:min-h-[34px] lg:py-1" id="opname-period" name="period" type="month" value="{{ $period }}" required>
            <button class="btn min-h-11 lg:min-h-[34px] lg:py-1">Tampilkan</button>
        </form>
    </div>

    @if(session('status'))
        <div class="callout mb-4 font-semibold" role="status">{{ session('status') }}</div>
    @endif
    @if($errors->any())
        <div class="callout-danger mb-4 font-semibold" role="alert">{{ $errors->first() }}</div>
    @endif

    <section aria-label="Ringkasan opname {{ $periodLabel }}" class="mb-4 grid grid-cols-2 gap-3 xl:grid-cols-3 xl:gap-4">
        <x-ui.stat label="Sesi bulanan" :value="$sessions->count()" unit="sesi" theme="brand" icon="box"
            :sub="$sessions->isEmpty() ? 'Belum ada sesi pada '.$periodLabel : $draftSessions.' draft · '.($sessions->count() - $draftSessions).' final · '.$periodLabel" />
        <x-ui.stat label="Isian fisik sesi" :value="$sessionStat['value']" :unit="$selectedSession ? 'batch' : null" :theme="$sessionStat['theme']" icon="edit" :sub="$sessionStat['sub']" />
        <x-ui.stat class="col-span-2 xl:col-span-1" label="Rekap harian disetujui" :value="$rows->total()" unit="hasil hitung" theme="teal" icon="check" sub="Dari pemeriksaan harian yang disetujui" />
    </section>

    <div class="grid items-start gap-4 xl:grid-cols-[minmax(0,1fr)_320px] 2xl:grid-cols-[minmax(0,1fr)_360px]">
        @if($hasSessionBlock)
        <div class="flex min-w-0 flex-col gap-3 xl:col-start-1 xl:row-start-1">
            @if($sessions->isNotEmpty())
                <nav aria-label="Pilih sesi opname" class="flex flex-wrap gap-1.5">
                    @foreach($sessions as $session)
                        @php($isSelected = $selectedSession?->id === $session->id)
                        <a href="{{ route('preview.opname', ['period' => $period, 'session' => $session->id]) }}" @class(['pill', 'pill-active' => $isSelected]) @if($isSelected) aria-current="true" @endif>
                            <span @class(['h-2 w-2 shrink-0 rounded-full', 'bg-emerald-500' => $session->status === 'finalized', 'bg-amber-500' => $session->status !== 'finalized'])></span>
                            {{ $session->location->name }} · {{ $session->status === 'finalized' ? 'Final' : 'Draft' }}
                        </a>
                    @endforeach
                </nav>
            @endif

            @if($selectedSession)
                @php($sessionVersion = app(\App\Services\OpnameSnapshotService::class)->version($selectedSession))
                @php($useOldCountInput = ! $errors->has('version'))
                <x-ui.section :title="'Sesi '.$selectedSession->reference.' · '.$selectedSession->location->name" heading-id="judul-sesi-opname" icon="box" tone="violet" flush
                    :subtitle="'Cut-off '.$selectedSession->cutoff_at->format('d/m/Y H:i').' · Status '.($selectedSession->status === 'finalized' ? 'Final' : 'Draft')">
                    @if($selectedSession->status === 'finalized')
                        <x-slot:actions><span class="badge badge-ok">Difinalisasi {{ $selectedSession->finalized_at?->format('d/m/Y H:i') }}</span></x-slot:actions>
                    @endif

                    @can('operate-inventory')
                    <form method="POST" action="{{ route('opname.save', $selectedSession) }}" data-required-alert="Periksa isian stock opname" novalidate>
                        @csrf
                        <input type="hidden" name="version" value="{{ $sessionVersion }}">
                    @else
                    <div>
                    @endcan
                        <div class="overflow-x-auto">
                            <table class="block w-full text-left text-[13px] md:table md:table-fixed">
                                <thead class="table-head hidden md:table-header-group">
                                    <tr>
                                        <th class="px-4 py-2.5">Obat / Batch</th>
                                        <th class="w-[84px] px-4 py-2.5">Stok Sistem</th>
                                        <th class="w-[112px] px-4 py-2.5">Fisik</th>
                                        <th class="w-[84px] px-4 py-2.5">Selisih</th>
                                        <th class="w-[28%] px-4 py-2.5">Alasan</th>
                                    </tr>
                                </thead>
                                <tbody class="block md:table-row-group">
                                    @forelse($selectedSession->items as $item)
                                        @php($difference = $item->physical_quantity === null ? null : (float)$item->physical_quantity - (float)$item->system_quantity)
                                        <tr class="{{ $entryRow }}">
                                            <td class="{{ $cell }} col-span-2 row-start-1 min-w-0 [overflow-wrap:anywhere]">
                                                <span class="font-extrabold text-slate-900">{{ $item->medicine->name }}</span>
                                                <span class="mt-1 flex flex-wrap items-center gap-1.5 text-xs text-slate-500"><span class="rounded bg-slate-100 px-1.5 font-mono text-[11px] font-semibold text-slate-600">{{ $item->batch?->number ?? 'Tanpa batch' }}</span>{{ $item->unit_name }}</span>
                                            </td>
                                            <td class="{{ $cell }} col-start-1 row-start-2 whitespace-nowrap font-extrabold tabular-nums text-slate-900"><span class="mr-1 text-xs font-bold text-slate-500 md:hidden">Sistem</span>{{ \App\Support\Quantity::format((float)$item->system_quantity) }}</td>
                                            <td class="{{ $cell }} col-start-1 row-start-3">
                                                <span class="mb-1 block text-xs font-bold text-slate-500 md:hidden" aria-hidden="true">Fisik</span>
                                                @can('operate-inventory')
                                                <input class="field w-full tabular-nums lg:py-1.5" name="items[{{ $item->id }}][physical_quantity]" type="number" min="0" step="0.0001" value="{{ $useOldCountInput ? old('items.'.$item->id.'.physical_quantity', \App\Support\Quantity::input($item->physical_quantity)) : \App\Support\Quantity::input($item->physical_quantity) }}" @disabled($selectedSession->status === 'finalized')>
                                                @else
                                                <span class="tabular-nums">{{ $item->physical_quantity !== null ? \App\Support\Quantity::format($item->physical_quantity) : 'Belum diisi' }}</span>
                                                @endcan
                                            </td>
                                            <td class="{{ $cell }} col-start-2 row-start-2 whitespace-nowrap font-extrabold tabular-nums {{ $difference === null ? 'text-slate-400' : ($difference < 0 ? 'text-rose-700' : ($difference > 0 ? 'text-emerald-700' : 'text-slate-700')) }}">
                                                <span class="mr-1 text-xs font-bold text-slate-500 md:hidden">Selisih</span>{{ $difference === null ? 'Belum diisi' : ($difference > 0 ? '+' : '').\App\Support\Quantity::format($difference) }}
                                            </td>
                                            <td class="{{ $cell }} col-start-2 row-start-3">
                                                <span class="mb-1 block text-xs font-bold text-slate-500 md:hidden" aria-hidden="true">Alasan</span>
                                                @can('operate-inventory')
                                                <input class="field w-full lg:py-1.5" name="items[{{ $item->id }}][reason]" value="{{ $useOldCountInput ? old('items.'.$item->id.'.reason', $item->reason) : $item->reason }}" placeholder="Penyebab selisih jika ada" maxlength="1000" @disabled($selectedSession->status === 'finalized')>
                                                @else
                                                <span>{{ $item->reason ?: '-' }}</span>
                                                @endcan
                                            </td>
                                        </tr>
                                    @empty
                                        <tr class="block md:table-row"><td colspan="5" class="empty-state block md:table-cell">Belum ada stok untuk snapshot sesi ini.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                        @if($selectedSession->status === 'draft' && auth()->user()->can('operate-inventory'))
                            <div class="flex flex-wrap items-center justify-between gap-2 border-t border-slate-100 px-4 py-3">
                                <p class="text-xs text-slate-500">Simpan isian sebelum finalisasi.</p>
                                @can('operate-inventory')
                                    <button class="btn-secondary min-h-11 lg:min-h-9">Simpan Isian</button>
                                @endcan
                            </div>
                        @endif
                    @can('operate-inventory')
                    </form>
                    @else
                    </div>
                    @endcan
                    @if($selectedSession->status === 'draft')
                        @if(auth()->user()->can('operate-inventory') || auth()->user()->can('finalize-opname'))
                        <div class="flex flex-wrap items-center justify-end gap-2 border-t border-slate-100 bg-slate-50/60 px-4 py-3">
                            @can('operate-inventory')
                                <form method="POST" action="{{ route('opname.recount', $selectedSession) }}" data-confirm-action data-confirm-title="Mulai hitung ulang?" data-confirm-text="Snapshot akan mengikuti stok saat ini dan semua isian fisik dikosongkan. Isian sebelumnya tetap tercatat di audit log. Hitung ulang seluruh batch sebelum finalisasi." data-confirm-button="Ya, mulai hitung ulang">
                                    @csrf
                                    <input type="hidden" name="version" value="{{ $sessionVersion }}">
                                    <button class="btn-secondary min-h-11 lg:min-h-9">Mulai Hitung Ulang</button>
                                </form>
                            @endcan
                            @can('finalize-opname')
                                <form method="POST" action="{{ route('opname.finalize', $selectedSession) }}" data-confirm-action data-confirm-title="Finalisasi stock opname?" data-confirm-text="Selisih akan diposting menjadi mutasi penyesuaian dan sesi tidak bisa diedit lagi." data-confirm-button="Ya, finalisasi">
                                    @csrf
                                    <input type="hidden" name="version" value="{{ $sessionVersion }}">
                                    <button class="btn min-h-11 lg:min-h-9">Finalisasi Kepala Apoteker</button>
                                </form>
                            @endcan
                        </div>
                        @endif
                    @endif
                </x-ui.section>
            @endif
        </div>
        @endif

        <aside aria-label="Sesi bulanan" class="flex min-w-0 flex-col gap-4 xl:col-start-2 xl:row-start-1">
            @can('operate-inventory')
            <x-ui.section title="Buat sesi bulanan" subtitle="Snapshot stok satu lokasi pada batas waktu" icon="plus">
                <form method="POST" action="{{ route('opname.store') }}" class="grid gap-3 sm:grid-cols-2 xl:grid-cols-1" data-required-alert="Lengkapi sesi stock opname" novalidate>
                    @csrf
                    <div class="min-w-0">
                        <label class="label" for="session-period">Periode</label>
                        <input class="field" id="session-period" name="period" type="month" value="{{ old('period', $period) }}" required>
                    </div>
                    <div class="min-w-0">
                        <label class="label" for="session-location">Lokasi</label>
                        <select class="field" id="session-location" name="location_id" required>
                            @foreach($locations as $location)
                                <option value="{{ $location->id }}" @selected(old('location_id')==$location->id)>{{ $location->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="min-w-0">
                        <label class="label" for="session-cutoff">Batas waktu pencatatan stok</label>
                        <input class="field" id="session-cutoff" name="cutoff_at" type="datetime-local" value="{{ old('cutoff_at',now('Asia/Jakarta')->format('Y-m-d\\TH:i')) }}" required>
                    </div>
                    <div class="min-w-0">
                        <label class="label" for="session-notes">Catatan</label>
                        <input class="field" id="session-notes" name="notes" value="{{ old('notes') }}" placeholder="Catatan sesi opname" maxlength="1000">
                    </div>
                    <div class="sm:col-span-2 xl:col-span-1">
                        <button class="btn min-h-11 w-full lg:min-h-9">Buat Sesi Bulanan</button>
                    </div>
                </form>
            </x-ui.section>
            @endcan
            <x-ui.section title="Alur opname bulanan" icon="info" tone="teal">
                <ul class="flex list-disc flex-col gap-1.5 pl-4 text-xs leading-relaxed text-slate-600 marker:text-slate-400">
                    <li>Opname bulanan membandingkan hitungan fisik dengan stok saat snapshot dibuat.</li>
                    <li>Simpan isian sebelum finalisasi.</li>
                    <li>Jika ada transaksi atau perubahan satuan setelah snapshot, pilih Mulai Hitung Ulang dan periksa kembali seluruh stok.</li>
                    <li>Rekap harian terpisah dari isian sesi bulanan.</li>
                </ul>
            </x-ui.section>
        </aside>

        {{-- Under a session the recap takes the full width, so its six columns fit on a 1280-1440 px laptop. --}}
        <x-ui.section @class(['xl:col-start-1', 'xl:col-span-2 xl:row-start-2' => $hasSessionBlock, 'xl:row-start-1' => ! $hasSessionBlock]) title="Rekap Pemeriksaan Harian Disetujui" heading-id="judul-rekap-harian" icon="report" tone="teal" flush
            :subtitle="'Hasil hitung fisik dari pemeriksaan harian yang disetujui pada '.$periodLabel.'.'" :meta="$rows->total().' hasil'">
            <div class="overflow-x-auto">
                <table class="block w-full text-left text-[13px] md:table">
                    <thead class="table-head hidden md:table-header-group">
                        <tr>
                            <th class="px-4 py-2.5">Tanggal</th>
                            <th class="px-4 py-2.5">Lokasi</th>
                            <th class="px-4 py-2.5">Obat</th>
                            <th class="px-4 py-2.5">Hasil Fisik</th>
                            <th class="px-4 py-2.5">Kondisi</th>
                            <th class="px-4 py-2.5 text-right">Pemeriksaan</th>
                        </tr>
                    </thead>
                    <tbody class="block md:table-row-group">
                        @forelse($rows as $row)
                            <tr class="{{ $stackRow }}">
                                <td class="{{ $cell }} col-start-1 row-start-2 whitespace-nowrap text-xs font-semibold tabular-nums text-slate-600 md:text-[13px] md:text-slate-700">{{ \Carbon\Carbon::parse($row->date)->locale('id')->translatedFormat('j M Y') }}</td>
                                <td class="{{ $cell }} col-start-2 row-start-2 whitespace-nowrap text-xs font-semibold text-slate-600 md:text-[13px] md:text-slate-700"><span class="md:hidden">· </span>{{ $row->location }}</td>
                                <td class="{{ $cell }} col-span-2 col-start-1 row-start-1 min-w-0 md:min-w-44">
                                    <span class="font-extrabold text-slate-900">{{ $row->name }}</span>
                                    <span class="mt-1 block"><span class="rounded bg-slate-100 px-1.5 font-mono text-[11px] font-semibold text-slate-600">{{ $row->code }}</span></span>
                                </td>
                                <td class="{{ $cell }} col-start-3 row-start-1 whitespace-nowrap text-right font-extrabold tabular-nums text-slate-900 md:text-left">{{ \App\Support\Quantity::format($row->quantity) }} <span class="font-semibold text-slate-500">{{ $row->unit }}</span></td>
                                <td class="{{ $cell }} col-start-3 row-start-2 whitespace-nowrap text-right md:text-left"><span class="badge {{ $row->quantity===0 ? 'badge-danger' : ($row->is_low ? 'badge-warn' : 'badge-ok') }}">{{ $row->quantity===0 ? 'Kosong' : ($row->is_low ? 'Menipis' : 'Cukup') }}</span></td>
                                <td class="{{ $cell }} col-span-3 row-start-3 pt-1 md:pt-2.5 md:text-right"><a class="{{ $outline }} border-brand-200 text-brand-800 hover:bg-brand-50" href="{{ route('nightly.show', $row->check_id) }}">Lihat sumber</a></td>
                            </tr>
                        @empty
                            <tr class="block md:table-row"><td colspan="6" class="empty-state block md:table-cell">Belum ada pemeriksaan yang disetujui pada {{ $periodLabel }}.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($rows->hasPages())
                <div class="border-t border-slate-100 px-4 py-3">{{ $rows->links() }}</div>
            @endif
        </x-ui.section>
    </div>
</x-layouts.app>
