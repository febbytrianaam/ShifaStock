<x-layouts.app title="Parameter Monitoring">
    @if(session('status'))
        <div class="mb-4 rounded-lg border border-[#b2d4ee] bg-[#edf5fb] p-3 text-sm font-semibold text-[#266194]">{{ session('status') }}</div>
    @endif
    @if($errors->any())
        <div class="mb-4 rounded-lg border border-rose-200 bg-rose-50 p-3 text-sm font-semibold text-rose-700">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ route('monitoring-parameters.store') }}" class="space-y-5" data-required-alert="Lengkapi parameter monitoring" novalidate>
        @csrf
        <section class="card p-4">
            <label class="label" for="expiry-warning-months">Peringatan kedaluwarsa</label>
            <div class="flex max-w-md items-center gap-3">
                <input class="field text-xs" id="expiry-warning-months" name="expiry_warning_months" type="number" min="1" max="24" value="{{ old('expiry_warning_months', $expiryWarningMonths) }}" required>
                <span class="text-sm font-semibold text-slate-600">bulan sebelum ED</span>
            </div>
            <p class="mt-2 text-xs text-slate-400">Dipakai oleh dashboard dan monitoring kedaluwarsa.</p>
        </section>

        <section class="card overflow-hidden">
            <div class="border-b border-slate-100 p-4">
                <h2 class="text-sm font-bold text-slate-900">Minimum stok per lokasi</h2>
                <p class="mt-1 text-xs text-slate-500">Kosongkan nilai jika batas minimum belum ditetapkan.</p>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full whitespace-nowrap text-left text-sm">
                    <thead class="bg-[#edf5fb] border-b border-[#d6e8f6] text-[#266194]">
                        <tr>
                            <th class="p-4 text-[11px] font-bold uppercase tracking-wider">Obat</th>
                            @foreach($locations as $location)
                                <th class="p-4 text-[11px] font-bold uppercase tracking-wider">{{ $location->name }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($medicines as $medicine)
                            <tr>
                                <td class="p-4">
                                    <b class="font-bold text-slate-900">{{ $medicine->name }}</b><br>
                                    <span class="font-mono text-[11px] text-slate-500">{{ $medicine->code }} · {{ $medicine->unit?->name }}</span>
                                </td>
                                @foreach($locations as $location)
                                    @php($parameter = $medicine->stockParameters->firstWhere('location_id', $location->id))
                                    <td class="p-4">
                                        <input class="field w-32 text-xs" name="minimums[{{ $medicine->id }}][{{ $location->id }}]" type="number" min="0" step="0.0001" value="{{ old('minimums.'.$medicine->id.'.'.$location->id, $parameter?->minimum_quantity !== null ? rtrim(rtrim((string)$parameter->minimum_quantity, '0'), '.') : '') }}" placeholder="Belum diatur">
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        <div class="flex justify-end">
            <button class="btn text-xs">Simpan Parameter</button>
        </div>
    </form>
</x-layouts.app>
