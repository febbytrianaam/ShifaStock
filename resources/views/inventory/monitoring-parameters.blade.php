<x-layouts.app title="Parameter Monitoring">
    @php
        $canManage = auth()->user()->can('manage-monitoring-parameters');
        $locationIds = $locations->pluck('id');
        $missingFor = fn ($medicine) => $locations->filter(fn ($location) => $medicine->stockParameters->firstWhere('location_id', $location->id)?->minimum_quantity === null)->count();
        $pairCount = $medicines->count() * $locations->count();
        $setCount = $medicines->sum(fn ($medicine) => $medicine->stockParameters->whereIn('location_id', $locationIds)->whereNotNull('minimum_quantity')->count());
        $incomplete = $medicines->filter(fn ($medicine) => $missingFor($medicine) > 0)->count();
        // Client-side filter only (Alpine ships with Livewire); rows stay in the form, so hidden values are still submitted.
        $filter = "{ q: '', unset: false, match(row) { const q = this.q.trim().toLowerCase(); return (q === '' || row.dataset.search.includes(q)) && (! this.unset || row.dataset.missing !== '0'); } }";
    @endphp

    @if(session('status'))
        <div role="status" class="callout mb-4 font-semibold">{{ session('status') }}</div>
    @endif
    @if($errors->any())
        <div role="alert" class="callout-danger mb-4 font-semibold">{{ $errors->first() }}</div>
    @endif

    @can('manage-monitoring-parameters')
    <form method="POST" action="{{ route('monitoring-parameters.store') }}" x-data="{{ $filter }}" data-required-alert="Lengkapi parameter monitoring" novalidate>
        @csrf
    @else
    <div x-data="{{ $filter }}">
    @endcan
        <div class="grid grid-cols-2 gap-3 xl:grid-cols-[minmax(0,1.3fr)_repeat(3,minmax(0,1fr))] xl:gap-4">
            <section class="card col-span-2 flex min-w-0 flex-col justify-center px-4 py-3.5 xl:col-span-1" aria-labelledby="judul-peringatan-ed">
                <div class="flex items-center gap-2.5">
                    <span class="icon-tile bg-violet-100 text-violet-700"><x-ui.icon name="calendar" /></span>
                    <div class="min-w-0">
                        <label class="block text-[15px] font-extrabold text-slate-900" id="judul-peringatan-ed" for="expiry-warning-months">Peringatan kedaluwarsa</label>
                        <p class="text-xs text-slate-500">Dipakai oleh dashboard dan monitoring kedaluwarsa.</p>
                    </div>
                </div>
                <div class="mt-3 flex items-center gap-2.5">
                    @can('manage-monitoring-parameters')
                    <input class="field min-h-11 w-24 tabular-nums lg:min-h-9" id="expiry-warning-months" name="expiry_warning_months" type="number" min="1" max="24" value="{{ old('expiry_warning_months', $expiryWarningMonths) }}" required>
                    @else
                    <span class="text-[26px] font-extrabold leading-none tabular-nums text-violet-700" id="expiry-warning-months">{{ $expiryWarningMonths }}</span>
                    @endcan
                    <span class="text-[13px] font-semibold text-slate-600">bulan sebelum ED</span>
                </div>
            </section>
            <x-ui.stat label="Obat dipantau" :value="\App\Support\Quantity::format($medicines->count())" unit="obat" :sub="$locations->count().' lokasi aktif'" theme="brand" icon="medicine" />
            <x-ui.stat label="Minimum diatur" :value="\App\Support\Quantity::format($setCount)" :unit="'dari '.\App\Support\Quantity::format($pairCount)" sub="Pasangan obat dan lokasi" :theme="$pairCount > 0 && $setCount === $pairCount ? 'green' : 'teal'" icon="ok" />
            <x-ui.stat label="Belum lengkap" :value="\App\Support\Quantity::format($incomplete)" unit="obat" :sub="$incomplete > 0 ? 'Ada lokasi tanpa batas minimum' : 'Semua lokasi sudah diatur'" :theme="$incomplete > 0 ? 'amber' : 'green'" icon="alert" class="col-span-2 xl:col-span-1" />
        </div>

        <div class="mt-4 flex flex-wrap items-center justify-between gap-2.5">
            <div class="flex w-full flex-wrap items-center gap-2 sm:w-auto">
                <x-ui.search label="Cari obat" x-model="q" x-on:keydown.enter.prevent="" placeholder="Cari nama atau kode obat" maxlength="255" width="sm:w-72" />
                <button type="button" class="pill" x-on:click="unset = ! unset" x-bind:class="unset && 'pill-active'" x-bind:aria-pressed="unset ? 'true' : 'false'" aria-pressed="false">
                    Belum lengkap saja
                    <span class="segmented-count">{{ $incomplete }}</span>
                </button>
            </div>
            @can('manage-monitoring-parameters')
            <button class="btn min-h-11 w-full sm:w-auto lg:min-h-9">Simpan Parameter</button>
            @endcan
        </div>

        <x-ui.section title="Minimum stok per lokasi" icon="parameter" tone="brand" flush class="mt-3.5" :meta="\App\Support\Quantity::format($medicines->count()).' obat · '.$locations->count().' lokasi'"
            :subtitle="$canManage ? 'Kosongkan nilai jika batas minimum belum ditetapkan.' : 'Batas minimum yang dipakai untuk menandai stok menipis pada tiap lokasi.'">
            <div class="max-h-[min(640px,72vh)] overflow-auto">
                <table class="w-full whitespace-nowrap text-left text-[13px]">
                    <thead class="table-head sticky top-0 z-20 shadow-[inset_0_-1px_0_#e2e8f0]">
                        <tr>
                            <th class="sticky left-0 z-10 bg-slate-50 px-4 py-2.5">Obat</th>
                            @foreach($locations as $location)
                                <th class="px-4 py-2.5">
                                    <span class="inline-flex items-center gap-1.5"><x-ui.icon name="location" class="h-3.5 w-3.5 {{ str_contains(strtolower($location->name), 'gudang') ? 'text-brand-600' : 'text-slate-400' }}" />{{ $location->name }}</span>
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($medicines as $medicine)
                            @php
                                $missing = $missingFor($medicine);
                            @endphp
                            <tr data-parameter-row data-search="{{ mb_strtolower($medicine->code.' '.$medicine->name) }}" data-missing="{{ $missing }}" x-show="match($el)">
                                <td class="sticky left-0 z-[1] min-w-[170px] max-w-[360px] sm:min-w-[220px] whitespace-normal bg-white px-4 py-2.5">
                                    <b class="font-extrabold text-slate-900">{{ $medicine->name }}</b>
                                    <span class="mt-1 flex flex-wrap items-center gap-1.5 text-xs text-slate-500">
                                        <span class="rounded bg-slate-100 px-1.5 font-mono text-[11px] font-semibold text-slate-600">{{ $medicine->code }}</span>
                                        <span>{{ $medicine->unit?->name }}</span>
                                        @if($missing > 0)<span class="badge badge-warn">{{ $missing === $locations->count() ? 'Belum diatur' : $missing.' lokasi belum diatur' }}</span>@endif
                                    </span>
                                </td>
                                @foreach($locations as $location)
                                    @php
                                        $parameter = $medicine->stockParameters->firstWhere('location_id', $location->id);
                                    @endphp
                                    <td class="px-4 py-2">
                                        @can('manage-monitoring-parameters')
                                        <span class="flex items-center gap-2">
                                            <input class="field min-h-11 w-32 tabular-nums lg:min-h-8 lg:py-1" name="minimums[{{ $medicine->id }}][{{ $location->id }}]" type="number" min="0" step="0.0001" value="{{ old('minimums.'.$medicine->id.'.'.$location->id, $parameter?->minimum_quantity !== null ? rtrim(rtrim((string)$parameter->minimum_quantity, '0'), '.') : '') }}" placeholder="Belum diatur" aria-label="Minimum {{ $medicine->name }} di {{ $location->name }}">
                                            <span class="text-xs text-slate-500">{{ $medicine->unit?->name }}</span>
                                        </span>
                                        @else
                                            @if($parameter?->minimum_quantity !== null)
                                                <span class="font-extrabold tabular-nums text-slate-900">{{ \App\Support\Quantity::format($parameter->minimum_quantity) }}</span> <span class="text-xs text-slate-500">{{ $medicine->unit?->name }}</span>
                                            @else
                                                <span class="text-xs text-slate-400">Belum diatur</span>
                                            @endif
                                        @endcan
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                        @if($medicines->isEmpty())
                            <tr><td colspan="{{ $locations->count() + 1 }}" class="empty-state">Belum ada obat operasional. Obat perlu aktif, punya satuan, dan bentuk sediaan agar bisa dipantau.</td></tr>
                        @else
                            <tr x-show="[...$root.querySelectorAll('[data-parameter-row]')].every((row) => ! match(row))" style="display: none">
                                <td colspan="{{ $locations->count() + 1 }}" class="empty-state">Tidak ada obat yang cocok dengan pencarian atau saringan ini.</td>
                            </tr>
                        @endif
                    </tbody>
                </table>
            </div>
        </x-ui.section>
    @can('manage-monitoring-parameters')
    </form>
    @else
    </div>
    @endcan
</x-layouts.app>
