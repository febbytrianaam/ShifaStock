<x-layouts.app title="Pengeluaran Obat" :hide-description="true">
    <x-issue-tabs></x-issue-tabs>
    @if(session('status'))
        <div role="status" class="callout-ok mb-4 font-semibold">{{ session('status') }}</div>
    @endif
    @if($errors->any())
        <div role="alert" class="callout-danger mb-4 font-semibold">{{ $errors->first() }}</div>
    @endif

    <div class="flex flex-col gap-4 2xl:flex-row 2xl:items-start">
        <x-ui.section title="Panduan pengeluaran" icon="info" tone="slate" heading-id="issue-guide-title" data-issue-guide class="2xl:order-2 2xl:w-[340px] 2xl:shrink-0">
            <div class="space-y-3 text-[13px] leading-relaxed text-slate-600">
                <p>Obat untuk pelayanan atau pemakaian internal dicatat di sini. ShifaStock tidak terhubung ke Neoklinik; pencatatan di Neoklinik tidak otomatis mengurangi stok di ShifaStock.</p>
                <p>Jika sudah memiliki rekap Excel Neoklinik, gunakan tab <a href="{{ route('issue-imports.index') }}" class="link">Impor &amp; Riwayat Excel</a> agar tidak mengetik ulang per obat. Jangan catat manual transaksi yang sama dengan yang akan diimpor.</p>
                <dl class="grid gap-3 border-t border-slate-100 pt-3 text-xs lg:grid-cols-3 2xl:grid-cols-1">
                    <div class="min-w-0">
                        <dt class="font-bold text-slate-800">Lokasi dan jumlah</dt>
                        <dd class="mt-0.5">Untuk obat yang keluar dari Apotek, lokasi sumbernya Apotek. Jumlah adalah banyaknya obat yang keluar, bukan sisa stok. Gunakan satuan stok obat, misalnya tablet, botol, atau tube.</dd>
                    </div>
                    <div class="min-w-0">
                        <dt class="font-bold text-slate-800">Referensi dan batch</dt>
                        <dd class="mt-0.5">Nomor referensi transaksi dibuat otomatis. Referensi sumber boleh diisi nomor resep atau rekap pengeluaran tanpa identitas pasien. Batch yang kosong memakai stok dengan kedaluwarsa terdekat secara otomatis (FEFO), bukan batch yang sudah kedaluwarsa.</dd>
                    </div>
                    <div class="min-w-0">
                        <dt class="font-bold text-slate-800">Setelah disimpan</dt>
                        <dd class="mt-0.5">Stok lokasi sumber dan batch langsung berkurang, serta mutasinya tercatat di kartu stok. Pengeluaran tidak menunggu persetujuan pemeriksaan harian. Satu penyimpanan mencatat satu jenis obat; FEFO dapat mengambil beberapa batch.</dd>
                    </div>
                </dl>
                <p class="rounded-xl bg-slate-50 px-3 py-2.5 text-xs"><strong class="text-slate-800">Contoh:</strong> stok Apotek 20 tablet, keluar 3 tablet. Jumlah diisi <strong class="text-slate-800">3</strong>, bukan sisa 17. Setelah disimpan, stok Apotek menjadi 17 tablet.</p>
                <p class="callout-warn text-xs"><strong>Hindari pencatatan ganda.</strong> Periksa riwayat sebelum mengulang pengeluaran, terutama jika pemakaian yang sama sudah diperhitungkan dalam penyesuaian stok melalui pemeriksaan.</p>
                <p class="text-xs">Pemindahan antar lokasi dicatat melalui Transfer Gudang&ndash;Apotek. Pengembalian ke supplier dicatat melalui Retur PBF, bukan Pengeluaran Obat.</p>
            </div>
        </x-ui.section>

        <div class="flex min-w-0 flex-1 flex-col gap-4 2xl:order-1">
            @can('operate-inventory')
            <form method="POST" action="{{ route('issues.store') }}" class="flex flex-col gap-4" data-issue-form data-required-alert="Lengkapi data pengeluaran obat" novalidate>
                @csrf
                <x-ui.section title="Data pengeluaran" icon="out">
                    <div class="grid gap-x-4 gap-y-3.5 sm:grid-cols-2 xl:grid-cols-4">
                        <div>
                            <label class="label" for="issue-reference">Referensi</label>
                            <input class="field min-h-11 bg-slate-50 font-mono text-xs font-semibold text-slate-700 lg:min-h-9" id="issue-reference" name="reference" value="{{ old('reference', $nextReference) }}" readonly required maxlength="100">
                        </div>
                        <div>
                            <label class="label" for="issue-source-reference">Referensi sumber</label>
                            <input class="field min-h-11 text-xs lg:min-h-9" id="issue-source-reference" name="source_reference" value="{{ old('source_reference') }}" placeholder="Nomor resep atau rekap pengeluaran" maxlength="255" aria-describedby="issue-source-help">
                            <p id="issue-source-help" class="mt-1 text-xs text-slate-500">Opsional. Cukup nomor atau kode sumber, tanpa identitas pasien.</p>
                        </div>
                        <div class="sm:col-span-2">
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
                            <select class="field min-h-11 text-xs lg:min-h-9" id="issue-location" name="source_location_id" required>
                                @foreach($locations as $location)
                                    <option value="{{ $location->id }}" @selected(old('source_location_id')==$location->id)>{{ $location->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="xl:col-span-2">
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
                            <input class="field min-h-11 text-xs tabular-nums lg:min-h-9" id="issue-quantity" name="quantity" type="number" min="0.0001" step="0.0001" value="{{ old('quantity') }}" required aria-describedby="issue-quantity-help">
                            <p id="issue-quantity-help" class="mt-1 text-xs text-slate-500">Isi jumlah yang keluar, bukan sisa stok.</p>
                        </div>
                        <div>
                            <label class="label" for="issue-time">Waktu keluar</label>
                            <input class="field min-h-11 text-xs lg:min-h-9" id="issue-time" name="occurred_at" type="datetime-local" value="{{ old('occurred_at',now('Asia/Jakarta')->format('Y-m-d\\TH:i')) }}" required>
                        </div>
                        <div class="sm:col-span-2 xl:col-span-3">
                            <label class="label" for="issue-notes">Catatan</label>
                            <input class="field min-h-11 text-xs lg:min-h-9" id="issue-notes" name="notes" value="{{ old('notes') }}" placeholder="Keterangan pengeluaran" maxlength="1000">
                        </div>
                        <x-stock-entry-context :medicines="$medicines" :batch-balances="$batchBalances" medicine="#issue-medicine" location="#issue-location" quantity="#issue-quantity" batch="#issue-batch" :fefo="true" />
                    </div>
                </x-ui.section>
                <div class="flex justify-end">
                    <button class="btn min-h-11 w-full sm:w-auto lg:min-h-9">Simpan &amp; Kurangi Stok</button>
                </div>
            </form>
            @endcan

            <x-ui.section title="Riwayat pengeluaran" icon="history" tone="slate" flush :meta="$rows->total().' mutasi'">
                <div class="overflow-x-auto">
                    <table class="w-full whitespace-nowrap text-left text-[13px]">
                        <thead class="table-head">
                            <tr>
                                <th class="px-4 py-2.5">Waktu</th>
                                <th class="px-4 py-2.5">Referensi</th>
                                <th class="px-4 py-2.5">Obat / Batch</th>
                                <th class="px-4 py-2.5">Lokasi</th>
                                <th class="px-4 py-2.5">Jumlah</th>
                                <th class="px-4 py-2.5">Sumber</th>
                                <th class="px-4 py-2.5">Petugas</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse($rows as $row)
                                <tr>
                                    <td class="px-4 py-2.5 tabular-nums text-slate-600">{{ $row->occurred_at->format('d/m/Y H:i') }}</td>
                                    <td class="px-4 py-2.5"><span class="inline-block rounded bg-slate-100 px-1.5 font-mono text-[11px] font-semibold text-slate-600">{{ $row->reference }}</span></td>
                                    <td class="px-4 py-2.5"><b class="font-extrabold text-slate-900">{{ $row->medicine->name }}</b><p class="text-xs text-slate-500">{{ $row->batch?->number ?? 'FEFO' }}</p></td>
                                    <td class="px-4 py-2.5 text-slate-700">{{ $row->sourceLocation?->name }}</td>
                                    <td class="px-4 py-2.5 font-extrabold tabular-nums text-rose-700">-{{ \App\Support\Quantity::format((float)$row->quantity) }} {{ $row->medicine->unit?->name }}</td>
                                    <td class="px-4 py-2.5 text-slate-600">{{ $row->counterparty ?: '—' }}</td>
                                    <td class="px-4 py-2.5 text-slate-600">{{ $row->creator?->name }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="empty-state">Belum ada pengeluaran obat.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if($rows->hasPages())<div class="border-t border-slate-100 px-4 py-3">{{ $rows->links() }}</div>@endif
            </x-ui.section>
        </div>
    </div>
</x-layouts.app>
