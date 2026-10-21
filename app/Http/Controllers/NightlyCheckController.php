<?php

namespace App\Http\Controllers;

use App\Models\Medicine;
use App\Models\Batch;
use App\Models\InventoryMovement;
use App\Models\Unit;
use App\Queries\LatestNightlyItems;
use App\Services\DailyPhysicalCountService;
use App\Services\InventoryStockService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class NightlyCheckController extends Controller
{
    public function shortages(Request $r)
    {
        $data = $r->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'location_id' => ['nullable', 'exists:locations,id'],
            'status' => ['nullable', 'in:all,empty,low'],
        ]);

        $query = LatestNightlyItems::query()
            ->join('locations as l', 'l.id', '=', 'c.location_id')
            ->where(fn ($q) => $q->where('i.quantity', 0)->orWhere('i.is_low', true))
            ->select('i.*', 'c.date', 'l.name as location', 'c.location_id');

        if (! empty($data['search'])) {
            $query->where(fn ($q) => $q
                ->where('i.name', 'like', '%'.$data['search'].'%')
                ->orWhere('i.code', 'like', '%'.$data['search'].'%'));
        }
        if (! empty($data['location_id'])) {
            $query->where('c.location_id', $data['location_id']);
        }
        if (($data['status'] ?? 'all') === 'empty') {
            $query->where('i.quantity', 0);
        } elseif (($data['status'] ?? 'all') === 'low') {
            $query->where('i.quantity', '>', 0)->where('i.is_low', true);
        }

        $parameterQuery = DB::table('stock_parameters as p')
            ->join('medicines as m', 'm.id', '=', 'p.medicine_id')
            ->join('locations as l', 'l.id', '=', 'p.location_id')
            ->leftJoin('stock_balances as b', fn ($join) => $join
                ->on('b.medicine_id', '=', 'p.medicine_id')
                ->on('b.location_id', '=', 'p.location_id'))
            ->leftJoin('units as u', 'u.id', '=', 'm.unit_id')
            ->whereNotNull('p.minimum_quantity')
            ->whereRaw('COALESCE(b.quantity, 0) <= p.minimum_quantity')
            ->selectRaw('m.id as medicine_id, m.code, m.name, l.id as location_id, l.name as location, COALESCE(b.quantity, 0) as quantity, p.minimum_quantity, u.name as unit, b.updated_at as stock_updated_at');

        if (! empty($data['search'])) {
            $parameterQuery->where(fn ($q) => $q
                ->where('m.name', 'like', '%'.$data['search'].'%')
                ->orWhere('m.code', 'like', '%'.$data['search'].'%'));
        }
        if (! empty($data['location_id'])) {
            $parameterQuery->where('p.location_id', $data['location_id']);
        }
        if (($data['status'] ?? 'all') === 'empty') {
            $parameterQuery->whereRaw('COALESCE(b.quantity, 0) = 0');
        } elseif (($data['status'] ?? 'all') === 'low') {
            $parameterQuery->whereRaw('COALESCE(b.quantity, 0) > 0');
        }

        $allLatestShortages = LatestNightlyItems::query()
            ->where(fn ($q) => $q->where('i.quantity', 0)->orWhere('i.is_low', true));

        return view('nightly.shortages', [
            'rows' => $query->orderBy('l.name')->orderBy('i.name')->paginate(25)->withQueryString(),
            'parameterRows' => $parameterQuery->orderBy('l.name')->orderBy('m.name')->get(),
            'locations' => DB::table('locations')->where('is_active', true)->orderBy('name')->get(),
            'summary' => [
                'total' => (clone $allLatestShortages)->count(),
                'empty' => (clone $allLatestShortages)->where('quantity', 0)->count(),
                'low' => (clone $allLatestShortages)->where('quantity', '>', 0)->where('is_low', true)->count(),
                'requested' => (clone $allLatestShortages)->whereNotNull('requested_quantity')->count(),
            ],
        ]);
    }

    public function history(Request $r)
    {
        $d = $r->validate(['from' => ['nullable', 'date_format:Y-m-d'], 'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'], 'search' => ['nullable', 'string', 'max:255'], 'location_id' => ['nullable', 'exists:locations,id']]);
        $rows = DB::table('nightly_check_items as i')->join('nightly_checks as c', 'c.id', '=', 'i.nightly_check_id')->join('locations as l', 'l.id', '=', 'c.location_id')
            ->where('c.status', 'approved')->select('i.*', 'c.date', 'l.name as location');
        if (! empty($d['from'])) {
            $rows->where('c.date', '>=', $d['from']);
        }
        if (! empty($d['to'])) {
            $rows->where('c.date', '<=', $d['to']);
        }
        if (! empty($d['location_id'])) {
            $rows->where('c.location_id', $d['location_id']);
        }
        if (! empty($d['search'])) {
            $rows->where(fn ($q) => $q->where('i.name', 'like', '%'.$d['search'].'%')->orWhere('i.code', 'like', '%'.$d['search'].'%'));
        }

        return view('nightly.history', ['rows' => $rows->orderByDesc('c.date')->orderBy('i.name')->paginate(30)->withQueryString(), 'locations' => DB::table('locations')->get()]);
    }

    public function index()
    {
        $checks = DB::table('nightly_checks as c')->join('locations as l', 'l.id', '=', 'c.location_id')->select('c.*', 'l.name as location')->orderByDesc('date')->orderByDesc('c.id')->paginate(20);

        return view('nightly.index', [
            'checks' => $checks,
            'locations' => DB::table('locations')->where('is_active', true)->get(),
            'medicines' => Medicine::with('unit')->where(fn ($q) => $q->where('is_active', true)->orWhereNotNull('import_source'))->orderBy('name')->get(),
        ]);
    }

    public function store(Request $r)
    {
        $d = $r->validate([
            'date' => ['required', 'date_format:Y-m-d', 'before_or_equal:'.now('Asia/Jakarta')->toDateString()],
            'location_id' => ['required', 'exists:locations,id'],
            'scope' => ['sometimes', 'required', 'in:complete,selected'],
            'medicine_ids' => ['required_if:scope,selected', 'nullable', 'array', 'min:1'],
            'medicine_ids.*' => ['required', 'integer', 'distinct', 'exists:medicines,id'],
        ], ['medicine_ids.required_if' => 'Pilih sedikitnya satu obat yang akan diperiksa.']);
        $scope = $d['scope'] ?? 'complete';
        $id = \App\Services\InventoryTransaction::run(function () use ($r, $d, $scope) {
            abort_unless(DB::table('locations')->where('id', $d['location_id'])->where('is_active', true)->exists(), 422);
            // Lock the location to serialize creation of a daily report.
            DB::table('locations')->where('id', $d['location_id'])->lockForUpdate()->first();
            $existing = DB::table('nightly_checks')->where('date', $d['date'])->where('location_id', $d['location_id'])->first();
            if ($existing) {
                return $existing->id;
            }
            $medicines = Medicine::with('unit')->where(fn ($q) => $q->where('is_active', true)->orWhereNotNull('import_source'))
                ->when($scope === 'selected', fn ($query) => $query->whereIn('id', $d['medicine_ids']))->get();
            if ($scope === 'selected' && $medicines->count() !== count($d['medicine_ids'])) {
                throw ValidationException::withMessages(['medicine_ids' => 'Ada obat yang tidak tersedia untuk pemeriksaan. Pilih obat aktif atau obat hasil impor.']);
            }
            if ($medicines->isEmpty()) {
                throw ValidationException::withMessages(['date' => 'Belum ada master obat untuk diperiksa.']);
            }
            $id = DB::table('nightly_checks')->insertGetId(['date' => $d['date'], 'location_id' => $d['location_id'], 'scope' => $scope, 'created_by' => $r->user()->id, 'status' => 'draft', 'created_at' => now(), 'updated_at' => now()]);
            foreach ($medicines as $m) {
                DB::table('nightly_check_items')->insert(['nightly_check_id' => $id, 'medicine_id' => $m->id, 'code' => $m->code, 'name' => $m->name, 'unit' => $m->unit?->name]);
            }

            return $id;
        });

        return redirect()->route('nightly.show', $id);
    }

    public function show(int $id)
    {
        $check = DB::table('nightly_checks as c')->join('locations as l', 'l.id', '=', 'c.location_id')->join('users as u', 'u.id', '=', 'c.created_by')->select('c.*', 'l.name as location', 'u.name as creator')->where('c.id', $id)->first();
        abort_unless($check, 404);
        $items = DB::table('nightly_check_items')->where('nightly_check_id', $id)->orderBy('name')->get();
        $medicineUnits = Medicine::with('unit')->whereIn('id', $items->pluck('medicine_id'))->get()
            ->mapWithKeys(fn ($medicine) => [$medicine->id => $medicine->unit?->name]);
        $countUnits = Unit::where('is_active', true)->orderBy('name')->pluck('name');
        $baseline = LatestNightlyItems::query($check->date)->where('c.location_id', $check->location_id)
            ->select('i.*', 'c.date as checked_date')->get()->keyBy('medicine_id');
        $shortages = $items->filter(fn ($i) => $i->quantity !== null && ($i->quantity === 0 || $i->is_low));
        $scopeLabel = $check->scope === 'selected' ? 'Obat tertentu' : 'Pemeriksaan lengkap';
        $summary = "Laporan stok malam {$check->date} - {$check->location}\nCakupan: {$scopeLabel} ({$items->count()} obat)\nStatus: {$check->status}\nObat kosong / menipis:\n";
        foreach ($shortages as $i) {
            $summary .= "- {$i->name}: {$i->quantity} {$i->unit}".($i->quantity === 0 ? ' (kosong)' : ' (menipis)').($i->requested_quantity !== null ? "; permintaan {$i->requested_quantity} {$i->unit}" : '')."\n";
        }
        if ($shortages->isEmpty()) {
            $summary .= "Belum ada obat kosong / menipis yang tercatat.\n";
        }

        $systemBalances = DB::table('stock_balances')->where('location_id', $check->location_id)->pluck('quantity', 'medicine_id');
        $batchOptions = Batch::whereIn('medicine_id', $items->pluck('medicine_id'))->orderBy('expiry_date')->orderBy('number')->get()->groupBy('medicine_id');
        $batchBalances = DB::table('batch_stock_balances')->where('location_id', $check->location_id)->pluck('quantity', 'batch_id');
        $observedRevision = (int) (InventoryMovement::where(fn ($query) => $query->where('source_location_id', $check->location_id)->orWhere('destination_location_id', $check->location_id))->max('id') ?? 0);
        foreach ($items as $item) {
            $item->saved_batch_counts = json_decode($item->batch_counts ?? '[]', true);
        }

        return view('nightly.show', compact('check', 'items', 'medicineUnits', 'countUnits', 'baseline', 'shortages', 'summary', 'systemBalances', 'batchOptions', 'batchBalances', 'observedRevision'));
    }

    public function save(Request $r, int $id, DailyPhysicalCountService $counts)
    {
        $r->validate(['batch_counts_payload' => ['nullable', 'json', 'max:200000'], 'observed_revision' => ['nullable', 'integer', 'min:0']]);
        if ($r->has('batch_counts_payload') && ! $r->filled('batch_counts_payload')) {
            throw ValidationException::withMessages(['items' => 'Rincian hitungan belum siap dikirim. Aktifkan JavaScript lalu muat ulang halaman.']);
        }
        if ($r->filled('batch_counts_payload')) {
            $payload = json_decode($r->input('batch_counts_payload'), true);
            $items = $r->input('items', []);
            if (! is_array($payload) || ! is_array($items)) {
                throw ValidationException::withMessages(['items' => 'Format rincian batch tidak valid. Muat ulang halaman.']);
            }
            foreach ($payload as $itemId => $batchInput) {
                if (! isset($items[$itemId]) || ! is_array($items[$itemId])) {
                    throw ValidationException::withMessages(['items' => 'Rincian batch tidak sesuai dengan baris obat.']);
                }
                $items[$itemId]['batch_counts'] = $batchInput;
            }
            $r->merge(['items' => $items]);
        }
        $d = $r->validate(['action' => ['required', 'in:save,submit'], 'items' => ['required', 'array'], 'items.*.quantity' => ['nullable', 'integer', 'min:0', 'max:2147483647'], 'items.*.unit' => ['nullable', 'string', 'max:100'], 'items.*.is_low' => ['nullable', 'boolean'], 'items.*.requested_quantity' => ['nullable', 'integer', 'min:0', 'max:2147483647'], 'items.*.note' => ['nullable', 'string', 'max:1000']]);
        $batchData = $r->validate(['items.*.batch_counts' => ['nullable', 'array'], 'items.*.batch_counts.*' => ['nullable', 'integer', 'min:0', 'max:2147483647']]);
        \App\Services\InventoryTransaction::run(function () use ($r, $id, $d, $batchData, $counts) {
            $c = DB::table('nightly_checks')->where('id', $id)->lockForUpdate()->first();
            abort_unless($c, 404);
            abort_unless($c->created_by === $r->user()->id && $c->status === 'draft', 403);
            if ($d['action'] === 'submit' && $r->filled('observed_revision')) {
                $latest = (int) (InventoryMovement::where(fn ($query) => $query->where('source_location_id', $c->location_id)->orWhere('destination_location_id', $c->location_id))->max('id') ?? 0);
                if ((int) $r->input('observed_revision') !== $latest) {
                    throw ValidationException::withMessages(['items' => 'Ada transaksi stok sejak halaman dibuka. Simpan draf, muat ulang halaman, dan periksa ulang hitungan sebelum mengirim.']);
                }
            }
            $items = DB::table('nightly_check_items')->where('nightly_check_id', $id)->get();
            foreach ($items as $i) {
                $v = $d['items'][$i->id] ?? null;
                if (! $v) {
                    throw ValidationException::withMessages(['items' => 'Daftar pemeriksaan tidak lengkap. Muat ulang halaman.']);
                }
                $q = $v['quantity'] ?? null;
                $unit = trim($v['unit'] ?? '');
                if ($q !== null && $unit === '') {
                    throw ValidationException::withMessages(['items' => "Isi satuan hitung untuk {$i->name}."]);
                }
                if ($d['action'] === 'submit' && $q === null) {
                    throw ValidationException::withMessages(['items' => 'Lengkapi semua obat dalam laporan ini sebelum mengirim. Isi 0 hanya jika benar-benar kosong.']);
                }
                $batchCounts = $counts->counts($i->medicine_id, $batchData['items'][$i->id]['batch_counts'] ?? []);
                $snapshot = null;
                if ($d['action'] === 'submit') {
                    $snapshot = $counts->snapshot($i->medicine_id, $c->location_id);
                    $counts->validateTarget($i, (int) $q, $unit, $batchCounts, $snapshot);
                }
                DB::table('nightly_check_items')->where('id', $i->id)->update([
                    'batch_counts' => json_encode($batchCounts, JSON_THROW_ON_ERROR),
                    'stock_snapshot' => $snapshot === null ? null : json_encode($snapshot, JSON_THROW_ON_ERROR),
                ]);
                DB::table('nightly_check_items')->where('id', $i->id)->update(['quantity' => $q, 'unit' => $unit ?: null, 'is_low' => ! empty($v['is_low']), 'requested_quantity' => $v['requested_quantity'] ?? null, 'note' => $v['note'] ?? null]);
            }
            DB::table('nightly_checks')->where('id', $id)->update(['status' => $d['action'] === 'submit' ? 'submitted' : 'draft', 'submitted_at' => $d['action'] === 'submit' ? now() : null, 'updated_at' => now()]);
        });

        return back()->with('status', $d['action'] === 'submit' ? 'Laporan dikirim untuk ditinjau kepala apoteker.' : 'Draf tersimpan.');
    }

    public function destroy(Request $r, int $id)
    {
        \App\Services\InventoryTransaction::run(function () use ($r, $id) {
            $c = DB::table('nightly_checks')->where('id', $id)->lockForUpdate()->first();
            abort_unless($c, 404);
            abort_unless($c->created_by === $r->user()->id && $c->status === 'draft', 403);

            DB::table('nightly_checks')->where('id', $id)->delete();
        });

        return redirect()->route('preview.nightly')->with('status', 'Draf pemeriksaan dibatalkan.');
    }

    public function review(Request $r, int $id, InventoryStockService $stock)
    {
        Gate::authorize('finalize-opname');
        $d = $r->validate(['action' => ['required', 'in:approve,return'], 'review_note' => ['nullable', 'string', 'max:2000', 'required_if:action,return']]);
        \App\Services\InventoryTransaction::run(function () use ($r, $id, $d, $stock) {
            $c = DB::table('nightly_checks')->where('id', $id)->lockForUpdate()->first();
            abort_unless($c, 404);
            abort_unless($c->status === 'submitted', 409);
            if ($d['action'] === 'approve') {
                foreach (DB::table('nightly_check_items')->where('nightly_check_id', $id)->get() as $item) {
                    $stock->setPhysicalCount(['id' => $id, 'location_id' => $c->location_id], $item, $r->user()->id);
                }
            }
            DB::table('nightly_checks')->where('id', $id)->update(['status' => $d['action'] === 'approve' ? 'approved' : 'draft', 'review_note' => $d['review_note'] ?? null, 'reviewed_by' => $r->user()->id, 'approved_at' => $d['action'] === 'approve' ? now() : null, 'updated_at' => now()]);
        }, 3);

        return back()->with('status', $d['action'] === 'approve' ? 'Disetujui. Total stok dan rincian batch diperbarui sesuai hasil hitung.' : 'Dikembalikan kepada petugas untuk dikoreksi.');
    }
}
