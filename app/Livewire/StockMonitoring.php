<?php

namespace App\Livewire;

use App\Support\Monitoring\StockMonitoringSummary;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;

class StockMonitoring extends Component
{
    public const TABS = ['minimum', 'ed', 'hitung'];

    /** KPI filters: the first two narrow the minimum tab, the last two the expiry tab. */
    public const FILTERS = ['kosong' => 'minimum', 'menipis' => 'minimum', 'expired' => 'ed', 'near' => 'ed'];

    public const PAGE_SIZE = 40;

    #[Locked]
    public string $tab = 'minimum';

    #[Locked]
    public ?int $locationId = null;

    #[Locked]
    public ?string $filter = null;

    #[Locked]
    public int $limit = self::PAGE_SIZE;

    public string $search = '';

    public function mount(): void
    {
        Gate::authorize('view-shortages');
        // Links from the dashboard and older bookmarks still use these query parameters.
        $validator = Validator::make(request()->query(), [
            'tab' => ['nullable', Rule::in(self::TABS)],
            'search' => ['nullable', 'string', 'max:255'],
            'location_id' => ['nullable', 'integer', 'exists:locations,id'],
            'status' => ['nullable', Rule::in(['all', 'empty', 'low', ...array_keys(self::FILTERS)])],
        ]);
        if ($validator->fails()) {
            // Livewire would turn a ValidationException into an error bag; a broken link should fail like any other page.
            throw new HttpResponseException(request()->expectsJson()
                ? response()->json(['message' => $validator->errors()->first(), 'errors' => $validator->errors()], 422)
                : new RedirectResponse(route('shortages.index')));
        }
        $query = $validator->validated();
        $this->search = $query['search'] ?? '';
        $this->locationId = isset($query['location_id']) ? (int) $query['location_id'] : null;
        $this->filter = match ($query['status'] ?? 'all') {
            'all' => null, 'empty' => 'kosong', 'low' => 'menipis', default => $query['status']
        };
        $this->tab = $this->filter ? self::FILTERS[$this->filter] : ($query['tab'] ?? 'minimum');
    }

    public function setTab(string $tab): void
    {
        $this->tab = in_array($tab, self::TABS, true) ? $tab : 'minimum';
        $this->filter = null;
        $this->limit = self::PAGE_SIZE;
    }

    public function toggleFilter(string $filter): void
    {
        if (! array_key_exists($filter, self::FILTERS)) {
            return;
        }
        $this->filter = $this->filter === $filter ? null : $filter;
        $this->tab = self::FILTERS[$filter];
        $this->limit = self::PAGE_SIZE;
    }

    public function setLocation(?int $locationId = null): void
    {
        $this->locationId = $locationId ?: null;
        $this->limit = self::PAGE_SIZE;
    }

    public function showMore(): void
    {
        $this->limit += self::PAGE_SIZE;
    }

    public function updatedSearch(): void
    {
        $this->search = mb_substr($this->search, 0, 255);
        $this->limit = self::PAGE_SIZE;
    }

    public function render()
    {
        Gate::authorize('view-shortages');
        $user = auth()->user();
        $data = (new StockMonitoringSummary($user))->build();
        $canExpiry = $user->can('view-expiry');
        $tab = $this->tab === 'ed' && ! $canExpiry ? 'minimum' : $this->tab;
        $needle = mb_strtolower(trim($this->search));
        $matches = fn (?string ...$fields) => $needle === '' || collect($fields)->contains(fn ($field) => str_contains(mb_strtolower((string) $field), $needle));
        $atLocation = fn (array $row) => $this->locationId === null || $row['location_id'] === $this->locationId;

        $medicines = collect($data['medicines'])->filter(function ($medicine) use ($matches, $atLocation) {
            $short = collect($medicine['cells'])->whereIn('status', ['kosong', 'menipis'])->filter($atLocation);

            return $short->isNotEmpty() && $matches($medicine['name'], $medicine['code'])
                && (! in_array($this->filter, ['kosong', 'menipis'], true) || $short->contains('status', $this->filter));
        })->values();
        $expiries = collect($data['expiries'])->filter(fn ($row) => $atLocation($row) && $matches($row['name'], $row['code'], $row['batch'])
            && match ($this->filter) {
                'expired' => $row['bucket'] === 'expired', 'near' => $row['bucket'] !== 'expired', default => true
            })->values();
        $counts = collect($data['counts'])->filter(fn ($row) => $atLocation($row) && $matches($row['name'], $row['code']))->values();
        $visible = match ($tab) {
            'ed' => $expiries, 'hitung' => $counts, default => $medicines
        };

        return view('livewire.stock-monitoring', [
            ...$data,
            'activeTab' => $tab,
            'canExpiry' => $canExpiry,
            'tabCounts' => ['minimum' => count($data['medicines']), 'ed' => $data['stats']['expired'] + $data['stats']['near'], 'hitung' => count($data['counts'])],
            'visibleMedicines' => $tab === 'minimum' ? $medicines->take($this->limit)->all() : [],
            'visibleExpiries' => $tab === 'ed' ? $expiries->take($this->limit)->groupBy('bucket')->all() : [],
            'visibleCounts' => $tab === 'hitung' ? $counts->take($this->limit)->all() : [],
            'resultCount' => $visible->count(),
            'expiryBatchCount' => $expiries->unique('batch_id')->count(),
            'hiddenCount' => max(0, $visible->count() - $this->limit),
        ])->layout('components.layouts.app', ['title' => 'Monitoring Stok', 'hideDescription' => true]);
    }
}
