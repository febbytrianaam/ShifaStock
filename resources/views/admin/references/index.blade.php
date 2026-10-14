<x-layouts.app title="Referensi Sistem">
    @if(session('status'))
        <div class="mb-5 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-700">{{ session('status') }}</div>
    @endif
    @if($errors->any())
        <div class="mb-5 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-700">{{ $errors->first() }}</div>
    @endif

    <div class="grid gap-5 xl:grid-cols-2">
        <section class="card overflow-hidden">
            <div class="border-b border-slate-100 p-4">
                <h2 class="text-base font-bold text-slate-900">Lokasi Stok</h2>
                <p class="mt-1 text-sm text-slate-500">Kelola lokasi yang dipakai untuk penerimaan, transfer, pengeluaran, monitoring, dan laporan.</p>
            </div>
            <form method="POST" action="{{ route('admin.references.locations.store') }}" class="grid gap-3 border-b border-slate-100 p-4 md:grid-cols-[120px_1fr_auto_auto]" data-required-alert="Lengkapi lokasi stok" novalidate>
                @csrf
                <input class="field text-xs uppercase" name="code" placeholder="Kode" required maxlength="20">
                <input class="field text-xs" name="name" placeholder="Nama lokasi" required maxlength="255">
                <label class="inline-flex items-center gap-2 text-xs font-semibold text-slate-600">
                    <input type="checkbox" name="is_active" value="1" checked class="h-4 w-4 rounded border-slate-300 text-[#3277B2]">
                    Aktif
                </label>
                <button class="btn text-xs">Tambah</button>
            </form>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-100 text-sm">
                    <thead class="bg-slate-50 text-left text-slate-500">
                        <tr>
                            <th class="table-cell">Kode</th>
                            <th class="table-cell">Nama</th>
                            <th class="table-cell">Status</th>
                            <th class="table-cell text-right">Simpan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($locations as $location)
                            <tr>
                                <form method="POST" action="{{ route('admin.references.locations.update', $location) }}" data-required-alert="Lengkapi lokasi stok" novalidate>
                                    @csrf
                                    @method('PUT')
                                    <td class="table-cell"><input class="field text-xs uppercase" name="code" value="{{ $location->code }}" required maxlength="20"></td>
                                    <td class="table-cell"><input class="field text-xs" name="name" value="{{ $location->name }}" required maxlength="255"></td>
                                    <td class="table-cell">
                                        <label class="inline-flex items-center gap-2 text-xs font-semibold text-slate-600">
                                            <input type="checkbox" name="is_active" value="1" @checked($location->is_active) class="h-4 w-4 rounded border-slate-300 text-[#3277B2]">
                                            {{ $location->is_active ? 'Aktif' : 'Nonaktif' }}
                                        </label>
                                    </td>
                                    <td class="table-cell text-right"><button class="btn-secondary text-xs">Simpan</button></td>
                                </form>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        <section class="card overflow-hidden">
            <div class="border-b border-slate-100 p-4">
                <h2 class="text-base font-bold text-slate-900">Satuan Obat</h2>
                <p class="mt-1 text-sm text-slate-500">Kelola satuan dasar yang dipakai pada master obat dan seluruh transaksi stok.</p>
            </div>
            <form method="POST" action="{{ route('admin.references.units.store') }}" class="grid gap-3 border-b border-slate-100 p-4 md:grid-cols-[1fr_auto_auto]" data-required-alert="Lengkapi satuan obat" novalidate>
                @csrf
                <input class="field text-xs" name="name" placeholder="Nama satuan" required maxlength="100">
                <label class="inline-flex items-center gap-2 text-xs font-semibold text-slate-600">
                    <input type="checkbox" name="is_active" value="1" checked class="h-4 w-4 rounded border-slate-300 text-[#3277B2]">
                    Aktif
                </label>
                <button class="btn text-xs">Tambah</button>
            </form>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-100 text-sm">
                    <thead class="bg-slate-50 text-left text-slate-500">
                        <tr>
                            <th class="table-cell">Nama</th>
                            <th class="table-cell">Status</th>
                            <th class="table-cell text-right">Simpan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($units as $unit)
                            <tr>
                                <form method="POST" action="{{ route('admin.references.units.update', $unit) }}" data-required-alert="Lengkapi satuan obat" novalidate>
                                    @csrf
                                    @method('PUT')
                                    <td class="table-cell"><input class="field text-xs" name="name" value="{{ $unit->name }}" required maxlength="100"></td>
                                    <td class="table-cell">
                                        <label class="inline-flex items-center gap-2 text-xs font-semibold text-slate-600">
                                            <input type="checkbox" name="is_active" value="1" @checked($unit->is_active) class="h-4 w-4 rounded border-slate-300 text-[#3277B2]">
                                            {{ $unit->is_active ? 'Aktif' : 'Nonaktif' }}
                                        </label>
                                    </td>
                                    <td class="table-cell text-right"><button class="btn-secondary text-xs">Simpan</button></td>
                                </form>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</x-layouts.app>
