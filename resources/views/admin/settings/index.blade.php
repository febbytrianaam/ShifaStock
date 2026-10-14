<x-layouts.app title="Pengaturan Sistem">
    @if(session('status'))
        <div class="mb-5 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-700">{{ session('status') }}</div>
    @endif
    @if($errors->any())
        <div class="mb-5 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-700">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ route('admin.settings.update') }}" class="card max-w-4xl p-5" data-required-alert="Lengkapi pengaturan sistem" novalidate>
        @csrf
        <div class="grid gap-4 md:grid-cols-2">
            <div>
                <label class="label" for="default-receipt-location">Default lokasi penerimaan</label>
                <select class="field text-xs" id="default-receipt-location" name="default_receipt_location_id">
                    <option value="">Gunakan Gudang jika tersedia</option>
                    @foreach($locations as $location)
                        <option value="{{ $location->id }}" @selected((string) ($settings['default_receipt_location_id'] ?? '') === (string) $location->id)>{{ $location->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="label" for="clinic-report-name">Nama klinik di laporan</label>
                <input class="field text-xs" id="clinic-report-name" name="clinic_report_name" value="{{ old('clinic_report_name', $settings['clinic_report_name'] ?? 'Klinik Shifa Medika') }}" required maxlength="255">
            </div>
            <div class="md:col-span-2">
                <label class="label" for="clinic-report-footer">Catatan footer laporan</label>
                <input class="field text-xs" id="clinic-report-footer" name="clinic_report_footer" value="{{ old('clinic_report_footer', $settings['clinic_report_footer'] ?? '') }}" placeholder="Opsional, misalnya alamat atau catatan internal" maxlength="500">
            </div>
        </div>

        <div class="mt-5 rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600">
            Periode peringatan kedaluwarsa saat ini {{ $settings['expiry_warning_months'] ?? 3 }} bulan. Pengaturan detailnya tetap dikelola di menu Parameter Monitoring.
        </div>

        <div class="mt-5 flex justify-end">
            <button class="btn">Simpan Pengaturan</button>
        </div>
    </form>
</x-layouts.app>
