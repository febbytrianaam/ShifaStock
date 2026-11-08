<?php

namespace App\Support\Dashboard;

use App\Models\IssueImport;
use App\Models\Location;
use App\Models\Medicine;
use App\Models\StockBalance;
use App\Models\StockOpnameSession;
use App\Models\SystemSetting;
use App\Models\User;
use App\Queries\MinimumStockAlerts;
use App\Support\Quantity;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Reads the live stock tables once and shapes everything the monitoring dashboard shows.
 * Presentation (colours, layout) stays in the Blade view; this class only returns data and links.
 */
final class DashboardSummary
{
    /** Movement types per chart series. Opening balances are setup data, not daily activity. */
    private const CHART_SERIES = [
        'receipts' => ['receipt'],
        'transfers' => ['transfer'],
        'issues' => ['issue'],
        'adjustments' => ['supplier_return', 'special_adjustment', 'stock_opname_adjustment', 'physical_count', 'reversal'],
    ];

    private CarbonImmutable $now;

    private CarbonImmutable $today;

    public function __construct(private readonly User $user, ?CarbonImmutable $now = null)
    {
        $this->now = $now ?? CarbonImmutable::now();
        $this->today = $this->now->startOfDay();
    }

    public function build(): array
    {
        $locations = Location::where('is_active', true)->orderBy('id')->get(['id', 'code', 'name']);
        $locationNames = Location::pluck('name', 'id');
        $parameters = DB::table('stock_parameters')->whereNotNull('minimum_quantity')->get(['medicine_id', 'location_id', 'minimum_quantity']);
        $shortages = MinimumStockAlerts::query()->orderByRaw('COALESCE(b.quantity, 0)')->orderBy('m.name')->orderBy('l.name')->get();
        $empty = $shortages->filter(fn ($row) => (float) $row->quantity === 0.0);
        $low = $shortages->reject(fn ($row) => (float) $row->quantity === 0.0);
        $months = (int) SystemSetting::value('expiry_warning_months', 3);
        $expiries = $this->expiries($months);
        $expiryBatches = $expiries->unique('batch_id');
        $checks = $this->openChecks();
        $trend = $this->shortageTrend($parameters);
        $chart = $this->chart();
        $steps = $this->steps($locations, $checks);
        $actions = $this->actions($empty, $low, $expiries, $checks, $locationNames, $parameters);

        return [
            'asOf' => $this->now,
            'expiryWarningMonths' => $months,
            'hasMonitoringParameters' => $parameters->isNotEmpty(),
            'stats' => [
                'kosong' => $empty->count(),
                'menipis' => $low->count(),
                'shortages' => $shortages->count(),
                'tracked' => $parameters->count(),
                'aman' => max(0, $parameters->count() - $shortages->count()),
                'expired' => $expiryBatches->where('days', '<', 0)->count(),
                'expiring' => $expiryBatches->where('days', '>=', 0)->count(),
                'stocked' => StockBalance::where('quantity', '>', 0)->distinct()->count('medicine_id'),
                'activeMedicines' => Medicine::where('is_active', true)->count(),
                'verified' => Medicine::where('verification_status', 'terverifikasi')->where('is_active', true)
                    ->whereNotNull('unit_id')->whereNotNull('dosage_form')->where('dosage_form', '<>', '')->count(),
            ],
            'trend' => $trend,
            'shortageSplit' => [
                'kosong' => $empty->countBy('location')->all(),
                'menipis' => $low->countBy('location')->all(),
            ],
            'buckets' => [
                'expired' => $expiryBatches->where('days', '<', 0)->count(),
                'd30' => $expiryBatches->whereBetween('days', [0, 30])->count(),
                'd60' => $expiryBatches->whereBetween('days', [31, 60])->count(),
                'later' => $expiryBatches->where('days', '>', 60)->count(),
            ],
            'shortages' => $shortages,
            'expiries' => $expiries,
            'radar' => $this->radar($expiries, $months),
            'actions' => $actions,
            'chart' => $chart,
            'steps' => $steps,
            'opname' => $this->opnameLine($locationNames),
            'locations' => $this->locationRows($locations, $parameters, $empty, $low, $expiries, $steps),
        ];
    }

    private function expiries(int $months): Collection
    {
        $cutoff = $this->today->addMonths($months)->endOfDay();

        return DB::table('batch_stock_balances as s')
            ->join('batches as bt', 'bt.id', '=', 's.batch_id')
            ->join('medicines as m', 'm.id', '=', 's.medicine_id')
            ->join('locations as l', 'l.id', '=', 's.location_id')
            ->leftJoin('units as u', 'u.id', '=', 'm.unit_id')
            ->where('s.quantity', '>', 0)->whereNotNull('bt.expiry_date')->where('bt.expiry_date', '<=', $cutoff)
            ->orderBy('bt.expiry_date')->orderBy('m.name')->orderBy('l.name')
            ->get(['s.batch_id', 's.medicine_id', 's.location_id', 's.quantity', 'bt.number as batch', 'bt.expiry_date', 'm.code', 'm.name', 'l.name as location', 'u.name as unit'])
            ->map(function ($row) {
                $row->days = (int) round($this->today->diffInDays(CarbonImmutable::parse($row->expiry_date)->startOfDay(), false));

                return $row;
            });
    }

    /** End-of-day kosong/menipis counts for the last 7 days, rebuilt from the movement ledger. */
    private function shortageTrend(Collection $parameters): array
    {
        $kosong = $menipis = array_fill(0, 7, 0);
        if ($parameters->isEmpty()) {
            return compact('kosong', 'menipis');
        }
        $start = $this->today->subDays(6);
        $medicineIds = $parameters->pluck('medicine_id')->unique()->all();
        $balances = DB::table('stock_balances')->whereIn('medicine_id', $medicineIds)->get(['medicine_id', 'location_id', 'quantity'])
            ->mapWithKeys(fn ($row) => [$row->medicine_id.':'.$row->location_id => (float) $row->quantity]);
        $movements = DB::table('inventory_movements')->whereIn('medicine_id', $medicineIds)->where('occurred_at', '>', $start)
            ->get(['medicine_id', 'source_location_id', 'destination_location_id', 'quantity', 'occurred_at'])
            ->map(fn ($row) => [
                'at' => CarbonImmutable::parse($row->occurred_at),
                'in' => $row->destination_location_id ? $row->medicine_id.':'.$row->destination_location_id : null,
                'out' => $row->source_location_id ? $row->medicine_id.':'.$row->source_location_id : null,
                'quantity' => (float) $row->quantity,
            ]);
        foreach (range(0, 6) as $day) {
            $end = $start->addDays($day)->endOfDay();
            $later = [];
            foreach ($movements as $movement) {
                if ($movement['at']->lessThanOrEqualTo($end)) {
                    continue;
                }
                if ($movement['in']) {
                    $later[$movement['in']] = ($later[$movement['in']] ?? 0) + $movement['quantity'];
                }
                if ($movement['out']) {
                    $later[$movement['out']] = ($later[$movement['out']] ?? 0) - $movement['quantity'];
                }
            }
            foreach ($parameters as $parameter) {
                $key = $parameter->medicine_id.':'.$parameter->location_id;
                $balance = round(($balances[$key] ?? 0) - ($later[$key] ?? 0), 4);
                if ($balance <= 0) {
                    $kosong[$day]++;
                } elseif ($balance <= (float) $parameter->minimum_quantity) {
                    $menipis[$day]++;
                }
            }
        }

        return compact('kosong', 'menipis');
    }

    /** Distinct documents per day and series; import lines collapse into their import. */
    private function chart(): array
    {
        $start = $this->today->subDays(6);
        $series = array_map(fn () => array_fill(0, 7, 0), self::CHART_SERIES);
        $typeToSeries = collect(self::CHART_SERIES)->flatMap(fn ($types, $key) => array_fill_keys($types, $key));
        $seen = [];
        DB::table('inventory_movements')->where('occurred_at', '>=', $start)->where('occurred_at', '<', $this->today->addDay())
            ->whereIn('type', $typeToSeries->keys())->orderBy('id')
            ->get(['type', 'reference', 'quantity', 'occurred_at'])
            ->each(function ($row) use (&$series, &$seen, $start, $typeToSeries) {
                if (in_array($row->type, ['stock_opname_adjustment', 'physical_count'], true) && (float) $row->quantity === 0.0) {
                    return;
                }
                $key = $typeToSeries[$row->type];
                $day = (int) round($start->diffInDays(CarbonImmutable::parse($row->occurred_at)->startOfDay()));
                $document = preg_replace('/^(OUT-IMP-\d+)-.*$/', '$1', (string) $row->reference);
                if (! isset($seen[$key][$day][$document])) {
                    $seen[$key][$day][$document] = true;
                    $series[$key][$day]++;
                }
            });
        $days = collect(range(0, 6))->map(fn ($offset) => $start->addDays($offset));

        return [
            'labels' => $days->map(fn ($date, $index) => $index === 6 ? 'Hari ini' : $date->locale('id')->translatedFormat('D j'))->all(),
            ...$series,
            'total' => array_sum(array_map('array_sum', $series)),
            'geometry' => $this->chartGeometry($series),
        ];
    }

    /** SVG paths in a 700×200 box with points at the centre of seven equal columns. */
    private function chartGeometry(array $series): array
    {
        $top = 26;
        $bottom = 174;
        $max = max(2, ...array_map(fn ($values) => max($values), array_values($series)));
        $max = (int) (ceil($max / 2) * 2);
        $x = fn (int $index) => 50 + 100 * $index;
        $y = fn (int $value) => round($bottom - $value / $max * ($bottom - $top), 1);
        $paths = [];
        $dots = [];
        foreach ($series as $key => $values) {
            $line = 'M'.$x(0).' '.$y($values[0]);
            foreach (array_slice($values, 1, null, true) as $index => $value) {
                $line .= sprintf(' C%.1f %.1f %.1f %.1f %d %.1f', $x($index - 1) + 33.3, $y($values[$index - 1]), $x($index) - 33.3, $y($value), $x($index), $y($value));
            }
            $paths[$key] = ['line' => $line, 'area' => $line.sprintf(' L%d %d L%d %d Z', $x(6), $bottom, $x(0), $bottom)];
            foreach ($values as $index => $value) {
                $dots[] = ['series' => $key, 'left' => round(($index + 0.5) / 7 * 100, 3), 'top' => $y($value), 'value' => $value];
            }
        }

        return [
            'paths' => $paths,
            'dots' => $dots,
            'ticks' => [['label' => $max, 'top' => $top], ['label' => $max / 2, 'top' => ($top + $bottom) / 2], ['label' => 0, 'top' => $bottom]],
        ];
    }

    /** Daily checks that are still in someone's hands: submitted, returned, or today's drafts. */
    private function openChecks(): Collection
    {
        $counts = DB::table('nightly_check_items')->selectRaw('nightly_check_id, COUNT(*) as expected, SUM(CASE WHEN quantity IS NOT NULL THEN 1 ELSE 0 END) as counted')
            ->groupBy('nightly_check_id');

        return DB::table('nightly_checks as c')
            ->join('locations as l', 'l.id', '=', 'c.location_id')
            ->leftJoinSub($counts, 'n', 'n.nightly_check_id', '=', 'c.id')
            ->where(fn ($query) => $query->whereDate('c.date', $this->today)->orWhereIn('c.status', ['submitted'])
                ->orWhere(fn ($returned) => $returned->where('c.status', 'draft')->whereNotNull('c.reviewed_by')->whereNotNull('c.review_note')))
            ->orderBy('c.date')->orderBy('l.name')
            ->get(['c.id', 'c.date', 'c.location_id', 'c.status', 'c.review_note', 'c.reviewed_by', 'c.submitted_at', 'c.approved_at', 'l.name as location', 'n.expected', 'n.counted'])
            ->map(function ($check) {
                $check->returned = $check->status === 'draft' && $check->reviewed_by !== null && $check->review_note !== null;
                $check->isToday = CarbonImmutable::parse($check->date)->isSameDay($this->today);

                return $check;
            });
    }

    private function steps(Collection $locations, Collection $checks): array
    {
        $today = $checks->where('isToday', true)->keyBy('location_id');
        $steps = $locations->map(function ($location) use ($today) {
            $check = $today->get($location->id);
            if (! $check) {
                return ['title' => 'Hitung '.$location->name, 'status' => 'Belum dimulai', 'tone' => 'idle', 'detail' => 'Belum ada pemeriksaan hari ini', 'done' => false];
            }
            $url = $this->user->can('view-nightly-checks') ? route('nightly.show', $check->id) : null;
            $expected = (int) $check->expected;
            $counted = (int) $check->counted;

            return match (true) {
                $check->status === 'approved' => ['title' => 'Hitung '.$location->name, 'status' => 'Disetujui', 'tone' => 'ok', 'detail' => 'Disetujui '.$this->time($check->approved_at).' · '.$expected.' item', 'done' => true, 'url' => $url],
                $check->status === 'submitted' => ['title' => 'Hitung '.$location->name, 'status' => 'Terkirim', 'tone' => 'ok', 'detail' => 'Dikirim '.$this->time($check->submitted_at).' · '.$expected.' item', 'done' => true, 'url' => $url],
                $check->returned => ['title' => 'Hitung '.$location->name, 'status' => 'Dikembalikan', 'tone' => 'warn', 'detail' => 'Perlu diperbaiki lalu dikirim ulang', 'done' => false, 'url' => $url],
                default => ['title' => 'Hitung '.$location->name, 'status' => 'Sedang diisi', 'tone' => 'info', 'detail' => $counted.' dari '.$expected.' item dihitung', 'done' => false, 'url' => $url,
                    'progress' => $expected > 0 ? (int) round($counted / $expected * 100) : 0],
            };
        })->values()->all();
        $submitted = $today->where('status', 'submitted')->count();
        $approved = $today->where('status', 'approved')->count();
        $steps[] = match (true) {
            $submitted > 0 => ['title' => 'Persetujuan', 'status' => 'Menunggu', 'tone' => 'warn', 'detail' => $submitted.' laporan menunggu ditinjau', 'done' => false],
            $today->isNotEmpty() && $approved === $today->count() => ['title' => 'Persetujuan', 'status' => 'Disetujui', 'tone' => 'ok', 'detail' => 'Semua laporan hari ini disetujui', 'done' => true],
            default => ['title' => 'Persetujuan', 'status' => 'Belum ada', 'tone' => 'idle', 'detail' => 'Belum ada laporan dikirim', 'done' => false],
        };
        $posted = IssueImport::where('status', 'posted')->latest('posted_at')->first(['id', 'posted_at', 'posted_count']);
        $drafts = IssueImport::where('status', 'draft')->where('created_at', '>=', $this->now->subDays(7))->count();
        $steps[] = match (true) {
            $posted?->posted_at?->isSameDay($this->today) ?? false => ['title' => 'Impor penjualan', 'status' => 'Diposting', 'tone' => 'ok', 'detail' => 'Diposting '.$this->time($posted->posted_at).' · '.(int) $posted->posted_count.' baris', 'done' => true],
            $drafts > 0 => ['title' => 'Impor penjualan', 'status' => 'Draf', 'tone' => 'warn', 'detail' => $drafts.' draf belum diposting', 'done' => false],
            $posted !== null => ['title' => 'Impor penjualan', 'status' => 'Belum', 'tone' => 'idle', 'detail' => 'Terakhir '.$posted->posted_at->locale('id')->translatedFormat('D j M, H.i'), 'done' => false],
            default => ['title' => 'Impor penjualan', 'status' => 'Belum', 'tone' => 'idle', 'detail' => 'Belum pernah diimpor', 'done' => false],
        };

        return $steps;
    }

    private function opnameLine(Collection $locationNames): string
    {
        $period = $this->today->format('Y-m');
        $label = $this->today->locale('id')->translatedFormat('F Y');
        $sessions = StockOpnameSession::where('period', $period)->get(['location_id', 'status']);
        if ($sessions->isEmpty()) {
            return 'Opname '.$label.': sesi belum dibuat';
        }

        return 'Opname '.$label.': '.$sessions->map(fn ($session) => ($locationNames[$session->location_id] ?? 'Lokasi').' '.($session->status === 'finalized' ? 'final' : 'draf'))->implode(', ');
    }

    /** Ranked follow-up list: segera (empty, expired), menunggu (checks, imports), pantau (low, ED ≤ 30 days). */
    private function actions(Collection $empty, Collection $low, Collection $expiries, Collection $checks, Collection $locationNames, Collection $parameters): array
    {
        $minimums = $parameters->mapWithKeys(fn ($row) => [$row->medicine_id.':'.$row->location_id => (float) $row->minimum_quantity]);
        $canTransfer = $this->user->can('operate-inventory') && $this->user->can('view-transfers');
        $canAdjust = $this->user->can('operate-inventory') && $this->user->can('view-special-adjustments');
        $canShortages = $this->user->can('view-shortages');
        $canExpiry = $this->user->can('view-expiry');
        $canNightly = $this->user->can('view-nightly-checks');
        $otherStock = StockBalance::whereIn('medicine_id', $empty->merge($low)->pluck('medicine_id')->unique())
            ->where('quantity', '>', 0)->get(['medicine_id', 'location_id', 'quantity'])->groupBy('medicine_id');
        $items = [];
        $stockItem = function ($row, bool $isEmpty) use (&$items, $otherStock, $locationNames, $minimums, $canTransfer, $canShortages) {
            // A location can only lend what it holds above its own minimum.
            $source = $otherStock->get($row->medicine_id, collect())->where('location_id', '!=', $row->location_id)
                ->filter(fn ($balance) => (float) $balance->quantity > ($minimums[$balance->medicine_id.':'.$balance->location_id] ?? 0))
                ->sortByDesc(fn ($balance) => (float) $balance->quantity)->first();
            $unit = $row->unit ? ' '.$row->unit : '';
            $sourceName = $source ? ($locationNames[$source->location_id] ?? 'Lokasi lain') : null;
            $hint = $source ? $sourceName.' masih '.Quantity::format($source->quantity).$unit : 'Tidak ada stok lebih di lokasi lain, perlu pesanan ke PBF';
            $shortageUrl = $canShortages ? route('shortages.index', ['search' => $row->code, 'location_id' => $row->location_id]).'#stok-minimum' : null;
            [$action, $actionUrl] = $source && $canTransfer
                ? ['Transfer dari '.$sourceName, route('preview.transfers', ['medicine_id' => $row->medicine_id, 'source_location_id' => $source->location_id, 'destination_location_id' => $row->location_id])]
                : [$source ? 'Lihat' : 'Ajukan permintaan', $shortageUrl];
            $items[] = [
                'group' => $isEmpty ? 'segera' : 'pantau', 'rank' => $isEmpty ? 1 : 4, 'kind' => 'stock',
                'chip' => $isEmpty ? 'Kosong' : 'Menipis', 'tone' => $isEmpty ? 'crit' : 'warn',
                'title' => $row->name, 'url' => $shortageUrl, 'location' => $row->location,
                'meta' => $row->location.' · '.Quantity::format($row->quantity).$unit.' dari minimum '.Quantity::format($row->minimum_quantity).$unit.' · '.$hint,
                'action' => $actionUrl ? $action : null, 'actionUrl' => $actionUrl,
            ];
        };
        $empty->each(fn ($row) => $stockItem($row, true));
        foreach ($expiries->where('days', '<', 0) as $row) {
            $expiryUrl = $canExpiry ? route('preview.expiry', ['search' => $row->code, 'status' => 'expired']) : null;
            $items[] = [
                'group' => 'segera', 'rank' => 2, 'kind' => 'expiry', 'chip' => 'Lewat ED', 'tone' => 'violet',
                'title' => $row->name.' · '.$row->batch, 'url' => $expiryUrl, 'location' => $row->location,
                'meta' => $row->location.' · '.Quantity::format($row->quantity).($row->unit ? ' '.$row->unit : '').' · ED '.$this->date($row->expiry_date).', masih tercatat stok',
                'action' => $canAdjust ? 'Catat kondisi khusus' : ($expiryUrl ? 'Lihat batch' : null),
                'actionUrl' => $canAdjust
                    ? route('special-adjustments.index', ['medicine_id' => $row->medicine_id, 'batch_id' => $row->batch_id, 'location_id' => $row->location_id, 'condition' => 'expired'])
                    : $expiryUrl,
            ];
        }
        foreach ($checks as $check) {
            $url = $canNightly ? route('nightly.show', $check->id) : null;
            $label = 'Pemeriksaan harian '.$check->location.', '.$this->date($check->date);
            if ($check->status === 'submitted') {
                $items[] = ['group' => 'menunggu', 'rank' => 3, 'kind' => 'process', 'chip' => 'Persetujuan', 'tone' => 'info', 'title' => $label, 'url' => $url, 'location' => $check->location,
                    'meta' => 'Dikirim '.$this->time($check->submitted_at).' · '.(int) $check->expected.' item · menunggu kepala apoteker',
                    'action' => $url ? ($this->user->can('finalize-opname') ? 'Tinjau' : 'Lihat laporan') : null, 'actionUrl' => $url];
            } elseif ($check->returned) {
                $items[] = ['group' => 'menunggu', 'rank' => 3, 'kind' => 'process', 'chip' => 'Dikembalikan', 'tone' => 'warn', 'title' => $label, 'url' => $url, 'location' => $check->location,
                    'meta' => 'Catatan: '.$check->review_note, 'action' => $url ? 'Perbaiki' : null, 'actionUrl' => $url];
            } elseif ($check->status === 'draft' && $check->isToday) {
                $items[] = ['group' => 'menunggu', 'rank' => 5, 'kind' => 'process', 'chip' => 'Sedang diisi', 'tone' => 'info', 'title' => $label, 'url' => $url, 'location' => $check->location,
                    'meta' => (int) $check->counted.' dari '.(int) $check->expected.' item dihitung · belum dikirim', 'action' => $url ? 'Lanjutkan' : null, 'actionUrl' => $url];
            }
        }
        foreach ($expiries->whereBetween('days', [0, 30]) as $row) {
            $expiryUrl = $canExpiry ? route('preview.expiry', ['search' => $row->code, 'status' => 'near']) : null;
            $items[] = [
                'group' => 'pantau', 'rank' => 3, 'kind' => 'expiry', 'chip' => 'ED '.$row->days.' hari', 'tone' => 'violet',
                'title' => $row->name.' · '.$row->batch, 'url' => $expiryUrl, 'location' => $row->location,
                'meta' => $row->location.' · '.Quantity::format($row->quantity).($row->unit ? ' '.$row->unit : '').' · ED '.$this->date($row->expiry_date).' · dahulukan saat dipakai',
                'action' => $expiryUrl ? 'Lihat batch' : null, 'actionUrl' => $expiryUrl,
            ];
        }
        $low->each(fn ($row) => $stockItem($row, false));
        if ($this->user->can('view-issues')) {
            IssueImport::where('status', 'draft')->where('created_at', '>=', $this->now->subDays(7))->orderBy('created_at')
                ->get(['id', 'period_start', 'period_end', 'row_count'])
                ->each(function ($import) use (&$items) {
                    $period = $import->period_start ? $import->period_start->locale('id')->translatedFormat('j M').'–'.$import->period_end?->locale('id')->translatedFormat('j M') : 'periode belum terbaca';
                    $items[] = ['group' => 'menunggu', 'rank' => 5, 'kind' => 'process', 'chip' => 'Impor', 'tone' => 'neutral', 'title' => 'Impor penjualan #'.$import->id,
                        'url' => route('issue-imports.show', $import->id), 'location' => null,
                        'meta' => 'Periode '.$period.' · '.(int) $import->row_count.' baris · belum diposting', 'action' => 'Lanjutkan', 'actionUrl' => route('issue-imports.show', $import->id)];
                });
        }

        $groupOrder = ['segera' => 0, 'menunggu' => 1, 'pantau' => 2];

        return collect($items)->sortBy([fn ($a, $b) => $a['rank'] <=> $b['rank'], fn ($a, $b) => $groupOrder[$a['group']] <=> $groupOrder[$b['group']]])->values()->all();
    }

    private function radar(Collection $expiries, int $months): array
    {
        $window = max(61, (int) round($this->today->diffInDays($this->today->addMonths($months))));
        $span = 15 + $window;
        $zones = collect([[-15, 0, 'Lewat'], [0, 30, '0–30'], [30, 60, '31–60'], [60, $window, '61+ hari']])
            ->map(fn ($zone) => ['label' => $zone[2], 'width' => round(($zone[1] - $zone[0]) / $span * 100, 4), 'bucket' => match (true) {
                $zone[1] <= 0 => 'expired', $zone[1] <= 30 => 'd30', $zone[1] <= 60 => 'd60', default => 'later',
            }])->all();
        $markers = $expiries->take(6)->values()->map(fn ($row, $index) => [
            'number' => $index + 1,
            'left' => round((max(-15, min($window, $row->days)) + 15) / $span * 100, 2),
            'bucket' => match (true) { $row->days < 0 => 'expired', $row->days <= 30 => 'd30', $row->days <= 60 => 'd60', default => 'later' },
            'name' => $row->name,
            'place' => $row->location.' '.Quantity::format($row->quantity).($row->unit ? ' '.$row->unit : ''),
            'when' => $row->days < 0 ? 'lewat '.abs($row->days).' hari' : ($row->days === 0 ? 'hari ini' : $row->days.' hari'),
        ])->all();

        return ['zones' => $zones, 'todayLeft' => round(15 / $span * 100, 4), 'markers' => $markers, 'more' => max(0, $expiries->count() - 6)];
    }

    private function locationRows(Collection $locations, Collection $parameters, Collection $empty, Collection $low, Collection $expiries, array $steps): array
    {
        $tracked = $parameters->countBy('location_id');
        $stepByTitle = collect($steps)->keyBy('title');

        return $locations->map(function ($location) use ($tracked, $empty, $low, $expiries, $stepByTitle) {
            $kosong = $empty->where('location_id', $location->id)->count();
            $menipis = $low->where('location_id', $location->id)->count();
            $total = (int) ($tracked[$location->id] ?? 0);
            $step = $stepByTitle->get('Hitung '.$location->name);

            return [
                'id' => $location->id, 'name' => $location->name, 'code' => $location->code,
                'tracked' => $total, 'kosong' => $kosong, 'menipis' => $menipis, 'aman' => max(0, $total - $kosong - $menipis),
                'expiring' => $expiries->where('location_id', $location->id)->unique('batch_id')->count(),
                'today' => $step['status'] ?? 'Belum dimulai', 'tone' => $step['tone'] ?? 'idle',
            ];
        })->values()->all();
    }

    private function time(mixed $value): string
    {
        return $value ? CarbonImmutable::parse($value)->format('H.i') : '-';
    }

    private function date(mixed $value): string
    {
        return CarbonImmutable::parse($value)->locale('id')->translatedFormat('j M Y');
    }
}
