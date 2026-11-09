@php
    $user = auth()->user();
    $tone = [
        'kosong' => ['chip' => 'border-rose-200 bg-rose-50 text-rose-800', 'qty' => 'text-rose-700', 'bar' => 'bg-rose-600'],
        'menipis' => ['chip' => 'border-amber-200 bg-amber-50 text-amber-800', 'qty' => 'text-amber-700', 'bar' => 'bg-amber-500'],
        'aman' => ['chip' => 'border-emerald-200 bg-emerald-50 text-emerald-700', 'qty' => 'text-slate-900', 'bar' => 'bg-emerald-500'],
        'none' => ['chip' => 'border-slate-200 bg-slate-100 text-slate-600', 'qty' => 'text-slate-900', 'bar' => 'bg-slate-400'],
    ];
    $kpiTheme = [
        'rose' => ['card' => 'border-rose-200 bg-rose-50/70', 'active' => 'border-rose-500 ring-2 ring-rose-500/40', 'icon' => 'bg-rose-600 text-white', 'value' => 'text-rose-700'],
        'amber' => ['card' => 'border-amber-200 bg-amber-50/70', 'active' => 'border-amber-500 ring-2 ring-amber-500/40', 'icon' => 'bg-amber-500 text-amber-950', 'value' => 'text-amber-700'],
        'violet' => ['card' => 'border-violet-200 bg-violet-50/60', 'active' => 'border-violet-500 ring-2 ring-violet-500/40', 'icon' => 'bg-violet-600 text-white', 'value' => 'text-violet-700'],
        'green' => ['card' => 'border-emerald-200 bg-emerald-50/60', 'active' => 'border-emerald-500 ring-2 ring-emerald-500/40', 'icon' => 'bg-emerald-700 text-white', 'value' => 'text-emerald-700'],
        'slate' => ['card' => 'border-slate-200 bg-white', 'active' => 'border-slate-500 ring-2 ring-slate-400/40', 'icon' => 'bg-slate-500 text-white', 'value' => 'text-slate-500'],
    ];
    $bucketMeta = [
        'expired' => ['label' => 'Sudah lewat ED', 'dot' => 'bg-rose-600', 'chip' => 'border-rose-200 bg-rose-50 text-rose-800'],
        'd30' => ['label' => '≤ 30 hari', 'dot' => 'bg-amber-500', 'chip' => 'border-amber-200 bg-amber-50 text-amber-800'],
        'd60' => ['label' => '31–60 hari', 'dot' => 'bg-yellow-400', 'chip' => 'border-yellow-200 bg-yellow-50 text-yellow-800'],
        'later' => ['label' => '61 hari – '.$expiryWarningMonths.' bulan', 'dot' => 'bg-slate-400', 'chip' => 'border-slate-200 bg-slate-100 text-slate-700'],
    ];
    $kpis = [
        [
            'filter' => 'kosong', 'label' => 'Stok kosong', 'unit' => 'item', 'icon' => 'M12 9v4m0 4h.01M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z',
            'theme' => ! $hasParameters ? 'slate' : ($stats['kosong'] > 0 ? 'rose' : 'green'), 'value' => $hasParameters ? $stats['kosong'] : '–',
            'sub' => ! $hasParameters ? 'Batas minimum belum diatur' : ($stats['kosongSplit'] ?: 'Tidak ada stok kosong'),
        ],
        [
            'filter' => 'menipis', 'label' => 'Menipis', 'unit' => 'item', 'icon' => 'M22 17l-8.5-8.5-5 5L2 7M16 17h6v-6',
            'theme' => ! $hasParameters ? 'slate' : ($stats['menipis'] > 0 ? 'amber' : 'green'), 'value' => $hasParameters ? $stats['menipis'] : '–',
            'sub' => ! $hasParameters ? 'Atur di Parameter Monitoring' : ($stats['menipisSplit'] ?: 'Tidak ada stok menipis'),
        ],
    ];
    if ($canExpiry) {
        $kpis[] = [
            'filter' => 'expired', 'label' => 'Lewat ED', 'unit' => 'batch', 'icon' => 'M12 7v5l3 2M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0z',
            'theme' => $stats['expired'] > 0 ? 'rose' : 'green', 'value' => $stats['expired'], 'sub' => $stats['expiredFirst'] ?? 'Tidak ada batch lewat ED',
        ];
        $kpis[] = [
            'filter' => 'near', 'label' => 'ED ≤ '.$expiryWarningMonths.' bulan', 'unit' => 'batch', 'icon' => 'M8 2v4M16 2v4M3 10h18M5 4h14a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2z',
            'theme' => $stats['near'] > 0 ? 'violet' : 'green', 'value' => $stats['near'],
            'sub' => $stats['nearestDays'] === null ? 'Tidak ada batch mendekati ED' : 'Terdekat '.($stats['nearestDays'] === 0 ? 'hari ini' : $stats['nearestDays'].' hari lagi'),
        ];
    }
    $tabs = array_filter(['minimum' => 'Stok minimum', 'ed' => $canExpiry ? 'Kedaluwarsa' : null, 'hitung' => 'Hasil pemeriksaan']);
    $panel = [
        'minimum' => ['Obat di bawah batas minimum', 'Satu baris per obat; stok tiap lokasi berdampingan agar keputusan transfer cepat.', $resultCount.' obat'],
        'ed' => ['Batch kedaluwarsa dan mendekati ED', 'Batch berstok, diurutkan dari yang sudah lewat atau paling dekat ED.', $expiryBatchCount.' batch'],
        'hitung' => ['Hasil pemeriksaan & permintaan', 'Dari hitung fisik terakhir yang disetujui untuk masing-masing obat di setiap lokasi.', $resultCount.' obat'],
    ][$activeTab];
    $cols = count($locations);
    // Minimums sized so the list still fits beside the side panel on a 1280-1440 px laptop; wider screens share the extra space.
    $grid = 'lg:grid-cols-[minmax(170px,1.6fr)_repeat(var(--monitor-cols),minmax(112px,1fr))_minmax(150px,1.2fr)]';
    $filtered = trim($search) !== '' || $locationId !== null || $filter !== null;
    $card = 'rounded-2xl border border-slate-200 bg-white p-4';
    $outline = 'inline-flex min-h-11 items-center justify-center rounded-lg border px-3 text-xs font-bold lg:min-h-[30px] lg:px-2.5';
    $parameterUrl = $user->can('view-monitoring-parameters') ? route('monitoring-parameters.index') : null;
@endphp

<x-slot:headerActions>
    @if($parameterUrl)<a href="{{ $parameterUrl }}" class="btn-secondary py-1.5 text-[13px]">Parameter Monitoring</a>@endif
</x-slot:headerActions>

<div>
    <p class="mb-3.5 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-slate-500">
        <span>Stok sistem dibandingkan batas minimum per lokasi dan tanggal kedaluwarsa batch · <b class="font-bold text-slate-700">data per {{ $asOf->format('H.i') }} WIB</b></span>
        @if($parameterUrl)<a href="{{ $parameterUrl }}" class="font-bold text-brand-700 hover:underline xl:hidden">Parameter Monitoring →</a>@endif
    </p>

    <section aria-label="Ringkasan monitoring" class="grid grid-cols-2 gap-3 xl:gap-4 {{ count($kpis) > 2 ? 'xl:grid-cols-4' : '' }}">
        @foreach($kpis as $kpi)
            @php($theme = $kpiTheme[$kpi['theme']])
            <button type="button" wire:click="toggleFilter('{{ $kpi['filter'] }}')" aria-pressed="{{ $filter === $kpi['filter'] ? 'true' : 'false' }}" data-monitor-kpi="{{ $kpi['filter'] }}"
                class="flex min-w-0 flex-col items-start rounded-2xl border p-3 text-left transition-shadow hover:shadow-md sm:px-4 sm:py-3.5 {{ $filter === $kpi['filter'] ? $theme['active'] : '' }} {{ $theme['card'] }}">
                <span class="flex min-w-0 items-center gap-2">
                    <span class="grid h-7 w-7 shrink-0 place-items-center rounded-lg {{ $theme['icon'] }}">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="{{ $kpi['icon'] }}" /></svg>
                    </span>
                    <span class="text-[13px] font-extrabold text-slate-700">{{ $kpi['label'] }}</span>
                </span>
                <span class="mt-2.5 flex items-baseline gap-1.5">
                    <span class="text-[26px] font-extrabold leading-none tabular-nums sm:text-[30px] {{ $theme['value'] }}" data-kpi-value>{{ $kpi['value'] }}</span>
                    <span class="text-xs font-semibold text-slate-500">{{ $kpi['unit'] }}</span>
                </span>
                <span class="mt-1.5 text-xs text-slate-600">{{ $kpi['sub'] }}</span>
            </button>
        @endforeach
    </section>

    <div class="mt-4 flex flex-wrap items-center justify-between gap-2.5">
        <div role="group" aria-label="Pilih tampilan" class="flex flex-wrap gap-1 rounded-xl bg-slate-200/80 p-1">
            @foreach($tabs as $key => $label)
                <button type="button" wire:click="setTab('{{ $key }}')" aria-pressed="{{ $activeTab === $key ? 'true' : 'false' }}" data-monitor-tab="{{ $key }}"
                    class="inline-flex min-h-10 items-center gap-2 rounded-lg px-3 text-[13px] font-bold lg:min-h-8 {{ $activeTab === $key ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-600 hover:text-slate-900' }}">
                    {{ $label }}
                    <span class="inline-grid min-w-5 place-items-center rounded-full px-1.5 text-[11px] font-extrabold leading-[18px] {{ $activeTab === $key ? 'bg-brand-100 text-brand-800' : 'bg-slate-300/80 text-slate-700' }}">{{ $tabCounts[$key] }}</span>
                </button>
            @endforeach
        </div>
        <div class="flex w-full flex-wrap items-center gap-2 sm:w-auto">
            <label class="relative flex min-h-11 w-full items-center gap-2 rounded-full border border-slate-300 bg-white px-3 sm:w-64 lg:min-h-[34px]">
                <svg class="h-[15px] w-[15px] shrink-0 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="m21 21-5.2-5.2" /><path d="M10.5 18a7.5 7.5 0 1 0 0-15 7.5 7.5 0 0 0 0 15Z" /></svg>
                <span class="sr-only">Cari obat</span>
                <input type="search" wire:model.live.debounce.300ms="search" maxlength="255" placeholder="Cari nama atau kode obat" data-monitor-search
                    class="w-full min-w-0 border-0 bg-transparent p-0 text-[13px] text-slate-900 outline-none focus:ring-0">
            </label>
            <div role="group" aria-label="Saring lokasi" class="flex flex-wrap gap-1">
                @foreach([['id' => null, 'name' => 'Semua'], ...$locations] as $location)
                    <button type="button" wire:click="setLocation({{ $location['id'] ?? 'null' }})" aria-pressed="{{ $locationId === $location['id'] ? 'true' : 'false' }}" data-monitor-location="{{ $location['id'] ?? 'semua' }}"
                        class="inline-flex min-h-11 items-center rounded-full border px-3 text-xs font-bold lg:min-h-[34px] {{ $locationId === $location['id'] ? 'border-brand-800 bg-brand-800 text-white' : 'border-slate-300 bg-white text-slate-700 hover:bg-slate-50' }}">{{ $location['name'] }}</button>
                @endforeach
            </div>
        </div>
    </div>

    <div class="mt-3.5 grid items-start gap-4 xl:grid-cols-[minmax(0,1fr)_320px] 2xl:grid-cols-[minmax(0,1fr)_360px]">
        <section aria-labelledby="judul-monitor" @if($activeTab === 'minimum') id="stok-minimum" @endif class="min-w-0 scroll-mt-24 overflow-hidden rounded-2xl border border-slate-200 bg-white" data-monitor-panel="{{ $activeTab }}">
            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 px-4 py-3.5">
                <div>
                    <h2 id="judul-monitor" class="text-base font-extrabold text-slate-900">{{ $panel[0] }}</h2>
                    <p class="mt-0.5 text-xs text-slate-500">{{ $panel[1] }}</p>
                </div>
                <span class="text-xs text-slate-500" data-monitor-count>{{ $panel[2] }}</span>
            </div>

            @if($activeTab === 'minimum')
                @if($visibleMedicines)
                    <div class="hidden gap-x-4 bg-slate-50 px-4 py-2.5 text-[11px] font-bold uppercase tracking-wider text-slate-500 lg:grid {{ $grid }}" style="--monitor-cols: {{ $cols }}">
                        <span>Obat</span>
                        @foreach($locations as $location)<span>{{ $location['name'] }}</span>@endforeach
                        <span>Saran</span>
                    </div>
                @endif
                @foreach($visibleMedicines as $medicine)
                    @php($suggestion = $medicine['suggestion'])
                    <div wire:key="obat-{{ $medicine['medicine_id'] }}" data-monitor-row
                        class="grid gap-3 border-t border-slate-100 px-4 py-3 lg:items-center lg:py-2.5 lg:gap-x-4 {{ $grid }} {{ $medicine['worst'] === 'kosong' ? 'bg-rose-50/40' : 'bg-white' }}" style="--monitor-cols: {{ $cols }}">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                                <span class="inline-flex shrink-0 whitespace-nowrap rounded-md border px-2 text-[11px] font-bold leading-4 {{ $tone[$medicine['worst']]['chip'] }}">{{ $medicine['worst'] === 'kosong' ? 'Kosong' : 'Menipis' }}</span>
                                <span class="text-[13px] font-extrabold text-slate-900">{{ $medicine['name'] }}</span>
                            </div>
                            <span class="mt-1 inline-block rounded bg-slate-100 px-1.5 font-mono text-[11px] font-semibold text-slate-600">{{ $medicine['code'] }}</span>
                        </div>
                        @foreach($medicine['cells'] as $cell)
                            @php($scale = $cell['minimum'] > 0 ? min($cell['quantity'] / ($cell['minimum'] * 2), 1) * 100 : ($cell['quantity'] > 0 ? 100 : 0))
                            <div class="grid min-w-0 grid-cols-[72px_minmax(0,1fr)] items-center gap-2.5 lg:block" data-monitor-cell="{{ $cell['status'] }}">
                                <span class="text-xs font-bold text-slate-600 lg:hidden">{{ $cell['location'] }}</span>
                                <div class="min-w-0">
                                    <div class="text-[13px] tabular-nums">
                                        <b class="font-extrabold {{ $tone[$cell['status']]['qty'] }}">{{ \App\Support\Quantity::format($cell['quantity']) }}{{ $medicine['unit'] ? ' '.$medicine['unit'] : '' }}</b>
                                        <span class="text-slate-500">{{ $cell['minimum'] === null ? 'tanpa minimum' : '/ min '.\App\Support\Quantity::format($cell['minimum']) }}</span>
                                    </div>
                                    @if($cell['minimum'] !== null)
                                        <div class="relative mt-1.5 h-[7px] rounded-full bg-slate-200 lg:max-w-[150px]" aria-hidden="true">
                                            <div class="h-full rounded-full {{ $tone[$cell['status']]['bar'] }}" style="width: {{ round($scale, 1) }}%"></div>
                                            <span class="absolute left-1/2 top-[-3px] h-[13px] w-0.5 rounded-sm bg-slate-900"></span>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                        <div class="min-w-0">
                            @if($suggestion && $suggestion['url'])
                                <a href="{{ $suggestion['url'] }}" data-monitor-action="{{ $suggestion['type'] }}" class="{{ $outline }} w-full max-w-full text-center border-brand-600 bg-brand-600 text-white hover:bg-brand-700 lg:w-auto lg:border-brand-200 lg:bg-white lg:text-brand-800 lg:hover:bg-brand-50">{{ $suggestion['label'] }}</a>
                            @elseif($suggestion)
                                <span data-monitor-action="{{ $suggestion['type'] }}" class="inline-flex items-center gap-1.5 text-xs font-bold {{ $suggestion['type'] === 'order' ? 'text-amber-800' : 'text-brand-800' }}">
                                    @if($suggestion['type'] === 'order')<svg class="h-3.5 w-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 2h12l2 4v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6zM4 6h16M9 11h6M9 15h4" /></svg>@endif
                                    {{ $suggestion['label'] }}
                                </span>
                            @endif
                            @if($suggestion)<p class="mt-1 text-[11px] text-slate-500">{{ $suggestion['note'] }}</p>@endif
                        </div>
                    </div>
                @endforeach
                @if(! $visibleMedicines)
                    <div class="px-4 py-8 text-center text-[13px] text-slate-500" data-monitor-empty>
                        @if(! $hasParameters)
                            Batas minimum stok belum diatur.
                            @if($parameterUrl)<a href="{{ $parameterUrl }}" class="font-bold text-brand-700 hover:underline">Atur di Parameter Monitoring →</a>@endif
                        @elseif($filtered)
                            Tidak ada obat yang cocok dengan pencarian, lokasi, atau saringan ini.
                        @else
                            Semua stok di atas batas minimum.
                        @endif
                    </div>
                @endif
            @elseif($activeTab === 'ed')
                @foreach($visibleExpiries as $bucket => $rows)
                    <div class="flex items-center gap-2 border-t border-slate-100 bg-slate-50 px-4 py-2.5 text-xs font-extrabold text-slate-700 first:border-t-0">
                        <span class="h-2.5 w-2.5 shrink-0 rounded-sm {{ $bucketMeta[$bucket]['dot'] }}"></span>{{ $bucketMeta[$bucket]['label'] }}
                        <span class="ml-auto font-semibold text-slate-500">{{ $rows->unique('batch_id')->count() }} batch</span>
                    </div>
                    @foreach($rows as $row)
                        <div wire:key="ed-{{ $row['batch_id'] }}-{{ $row['location_id'] }}" data-expiry-row="{{ $bucket }}" class="flex flex-wrap items-center gap-x-3.5 gap-y-2 border-t border-slate-100 px-4 py-3">
                            <div class="min-w-0 flex-[1_1_260px]">
                                <div class="text-[13px] font-extrabold text-slate-900">{{ $row['name'] }} <span class="ml-1 font-semibold text-slate-500">· {{ $row['batch'] }}</span></div>
                                <div class="mt-0.5 text-xs text-slate-600">{{ $row['detail'] }}</div>
                            </div>
                            <span class="inline-flex shrink-0 whitespace-nowrap rounded-md border px-2 text-[11px] font-bold leading-4 {{ $bucketMeta[$bucket]['chip'] }}">{{ $row['when'] }}</span>
                            @if($row['actions'])
                                <div class="flex flex-wrap gap-1.5">
                                    @foreach($row['actions'] as $action)
                                        <a href="{{ $action['url'] }}" class="{{ $outline }} {{ $action['tone'] === 'crit' ? 'border-rose-600 bg-rose-600 text-white hover:bg-rose-700' : 'border-slate-300 bg-white text-slate-700 hover:bg-slate-50' }}">{{ $action['label'] }}</a>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @endforeach
                @endforeach
                @if(! $visibleExpiries)
                    <p class="px-4 py-8 text-center text-[13px] text-slate-500" data-monitor-empty>
                        {{ $filtered ? 'Tidak ada batch yang cocok dengan pencarian, lokasi, atau saringan ini.' : 'Tidak ada batch berstok yang lewat atau mendekati ED dalam '.$expiryWarningMonths.' bulan.' }}
                    </p>
                @endif
            @else
                @foreach($visibleCounts as $row)
                    <div wire:key="hitung-{{ $row['medicine_id'] }}-{{ $row['location_id'] }}" data-count-row class="flex flex-wrap items-center gap-x-4 gap-y-2 border-t border-slate-100 px-4 py-3 first:border-t-0">
                        <div class="min-w-0 flex-[1_1_220px]">
                            <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                                <span class="inline-flex shrink-0 whitespace-nowrap rounded-md border px-2 text-[11px] font-bold leading-4 {{ $tone[$row['status']]['chip'] }}">{{ $row['status'] === 'kosong' ? 'Kosong' : 'Menipis' }}</span>
                                <span class="text-[13px] font-extrabold text-slate-900">{{ $row['name'] }}</span>
                            </div>
                            <div class="mt-0.5 text-xs text-slate-600">{{ $row['detail'] }}</div>
                        </div>
                        <div class="min-w-[130px]">
                            <div class="text-[11px] text-slate-500">Diminta</div>
                            <div class="text-[13px] font-extrabold {{ $row['requested'] ? 'text-brand-800' : 'text-slate-400' }}">{{ $row['requested'] ?? 'Belum ditentukan' }}</div>
                        </div>
                        @if($row['url'])<a href="{{ $row['url'] }}" class="{{ $outline }} border-brand-200 bg-white text-brand-800 hover:bg-brand-50">Lihat laporan</a>@endif
                    </div>
                @endforeach
                @if(! $visibleCounts)
                    <p class="px-4 py-8 text-center text-[13px] text-slate-500" data-monitor-empty>
                        {{ $filtered ? 'Tidak ada obat yang cocok dengan pencarian atau lokasi ini.' : 'Belum ada obat kosong atau menipis dari pemeriksaan yang disetujui.' }}
                    </p>
                @endif
            @endif

            @if($hiddenCount > 0)
                <div class="border-t border-slate-100 p-3 text-center">
                    <button type="button" wire:click="showMore" class="btn-secondary min-h-11 text-xs lg:min-h-0">Tampilkan {{ min($hiddenCount, \App\Livewire\StockMonitoring::PAGE_SIZE) }} lainnya</button>
                </div>
            @endif
        </section>

        <aside aria-label="Saran tindakan" class="flex min-w-0 flex-col gap-3.5">
            @if($activeTab === 'minimum')
                @if($warehouse)
                    @forelse($transfers as $group)
                        <section class="{{ $card }}" data-transfer-plan>
                            <div class="flex items-center gap-2.5">
                                <span class="grid h-8 w-8 shrink-0 place-items-center rounded-lg bg-brand-100 text-brand-700">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M7 7h13M16 3l4 4-4 4M17 17H4M8 13l-4 4 4 4" /></svg>
                                </span>
                                <div>
                                    <h2 class="text-[15px] font-extrabold text-slate-900">Transfer {{ $group['source'] }} → {{ $group['destination'] }}</h2>
                                    <p class="text-xs text-slate-500">{{ count($group['items']) }} obat bisa dipenuhi dari {{ $group['source'] }}</p>
                                </div>
                            </div>
                            <ul class="mt-3 flex max-h-64 flex-col gap-1.5 overflow-y-auto">
                                @foreach($group['items'] as $item)
                                    <li class="flex justify-between gap-2.5 text-[13px]"><span class="min-w-0 text-slate-700">{{ $item['name'] }}</span><b class="whitespace-nowrap tabular-nums text-brand-800">{{ $item['quantity'] !== null ? \App\Support\Quantity::format($item['quantity']).($item['unit'] ? ' '.$item['unit'] : '') : 'isi jumlah' }}</b></li>
                                @endforeach
                            </ul>
                            @if($group['url'])
                                <a href="{{ $group['url'] }}" class="btn mt-3.5 min-h-11 w-full text-[13px] lg:min-h-0" data-transfer-plan-open>Buka form transfer ({{ min(count($group['items']), 50) }} obat)</a>
                                @if(count($group['items']) > 50)<p class="mt-1.5 text-[11px] text-slate-500">Satu dokumen transfer memuat 50 obat; sisanya dibuat setelah yang pertama disimpan.</p>@endif
                            @endif
                        </section>
                    @empty
                        <section class="{{ $card }}" data-transfer-plan>
                            <h2 class="text-[15px] font-extrabold text-slate-900">Transfer dari {{ $warehouse }}</h2>
                            <p class="mt-1 text-xs text-slate-500">Tidak ada kekurangan yang bisa dipenuhi dari {{ $warehouse }}.</p>
                        </section>
                    @endforelse
                @endif
                <section class="{{ $card }}" data-order-plan>
                    <div class="flex items-center gap-2.5">
                        <span class="grid h-8 w-8 shrink-0 place-items-center rounded-lg bg-amber-100 text-amber-700">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 2h12l2 4v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6zM4 6h16M9 11h6M9 15h4" /></svg>
                        </span>
                        <div>
                            <h2 class="text-[15px] font-extrabold text-slate-900">Pesan ke PBF</h2>
                            <p class="text-xs text-slate-500">{{ $orders ? count($orders).' obat tidak cukup dari lokasi mana pun' : 'Tidak ada yang perlu dipesan' }}</p>
                        </div>
                    </div>
                    @if($orders)
                        <ul class="mt-3 flex max-h-64 flex-col gap-1.5 overflow-y-auto">
                            @foreach($orders as $order)
                                <li class="flex justify-between gap-2.5 text-[13px]"><span class="min-w-0 text-slate-700">{{ $order['name'] }}</span><b class="whitespace-nowrap tabular-nums text-amber-800">{{ $order['quantity'] !== null ? '± '.\App\Support\Quantity::format($order['quantity']).($order['unit'] ? ' '.$order['unit'] : '') : 'tentukan' }}</b></li>
                            @endforeach
                        </ul>
                        <button type="button" aria-live="polite" data-copy-text="{{ $orderText }}" data-copy-done="Daftar pesanan tersalin" class="mt-3.5 inline-flex min-h-11 w-full items-center justify-center rounded-lg border border-amber-300 bg-white px-3 text-[13px] font-bold text-amber-800 hover:bg-amber-50 lg:min-h-9">Salin daftar pesanan</button>
                    @endif
                </section>
                <p class="text-[11px] text-slate-500">Jumlah saran = kekurangan terhadap batas minimum. {{ $warehouse ? $warehouse.' dipakai sebagai sumber hanya bila stoknya di atas minimumnya sendiri.' : 'Lokasi penerimaan belum ditentukan, jadi semua kekurangan disarankan dipesan.' }}</p>
            @elseif($activeTab === 'ed')
                @php($bucketTotal = array_sum($stats['buckets']))
                <section class="{{ $card }}" data-expiry-summary>
                    <h2 class="text-[15px] font-extrabold text-slate-900">Ringkasan kedaluwarsa</h2>
                    <div class="mt-3 flex h-2.5 overflow-hidden rounded-full bg-slate-100" aria-hidden="true">
                        @foreach($stats['buckets'] as $bucket => $count)
                            @if($count > 0)<span class="border-r-2 border-white last:border-r-0 {{ $bucketMeta[$bucket]['dot'] }}" style="width: {{ $count / max(1, $bucketTotal) * 100 }}%"></span>@endif
                        @endforeach
                    </div>
                    <ul class="mt-3 flex flex-col gap-2">
                        @foreach($stats['buckets'] as $bucket => $count)
                            <li class="flex items-center gap-2 text-[13px] text-slate-700"><span class="h-2.5 w-2.5 shrink-0 rounded-sm {{ $bucketMeta[$bucket]['dot'] }}"></span>{{ $bucketMeta[$bucket]['label'] }}<b class="ml-auto tabular-nums">{{ $count }} batch</b></li>
                        @endforeach
                    </ul>
                    <p class="mt-3 text-xs text-slate-500">
                        Periode peringatan {{ $expiryWarningMonths }} bulan, diatur di {!! $parameterUrl ? '<a href="'.e($parameterUrl).'" class="font-bold text-brand-700 hover:underline">Parameter Monitoring</a>' : 'Parameter Monitoring' !!}.
                    </p>
                    <a href="{{ route('preview.expiry') }}" class="mt-3 inline-flex text-xs font-extrabold text-brand-700 hover:underline">Lihat semua batch, termasuk yang masih aman →</a>
                </section>
            @else
                <section class="{{ $card }}" data-request-summary>
                    <h2 class="text-[15px] font-extrabold text-slate-900">Permintaan dari pemeriksaan</h2>
                    <p class="mt-1 text-xs text-slate-500">Jumlah yang diusulkan petugas saat hitung fisik terakhir yang disetujui.</p>
                    <div class="mt-3 grid grid-cols-2 gap-2.5">
                        <div class="rounded-xl bg-brand-50 p-2.5"><div class="text-[22px] font-extrabold tabular-nums text-brand-800">{{ $stats['requested'] }}</div><div class="text-xs text-slate-600">sudah diisi</div></div>
                        <div class="rounded-xl bg-slate-50 p-2.5"><div class="text-[22px] font-extrabold tabular-nums text-slate-600">{{ $stats['unrequested'] }}</div><div class="text-xs text-slate-600">belum ditentukan</div></div>
                    </div>
                    @if($counts)
                        <button type="button" aria-live="polite" data-copy-text="{{ $countText }}" data-copy-done="Daftar permintaan tersalin" class="mt-3.5 inline-flex min-h-11 w-full items-center justify-center rounded-lg border border-brand-200 bg-white px-3 text-[13px] font-bold text-brand-800 hover:bg-brand-50 lg:min-h-9">Salin daftar permintaan</button>
                    @endif
                </section>
            @endif
        </aside>
    </div>
</div>
