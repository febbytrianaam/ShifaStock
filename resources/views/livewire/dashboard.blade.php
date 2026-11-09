@php
    $user = auth()->user();
    $chipTone = [
        'crit' => 'bg-rose-600 text-white', 'warn' => 'bg-amber-500 text-amber-950', 'info' => 'bg-brand-600 text-white',
        'ok' => 'bg-emerald-700 text-white', 'neutral' => 'bg-slate-500 text-white', 'violet' => 'bg-violet-600 text-white', 'idle' => 'bg-slate-200 text-slate-700',
    ];
    $kpiTheme = [
        'rose' => ['card' => 'border-rose-200 bg-rose-50/70', 'icon' => 'bg-rose-600 text-white', 'value' => 'text-rose-700', 'chip' => 'border-rose-200 text-rose-700', 'now' => 'bg-rose-600', 'past' => 'bg-rose-300'],
        'amber' => ['card' => 'border-amber-200 bg-amber-50/70', 'icon' => 'bg-amber-500 text-amber-950', 'value' => 'text-amber-700', 'chip' => 'border-amber-200 text-amber-800', 'now' => 'bg-amber-600', 'past' => 'bg-amber-300'],
        'green' => ['card' => 'border-emerald-200 bg-emerald-50/60', 'icon' => 'bg-emerald-700 text-white', 'value' => 'text-emerald-700', 'chip' => 'border-emerald-200 text-emerald-700', 'now' => 'bg-emerald-600', 'past' => 'bg-emerald-200'],
        'violet' => ['card' => 'border-violet-200 bg-violet-50/60', 'icon' => 'bg-violet-600 text-white', 'value' => 'text-violet-700', 'chip' => 'border-violet-200 text-violet-700', 'now' => 'bg-violet-600', 'past' => 'bg-violet-300'],
        'teal' => ['card' => 'border-teal-200 bg-teal-50/60', 'icon' => 'bg-teal-700 text-white', 'value' => 'text-teal-700', 'chip' => 'border-teal-200 text-teal-700', 'now' => 'bg-teal-700', 'past' => 'bg-teal-200'],
        'slate' => ['card' => 'border-slate-200 bg-white', 'icon' => 'bg-slate-500 text-white', 'value' => 'text-slate-500', 'chip' => 'border-slate-200 text-slate-600', 'now' => 'bg-slate-500', 'past' => 'bg-slate-200'],
    ];
    $groupRow = ['segera' => 'border-rose-200 bg-rose-50/60', 'pantau' => 'border-amber-200 bg-amber-50/50', 'menunggu' => 'border-brand-100 bg-brand-50/50'];
    $groupButton = [
        'segera' => 'border-rose-600 bg-rose-600 text-white hover:bg-rose-700',
        'pantau' => 'border-amber-300 bg-white text-amber-800 hover:bg-amber-50',
        'menunggu' => 'border-brand-200 bg-white text-brand-800 hover:bg-brand-50',
    ];
    $tabActive = ['semua' => 'border-slate-800 bg-slate-800 text-white', 'segera' => 'border-rose-600 bg-rose-600 text-white', 'pantau' => 'border-amber-500 bg-amber-500 text-amber-950', 'menunggu' => 'border-brand-600 bg-brand-600 text-white'];
    $stepNode = ['ok' => 'bg-emerald-700 text-white', 'info' => 'bg-brand-600 text-white', 'warn' => 'bg-amber-500 text-amber-950', 'idle' => 'border-2 border-dashed border-slate-400 bg-slate-100 text-slate-600'];
    $bucketTone = ['expired' => 'bg-rose-600 text-white', 'd30' => 'bg-amber-500 text-amber-950', 'd60' => 'bg-yellow-500 text-yellow-950', 'later' => 'bg-slate-500 text-white'];
    $bucketWhen = ['expired' => 'text-rose-700', 'd30' => 'text-amber-700', 'd60' => 'text-yellow-800', 'later' => 'text-slate-600'];
    $zoneTone = ['expired' => 'bg-rose-100 text-rose-800', 'd30' => 'bg-amber-100 text-amber-800', 'd60' => 'bg-yellow-100 text-yellow-800', 'later' => 'bg-slate-200 text-slate-700'];
    $locationTone = [['tile' => 'bg-brand-600', 'box' => 'bg-brand-50/70'], ['tile' => 'bg-teal-700', 'box' => 'bg-teal-50/70'], ['tile' => 'bg-slate-600', 'box' => 'bg-slate-50']];
    $series = [
        'receipts' => ['label' => 'Penerimaan', 'color' => '#10b981', 'fill' => 'rgba(16,185,129,0.14)', 'chip' => 'bg-emerald-50 text-emerald-700'],
        'transfers' => ['label' => 'Transfer', 'color' => '#3277B2', 'fill' => 'rgba(50,119,178,0.14)', 'chip' => 'bg-brand-50 text-brand-800'],
        'issues' => ['label' => 'Pengeluaran & impor', 'color' => '#f59e0b', 'fill' => 'rgba(245,158,11,0.14)', 'chip' => 'bg-amber-50 text-amber-800'],
        'adjustments' => ['label' => 'Retur & penyesuaian', 'color' => '#8b5cf6', 'fill' => null, 'chip' => 'bg-violet-50 text-violet-800'],
    ];
    $sectionCard = 'min-w-0 rounded-2xl border border-slate-200/80 bg-white p-4 shadow-sm';
@endphp

<x-slot:headerActions>
    @can('view-nightly-checks')<a href="{{ route('preview.nightly') }}" class="btn py-1.5 text-[13px]">Pemeriksaan Harian</a>@endcan
    @can('view-receipts')<a href="{{ route('preview.receipts') }}" class="btn-secondary py-1.5 text-[13px]">Obat Masuk</a>@endcan
    @can('view-transfers')<a href="{{ route('preview.transfers') }}" class="btn-secondary py-1.5 text-[13px]">Transfer</a>@endcan
    @can('view-issues')<a href="{{ route('issue-imports.index') }}" class="btn-secondary py-1.5 text-[13px]">Impor Penjualan</a>@endcan
</x-slot:headerActions>

<div @can('view-inventory') wire:poll.visible.60s @endcan>
@can('view-inventory')
    @php
        $split =fn (array $counts) => collect($counts)->map(fn ($count, $location) => $count.' '.$location)->implode(', ');
        $delta = function (array $values) {
            $change = $values[6] - $values[5];
            return $change > 0 ? '▲ '.$change.' dari kemarin' : ($change < 0 ? '▼ '.abs($change).' dari kemarin' : 'Sama seperti kemarin');
        };
        $shortageUrl = $user->can('view-shortages') ? route('shortages.index').'#stok-minimum' : null;
        $expiryUrl = $user->can('view-expiry') ? route('preview.expiry') : null;
        $parameterUrl = $user->can('view-monitoring-parameters') ? route('monitoring-parameters.index') : null;
        $ringLength = 2 * M_PI * 16;
        $healthPct = $stats['tracked'] > 0 ? (int) round($stats['aman'] / $stats['tracked'] * 100) : null;
        $kpis = [
            [
                'key' => 'kosong', 'label' => 'Stok kosong', 'icon' => 'M12 9v4m0 4h.01M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z',
                'theme' => ! $hasMonitoringParameters ? 'slate' : ($stats['kosong'] > 0 ? 'rose' : 'green'),
                'chip' => ! $hasMonitoringParameters ? 'Belum diatur' : ($stats['kosong'] > 0 ? 'Segera' : 'Aman'),
                'value' => $hasMonitoringParameters ? $stats['kosong'] : '–', 'unit' => 'item obat-lokasi', 'trend' => $hasMonitoringParameters ? $trend['kosong'] : null,
                'foot' => $hasMonitoringParameters ? trim($delta($trend['kosong']).($stats['kosong'] ? ' · '.$split($shortageSplit['kosong']) : '')) : 'Batas minimum stok belum diatur',
                'url' => $hasMonitoringParameters ? ($shortageUrl ? route('shortages.index', ['status' => 'empty']).'#stok-minimum' : null) : $parameterUrl,
            ],
            [
                'key' => 'menipis', 'label' => 'Menipis', 'icon' => 'M22 17l-8.5-8.5-5 5L2 7M16 17h6v-6',
                'theme' => ! $hasMonitoringParameters ? 'slate' : ($stats['menipis'] > 0 ? 'amber' : 'green'),
                'chip' => ! $hasMonitoringParameters ? 'Belum diatur' : ($stats['menipis'] > 0 ? 'Pantau' : 'Aman'),
                'value' => $hasMonitoringParameters ? $stats['menipis'] : '–', 'unit' => 'item obat-lokasi', 'trend' => $hasMonitoringParameters ? $trend['menipis'] : null,
                'foot' => $hasMonitoringParameters ? trim($delta($trend['menipis']).($stats['menipis'] ? ' · '.$split($shortageSplit['menipis']) : '')) : 'Atur di Parameter Monitoring',
                'url' => $hasMonitoringParameters ? ($shortageUrl ? route('shortages.index', ['status' => 'low']).'#stok-minimum' : null) : $parameterUrl,
            ],
            [
                'key' => 'kedaluwarsa', 'label' => 'Kedaluwarsa', 'icon' => 'M12 7v5l3 2M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0z',
                'theme' => $stats['expired'] + $stats['expiring'] > 0 ? 'violet' : 'green',
                'chip' => $stats['expired'] > 0 ? 'Segera' : ($stats['expiring'] > 0 ? 'Pantau' : 'Aman'),
                'value' => $stats['expired'] + $stats['expiring'], 'unit' => 'batch berstok, ≤ '.$expiryWarningMonths.' bulan', 'buckets' => true,
                'foot' => $stats['expired'] > 0 ? $stats['expired'].' sudah lewat ED, masih tercatat stok' : 'Tidak ada yang lewat ED',
                'url' => $expiryUrl,
            ],
            [
                'key' => 'kesehatan', 'label' => 'Kesehatan stok', 'icon' => 'M19 14c1.5-1.5 3-3.2 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.8 0-3 .5-4.5 2-1.5-1.5-2.7-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4 3 5.5l7 7Z',
                'theme' => $hasMonitoringParameters ? 'teal' : 'slate', 'chip' => $hasMonitoringParameters ? ($healthPct === 100 ? 'Aman' : 'Info') : 'Belum diatur',
                'value' => $healthPct === null ? '–' : $healthPct.'%', 'unit' => $hasMonitoringParameters ? $stats['aman'].' dari '.$stats['tracked'].' item aman' : 'belum ada batas minimum', 'ring' => $hasMonitoringParameters,
                'foot' => $stats['stocked'].' dari '.$stats['activeMedicines'].' obat aktif berstok',
                'url' => $user->can('view-reports') ? route('preview.reports') : null,
            ],
        ];
        $bucketLabels = ['expired' => 'Lewat', 'd30' => '≤ 30 h', 'd60' => '31–60 h', 'later' => '61+ h'];
        $bucketTotal = array_sum($buckets);
        $ringSegments = [];
        $offset = 0;
        foreach ([['aman', '#10b981'], ['menipis', '#f59e0b'], ['kosong', '#e11d48']] as [$part, $color]) {
            $length = $stats['tracked'] > 0 ? $stats[$part] / $stats['tracked'] * $ringLength : 0;
            $ringSegments[] = ['color' => $color, 'dash' => sprintf('%.2f %.2f', $length, $ringLength - $length), 'offset' => sprintf('%.2f', -$offset)];
            $offset += $length;
        }
        $tabs = ['semua' => 'Semua', 'segera' => 'Segera', 'pantau' => 'Pantau', 'menunggu' => 'Menunggu'];
        $moreUrl = $tab === 'menunggu' ? ($user->can('view-nightly-checks') ? route('preview.nightly') : null) : $shortageUrl;
        $stepCount = count($steps);
    @endphp

    <div class="mb-4 flex flex-wrap items-center gap-x-3 gap-y-1.5 text-xs text-slate-500">
        <span>{{ $asOf->locale('id')->translatedFormat('l, j F Y') }} · <b class="font-bold text-slate-700">data per {{ $asOf->format('H.i') }} WIB</b> · diperbarui otomatis tiap menit</span>
        @if(count($actions))
            <span class="rounded-md border border-rose-200 bg-rose-50 px-2 py-0.5 font-bold text-rose-700" data-action-total>{{ count($actions) }} perlu ditindaklanjuti</span>
        @else
            <span class="rounded-md border border-emerald-200 bg-emerald-50 px-2 py-0.5 font-bold text-emerald-700" data-action-total>Semua aman</span>
        @endif
    </div>

    <div class="mb-4 grid grid-cols-2 gap-2 sm:grid-cols-4 xl:hidden">
        @can('view-nightly-checks')<a href="{{ route('preview.nightly') }}" class="btn min-h-11">Pemeriksaan Harian</a>@endcan
        @can('view-receipts')<a href="{{ route('preview.receipts') }}" class="btn-secondary min-h-11">Obat Masuk</a>@endcan
        @can('view-transfers')<a href="{{ route('preview.transfers') }}" class="btn-secondary min-h-11">Transfer</a>@endcan
        @can('view-issues')<a href="{{ route('issue-imports.index') }}" class="btn-secondary min-h-11">Impor Penjualan</a>@endcan
    </div>

    <section aria-label="Angka utama" class="grid grid-cols-2 gap-3 xl:grid-cols-4 xl:gap-4">
        @foreach($kpis as $kpi)
            @php($theme = $kpiTheme[$kpi['theme']])
            <a @if($kpi['url']) href="{{ $kpi['url'] }}" @endif data-kpi="{{ $kpi['key'] }}" class="relative flex min-w-0 flex-col rounded-2xl border p-3 transition-shadow hover:shadow-md sm:px-4 sm:py-3.5 {{ $theme['card'] }}">
                <span class="flex items-center justify-between gap-2">
                    <span class="flex min-w-0 items-center gap-2">
                        <span class="grid h-[30px] w-[30px] shrink-0 place-items-center rounded-lg {{ $theme['icon'] }}">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="{{ $kpi['icon'] }}" /></svg>
                        </span>
                        <span class="text-[13px] font-extrabold text-slate-700">{{ $kpi['label'] }}</span>
                    </span>
                    <span class="hidden shrink-0 rounded-full border bg-white px-2.5 text-[11px] font-extrabold leading-[18px] sm:inline-flex {{ $theme['chip'] }}">{{ $kpi['chip'] }}</span>
                </span>
                <span class="mt-2.5 flex flex-wrap items-end justify-between gap-x-3 gap-y-2">
                    <span class="flex min-w-0 flex-col gap-1">
                        <span class="text-[28px] font-extrabold leading-none tabular-nums sm:text-[34px] {{ $theme['value'] }}" data-kpi-value>{{ $kpi['value'] }}</span>
                        <span class="text-xs font-semibold text-slate-500">{{ $kpi['unit'] }}</span>
                    </span>
                    @if($kpi['trend'] ?? null)
                        @php($peak = max(1, max($kpi['trend'])))
                        <span class="flex shrink-0 flex-col items-end gap-1">
                            <span class="flex h-10 items-end gap-[3px]" aria-hidden="true">
                                @foreach($kpi['trend'] as $index => $value)
                                    <span class="block w-2 rounded-[3px] {{ $value === 0 ? 'bg-slate-200' : ($index === 6 ? $theme['now'] : $theme['past']) }}" style="height: {{ $value === 0 ? 3 : max(5, (int) round($value / $peak * 40)) }}px"></span>
                                @endforeach
                            </span>
                            <span class="text-[10px] text-slate-500">7 hari terakhir</span>
                            <span class="sr-only">Tren 7 hari terakhir: {{ implode(', ', $kpi['trend']) }}</span>
                        </span>
                    @endif
                    @if($kpi['buckets'] ?? false)
                        <span class="grid shrink-0 grid-cols-[auto_auto] gap-x-2.5 gap-y-0.5 text-[11px] font-semibold text-slate-600">
                            @foreach($bucketLabels as $bucket => $label)
                                <span class="inline-flex items-center gap-1.5 whitespace-nowrap"><span class="h-2 w-2 rounded-sm {{ explode(' ', $bucketTone[$bucket])[0] }}"></span>{{ $label }} · {{ $buckets[$bucket] }}</span>
                            @endforeach
                        </span>
                    @endif
                    @if($kpi['ring'] ?? false)
                        <svg class="h-[58px] w-[58px] shrink-0 -rotate-90" viewBox="0 0 40 40" role="img" aria-label="Kesehatan stok {{ $kpi['value'] }}: {{ $stats['aman'] }} aman, {{ $stats['menipis'] }} menipis, {{ $stats['kosong'] }} kosong">
                            <circle cx="20" cy="20" r="16" fill="none" stroke="#ccfbf1" stroke-width="5" />
                            @foreach($ringSegments as $segment)
                                <circle cx="20" cy="20" r="16" fill="none" stroke="{{ $segment['color'] }}" stroke-width="5" stroke-dasharray="{{ $segment['dash'] }}" stroke-dashoffset="{{ $segment['offset'] }}" />
                            @endforeach
                        </svg>
                    @endif
                </span>
                @if(($kpi['buckets'] ?? false) && $bucketTotal > 0)
                    <span class="mt-2.5 flex h-2.5 overflow-hidden rounded-full bg-white p-0.5" aria-hidden="true">
                        @foreach($buckets as $bucket => $count)
                            @if($count > 0)<span class="border-r-2 border-white {{ explode(' ', $bucketTone[$bucket])[0] }}" style="width: {{ $count / $bucketTotal * 100 }}%"></span>@endif
                        @endforeach
                    </span>
                @endif
                <span class="mt-auto flex items-end justify-between gap-2 pt-2 text-xs font-semibold text-slate-600">
                    <span>{{ $kpi['foot'] }}</span>
                    @if($kpi['url'])<span class="font-extrabold {{ $theme['value'] }}" aria-hidden="true">→</span>@endif
                </span>
            </a>
        @endforeach
    </section>

    <div class="mt-4 grid gap-4 lg:grid-cols-12">
        <section aria-labelledby="judul-tindak-lanjut" class="{{ $sectionCard }} flex flex-col lg:col-span-12 xl:col-span-5" data-stock-alerts>
            <div class="flex flex-wrap items-center justify-between gap-2">
                <div class="flex items-center gap-2.5">
                    <span class="grid h-8 w-8 shrink-0 place-items-center rounded-lg bg-rose-100 text-rose-700">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 9v4m0 4h.01M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z" /></svg>
                    </span>
                    <h2 id="judul-tindak-lanjut" class="text-base font-extrabold text-slate-900">Perlu Ditindaklanjuti</h2>
                </div>
                @can('view-reports')<a href="{{ route('preview.reports') }}" class="text-xs font-extrabold text-brand-700 hover:underline">Buka laporan stok →</a>@endcan
            </div>
            @if(count($actions))
                <div role="group" aria-label="Saring tindak lanjut" class="mt-3 flex flex-wrap gap-1.5">
                    @foreach($tabs as $key => $label)
                        <button type="button" wire:click="setTab('{{ $key }}')" aria-pressed="{{ $tab === $key ? 'true' : 'false' }}" data-action-tab="{{ $key }}"
                            class="inline-flex min-h-11 items-center rounded-full border px-3 text-xs font-extrabold lg:min-h-8 {{ $tab === $key ? $tabActive[$key] : 'border-slate-300 bg-white text-slate-700 hover:bg-slate-50' }}">{{ $label }} {{ $actionCounts[$key] }}</button>
                    @endforeach
                </div>
                <ul class="mt-2.5 flex flex-col gap-1">
                    @foreach($visibleActions as $item)
                        <li wire:key="aksi-{{ $loop->index }}-{{ md5($item['title'].$item['meta']) }}" data-action-row data-action-group="{{ $item['group'] }}" @if($item['kind'] === 'stock') data-stock-alert @endif
                            class="flex flex-wrap items-center gap-x-2.5 gap-y-2 rounded-xl border px-2.5 py-2 lg:py-1.5 {{ $groupRow[$item['group']] }}">
                            <div class="min-w-0 flex-[1_1_220px]">
                                <div class="flex min-w-0 items-center gap-2">
                                    <span class="inline-flex shrink-0 whitespace-nowrap rounded-full px-2 text-[11px] font-bold leading-[18px] {{ $chipTone[$item['tone']] }}">{{ $item['chip'] }}</span>
                                    @if($item['url'])
                                        <a href="{{ $item['url'] }}" class="min-w-0 text-[13px] font-extrabold text-slate-900 hover:underline lg:truncate">{{ $item['title'] }}</a>
                                    @else
                                        <span class="min-w-0 text-[13px] font-extrabold text-slate-900 lg:truncate">{{ $item['title'] }}</span>
                                    @endif
                                </div>
                                <p class="mt-0.5 text-xs text-slate-600 lg:truncate">{{ $item['meta'] }}</p>
                            </div>
                            @if($item['action'])
                                <a href="{{ $item['actionUrl'] }}" class="inline-flex min-h-11 shrink-0 items-center justify-center whitespace-nowrap rounded-lg border px-2.5 text-xs font-extrabold lg:min-h-[30px] {{ $groupButton[$item['group']] }}">{{ $item['action'] }}</a>
                            @endif
                        </li>
                    @endforeach
                </ul>
                @if($hiddenActions > 0 && $moreUrl)
                    <a href="{{ $moreUrl }}" class="mt-auto block pt-2 text-xs font-extrabold text-brand-700 hover:underline">Lihat {{ $hiddenActions }} lainnya →</a>
                @endif
            @elseif($hasMonitoringParameters)
                <div class="mt-3 flex items-center gap-3 rounded-xl border border-emerald-200 bg-emerald-50 p-3.5">
                    <svg class="h-5 w-5 shrink-0 text-emerald-700" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
                    <div>
                        <p class="text-sm font-extrabold text-emerald-900">Tidak ada yang perlu ditindaklanjuti</p>
                        <p class="mt-0.5 text-xs text-emerald-800">Stok di atas minimum, tidak ada batch lewat ED, dan tidak ada laporan yang menunggu.</p>
                    </div>
                </div>
            @else
                <div class="mt-3 rounded-xl border border-slate-200 bg-slate-50 p-3.5">
                    <p class="text-sm font-extrabold text-slate-800">Batas minimum stok belum diatur</p>
                    <p class="mt-0.5 text-xs text-slate-600">Minta kepala apoteker mengatur batas minimum per obat dan lokasi pada Parameter Monitoring agar stok kosong dan menipis bisa dipantau.</p>
                </div>
            @endif
        </section>

        <div class="flex min-w-0 flex-col gap-4 lg:col-span-7 xl:col-span-4">
            <section aria-labelledby="judul-aktivitas" class="{{ $sectionCard }}">
                <div class="flex items-center gap-2.5">
                    <span class="grid h-8 w-8 shrink-0 place-items-center rounded-lg bg-indigo-100 text-indigo-700">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 12h-4l-3 9L9 3l-3 9H2" /></svg>
                    </span>
                    <div class="min-w-0">
                        <h2 id="judul-aktivitas" class="text-base font-extrabold text-slate-900">Aktivitas Persediaan</h2>
                        <p class="text-xs text-slate-500">{{ $chart['total'] }} transaksi, semua lokasi, {{ $asOf->subDays(6)->locale('id')->translatedFormat('j M') }}–{{ $asOf->locale('id')->translatedFormat('j M Y') }}</p>
                    </div>
                </div>
                <div class="mt-2.5 flex flex-wrap gap-1.5">
                    @foreach($series as $key => $meta)
                        <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $meta['chip'] }}"><span class="h-2 w-2 rounded-full" style="background: {{ $meta['color'] }}"></span>{{ $meta['label'] }} <b>{{ array_sum($chart[$key]) }}</b></span>
                    @endforeach
                </div>
                <figure id="stockChart" class="mt-2.5" aria-label="Grafik garis jumlah transaksi per hari menurut jenis selama 7 hari terakhir, total {{ $chart['total'] }} transaksi">
                    <div class="flex gap-2">
                        <div class="relative h-[200px] w-3.5 shrink-0 text-right text-[11px] text-slate-500" aria-hidden="true">
                            @foreach($chart['geometry']['ticks'] as $tick)
                                <span class="absolute right-0" style="top: {{ $tick['top'] - 8 }}px">{{ $tick['label'] }}</span>
                            @endforeach
                        </div>
                        <div class="relative h-[200px] min-w-0 flex-1">
                            @foreach($chart['geometry']['ticks'] as $tick)
                                <div class="absolute inset-x-0 border-t {{ $tick['label'] === 0 ? 'border-slate-200' : 'border-slate-100' }}" style="top: {{ $tick['top'] }}px"></div>
                            @endforeach
                            <svg class="absolute inset-0 h-[200px] w-full overflow-visible" viewBox="0 0 700 200" preserveAspectRatio="none" aria-hidden="true">
                                @foreach($series as $key => $meta)
                                    @if($meta['fill'])<path d="{{ $chart['geometry']['paths'][$key]['area'] }}" fill="{{ $meta['fill'] }}" stroke="none" />@endif
                                @endforeach
                                @foreach(array_reverse($series, true) as $key => $meta)
                                    <path d="{{ $chart['geometry']['paths'][$key]['line'] }}" fill="none" stroke="{{ $meta['color'] }}" stroke-width="{{ $meta['fill'] ? '2.5' : '2' }}" @if(! $meta['fill']) stroke-dasharray="5 4" @endif vector-effect="non-scaling-stroke" />
                                @endforeach
                            </svg>
                            @foreach(collect($chart['geometry']['dots'])->sortBy(fn ($dot) => array_search($dot['series'], array_keys(array_reverse($series, true)))) as $dot)
                                <span class="absolute h-2.5 w-2.5 rounded-full border-[2.5px] bg-white" style="left: calc({{ $dot['left'] }}% - 5px); top: {{ $dot['top'] - 5 }}px; border-color: {{ $series[$dot['series']]['color'] }}" title="{{ $series[$dot['series']]['label'] }}: {{ $dot['value'] }}"></span>
                            @endforeach
                        </div>
                    </div>
                    <div class="ml-[22px] mt-1 flex">
                        @foreach($chart['labels'] as $index => $label)
                            <span class="min-w-0 flex-1 whitespace-nowrap text-center text-[11px] {{ $index === 6 ? 'font-extrabold text-slate-900' : 'text-slate-500' }}">{{ $label }}</span>
                        @endforeach
                    </div>
                </figure>
            </section>

            <section aria-labelledby="judul-alur" class="{{ $sectionCard }} flex flex-1 flex-col">
                <div class="flex items-center gap-2.5">
                    <span class="grid h-8 w-8 shrink-0 place-items-center rounded-lg bg-teal-100 text-teal-700">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 4h6a1 1 0 0 1 1 1v1H8V5a1 1 0 0 1 1-1z" /><path d="M8 5H6a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2" /><path d="m9 14 2 2 4-4" /></svg>
                    </span>
                    <h2 id="judul-alur" class="text-base font-extrabold text-slate-900">Alur Kerja Hari Ini</h2>
                    <span class="ml-auto text-xs text-slate-500">{{ collect($steps)->where('done', true)->count() }} dari {{ $stepCount }} selesai</span>
                </div>
                <div class="relative mt-3.5 hidden sm:block">
                    @foreach($steps as $index => $step)
                        @if(! $loop->last)
                            <span aria-hidden="true" class="absolute top-[15px] h-1 rounded-full {{ $step['done'] ? 'bg-emerald-500' : 'bg-slate-200' }}" style="left: calc({{ (100 / $stepCount) * ($index + 0.5) }}% + 22px); width: calc({{ 100 / $stepCount }}% - 44px)"></span>
                        @endif
                    @endforeach
                <ol class="grid" style="grid-template-columns: repeat({{ $stepCount }}, minmax(0, 1fr))">
                    @foreach($steps as $index => $step)
                        <li class="relative flex min-w-0 flex-col items-center gap-1 px-1 text-center" data-step="{{ $step['title'] }}">
                            <span class="relative z-10 grid h-[34px] w-[34px] place-items-center rounded-full text-[13px] font-extrabold ring-4 ring-white {{ $stepNode[$step['tone']] }}">{{ $step['done'] ? '✓' : (isset($step['progress']) ? $step['progress'].'%' : ($step['tone'] === 'warn' ? '!' : $index + 1)) }}</span>
                            @if($step['url'] ?? null)
                                <a href="{{ $step['url'] }}" class="mt-0.5 text-[13px] font-extrabold text-slate-900 hover:underline">{{ $step['title'] }}</a>
                            @else
                                <span class="mt-0.5 text-[13px] font-extrabold text-slate-900">{{ $step['title'] }}</span>
                            @endif
                            <span class="inline-flex whitespace-nowrap rounded-full px-2 text-[11px] font-bold leading-[18px] {{ $chipTone[$step['tone']] }}">{{ $step['status'] }}</span>
                            <span class="text-[11px] text-slate-600">{{ $step['detail'] }}</span>
                        </li>
                    @endforeach
                </ol>
                </div>
                <ol class="mt-3.5 flex flex-col sm:hidden">
                    @foreach($steps as $index => $step)
                        <li class="flex items-stretch gap-3">
                            <div class="flex shrink-0 flex-col items-center">
                                <span class="grid h-[34px] w-[34px] place-items-center rounded-full text-[13px] font-extrabold {{ $stepNode[$step['tone']] }}">{{ $step['done'] ? '✓' : (isset($step['progress']) ? $step['progress'].'%' : ($step['tone'] === 'warn' ? '!' : $index + 1)) }}</span>
                                @if(! $loop->last)<span class="my-1 block min-h-2.5 w-[3px] flex-1 rounded-full {{ $step['done'] ? 'bg-emerald-500' : 'bg-slate-200' }}" aria-hidden="true"></span>@endif
                            </div>
                            <div class="min-w-0 pb-3 pt-1">
                                <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                                    <span class="text-sm font-extrabold text-slate-900">{{ $step['title'] }}</span>
                                    <span class="inline-flex whitespace-nowrap rounded-full px-2 text-[11px] font-bold leading-[18px] {{ $chipTone[$step['tone']] }}">{{ $step['status'] }}</span>
                                </div>
                                <p class="mt-0.5 text-xs text-slate-600">{{ $step['detail'] }}</p>
                            </div>
                        </li>
                    @endforeach
                </ol>
                <p class="mt-auto border-t border-slate-100 pt-2.5 text-xs text-slate-600">{{ $opname }}</p>
            </section>
        </div>

        <div class="flex min-w-0 flex-col gap-4 lg:col-span-5 xl:col-span-3">
            <section id="kedaluwarsa" aria-labelledby="judul-radar" class="{{ $sectionCard }}">
                <div class="flex items-center justify-between gap-2">
                    <div class="flex items-center gap-2.5">
                        <span class="grid h-8 w-8 shrink-0 place-items-center rounded-lg bg-violet-100 text-violet-700">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 7v5l3 2" /><path d="M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0z" /></svg>
                        </span>
                        <h2 id="judul-radar" class="text-base font-extrabold text-slate-900">Radar Kedaluwarsa</h2>
                    </div>
                    @if($expiryUrl)<a href="{{ $expiryUrl }}" class="text-xs font-extrabold text-violet-700 hover:underline">Semua →</a>@endif
                </div>
                @if(count($radar['markers']))
                    <div class="relative mt-7" aria-hidden="true">
                        <div class="flex h-10 overflow-hidden rounded-lg">
                            @foreach($radar['zones'] as $zone)
                                <div class="border-r-2 border-white {{ explode(' ', $zoneTone[$zone['bucket']])[0] }}" style="width: {{ $zone['width'] }}%"></div>
                            @endforeach
                        </div>
                        <span class="absolute -top-[22px] -translate-x-1/2 whitespace-nowrap text-[11px] font-extrabold text-slate-900" style="left: {{ $radar['todayLeft'] }}%">Hari ini</span>
                        <span class="absolute -bottom-1 -top-1 w-0.5 rounded bg-slate-900" style="left: calc({{ $radar['todayLeft'] }}% - 1px)"></span>
                        @foreach($radar['markers'] as $marker)
                            <span class="absolute top-5 grid h-[26px] w-[26px] -translate-y-1/2 place-items-center rounded-full text-[11px] font-extrabold ring-[3px] ring-white {{ $bucketTone[$marker['bucket']] }}" style="left: calc({{ $marker['left'] }}% - 13px)">{{ $marker['number'] }}</span>
                        @endforeach
                    </div>
                    <div class="mt-1.5 flex" aria-hidden="true">
                        @foreach($radar['zones'] as $zone)
                            <span class="whitespace-nowrap text-center text-[11px] font-extrabold {{ explode(' ', $zoneTone[$zone['bucket']])[1] }}" style="width: {{ $zone['width'] }}%">{{ $zone['label'] }}</span>
                        @endforeach
                    </div>
                    <ul class="mt-2.5 flex flex-col gap-1">
                        @foreach($radar['markers'] as $marker)
                            <li class="flex min-w-0 items-center gap-2 py-0.5" data-expiry-row>
                                <span class="grid h-[22px] w-[22px] shrink-0 place-items-center rounded-full text-[11px] font-extrabold {{ $bucketTone[$marker['bucket']] }}">{{ $marker['number'] }}</span>
                                <span class="min-w-0 flex-1 truncate text-xs text-slate-600"><b class="text-slate-900">{{ $marker['name'] }}</b> · {{ $marker['place'] }}</span>
                                <span class="shrink-0 text-xs font-extrabold {{ $bucketWhen[$marker['bucket']] }}">{{ $marker['when'] }}</span>
                            </li>
                        @endforeach
                    </ul>
                    @if($radar['more'] > 0 && $expiryUrl)
                        <a href="{{ $expiryUrl }}" class="mt-1.5 block text-xs font-extrabold text-violet-700 hover:underline">Lihat {{ $radar['more'] }} lainnya →</a>
                    @endif
                @else
                    <p class="mt-3 rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-xs font-semibold text-emerald-800">Tidak ada batch berstok yang lewat ED atau kedaluwarsa dalam {{ $expiryWarningMonths }} bulan.</p>
                @endif
            </section>

            <section aria-labelledby="judul-lokasi" class="{{ $sectionCard }} flex flex-1 flex-col gap-2">
                <div class="mb-1 flex items-center gap-2.5">
                    <span class="grid h-8 w-8 shrink-0 place-items-center rounded-lg bg-sky-100 text-sky-700">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 21h18" /><path d="M5 21V8l7-5 7 5v13" /><path d="M9 21v-6h6v6" /></svg>
                    </span>
                    <h2 id="judul-lokasi" class="text-base font-extrabold text-slate-900">Kondisi per Lokasi</h2>
                </div>
                @foreach($locations as $location)
                    @php($tone = $locationTone[min($loop->index, 2)])
                    <div class="rounded-xl px-3 py-2.5 {{ $tone['box'] }}" data-location-row="{{ $location['code'] }}">
                        <div class="flex items-center gap-2">
                            <span class="grid h-7 w-7 shrink-0 place-items-center rounded-lg text-[13px] font-extrabold text-white {{ $tone['tile'] }}">{{ mb_substr($location['name'], 0, 1) }}</span>
                            <span class="min-w-0 flex-1 truncate text-sm font-extrabold text-slate-900">{{ $location['name'] }}</span>
                            <span class="inline-flex whitespace-nowrap rounded-full px-2 text-[11px] font-bold leading-[18px] {{ $chipTone[$location['tone']] }}">{{ $location['today'] }}</span>
                        </div>
                        @if($location['tracked'] > 0)
                            <div class="mt-2 flex h-2 overflow-hidden rounded-full bg-slate-200" aria-hidden="true">
                                @foreach([['aman', 'bg-emerald-500'], ['menipis', 'bg-amber-500'], ['kosong', 'bg-rose-600']] as [$part, $color])
                                    @if($location[$part] > 0)<span class="border-r-2 border-white {{ $color }}" style="width: {{ $location[$part] / $location['tracked'] * 100 }}%"></span>@endif
                                @endforeach
                            </div>
                        @endif
                        <p class="mt-1.5 flex flex-wrap gap-x-2.5 text-xs text-slate-700">
                            @if($location['tracked'] > 0)
                                <span><b class="text-emerald-700">{{ $location['aman'] }}</b> aman</span>
                                <span><b class="text-amber-700">{{ $location['menipis'] }}</b> menipis</span>
                                <span><b class="text-rose-700">{{ $location['kosong'] }}</b> kosong</span>
                            @else
                                <span class="text-slate-500">Belum ada batas minimum</span>
                            @endif
                            <span><b class="text-slate-900">{{ $location['expiring'] }}</b> batch ED ≤ {{ $expiryWarningMonths }} bln</span>
                        </p>
                    </div>
                @endforeach
            </section>
        </div>
    </div>
@else
    @if($adminStats)
        {{-- Inline @php statements: a block @php here would pair with the inline ones above it. --}}
        @php($inactiveUsers = $adminStats['users'] - $adminStats['active_users'])
        @php($adminMenus = array_filter([
            ($user->can('manage-users') || $user->can('manage-permissions')) ? ['admin.users.index', 'users', 'brand', $user->can('manage-users') ? 'Pengguna & Hak Akses' : 'Hak Akses', 'Tambah akun, ubah role, nonaktifkan petugas, dan atur matrix hak akses.'] : null,
            $user->can('view-audit-logs') ? ['admin.audit-logs.index', 'audit', 'slate', 'Audit Log', 'Telusuri login, perubahan pengguna, hak akses, dan transaksi stok.'] : null,
            $user->can('manage-system-references') ? ['admin.references.index', 'reference', 'teal', 'Referensi Sistem', 'Lokasi stok dan satuan obat yang dipakai di semua transaksi.'] : null,
            $user->can('manage-system-settings') ? ['admin.settings.index', 'settings', 'violet', 'Pengaturan Sistem', 'Default lokasi penerimaan dan identitas klinik di laporan.'] : null,
        ]))
        @php($menuTone = ['brand' => 'bg-brand-100 text-brand-700', 'slate' => 'bg-slate-100 text-slate-600', 'teal' => 'bg-teal-100 text-teal-700', 'violet' => 'bg-violet-100 text-violet-700'])
        <p class="mb-3.5 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-slate-500">
            <span class="badge badge-brand">Portal Administrator</span>
            <span>Manajemen akses dan konfigurasi sistem Klinik Shifa Medika</span>
        </p>
        <section aria-label="Ringkasan akun" class="grid grid-cols-2 gap-3 xl:grid-cols-4 xl:gap-4">
            <x-ui.stat label="Pengguna Terdaftar" :value="$adminStats['users']" unit="akun" sub="Akun individu" theme="brand" icon="users" :href="$user->can('manage-users') ? route('admin.users.index') : null" />
            <x-ui.stat label="Akun Aktif" :value="$adminStats['active_users']" unit="akun" sub="Dapat mengakses sistem" theme="green" icon="ok" :href="$user->can('manage-users') ? route('admin.users.index', ['status' => 'active']) : null" />
            <x-ui.stat label="Akun Nonaktif" :value="$inactiveUsers" unit="akun" :sub="$inactiveUsers > 0 ? 'Tidak bisa login, riwayat tetap tersimpan' : 'Semua akun aktif'" :theme="$inactiveUsers > 0 ? 'amber' : 'neutral'" icon="lock" :href="$user->can('manage-users') ? route('admin.users.index', ['status' => 'inactive']) : null" />
            <x-ui.stat label="Role Sistem" :value="$adminStats['roles']" unit="role" sub="Admin, petugas, kepala, guest" theme="neutral" icon="key" :href="$user->can('manage-permissions') ? route('admin.users.index', ['tab' => 'permissions']) : null" />
        </section>
        @if($adminMenus)
            <x-ui.section class="mt-4" title="Menu administrasi" icon="settings" tone="slate" subtitle="Semua perubahan di menu ini tercatat di Audit Log.">
                <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                    @foreach($adminMenus as [$route, $icon, $tone, $label, $hint])
                        <a href="{{ route($route) }}" class="flex min-w-0 items-start gap-3 rounded-xl border border-slate-200 p-3.5 hover:border-slate-300 hover:bg-slate-50">
                            <span class="icon-tile {{ $menuTone[$tone] }}"><x-ui.icon :name="$icon" /></span>
                            <span class="min-w-0">
                                <span class="block text-[13px] font-extrabold text-slate-900">{{ $label }}</span>
                                <span class="mt-0.5 block text-xs text-slate-500">{{ $hint }}</span>
                            </span>
                        </a>
                    @endforeach
                </div>
            </x-ui.section>
        @endif
    @else
        <div class="callout max-w-3xl">Ringkasan persediaan belum diaktifkan untuk akun ini. Gunakan menu yang tersedia di sidebar.</div>
    @endif
@endcan
</div>
