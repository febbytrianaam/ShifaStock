<x-layouts.app title="Pemeriksaan Stok Harian" :hide-description="true">
@php
    $editable = auth()->user()->can('operate-inventory') && $check->status === 'draft';
    $reviewable = $check->status === 'submitted' && auth()->user()->can('finalize-opname');
    $statusLabels = ['draft' => 'Draf', 'submitted' => 'Menunggu tinjauan', 'approved' => 'Disetujui'];
    $columnHelp = [
        'medicine' => ['Obat', 'Nama dan kode obat dalam laporan, mengikuti Data Obat saat obat ditambahkan ke pemeriksaan.'],
        'system' => ['Stok sistem', 'Stok pada lokasi pemeriksaan saat halaman dibuka, berdasarkan stok awal dan transaksi yang tercatat. Ini bukan hasil hitung fisik. Obat terpakai belum mengurangi angka ini jika pengeluarannya belum dicatat.'],
        'physical' => ['Jumlah fisik / satuan', 'Jumlah obat yang benar-benar dihitung di lokasi ini, dalam satuan stok obat. Isi 0 jika sudah dihitung dan habis; kosong berarti belum diperiksa. Jika hitungan per batch diisi, jumlah dijumlahkan dari batch.'],
        'status' => ['Status', 'Belum dicek berarti jumlah masih kosong; Kosong berarti hasil hitung 0; Menipis berarti ditandai menipis; Diperiksa berarti jumlah sudah terisi. Ini status per obat, bukan persetujuan laporan.'],
        'counted' => ['Hasil hitung', 'Jumlah fisik yang tersimpan saat laporan dikirim. Hanya obat yang sudah diperiksa yang masuk penyesuaian setelah disetujui apoteker.'],
        'difference' => ['Perubahan jika disetujui', 'Hasil hitung dikurangi stok sistem sekarang. Nilai negatif berarti stok berkurang, positif berarti bertambah, dan 0 berarti tetap. Ini penyesuaian hasil pemeriksaan, bukan jumlah pemakaian pasien.'],
    ];
@endphp
<section class="nightly-workspace" data-nightly-workspace data-nightly-initial-view="{{ request()->has('activity_page') ? 'activity' : 'list' }}">
    <header class="nightly-head">
        <div class="flex min-w-0 items-center gap-3">
            <a class="nightly-icon" href="{{ route('preview.nightly') }}" aria-label="Kembali ke daftar pemeriksaan" title="Kembali ke daftar pemeriksaan"><span data-nightly-icon="back" aria-hidden="true"></span></a>
            <div><h2>{{ $check->location }} <span class="ml-1 text-xs font-medium text-slate-500">{{ $check->date }}</span></h2><p data-nightly-creator>Dibuat oleh: {{ $check->creator }} &middot; {{ $check->scope === 'selected' ? 'Obat tertentu' : 'Semua obat' }}</p><p data-nightly-last-worker>Dikerjakan terakhir: {{ $lastWork?->actor_name ?? 'Belum tercatat' }}@if($lastWork) &middot; {{ \Carbon\Carbon::parse($lastWork->created_at, config('app.timezone'))->timezone('Asia/Jakarta')->format('d/m H:i') }} WIB @endif</p></div>
        </div>
        <div class="nightly-head-actions">
            <span class="badge {{ $check->status === 'approved' ? 'badge-ok' : ($check->status === 'submitted' ? 'badge-warn' : 'bg-slate-100 text-slate-600') }}">{{ $statusLabels[$check->status] ?? 'Draf' }}</span>
            <a class="nightly-icon" href="{{ route('nightly.print', $check->id) }}" target="_blank" rel="noopener" data-nightly-print aria-label="Cetak / Simpan PDF" title="Cetak / Simpan PDF"><span data-nightly-icon="print" aria-hidden="true"></span></a>
            @if($editable && $check->created_by === auth()->id())
                <form method="POST" action="{{ route('nightly.destroy', $check->id) }}" data-confirm-cancel-draft>
                    @csrf @method('DELETE')
                    <input type="hidden" name="draft_revision" value="{{ $check->draft_revision }}">
                    <button class="nightly-icon text-rose-600" aria-label="Cancel Draf" title="Batalkan draf pemeriksaan"><span data-nightly-icon="cancel" aria-hidden="true"></span></button>
                </form>
            @endif
        </div>
    </header>
    <details class="nightly-count-guide" data-nightly-count-guide>
        <summary>Stok sistem dan jumlah fisik</summary>
        <div>
            <div class="nightly-mobile-column-help" aria-label="Keterangan kolom pemeriksaan">
                @foreach(['medicine', 'system', 'physical', 'status'] as $column)
                    <x-nightly-column-help :column="$column" :label="$columnHelp[$column][0]" />
                @endforeach
            </div>
            <p><strong>Stok sistem</strong> berasal dari stok awal dan transaksi yang sudah tercatat. <strong>Jumlah fisik</strong> adalah hasil hitung langsung di {{ $check->location }}, sesuai satuan obat; bukan angka yang harus disamakan dengan stok sistem.</p>
            <p><strong>Contoh:</strong> sistem 10 tablet, fisik 2 tablet. Jumlah fisik diisi 2. Setelah disetujui, stok sistem menjadi 2, bukan 8.</p>
            <p>Isi 0 jika sudah dihitung dan habis; kosong berarti belum diperiksa. Simpan draf atau kirim laporan belum mengubah stok. Penyesuaian hanya untuk obat yang diperiksa, setelah kepala apoteker menyetujui. Stok awal tidak perlu diisi ulang setiap pemeriksaan.</p>
        </div>
    </details>
    <div class="nightly-notices">
        @if(!$editable)
            <p role="note" data-nightly-readonly class="nightly-notice"><strong>Mode lihat saja.</strong>
                @if(!auth()->user()->can('operate-inventory')) Akun Anda hanya memiliki akses melihat laporan, bukan mengisi hasil hitung.
                @elseif($check->status === 'submitted') Laporan sudah dikirim untuk ditinjau. Jika perlu diperbaiki, kepala apoteker dapat mengembalikannya melalui Kembalikan untuk Koreksi agar petugas atau apoteker dapat melanjutkan draf.
                @else Laporan sudah disetujui dan tidak dapat diedit. Gunakan pemeriksaan berikutnya untuk mencatat hasil hitung baru; riwayat laporan ini tetap dipertahankan.
                @endif
            </p>
        @endif
        @if(session('status'))<p role="status" class="nightly-notice !border-emerald-500 !bg-emerald-50 text-emerald-800">{{ session('status') }}</p>@endif
        @if($errors->any())<p role="alert" class="nightly-notice !border-rose-500 !bg-rose-50 text-rose-800">{{ $errors->first() }} @if($errors->has('draft_revision'))<a href="{{ route('nightly.show', $check->id) }}" target="_blank" rel="noopener" class="font-semibold underline" data-nightly-latest>Buka versi terbaru di tab baru</a>@endif</p>@endif
        @if($check->review_note)<p class="nightly-notice !border-amber-500 !bg-amber-50"><strong>Catatan apoteker:</strong> {{ $check->review_note }}</p>@endif
        @if($uncheckedCount > 0 && !$editable)<p data-nightly-incomplete class="text-xs text-amber-800">{{ $uncheckedCount }} obat belum diperiksa. {{ $check->status === 'draft' ? 'Belum ada perubahan stok dari draf ini.' : 'Pemeriksaan dikirim sebagian. Persetujuan hanya mencakup '.$checkedCount.' obat yang sudah diperiksa.' }} Obat yang belum diperiksa tidak dianggap kosong dan stoknya tidak disesuaikan.</p>@endif
    </div>
    <div class="nightly-tabs" role="tablist" aria-label="Tampilan pemeriksaan">
        <button id="nightly-tab-list" type="button" role="tab" aria-selected="true" aria-controls="nightly-panel-list" data-nightly-view="list">Daftar Obat</button>
        @if($reviewable)<button id="nightly-tab-review" type="button" role="tab" aria-selected="false" tabindex="-1" aria-controls="nightly-panel-review" data-nightly-view="review">Tinjauan Apoteker</button>@endif
        <button id="nightly-tab-summary" type="button" role="tab" aria-selected="false" tabindex="-1" aria-controls="nightly-panel-summary" data-nightly-view="summary">Ringkasan</button>
        <button id="nightly-tab-activity" type="button" role="tab" aria-selected="false" tabindex="-1" aria-controls="nightly-panel-activity" data-nightly-view="activity">Aktivitas</button>
    </div>
    @if($editable)
    <form id="nightly-count-form" class="nightly-panel" method="POST" action="{{ route('nightly.save', $check->id) }}" data-nightly-count-form novalidate>
        @csrf
        <input type="hidden" name="batch_counts_payload" data-nightly-batch-payload>
        <input type="hidden" name="observed_revision" value="{{ old('observed_revision', $observedRevision) }}">
        <input type="hidden" name="draft_revision" value="{{ old('draft_revision', $check->draft_revision) }}">
        <input type="hidden" name="confirm_partial" value="0" data-nightly-confirm-partial>
    @else
    <div class="nightly-panel" data-nightly-list-container>
    @endif
        <div id="nightly-panel-list" class="nightly-panel" role="tabpanel" aria-labelledby="nightly-tab-list" data-nightly-panel="list">
            <div class="nightly-toolbar">
                <label><span class="label">Cari obat</span><input id="nightly-search" class="field" type="search" placeholder="Nama atau kode obat..." autocomplete="off"></label>
                <label><span class="label">Tampilkan</span><select id="nightly-filter" class="field"><option value="all">Semua obat</option><option value="unchecked">Belum dicek</option><option value="empty">Stok kosong</option><option value="low">Stok menipis</option></select></label>
                <label><span class="label">Per halaman</span><select id="nightly-page-size" class="field"><option value="10">10 obat</option><option value="25">25 obat</option><option value="50">50 obat</option><option value="100">100 obat</option></select></label>
                @if($editable)<button type="button" class="btn-secondary self-end" data-nightly-add-open aria-label="Tambah Obat" aria-controls="nightly-add-dialog" @disabled($availableMedicines->isEmpty()) title="{{ $availableMedicines->isEmpty() ? 'Semua obat sudah masuk laporan' : 'Tambahkan obat ke draf' }}"><span data-nightly-add-icon aria-hidden="true"></span><span class="nightly-add-label">Tambah Obat</span></button>@endif
            </div>
            <div class="nightly-pager">
                <span id="nightly-result-count" aria-live="polite">{{ $items->count() }} obat</span>
                <div><button id="nightly-prev" class="nightly-icon" type="button" aria-label="Halaman sebelumnya" title="Halaman sebelumnya"><span data-nightly-icon="previous" aria-hidden="true"></span></button><span id="nightly-page-info">Halaman 1</span><button id="nightly-next" class="nightly-icon" type="button" aria-label="Halaman berikutnya" title="Halaman berikutnya"><span data-nightly-icon="next" aria-hidden="true"></span></button></div>
            </div>
            <div id="nightly-table-scroll" class="nightly-list" tabindex="0" aria-label="Daftar hasil hitung obat">
                <div class="nightly-columns" role="group" aria-label="Keterangan kolom pemeriksaan">
                    @foreach(['medicine', 'system', 'physical', 'status'] as $column)
                        <span><x-nightly-column-help :column="$column" :label="$columnHelp[$column][0]" /></span>
                    @endforeach
                </div>
                @foreach($items as $i) @include('nightly.partials.medicine-row') @endforeach
                <p class="p-5 text-center text-xs text-slate-500" data-nightly-empty hidden>Tidak ada obat yang sesuai. Ubah pencarian atau filter.</p>
            </div>
            <p class="text-[11px] text-slate-500">{{ $editable ? 'Kosong = belum diperiksa. Isi 0 jika stok habis. Buka Rincian untuk hitung per batch, permintaan, atau catatan.' : 'Stok sistem adalah stok saat halaman dibuka; hasil hitung adalah angka yang disimpan dalam laporan.' }}</p>
        </div>
        @if($editable)
            <footer class="nightly-footer" data-nightly-footer>
                <div class="nightly-progress"><div class="flex justify-between gap-2"><strong data-nightly-progress-label>{{ $checkedCount }} / {{ $items->count() }} obat diperiksa</strong><span class="nightly-save-state" data-nightly-save-state>Isian tersimpan</span></div><progress aria-label="Kemajuan pemeriksaan" max="{{ max(1, $items->count()) }}" value="{{ $checkedCount }}" data-nightly-progress></progress></div>
                <div class="nightly-footer-actions">
                    <button type="button" class="nightly-icon" data-next-unchecked aria-label="Ke obat berikutnya yang belum dicek" title="Ke obat berikutnya yang belum dicek"><span data-nightly-icon="next" aria-hidden="true"></span></button>
                    <button class="btn-secondary" name="action" value="save"><span data-nightly-icon="save" aria-hidden="true"></span> Simpan Draf</button>
                    <button class="btn" name="action" value="submit"><span data-nightly-icon="send" aria-hidden="true"></span> Kirim ke Kepala Apoteker</button>
                </div>
            </footer>
            @include('nightly.partials.add-dialog')
        @endif
    @if($editable)</form>@else</div>@endif
    @if($reviewable)
        <section id="nightly-panel-review" class="nightly-panel" role="tabpanel" aria-labelledby="nightly-tab-review" data-nightly-panel="review" hidden>
            <form class="nightly-panel" method="POST" action="{{ route('nightly.review', $check->id) }}">
                @csrf
                <p class="text-xs text-slate-600">Persetujuan memperbarui total dan batch hanya untuk {{ $checkedCount }} obat yang sudah diperiksa dalam laporan ini. {{ $uncheckedCount }} obat belum diperiksa dan stoknya tidak berubah. Jika stok berubah setelah laporan dikirim, kembalikan untuk koreksi dan hitung ulang.</p>
                <div class="nightly-scroll-panel"><table class="w-full text-left text-xs"><thead><tr>
                    <th class="p-2"><x-nightly-column-help column="medicine" label="Obat" /></th>
                    <th class="p-2"><x-nightly-column-help column="system" label="Stok sistem sekarang" /></th>
                    <th class="p-2"><x-nightly-column-help column="counted" label="Hasil hitung" /></th>
                    <th class="p-2"><x-nightly-column-help column="difference" label="Perubahan jika disetujui" /></th>
                </tr></thead><tbody>
                    @foreach($items->whereNotNull('quantity') as $item)
                        @php($systemQuantity = (float) ($systemBalances[$item->medicine_id] ?? 0))
                        <tr class="border-t border-slate-100"><td class="p-2">{{ $item->name }}</td><td class="p-2">{{ \App\Support\Quantity::format($systemQuantity) }} {{ $item->unit }}</td><td class="p-2">{{ \App\Support\Quantity::format($item->quantity) }} {{ $item->unit }}</td><td class="p-2">{{ \App\Support\Quantity::format($item->quantity - $systemQuantity) }} {{ $item->unit }}</td></tr>
                    @endforeach
                </tbody></table></div>
                <label class="text-xs font-semibold" for="review">Catatan tinjauan (wajib jika dikembalikan)</label><textarea class="field" id="review" name="review_note" maxlength="2000" rows="2" placeholder="Arahan atau alasan koreksi..."></textarea>
                <div class="nightly-footer"><button class="btn" name="action" value="approve" data-confirm-stock-review data-checked-count="{{ $checkedCount }}" data-unchecked-count="{{ $uncheckedCount }}">Setujui &amp; Sesuaikan Stok</button><button class="btn-secondary text-rose-700" name="action" value="return">Kembalikan untuk Koreksi</button></div>
            </form>
        </section>
    @endif
    <section id="nightly-panel-summary" class="nightly-panel" role="tabpanel" aria-labelledby="nightly-tab-summary" data-nightly-panel="summary" hidden>
        <h2>Rekap Kekurangan dan Permintaan Persediaan</h2>
        <p class="text-xs text-slate-600">{{ $check->status === 'approved' ? 'Berdasarkan hasil pemeriksaan yang disetujui.' : 'Draf rekap, belum disetujui apoteker.' }} Tujuan supplier dan jumlah pesanan akhir ditentukan apoteker. Laporan ini tidak mengirim pesanan otomatis.</p>
        <p class="text-xs text-slate-500">Ringkasan dan cetakan memakai data terakhir yang disimpan. Simpan Draf terlebih dahulu jika ada perubahan.</p>
        <textarea id="nightly-summary" aria-label="Ringkasan untuk disalin" readonly class="field nightly-summary font-mono">{{ $summary }}</textarea>
        <div class="nightly-footer"><button type="button" class="btn-secondary" data-nightly-copy><span data-nightly-icon="copy" aria-hidden="true"></span><span data-nightly-copy-label>Salin Ringkasan WAG</span></button></div>
    </section>
    <section id="nightly-panel-activity" class="nightly-panel" role="tabpanel" aria-labelledby="nightly-tab-activity" data-nightly-panel="activity" hidden>
        <p class="text-xs text-slate-600">Draf dapat dilanjutkan petugas atau kepala apoteker yang memiliki izin operasional. Nama pembuat tetap tersimpan; riwayat berikut mencatat setiap penyimpanan yang berhasil.</p>
        <div class="nightly-scroll-panel">
            <ol class="divide-y divide-slate-100" data-nightly-activities>
                @forelse($activities as $activity)
                    @php($activityLabels = ['created' => 'Membuat laporan', 'saved' => 'Menyimpan draf', 'added' => 'Menambah obat', 'submitted' => 'Mengirim untuk tinjauan', 'returned' => 'Mengembalikan untuk koreksi', 'approved' => 'Menyetujui laporan'])
                    @php($metadata = json_decode($activity->metadata ?? '[]', true))
                    <li class="py-3 text-xs">
                        <div class="flex flex-wrap justify-between gap-2"><strong>{{ $activity->actor_name }}</strong><time class="text-slate-500">{{ \Carbon\Carbon::parse($activity->created_at, config('app.timezone'))->timezone('Asia/Jakarta')->format('d/m/Y H:i:s') }} WIB</time></div>
                        <p class="mt-1">{{ $activityLabels[$activity->action] ?? $activity->action }}
                            @if(isset($metadata['changed_count'])) &middot; {{ $metadata['changed_count'] }} obat diperbarui @endif
                            @if(!empty($metadata['added_count'])) &middot; {{ $metadata['added_count'] }} obat ditambahkan @endif
                        </p>
                    </li>
                @empty
                    <li class="py-3 text-xs text-slate-500">Belum ada catatan pengerjaan. Aktivitas sebelum pembaruan ini tidak dicatat ulang; nama pembuat tetap tersedia.</li>
                @endforelse
            </ol>
        </div>
        <div class="text-xs">{{ $activities->links() }}</div>
    </section>
</section>
@foreach($columnHelp as $column => [$label, $description])
    <div id="nightly-help-{{ $column }}" role="tooltip" popover="auto" class="nightly-column-tooltip"><strong>{{ $label }}</strong><p>{{ $description }}</p></div>
@endforeach
</x-layouts.app>
