<x-layouts.app title="Pengaturan Sistem">
    @if(session('status'))
        <div class="callout-ok mb-4 max-w-4xl font-semibold">{{ session('status') }}</div>
    @endif
    @if($errors->any())
        <div class="callout-danger mb-4 max-w-4xl font-semibold">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ route('admin.settings.update') }}" class="max-w-4xl" data-required-alert="Lengkapi pengaturan sistem" novalidate>
        @csrf
        <x-ui.section title="Operasional dan laporan" icon="settings" tone="slate"
            subtitle="Berlaku untuk semua pengguna setelah disimpan.">
            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <label class="label" for="default-receipt-location">Default lokasi penerimaan</label>
                    <select class="field" id="default-receipt-location" name="default_receipt_location_id">
                        <option value="">Gunakan Gudang jika tersedia</option>
                        @foreach($locations as $location)
                            <option value="{{ $location->id }}" @selected((string) ($settings['default_receipt_location_id'] ?? '') === (string) $location->id)>{{ $location->name }}</option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-xs text-slate-500">Juga dipakai Monitoring Stok sebagai sumber saran transfer.</p>
                </div>
                <div>
                    <label class="label" for="clinic-report-name">Nama klinik di laporan</label>
                    <input class="field" id="clinic-report-name" name="clinic_report_name" value="{{ old('clinic_report_name', $settings['clinic_report_name'] ?? 'Klinik Shifa Medika') }}" required maxlength="255">
                </div>
                <div class="md:col-span-2">
                    <label class="label" for="clinic-report-footer">Catatan footer laporan</label>
                    <input class="field" id="clinic-report-footer" name="clinic_report_footer" value="{{ old('clinic_report_footer', $settings['clinic_report_footer'] ?? '') }}" placeholder="Opsional, misalnya alamat atau catatan internal" maxlength="500">
                </div>
            </div>

            <div class="callout mt-5 flex items-start gap-2.5">
                <x-ui.icon name="info" class="mt-0.5 h-4 w-4 shrink-0 text-brand-700" />
                <p>Periode peringatan kedaluwarsa saat ini {{ $settings['expiry_warning_months'] ?? 3 }} bulan. Pengaturan detailnya tetap dikelola di menu Parameter Monitoring.</p>
            </div>

            <div class="mt-5 flex justify-end border-t border-slate-100 pt-4">
                <button class="btn min-h-11 lg:min-h-0">Simpan Pengaturan</button>
            </div>
        </x-ui.section>
    </form>
</x-layouts.app>
