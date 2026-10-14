<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Models\SystemSetting;
use App\Support\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SystemSettingController extends Controller
{
    public function edit()
    {
        return view('admin.settings.index', [
            'locations' => Location::where('is_active', true)->orderBy('name')->get(),
            'settings' => [
                'default_receipt_location_id' => SystemSetting::value('default_receipt_location_id'),
                'clinic_report_name' => SystemSetting::value('clinic_report_name', 'Klinik Shifa Medika'),
                'clinic_report_footer' => SystemSetting::value('clinic_report_footer'),
                'expiry_warning_months' => SystemSetting::value('expiry_warning_months', 3),
            ],
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'default_receipt_location_id' => ['nullable', Rule::exists('locations', 'id')->where('is_active', true)],
            'clinic_report_name' => ['required', 'string', 'max:255'],
            'clinic_report_footer' => ['nullable', 'string', 'max:500'],
        ]);

        $before = [
            'default_receipt_location_id' => SystemSetting::value('default_receipt_location_id'),
            'clinic_report_name' => SystemSetting::value('clinic_report_name'),
            'clinic_report_footer' => SystemSetting::value('clinic_report_footer'),
        ];

        foreach ($data as $key => $value) {
            SystemSetting::put($key, $value ?? '');
        }

        AuditLogger::record('system_settings.updated', 'Pengaturan sistem diperbarui.', null, [
            'before' => $before,
            'after' => $data,
        ], $request);

        return back()->with('status', 'Pengaturan sistem berhasil disimpan.');
    }
}
