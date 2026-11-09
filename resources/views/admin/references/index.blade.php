<x-layouts.app title="Referensi Sistem">
    @if(session('status'))
        <div class="callout-ok mb-4 font-semibold">{{ session('status') }}</div>
    @endif
    @if($errors->any())
        <div class="callout-danger mb-4 font-semibold">{{ $errors->first() }}</div>
    @endif

    @php
        $control = 'field min-h-11 lg:min-h-9 lg:py-1.5';
        $checkbox = 'h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-600';
    @endphp
    <div class="grid items-start gap-4 xl:grid-cols-2">
        <x-ui.section title="Lokasi Stok" icon="location" flush
            subtitle="Kelola lokasi yang dipakai untuk penerimaan, transfer, pengeluaran, monitoring, dan laporan."
            :meta="$locations->count().' lokasi'">
            <form method="POST" action="{{ route('admin.references.locations.store') }}" class="grid gap-2 border-b border-slate-100 bg-slate-50/60 p-4 sm:grid-cols-[120px_1fr_auto_auto] sm:items-center" data-required-alert="Lengkapi lokasi stok" novalidate>
                @csrf
                <input class="{{ $control }} uppercase" name="code" placeholder="Kode" aria-label="Kode lokasi baru" required maxlength="20">
                <input class="{{ $control }}" name="name" placeholder="Nama lokasi" aria-label="Nama lokasi baru" required maxlength="255">
                <label class="inline-flex min-h-11 items-center gap-2 text-xs font-bold text-slate-600 lg:min-h-0">
                    <input type="checkbox" name="is_active" value="1" checked class="{{ $checkbox }}">
                    Aktif
                </label>
                <button class="btn min-h-11 lg:min-h-9 lg:py-1.5"><x-ui.icon name="plus" /> Tambah</button>
            </form>
            <div class="overflow-x-auto">
                <table class="min-w-full text-[13px]">
                    <thead class="table-head text-left">
                        <tr>
                            <th class="px-4 py-2.5">Kode</th>
                            <th class="px-4 py-2.5">Nama</th>
                            <th class="px-4 py-2.5">Status</th>
                            <th class="px-4 py-2.5 text-right">Simpan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($locations as $location)
                            <tr class="border-t border-slate-100">
                                <form method="POST" action="{{ route('admin.references.locations.update', $location) }}" data-required-alert="Lengkapi lokasi stok" novalidate>
                                    @csrf
                                    @method('PUT')
                                    <td class="w-32 px-4 py-2"><input class="{{ $control }} uppercase" name="code" value="{{ $location->code }}" aria-label="Kode {{ $location->name }}" required maxlength="20"></td>
                                    <td class="px-4 py-2"><input class="{{ $control }}" name="name" value="{{ $location->name }}" aria-label="Nama {{ $location->name }}" required maxlength="255"></td>
                                    <td class="px-4 py-2">
                                        <label class="inline-flex min-h-11 items-center gap-2 lg:min-h-0">
                                            <input type="checkbox" name="is_active" value="1" @checked($location->is_active) class="{{ $checkbox }}">
                                            <span class="badge {{ $location->is_active ? 'badge-ok' : 'badge-muted' }}">{{ $location->is_active ? 'Aktif' : 'Nonaktif' }}</span>
                                        </label>
                                    </td>
                                    <td class="px-4 py-2 text-right"><button class="btn-secondary min-h-11 lg:min-h-8 lg:py-1">Simpan</button></td>
                                </form>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-ui.section>

        <x-ui.section title="Satuan Obat" icon="box" tone="teal" flush
            subtitle="Kelola satuan dasar yang dipakai pada master obat dan seluruh transaksi stok."
            :meta="$units->count().' satuan'">
            <form method="POST" action="{{ route('admin.references.units.store') }}" class="grid gap-2 border-b border-slate-100 bg-slate-50/60 p-4 sm:grid-cols-[1fr_auto_auto] sm:items-center" data-required-alert="Lengkapi satuan obat" novalidate>
                @csrf
                <input class="{{ $control }}" name="name" placeholder="Nama satuan" aria-label="Nama satuan baru" required maxlength="100">
                <label class="inline-flex min-h-11 items-center gap-2 text-xs font-bold text-slate-600 lg:min-h-0">
                    <input type="checkbox" name="is_active" value="1" checked class="{{ $checkbox }}">
                    Aktif
                </label>
                <button class="btn min-h-11 lg:min-h-9 lg:py-1.5"><x-ui.icon name="plus" /> Tambah</button>
            </form>
            <div class="overflow-x-auto">
                <table class="min-w-full text-[13px]">
                    <thead class="table-head text-left">
                        <tr>
                            <th class="px-4 py-2.5">Nama</th>
                            <th class="px-4 py-2.5">Status</th>
                            <th class="px-4 py-2.5 text-right">Simpan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($units as $unit)
                            <tr class="border-t border-slate-100">
                                <form method="POST" action="{{ route('admin.references.units.update', $unit) }}" data-required-alert="Lengkapi satuan obat" novalidate>
                                    @csrf
                                    @method('PUT')
                                    <td class="px-4 py-2"><input class="{{ $control }}" name="name" value="{{ $unit->name }}" aria-label="Nama satuan {{ $unit->name }}" required maxlength="100"></td>
                                    <td class="px-4 py-2">
                                        <label class="inline-flex min-h-11 items-center gap-2 lg:min-h-0">
                                            <input type="checkbox" name="is_active" value="1" @checked($unit->is_active) class="{{ $checkbox }}">
                                            <span class="badge {{ $unit->is_active ? 'badge-ok' : 'badge-muted' }}">{{ $unit->is_active ? 'Aktif' : 'Nonaktif' }}</span>
                                        </label>
                                    </td>
                                    <td class="px-4 py-2 text-right"><button class="btn-secondary min-h-11 lg:min-h-8 lg:py-1">Simpan</button></td>
                                </form>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-ui.section>
    </div>
</x-layouts.app>
