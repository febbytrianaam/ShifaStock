<x-layouts.app title="Pengeluaran Obat" :hide-description="true">
    <x-issue-tabs></x-issue-tabs>
    <section aria-labelledby="issue-guide-title" data-issue-guide class="mb-5 border-b border-slate-200 pb-5 text-sm leading-relaxed text-slate-600">
        <h2 id="issue-guide-title" class="text-base font-semibold text-slate-900">Catat obat yang keluar dari persediaan</h2>
        <p class="mt-1">Obat untuk pelayanan atau pemakaian internal dicatat di sini. ShifaStock tidak terhubung ke Neoklinik; pencatatan di Neoklinik tidak otomatis mengurangi stok di ShifaStock.</p>
        <p class="mt-2">Jika sudah memiliki rekap Excel Neoklinik, gunakan tab <a href="{{ route('issue-imports.index') }}" class="font-semibold text-blue-700">Impor &amp; Riwayat Excel</a> agar tidak mengetik ulang per obat. Jangan catat manual transaksi yang sama dengan yang akan diimpor.</p>
        <dl class="mt-4 grid gap-4 lg:grid-cols-3">
            <div class="min-w-0">
                <dt class="font-semibold text-slate-800">Lokasi dan jumlah</dt>
                <dd class="mt-1">Untuk obat yang keluar dari Apotek, lokasi sumbernya Apotek. Jumlah adalah banyaknya obat yang keluar, bukan sisa stok. Gunakan satuan stok obat, misalnya tablet, botol, atau tube.</dd>
            </div>
            <div class="min-w-0">
                <dt class="font-semibold text-slate-800">Referensi dan batch</dt>
                <dd class="mt-1">Nomor referensi transaksi dibuat otomatis. Referensi sumber boleh diisi nomor resep atau rekap pengeluaran tanpa identitas pasien. Batch yang kosong memakai stok dengan kedaluwarsa terdekat secara otomatis (FEFO), bukan batch yang sudah kedaluwarsa.</dd>
            </div>
            <div class="min-w-0">
                <dt class="font-semibold text-slate-800">Setelah disimpan</dt>
                <dd class="mt-1">Stok lokasi sumber dan batch langsung berkurang, serta mutasinya tercatat di kartu stok. Pengeluaran tidak menunggu persetujuan pemeriksaan harian. Satu penyimpanan mencatat satu jenis obat; FEFO dapat mengambil beberapa batch.</dd>
            </div>
        </dl>
        <p class="mt-4"><strong class="text-slate-800">Contoh:</strong> stok Apotek 20 tablet, keluar 3 tablet. Jumlah diisi <strong class="text-slate-800">3</strong>, bukan sisa 17. Setelah disimpan, stok Apotek menjadi 17 tablet.</p>
        <p class="mt-2"><strong class="text-slate-800">Hindari pencatatan ganda.</strong> Periksa riwayat sebelum mengulang pengeluaran, terutama jika pemakaian yang sama sudah diperhitungkan dalam penyesuaian stok melalui pemeriksaan.</p>
        <p class="mt-2">Pemindahan antar lokasi dicatat melalui Transfer Gudang&ndash;Apotek. Pengembalian ke supplier dicatat melalui Retur PBF, bukan Pengeluaran Obat.</p>
    </section>
    @if(session('status'))
        <div class="mb-4 rounded-lg border border-[#b2d4ee] bg-[#edf5fb] p-3 text-sm font-semibold text-[#266194]">{{ session('status') }}</div>
    @endif
    @if($errors->any())
        <div class="mb-4 rounded-lg border border-rose-200 bg-rose-50 p-3 text-sm font-semibold text-rose-700">{{ $errors->first() }}</div>
    @endif

    @can('operate-inventory')
    <form method="POST" action="{{ route('issues.store') }}" class="card mb-5 grid gap-4 p-4 md:grid-cols-2 xl:grid-cols-4" data-issue-form data-required-alert="Lengkapi data pengeluaran obat" novalidate>
        @csrf
        <div>
            <label class="label" for="issue-reference">Referensi</label>
            <input class="field bg-slate-50 text-xs font-semibold text-slate-700" id="issue-reference" name="reference" value="{{ old('reference', $nextReference) }}" readonly required maxlength="100">
        </div>
        <div>
            <label class="label" for="issue-source-reference">Referensi sumber</label>
            <input class="field text-xs" id="issue-source-reference" name="source_reference" value="{{ old('source_reference') }}" placeholder="Nomor resep atau rekap pengeluaran" maxlength="255" aria-describedby="issue-source-help">
            <p id="issue-source-help" class="mt-1 text-xs text-slate-500">Opsional. Cukup nomor atau kode sumber, tanpa identitas pasien.</p>
        </div>
        <div class="md:col-span-2">
            <label class="label" for="issue-medicine">Obat</label>
            <select class="field text-xs" id="issue-medicine" name="medicine_id" data-searchable-select data-search-placeholder="Cari kode atau nama obat..." required>
                <option value="">Pilih obat</option>
                @foreach($medicines as $medicine)
                    <option value="{{ $medicine->id }}" @selected(old('medicine_id')==$medicine->id)>{{ $medicine->code }} — {{ $medicine->name }} ({{ $medicine->unit?->name }})</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="label" for="issue-location">Lokasi sumber</label>
            <select class="field text-xs" id="issue-location" name="source_location_id" required>
                @foreach($locations as $location)
                    <option value="{{ $location->id }}" @selected(old('source_location_id')==$location->id)>{{ $location->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="label" for="issue-batch">Batch</label>
            <select class="field text-xs" id="issue-batch" name="batch_id" data-searchable-select data-empty-label="Otomatis: kedaluwarsa terdekat" data-search-placeholder="Cari batch..." data-depends-on="#issue-medicine,#issue-location" data-depends-empty="Pilih obat dan lokasi sumber dulu">
                <option value="">Otomatis: kedaluwarsa terdekat</option>
                @foreach($batchBalances as $balance)
                    <option value="{{ $balance->batch_id }}" data-depends-values="{{ $balance->medicine_id }}|{{ $balance->location_id }}" @selected(old('batch_id')==$balance->batch_id)>
                        {{ $balance->medicine->code }} · {{ $balance->batch->number }} · {{ $balance->location->name }} · ED {{ $balance->batch->expiry_date?->format('d/m/Y') }} · {{ \App\Support\Quantity::format((float)$balance->quantity) }} {{ $balance->medicine->unit?->name }}
                    </option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="label" for="issue-quantity">Jumlah</label>
            <input class="field text-xs" id="issue-quantity" name="quantity" type="number" min="0.0001" step="0.0001" value="{{ old('quantity') }}" required aria-describedby="issue-quantity-help">
            <p id="issue-quantity-help" class="mt-1 text-xs text-slate-500">Isi jumlah yang keluar, bukan sisa stok.</p>
        </div>
        <div>
            <label class="label" for="issue-time">Waktu keluar</label>
            <input class="field text-xs" id="issue-time" name="occurred_at" type="datetime-local" value="{{ old('occurred_at',now('Asia/Jakarta')->format('Y-m-d\\TH:i')) }}" required>
        </div>
        <div class="md:col-span-2 xl:col-span-3">
            <label class="label" for="issue-notes">Catatan</label>
            <input class="field text-xs" id="issue-notes" name="notes" value="{{ old('notes') }}" placeholder="Keterangan pengeluaran" maxlength="1000">
        </div>
        <div class="flex items-end">
            <button class="btn w-full text-xs">Simpan &amp; Kurangi Stok</button>
        </div>
        <x-stock-entry-context :medicines="$medicines" :batch-balances="$batchBalances" medicine="#issue-medicine" location="#issue-location" quantity="#issue-quantity" batch="#issue-batch" :fefo="true" />
    </form>
    @endcan

    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full whitespace-nowrap text-left text-sm">
                <thead class="bg-[#edf5fb] border-b border-[#d6e8f6] text-[#266194]">
                    <tr>
                        <th class="p-4">Waktu</th>
                        <th class="p-4">Referensi</th>
                        <th class="p-4">Obat / Batch</th>
                        <th class="p-4">Lokasi</th>
                        <th class="p-4">Jumlah</th>
                        <th class="p-4">Sumber</th>
                        <th class="p-4">Petugas</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($rows as $row)
                        <tr>
                            <td class="p-4 text-xs font-medium text-slate-600 tabular-nums">{{ $row->occurred_at->format('d/m/Y H:i') }}</td>
                            <td class="p-4 font-mono text-xs font-semibold text-slate-700">{{ $row->reference }}</td>
                            <td class="p-4"><b>{{ $row->medicine->name }}</b><br><span class="text-xs text-slate-500">{{ $row->batch?->number ?? 'FEFO' }}</span></td>
                            <td class="p-4 text-xs font-semibold text-slate-600">{{ $row->sourceLocation?->name }}</td>
                            <td class="p-4 font-bold tabular-nums text-rose-700">-{{ \App\Support\Quantity::format((float)$row->quantity) }} {{ $row->medicine->unit?->name }}</td>
                            <td class="p-4 text-xs font-medium text-slate-600">{{ $row->counterparty ?: '—' }}</td>
                            <td class="p-4 text-xs font-medium text-slate-600">{{ $row->creator?->name }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="p-8 text-center text-slate-500 font-medium">Belum ada pengeluaran obat.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-4">{{ $rows->links() }}</div>
</x-layouts.app>
