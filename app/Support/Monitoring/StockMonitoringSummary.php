<?php

namespace App\Support\Monitoring;

use App\Models\Location;
use App\Models\SystemSetting;
use App\Models\User;
use App\Queries\LatestNightlyItems;
use App\Queries\MinimumStockAlerts;
use App\Support\Quantity;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Shapes the Monitoring Stok page: one row per medicine below its minimum, batches near expiry,
 * and the latest approved count results. Suggestions follow the clinic's flow: the warehouse
 * supplies the other locations, and anything the warehouse cannot cover is ordered from a PBF.
 */
final class StockMonitoringSummary
{
    public const BUCKETS = ['expired', 'd30', 'd60', 'later'];

    private CarbonImmutable $now;

    private CarbonImmutable $today;

    public function __construct(private readonly User $user, ?CarbonImmutable $now = null)
    {
        $this->now = $now ?? CarbonImmutable::now('Asia/Jakarta');
        $this->today = $this->now->startOfDay();
    }

    public function build(): array
    {
        $alerts = MinimumStockAlerts::query()->get();
        $warehouseId = $this->warehouseId();
        $locations = Location::where('is_active', true)->orWhereIn('id', $alerts->pluck('location_id')->unique())->get(['id', 'code', 'name'])
            ->sortBy([fn ($a, $b) => ($a->id !== $warehouseId) <=> ($b->id !== $warehouseId), fn ($a, $b) => strcmp($a->name, $b->name)])->values();
        $warehouse = $locations->firstWhere('id', $warehouseId);
        $months = (int) SystemSetting::value('expiry_warning_months', 3);
        $minimum = $this->minimum($alerts, $locations, $warehouse);
        $expiries = $this->expiries($months, $warehouse, $locations);
        $counts = $this->counts();
        $cells = collect($minimum['medicines'])->flatMap(fn ($medicine) => $medicine['cells']);
        $batches = $expiries->unique('batch_id');
        $expired = $batches->where('bucket', 'expired');
        $near = $batches->where('bucket', '!=', 'expired');

        return [
            'asOf' => $this->now,
            'expiryWarningMonths' => $months,
            'hasParameters' => DB::table('stock_parameters')->whereNotNull('minimum_quantity')->exists(),
            'locations' => $locations->map(fn ($location) => ['id' => $location->id, 'name' => $location->name])->all(),
            'warehouse' => $warehouse?->name,
            'medicines' => $minimum['medicines'],
            'transfers' => $minimum['transfers'],
            'orders' => $minimum['orders'],
            'orderText' => $this->orderText($minimum['orders']),
            'expiries' => $expiries->all(),
            'counts' => $counts->all(),
            'countText' => $this->countText($counts),
            'stats' => [
                'kosong' => $cells->where('status', 'kosong')->count(),
                'menipis' => $cells->where('status', 'menipis')->count(),
                'kosongSplit' => $this->split($cells->where('status', 'kosong')),
                'menipisSplit' => $this->split($cells->where('status', 'menipis')),
                'expired' => $expired->count(),
                'expiredFirst' => $expired->first() ? $expired->first()['name'].' di '.$expired->first()['location'].($expired->count() > 1 ? ' dan '.($expired->count() - 1).' lainnya' : '') : null,
                'near' => $near->count(),
                'nearestDays' => $near->min('days'),
                'buckets' => collect(self::BUCKETS)->mapWithKeys(fn ($bucket) => [$bucket => $batches->where('bucket', $bucket)->count()])->all(),
                'requested' => $counts->whereNotNull('requested')->count(),
                'unrequested' => $counts->whereNull('requested')->count(),
            ],
        ];
    }

    /** Where receipts land is where the other locations are refilled from. */
    private function warehouseId(): ?int
    {
        $setting = SystemSetting::value('default_receipt_location_id');
        if ($setting && Location::whereKey($setting)->where('is_active', true)->exists()) {
            return (int) $setting;
        }

        return Location::where('code', 'GDG')->where('is_active', true)->value('id');
    }

    private function minimum(Collection $alerts, Collection $locations, ?Location $warehouse): array
    {
        $medicineIds = $alerts->pluck('medicine_id')->unique()->values();
        $balances = DB::table('stock_balances')->whereIn('medicine_id', $medicineIds)->get(['medicine_id', 'location_id', 'quantity'])
            ->mapWithKeys(fn ($row) => [$row->medicine_id.':'.$row->location_id => (float) $row->quantity]);
        $minimums = DB::table('stock_parameters')->whereIn('medicine_id', $medicineIds)->whereNotNull('minimum_quantity')->get(['medicine_id', 'location_id', 'minimum_quantity'])
            ->mapWithKeys(fn ($row) => [$row->medicine_id.':'.$row->location_id => (float) $row->minimum_quantity]);
        $canTransfer = $this->user->can('operate-inventory') && $this->user->can('view-transfers');
        $medicines = [];
        $transfers = [];
        $orders = [];

        foreach ($alerts->unique('medicine_id') as $alert) {
            $unit = $alert->unit ? ' '.$alert->unit : '';
            $cells = $locations->map(function ($location) use ($alert, $balances, $minimums) {
                $key = $alert->medicine_id.':'.$location->id;
                $quantity = $balances[$key] ?? 0.0;
                $minimum = $minimums[$key] ?? null;

                return [
                    'location_id' => $location->id, 'location' => $location->name, 'quantity' => $quantity, 'minimum' => $minimum,
                    'status' => match (true) {
                        $minimum === null => 'none',
                        $quantity <= 0 => 'kosong',
                        $quantity <= $minimum => 'menipis',
                        default => 'aman',
                    },
                ];
            })->values();
            $short = $cells->whereIn('status', ['kosong', 'menipis']);
            $source = $warehouse ? $cells->firstWhere('location_id', $warehouse->id) : null;
            // The warehouse only lends what it holds above its own minimum.
            $surplus = $source && ! in_array($source['status'], ['kosong', 'menipis'], true) ? $source['quantity'] - ($source['minimum'] ?? 0) : 0.0;
            $moves = [];
            $order = 0.0;
            $orderOpen = false;

            foreach ($short->where('location_id', '!=', $warehouse?->id)->sortBy(fn ($cell) => $cell['status'] === 'kosong' ? 0 : 1) as $cell) {
                $need = max(0.0, $cell['minimum'] - $cell['quantity']);
                if ($surplus > 0) {
                    $quantity = $need > 0 ? min($need, $surplus) : null;
                    $surplus -= $quantity ?? 0;
                    $moves[] = ['destination_id' => $cell['location_id'], 'destination' => $cell['location'], 'quantity' => $quantity];
                    $order += $need - ($quantity ?? 0);
                } else {
                    $order += $need;
                    $orderOpen = $orderOpen || $need == 0;
                }
            }
            $warehouseShort = $source && in_array($source['status'], ['kosong', 'menipis'], true);
            if ($warehouseShort) {
                $order += max(0.0, $source['minimum'] - $source['quantity']);
                $orderOpen = $orderOpen || $source['minimum'] - $source['quantity'] <= 0;
            }

            $suggestion = null;
            if ($moves) {
                $move = $moves[0];
                $label = 'Transfer '.($move['quantity'] !== null ? Quantity::format($move['quantity']).$unit.' ' : '').'dari '.$warehouse->name;
                $note = $warehouse->name.' masih '.Quantity::format($source['quantity']).$unit.(count($moves) > 1 ? ' · juga ke '.collect($moves)->skip(1)->pluck('destination')->implode(', ') : '')
                    .($order > 0 ? ' · sisa ± '.Quantity::format($order).$unit.' dipesan ke PBF' : '');
                $suggestion = ['type' => 'transfer', 'label' => $label, 'note' => $note,
                    'url' => $canTransfer ? $this->transferUrl($warehouse->id, $move['destination_id'], [['medicine_id' => $alert->medicine_id, 'quantity' => $move['quantity']]]) : null];
                foreach ($moves as $move) {
                    $transfers[$move['destination_id']] ??= ['destination_id' => $move['destination_id'], 'destination' => $move['destination'], 'items' => []];
                    $transfers[$move['destination_id']]['items'][] = ['medicine_id' => $alert->medicine_id, 'name' => $alert->name, 'code' => $alert->code, 'quantity' => $move['quantity'], 'unit' => $alert->unit];
                }
            } elseif ($order > 0 || $orderOpen) {
                $note = match (true) {
                    $warehouseShort && $short->count() > 1 => 'Semua lokasi di bawah minimum',
                    $warehouseShort => 'Stok '.$warehouse->name.' di bawah minimum',
                    $warehouse !== null => $warehouse->name.' tidak punya stok lebih',
                    default => 'Tidak ada lokasi sumber untuk transfer',
                };
                $suggestion = ['type' => 'order', 'label' => $order > 0 ? 'Pesan ± '.Quantity::format($order).$unit.' ke PBF' : 'Pesan ke PBF', 'note' => $note, 'url' => null];
            }
            if ($order > 0 || ($orderOpen && ! $moves)) {
                $orders[] = ['medicine_id' => $alert->medicine_id, 'name' => $alert->name, 'code' => $alert->code, 'quantity' => $order > 0 ? $order : null, 'unit' => $alert->unit];
            }

            $medicines[] = [
                'medicine_id' => $alert->medicine_id, 'code' => $alert->code, 'name' => $alert->name, 'unit' => $alert->unit,
                'worst' => $short->contains('status', 'kosong') ? 'kosong' : 'menipis',
                'cells' => $cells->all(),
                'suggestion' => $suggestion,
            ];
        }

        usort($medicines, fn ($a, $b) => [$a['worst'] === 'kosong' ? 0 : 1, $a['name']] <=> [$b['worst'] === 'kosong' ? 0 : 1, $b['name']]);
        usort($orders, fn ($a, $b) => strcmp($a['name'], $b['name']));
        $transfers = collect($transfers)->map(function ($group) use ($warehouse, $canTransfer) {
            $items = collect($group['items'])->sortBy('name')->values();
            $group['items'] = $items->all();
            // The transfer form takes at most 50 lines per document.
            $group['url'] = $canTransfer ? $this->transferUrl($warehouse->id, $group['destination_id'], $items->take(50)->all()) : null;
            $group['source'] = $warehouse->name;

            return $group;
        })->values()->all();

        return compact('medicines', 'transfers', 'orders');
    }

    private function transferUrl(int $sourceId, int $destinationId, array $items): string
    {
        return route('preview.transfers', [
            'source_location_id' => $sourceId,
            'destination_location_id' => $destinationId,
            'items' => collect($items)->map(fn ($item) => array_filter([
                'medicine_id' => $item['medicine_id'],
                'quantity' => $item['quantity'] !== null ? Quantity::input($item['quantity']) : null,
            ], fn ($value) => $value !== null))->all(),
        ]);
    }

    private function expiries(int $months, ?Location $warehouse, Collection $locations): Collection
    {
        $cutoff = $this->today->addMonths($months)->endOfDay();
        $canAdjust = $this->user->can('operate-inventory') && $this->user->can('view-special-adjustments');
        $canReturn = $this->user->can('operate-inventory') && $this->user->can('view-returns');
        $canCard = $this->user->can('view-stock-card');

        return DB::table('batch_stock_balances as s')
            ->join('batches as bt', 'bt.id', '=', 's.batch_id')
            ->join('medicines as m', 'm.id', '=', 's.medicine_id')
            ->join('locations as l', 'l.id', '=', 's.location_id')
            ->leftJoin('units as u', 'u.id', '=', 'm.unit_id')
            ->where('s.quantity', '>', 0)->whereNotNull('bt.expiry_date')->where('bt.expiry_date', '<=', $cutoff)
            ->orderBy('bt.expiry_date')->orderBy('m.name')->orderBy('l.name')
            ->get(['s.batch_id', 's.medicine_id', 's.location_id', 's.quantity', 'bt.number as batch', 'bt.expiry_date', 'm.code', 'm.name', 'l.name as location', 'u.name as unit'])
            ->map(function ($row) use ($canAdjust, $canReturn, $canCard) {
                $days = (int) round($this->today->diffInDays(CarbonImmutable::parse($row->expiry_date, 'Asia/Jakarta')->startOfDay(), false));
                $bucket = match (true) {
                    $days < 0 => 'expired', $days <= 30 => 'd30', $days <= 60 => 'd60', default => 'later'
                };
                $actions = [];
                if ($bucket === 'expired' && $canAdjust) {
                    $actions[] = ['label' => 'Catat kondisi khusus', 'tone' => 'crit', 'url' => route('special-adjustments.index', ['medicine_id' => $row->medicine_id, 'batch_id' => $row->batch_id, 'location_id' => $row->location_id, 'condition' => 'expired'])];
                }
                if ($bucket === 'expired' && $canReturn) {
                    $actions[] = ['label' => 'Retur ke PBF', 'tone' => 'plain', 'url' => route('preview.returns', ['medicine_id' => $row->medicine_id, 'batch_id' => $row->batch_id, 'source_location_id' => $row->location_id])];
                }
                if ($canCard) {
                    $actions[] = ['label' => 'Kartu stok', 'tone' => 'plain', 'url' => route('stock-card.index', ['medicine_id' => $row->medicine_id, 'location_id' => $row->location_id, 'batch_id' => $row->batch_id,
                        'start' => $this->today->subDays(30)->toDateString(), 'end' => $this->today->toDateString()])];
                }

                return [
                    'batch_id' => $row->batch_id, 'medicine_id' => $row->medicine_id, 'location_id' => $row->location_id,
                    'name' => $row->name, 'code' => $row->code, 'batch' => $row->batch, 'location' => $row->location,
                    'detail' => $row->location.' '.Quantity::format($row->quantity).($row->unit ? ' '.$row->unit : '').' · ED '.CarbonImmutable::parse($row->expiry_date)->locale('id')->translatedFormat('j M Y'),
                    'days' => $days, 'bucket' => $bucket,
                    'when' => match (true) {
                        $days < 0 => 'Lewat '.abs($days).' hari', $days === 0 => 'Hari ini', default => $days.' hari lagi'
                    },
                    'actions' => $actions,
                ];
            });
    }

    /** Latest approved count per medicine and location that came out empty or low. */
    private function counts(): Collection
    {
        $canNightly = $this->user->can('view-nightly-checks');

        return LatestNightlyItems::query()
            ->join('locations as l', 'l.id', '=', 'c.location_id')
            ->where(fn ($query) => $query->where('i.quantity', 0)->orWhere('i.is_low', true))
            ->orderBy('i.name')->orderBy('l.name')
            ->get(['i.medicine_id', 'i.nightly_check_id', 'i.name', 'i.code', 'i.unit', 'i.quantity', 'i.requested_quantity', 'c.date', 'c.location_id', 'l.name as location'])
            ->map(fn ($row) => [
                'medicine_id' => $row->medicine_id, 'location_id' => $row->location_id, 'name' => $row->name, 'code' => $row->code, 'location' => $row->location,
                'status' => (float) $row->quantity === 0.0 ? 'kosong' : 'menipis',
                'detail' => $row->location.' · dihitung '.CarbonImmutable::parse($row->date)->locale('id')->translatedFormat('D j M').' · stok fisik '.Quantity::format($row->quantity).($row->unit ? ' '.$row->unit : ''),
                'requested' => $row->requested_quantity === null ? null : Quantity::format($row->requested_quantity).($row->unit ? ' '.$row->unit : ''),
                'url' => $canNightly ? route('nightly.show', $row->nightly_check_id) : null,
            ]);
    }

    private function split(Collection $cells): string
    {
        return $cells->countBy('location')->map(fn ($count, $location) => $count.' di '.$location)->implode(' · ');
    }

    private function orderText(array $orders): string
    {
        return collect($orders)->values()->map(fn ($order, $index) => ($index + 1).'. '.$order['name'].' ('.$order['code'].'): '
            .($order['quantity'] !== null ? '± '.Quantity::format($order['quantity']).($order['unit'] ? ' '.$order['unit'] : '') : 'jumlah ditentukan'))
            ->prepend('Daftar pesanan ke PBF, '.$this->today->locale('id')->translatedFormat('j F Y'))->implode("\n");
    }

    private function countText(Collection $counts): string
    {
        return $counts->values()->map(fn ($row, $index) => ($index + 1).'. '.$row['name'].' ('.$row['location'].'): '.($row['requested'] ?? 'jumlah belum ditentukan'))
            ->prepend('Permintaan dari pemeriksaan, '.$this->today->locale('id')->translatedFormat('j F Y'))->implode("\n");
    }
}
