<?php

namespace App\Http\Controllers;

use App\Models\Medicine;
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

        $latestChecks = DB::table('nightly_checks')
            ->where('status', 'approved')
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->get()
            ->unique('location_id');

        $query = DB::table('nightly_check_items as i')
            ->join('nightly_checks as c', 'c.id', '=', 'i.nightly_check_id')
            ->join('locations as l', 'l.id', '=', 'c.location_id')
            ->whereIn('c.id', $latestChecks->pluck('id'))
            ->where(fn ($q) => $q->where('i.quantity', 0)->orWhere('i.is_low', true))
            ->select('i.*', 'c.date', 'l.name as location');

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
            ->selectRaw('m.code, m.name, l.name as location, COALESCE(b.quantity, 0) as quantity, p.minimum_quantity, u.name as unit, b.updated_at as stock_updated_at');

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

        $allLatestShortages = DB::table('nightly_check_items')
            ->whereIn('nightly_check_id', $latestChecks->pluck('id'))
            ->where(fn ($q) => $q->where('quantity', 0)->orWhere('is_low', true));

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

        return view('nightly.index', ['checks' => $checks, 'locations' => DB::table('locations')->where('is_active', true)->get()]);
    }

    public function store(Request $r)
    {
        $d = $r->validate(['date' => ['required', 'date_format:Y-m-d', 'before_or_equal:'.now('Asia/Jakarta')->toDateString()], 'location_id' => ['required', 'exists:locations,id']]);
        $id = DB::transaction(function () use ($r, $d) {
            abort_unless(DB::table('locations')->where('id', $d['location_id'])->where('is_active', true)->exists(), 422);
            // Lock the location to serialize creation of a daily report.
            DB::table('locations')->where('id', $d['location_id'])->lockForUpdate()->first();
            $existing = DB::table('nightly_checks')->where($d)->first();
            if ($existing) {
                return $existing->id;
            }
            $medicines = Medicine::with('unit')->where(fn ($q) => $q->where('is_active', true)->orWhereNotNull('import_source'))->get();
            if ($medicines->isEmpty()) {
                throw ValidationException::withMessages(['date' => 'Belum ada master obat untuk diperiksa.']);
            }
            $id = DB::table('nightly_checks')->insertGetId($d + ['created_by' => $r->user()->id, 'status' => 'draft', 'created_at' => now(), 'updated_at' => now()]);
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
        $previous = DB::table('nightly_checks')->where('location_id', $check->location_id)->where('status', 'approved')->where('date', '<', $check->date)->orderByDesc('date')->first();
        $baseline = $previous ? DB::table('nightly_check_items')->where('nightly_check_id', $previous->id)->get()->keyBy('medicine_id') : collect();
        $shortages = $items->filter(fn ($i) => $i->quantity !== null && ($i->quantity === 0 || $i->is_low));
        $summary = "Laporan stok malam {$check->date} - {$check->location}\nStatus: {$check->status}\nObat kosong / menipis:\n";
        foreach ($shortages as $i) {
            $summary .= "- {$i->name}: {$i->quantity} {$i->unit}".($i->quantity === 0 ? ' (kosong)' : ' (menipis)').($i->requested_quantity !== null ? "; permintaan {$i->requested_quantity} {$i->unit}" : '')."\n";
        }
        if ($shortages->isEmpty()) {
            $summary .= "Belum ada obat kosong / menipis yang tercatat.\n";
        }

        $systemBalances = DB::table('stock_balances')->where('location_id', $check->location_id)->pluck('quantity', 'medicine_id');

        return view('nightly.show', compact('check', 'items', 'previous', 'baseline', 'shortages', 'summary', 'systemBalances'));
    }

    public function save(Request $r, int $id)
    {
        $d = $r->validate(['action' => ['required', 'in:save,submit'], 'items' => ['required', 'array'], 'items.*.quantity' => ['nullable', 'integer', 'min:0', 'max:2147483647'], 'items.*.unit' => ['nullable', 'string', 'max:100'], 'items.*.is_low' => ['nullable', 'boolean'], 'items.*.requested_quantity' => ['nullable', 'integer', 'min:0', 'max:2147483647'], 'items.*.note' => ['nullable', 'string', 'max:1000']]);
        DB::transaction(function () use ($r, $id, $d) {
            $c = DB::table('nightly_checks')->where('id', $id)->lockForUpdate()->first();
            abort_unless($c, 404);
            abort_unless($c->created_by === $r->user()->id && $c->status === 'draft', 403);
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
                    throw ValidationException::withMessages(['items' => 'Periksa semua obat sebelum mengirim. Isi 0 hanya jika benar-benar kosong.']);
                }
                DB::table('nightly_check_items')->where('id', $i->id)->update(['quantity' => $q, 'unit' => $unit ?: null, 'is_low' => ! empty($v['is_low']), 'requested_quantity' => $v['requested_quantity'] ?? null, 'note' => $v['note'] ?? null]);
            }
            DB::table('nightly_checks')->where('id', $id)->update(['status' => $d['action'] === 'submit' ? 'submitted' : 'draft', 'submitted_at' => $d['action'] === 'submit' ? now() : null, 'updated_at' => now()]);
        });

        return back()->with('status', $d['action'] === 'submit' ? 'Laporan dikirim untuk ditinjau kepala apoteker.' : 'Draf tersimpan.');
    }

    public function destroy(Request $r, int $id)
    {
        DB::transaction(function () use ($r, $id) {
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
        DB::transaction(function () use ($r, $id, $d, $stock) {
            $c = DB::table('nightly_checks')->where('id', $id)->lockForUpdate()->first();
            abort_unless($c, 404);
            abort_unless($c->status === 'submitted', 409);
            if ($d['action'] === 'approve') {
                foreach (DB::table('nightly_check_items')->where('nightly_check_id', $id)->get() as $item) {
                    $stock->setPhysicalCount(['id' => $id, 'location_id' => $c->location_id], $item, $r->user()->id);
                }
            }
            DB::table('nightly_checks')->where('id', $id)->update(['status' => $d['action'] === 'approve' ? 'approved' : 'draft', 'review_note' => $d['review_note'] ?? null, 'reviewed_by' => $r->user()->id, 'approved_at' => $d['action'] === 'approve' ? now() : null, 'updated_at' => now()]);
        });

        return back()->with('status', $d['action'] === 'approve' ? 'Disetujui. Hasil hitung menjadi catatan stok pada tanggal pemeriksaan.' : 'Dikembalikan kepada petugas untuk dikoreksi.');
    }
}
