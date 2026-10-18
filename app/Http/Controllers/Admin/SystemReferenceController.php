<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Models\Unit;
use App\Support\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SystemReferenceController extends Controller
{
    public function index()
    {
        return view('admin.references.index', [
            'locations' => Location::orderBy('name')->get(),
            'units' => Unit::orderBy('name')->get(),
        ]);
    }

    public function storeLocation(Request $request)
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:20', 'alpha_dash:ascii', 'unique:locations,code'],
            'name' => ['required', 'string', 'max:255', 'unique:locations,name'],
            'is_active' => ['nullable', 'boolean'],
        ]);
        $data['code'] = strtoupper($data['code']);
        $data['is_active'] = $request->boolean('is_active', true);

        $location = Location::create($data);

        AuditLogger::record('reference.location.created', 'Lokasi stok '.$location->name.' dibuat.', $location, [
            'after' => $location->only(['id', 'code', 'name', 'is_active']),
        ], $request);

        return back()->with('status', 'Lokasi stok berhasil ditambahkan.');
    }

    public function updateLocation(Request $request, Location $location)
    {
        $before = $location->only(['id', 'code', 'name', 'is_active']);
        $data = $request->validate([
            'code' => ['required', 'string', 'max:20', 'alpha_dash:ascii', Rule::unique('locations', 'code')->ignore($location)],
            'name' => ['required', 'string', 'max:255', Rule::unique('locations', 'name')->ignore($location)],
            'is_active' => ['nullable', 'boolean'],
        ]);
        $data['code'] = strtoupper($data['code']);
        $data['is_active'] = $request->boolean('is_active');

        if (! $data['is_active'] && Location::whereKeyNot($location->id)->where('is_active', true)->doesntExist()) {
            return back()->withErrors(['location' => 'Minimal harus ada satu lokasi aktif.']);
        }

        $location->update($data);
        $location->refresh();

        AuditLogger::record('reference.location.updated', 'Lokasi stok '.$location->name.' diperbarui.', $location, [
            'before' => $before,
            'after' => $location->only(['id', 'code', 'name', 'is_active']),
        ], $request);

        return back()->with('status', 'Lokasi stok berhasil diperbarui.');
    }

    public function storeUnit(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:units,name'],
            'is_active' => ['nullable', 'boolean'],
        ]);
        $data['is_active'] = $request->boolean('is_active', true);

        $unit = Unit::create($data);

        AuditLogger::record('reference.unit.created', 'Satuan obat '.$unit->name.' dibuat.', $unit, [
            'after' => $unit->only(['id', 'name', 'is_active']),
        ], $request);

        return back()->with('status', 'Satuan obat berhasil ditambahkan.');
    }

    public function updateUnit(Request $request, Unit $unit)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('units', 'name')->ignore($unit)],
            'is_active' => ['nullable', 'boolean'],
        ]);
        $data['is_active'] = $request->boolean('is_active');

        if (! $data['is_active'] && Unit::whereKeyNot($unit->id)->where('is_active', true)->doesntExist()) {
            return back()->withErrors(['unit' => 'Minimal harus ada satu satuan aktif.']);
        }

        \App\Services\InventoryTransaction::run(function () use ($unit, $data, $request) {
            $unit = Unit::whereKey($unit->id)->lockForUpdate()->firstOrFail();
            $before = $unit->only(['id', 'name', 'is_active']);
            $unit->update($data);
            $unit->refresh();

            AuditLogger::record('reference.unit.updated', 'Satuan obat '.$unit->name.' diperbarui.', $unit, [
                'before' => $before,
                'after' => $unit->only(['id', 'name', 'is_active']),
            ], $request);
        });

        return back()->with('status', 'Satuan obat berhasil diperbarui.');
    }
}
