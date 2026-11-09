<x-layouts.app title="Monitoring Kedaluwarsa">
    @php
        $activeStatus = $filters['status'] ?? 'all';
        $activeLocation = $filters['location_id'] ?? null;
        $keptFilters = array_filter(['search' => $filters['search'] ?? null, 'location_id' => $activeLocation, 'from' => $filters['from'] ?? null, 'to' => $filters['to'] ?? null], fn ($value) => filled($value));
        $statusUrl = fn (string $status) => route('preview.expiry', $keptFilters + ['status' => $activeStatus === $status ? 'all' : $status]);
        $locationUrl = fn ($id) => route('preview.expiry', array_filter(['location_id' => $id] + $keptFilters + ['status' => $activeStatus], fn ($value) => filled($value) && $value !== 'all'));
        $filtered = $keptFilters !== [] || $activeStatus !== 'all';
        $outline = 'inline-flex min-h-11 items-center justify-center whitespace-nowrap rounded-lg border px-3 text-xs font-bold lg:min-h-[28px] lg:px-2.5';
        $control = 'field w-auto min-h-11 py-1.5 lg:min-h-[32px] lg:py-1';
        // Batches outside the warning period keep their actions as quiet text links so the list stays calm.
        $quiet = 'inline-flex min-h-11 items-center whitespace-nowrap px-1.5 text-xs font-bold text-brand-700 hover:underline lg:min-h-[28px]';
        // Below md each row stacks into a small grid so the actions stay on screen; from md up it is a normal table.
        $stackRow = 'grid grid-cols-[minmax(0,1fr)_auto] items-start gap-x-3 gap-y-1.5 border-t border-slate-100 px-4 py-3 first:border-t-0 md:table-row md:p-0 md:first:border-t';
        $cell = 'block md:table-cell md:px-4 md:py-2.5 md:align-top';
        // The same three views as Monitoring Stok, plus this page: every stocked batch, including the safe ones.
        $views = auth()->user()->can('view-shortages') ? [
            ['label' => 'Stok minimum', 'href' => route('shortages.index'), 'active' => false],
            ['label' => 'Kedaluwarsa', 'href' => route('shortages.index', ['tab' => 'ed']), 'active' => false],
            ['label' => 'Hasil pemeriksaan', 'href' => route('shortages.index', ['tab' => 'hitung']), 'active' => false],
        ] : [];
        $views[] = ['label' => 'Semua batch', 'href' => route('preview.expiry'), 'active' => true, 'count' => $counts['expired'] + $counts['near'] + $counts['safe']];
    @endphp

    @if($errors->any())
        <div class="callout-danger mb-4 font-semibold" role="alert">{{ $errors->first() }}</div>
    @endif

    <section aria-label="Ringkasan kedaluwarsa" class="grid grid-cols-2 gap-3 xl:grid-cols-4 xl:gap-4">
        <x-ui.stat label="Lewat ED" :value="$counts['expired']" unit="batch" icon="clock" :theme="$counts['expired'] > 0 ? 'rose' : 'green'"
            :sub="$counts['expired'] > 0 ? 'Sudah melewati tanggal batas' : 'Tidak ada batch lewat ED'" :href="$statusUrl('expired')" :active="$activeStatus === 'expired'" data-expiry-kpi="expired" />
        <x-ui.stat :label="'ED ≤ '.$warningMonths.' bulan'" :value="$counts['near']" unit="batch" icon="calendar" :theme="$counts['near'] > 0 ? 'violet' : 'green'"
            sub="Perlu prioritas penggunaan (FEFO)" :href="$statusUrl('near')" :active="$activeStatus === 'near'" data-expiry-kpi="near" />
        <x-ui.stat :label="'ED > '.$warningMonths.' bulan'" :value="$counts['safe']" unit="batch" icon="ok" theme="green"
            sub="Masa simpan masih aman" :href="$statusUrl('safe')" :active="$activeStatus === 'safe'" data-expiry-kpi="safe" />
        <x-ui.stat label="Batch berstok" :value="$counts['expired'] + $counts['near'] + $counts['safe']" unit="batch" icon="box" theme="neutral"
            :sub="$activeLocation ? 'Di lokasi yang dipilih' : 'Di semua lokasi'" :href="route('preview.expiry', $keptFilters)" :active="$activeStatus === 'all'" data-expiry-kpi="all" />
    </section>

    <div class="mb-3.5 mt-4 flex flex-wrap items-center justify-between gap-2.5">
        <x-ui.tabs :links="$views" label="Pilih tampilan monitoring" />
        <div class="flex w-full flex-wrap items-center gap-2 sm:w-auto">
            <form method="GET" role="search" aria-label="Cari batch" class="w-full sm:w-auto">
                @foreach(array_filter(['location_id' => $activeLocation, 'status' => $activeStatus === 'all' ? null : $activeStatus, 'from' => $filters['from'] ?? null, 'to' => $filters['to'] ?? null], fn ($value) => filled($value)) as $name => $value)
                    <input type="hidden" name="{{ $name }}" value="{{ $value }}">
                @endforeach
                <x-ui.search id="expiry-search" name="search" value="{{ $filters['search'] ?? '' }}" label="Cari obat atau batch" placeholder="Cari nama, kode, atau batch" />
            </form>
            <nav aria-label="Saring lokasi" class="flex flex-wrap gap-1">
                <a href="{{ $locationUrl(null) }}" @class(['pill', 'pill-active' => ! $activeLocation]) @if(! $activeLocation) aria-current="true" @endif>Semua</a>
                @foreach($locations as $location)
                    <a href="{{ $locationUrl($location->id) }}" @class(['pill', 'pill-active' => $activeLocation == $location->id]) @if($activeLocation == $location->id) aria-current="true" @endif>{{ $location->name }}</a>
                @endforeach
            </nav>
        </div>
    </div>

    <x-ui.section title="Batch berstok per tanggal kedaluwarsa" subtitle="Diurutkan dari ED terdekat. Tindak lanjut tersedia per lokasi stok." icon="calendar" tone="violet" flush :meta="$batches->total().' batch'">
        <form method="GET" class="flex flex-wrap items-center gap-x-2 gap-y-1.5 border-b border-slate-100 bg-slate-50/60 px-4 py-2.5" aria-label="Saring tanggal kedaluwarsa">
            @foreach(array_filter(['search' => $filters['search'] ?? null, 'location_id' => $activeLocation, 'status' => $activeStatus === 'all' ? null : $activeStatus], fn ($value) => filled($value)) as $name => $value)
                <input type="hidden" name="{{ $name }}" value="{{ $value }}">
            @endforeach
            <span class="text-xs font-bold text-slate-600">Tanggal ED</span>
            <label class="sr-only" for="expiry-from">ED dari</label>
            <input class="{{ $control }}" id="expiry-from" name="from" type="date" value="{{ $filters['from'] ?? '' }}">
            <span class="text-xs text-slate-500">s/d</span>
            <label class="sr-only" for="expiry-to">ED sampai</label>
            <input class="{{ $control }}" id="expiry-to" name="to" type="date" value="{{ $filters['to'] ?? '' }}">
            <button class="btn-secondary min-h-11 py-1 text-xs lg:min-h-[32px]">Terapkan</button>
            @if($filtered)
                <a class="link ml-auto inline-flex min-h-11 items-center text-xs lg:min-h-0" href="{{ route('preview.expiry') }}">Hapus semua saringan</a>
            @endif
        </form>
        <div class="overflow-x-auto">
            <table class="block w-full text-left text-[13px] md:table">
                <thead class="table-head hidden md:table-header-group">
                    <tr>
                        <th class="px-4 py-2.5">Obat</th>
                        <th class="px-4 py-2.5">Batch</th>
                        <th class="px-4 py-2.5">Kedaluwarsa</th>
                        <th class="px-4 py-2.5">Lokasi Stok</th>
                        <th class="px-4 py-2.5 text-right">Stok Saat Ini</th>
                    </tr>
                </thead>
                <tbody class="block md:table-row-group">
                    @forelse($batches as $batch)
                        @php
                            $days=now('Asia/Jakarta')->startOfDay()->diffInDays($batch->expiry_date,false);
                            $isNear = $batch->expiry_date->lte($warningCutoff);
                            $locations = $batch->balances
                                ->filter(fn($balance) => (float) $balance->quantity > 0)
                                ->sortBy(fn($balance) => $balance->location?->name)
                                ->values();
                            $plainAction = $isNear ? $outline.' border-slate-300 bg-white text-slate-700 hover:bg-slate-50' : $quiet;
                        @endphp
                        <tr @class([$stackRow, 'attention-critical' => $days < 0, 'attention-warning' => $days >= 0 && $isNear]) data-expiry-row="{{ $days < 0 ? 'expired' : ($isNear ? 'near' : 'safe') }}">
                            <td class="{{ $cell }} col-start-1 row-start-1 min-w-0 md:min-w-52">
                                <span class="font-extrabold text-slate-900">{{ $batch->medicine->name }}</span>
                                <span class="mt-1 block"><span class="rounded bg-slate-100 px-1.5 font-mono text-[11px] font-semibold text-slate-600">{{ $batch->medicine->code }}</span></span>
                            </td>
                            <td class="{{ $cell }} col-start-1 row-start-2 whitespace-nowrap font-mono text-xs font-semibold text-slate-700"><span class="font-sans font-bold text-slate-500 md:hidden">Batch </span>{{ $batch->number }}</td>
                            <td class="{{ $cell }} col-start-2 row-start-1 whitespace-nowrap text-right md:text-left">
                                <span class="block font-bold tabular-nums text-slate-900">{{ $batch->expiry_date->format('d/m/Y') }}</span>
                                <span class="badge mt-1 {{ $days<0 ? 'badge-danger' : ($isNear ? 'badge-warn' : 'badge-ok') }}">{{ $days<0 ? 'Kedaluwarsa' : ($isNear ? $days.' hari lagi' : 'Aman') }}</span>
                            </td>
                            <td class="{{ $cell }} col-span-2 row-start-3 pt-1 md:min-w-72 md:pt-2.5">
                                <div class="flex flex-col gap-1.5">
                                    @forelse($locations as $balance)
                                        <div class="flex flex-wrap items-center gap-1.5">
                                            <span class="inline-flex items-center gap-1.5 whitespace-nowrap rounded-md border border-slate-200 bg-white px-2 py-0.5 text-xs font-semibold text-slate-700">
                                                <span class="h-1.5 w-1.5 rounded-full {{ str_contains(strtolower($balance->location?->name ?? ''), 'gudang') ? 'bg-brand-600' : 'bg-slate-400' }}"></span>
                                                {{ $balance->location?->name ?? 'Lokasi tidak diketahui' }}
                                                <span class="tabular-nums text-slate-500">{{ \App\Support\Quantity::format((float)$balance->quantity) }}</span>
                                            </span>
                                            @can('operate-inventory')
                                                @if($balance->location?->is_active && $batch->medicine->is_active && $batch->medicine->unit_id && filled($batch->medicine->dosage_form))
                                                    @can('view-returns')
                                                        <a class="{{ $plainAction }}" href="{{ route('preview.returns', ['medicine_id' => $batch->medicine_id, 'batch_id' => $batch->id, 'source_location_id' => $balance->location_id]) }}">Retur dari {{ $balance->location->name }}</a>
                                                    @endcan
                                                    @can('view-special-adjustments')
                                                        <a class="{{ $days < 0 ? $outline.' border-rose-600 bg-rose-600 text-white hover:bg-rose-700' : $plainAction }}" href="{{ route('special-adjustments.index', ['medicine_id' => $batch->medicine_id, 'batch_id' => $batch->id, 'location_id' => $balance->location_id, 'condition' => $days < 0 ? 'expired' : 'lainnya']) }}">Catat Kondisi</a>
                                                    @endcan
                                                @endif
                                            @endcan
                                        </div>
                                    @empty
                                        <span class="text-xs text-slate-500">Belum ada stok lokasi</span>
                                    @endforelse
                                </div>
                            </td>
                            <td class="{{ $cell }} col-start-2 row-start-2 whitespace-nowrap text-right">
                                <span class="font-extrabold tabular-nums text-slate-900">{{ \App\Support\Quantity::format((float)$batch->balances->sum('quantity')) }}</span> <span class="font-semibold text-slate-500">{{ $batch->medicine->unit?->name }}</span>
                                <span class="block text-[11px] text-slate-500">batch ini</span>
                            </td>
                        </tr>
                    @empty
                        <tr class="block md:table-row">
                            <td colspan="5" class="empty-state block md:table-cell">Belum ada batch dari penerimaan obat.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($batches->hasPages())
            <div class="border-t border-slate-100 px-4 py-3">{{ $batches->links() }}</div>
        @endif
    </x-ui.section>
</x-layouts.app>
