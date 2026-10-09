<?php

namespace App\Http\Controllers;

use App\Models\Batch;
use App\Models\InventoryMovement;
use App\Models\Location;
use App\Models\Medicine;
use App\Models\StockBalance;
use App\Services\InventoryStockService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class InventoryController extends Controller
{
    public function receipts()
    {
        return view('inventory.receipts', [
            'medicines' => $this->operationalMedicines()->with('unit')->orderBy('name')->get(),
            'nextReference' => $this->nextReference('receipt', 'FKT'),
            'rows' => InventoryMovement::with(['medicine.unit', 'batch', 'destinationLocation', 'creator'])
                ->where('type', 'receipt')->latest('occurred_at')->paginate(20),
        ]);
    }

    public function storeReceipt(Request $request, InventoryStockService $stock)
    {
        $data = $request->validate([
            'reference' => ['required', 'string', 'max:100', Rule::unique('inventory_movements', 'reference')],
            'medicine_id' => ['required', 'exists:medicines,id'],
            'batch_number' => ['required', 'string', 'max:100'],
            'expiry_date' => ['required', 'date_format:Y-m-d'],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'occurred_at' => ['required', 'date_format:Y-m-d\\TH:i'],
            'counterparty' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);
        $medicine = Medicine::with('unit')->findOrFail($data['medicine_id']);
        if (! $this->isOperationalMedicine($medicine)) {
            throw ValidationException::withMessages(['medicine_id' => 'Obat harus aktif, punya bentuk sediaan, dan satuan dasar sebelum penerimaan dicatat.']);
        }
        $warehouse = Location::where('code', 'GDG')->where('is_active', true)->firstOrFail();

        DB::transaction(function () use ($data, $medicine, $warehouse, $request, $stock) {
            $batch = Batch::firstOrCreate(
                ['medicine_id' => $medicine->id, 'number' => trim($data['batch_number'])],
                ['expiry_date' => $data['expiry_date'], 'expiry_precision' => 'day'],
            );
            if ($batch->expiry_date?->toDateString() !== $data['expiry_date']) {
                throw ValidationException::withMessages(['expiry_date' => 'Tanggal kedaluwarsa berbeda dari batch yang sudah tercatat.']);
            }
            $stock->add($medicine->id, $warehouse->id, (float) $data['quantity']);
            InventoryMovement::create([
                'type' => 'receipt', 'reference' => $data['reference'], 'medicine_id' => $medicine->id,
                'batch_id' => $batch->id, 'destination_location_id' => $warehouse->id,
                'quantity' => $data['quantity'], 'occurred_at' => Carbon::parse($data['occurred_at'], 'Asia/Jakarta'),
                'counterparty' => $data['counterparty'] ?? null, 'notes' => $data['notes'] ?? null,
                'created_by' => $request->user()->id,
            ]);
        });

        return back()->with('status', 'Penerimaan diposting. Stok Gudang bertambah.');
    }

    public function transfers()
    {
        return view('inventory.transfers', [
            'medicines' => $this->operationalMedicines()->with(['unit', 'batches'])->orderBy('name')->get(),
            'locations' => Location::where('is_active', true)->orderBy('name')->get(),
            'nextReference' => $this->nextReference('transfer', 'TRF'),
            'balances' => StockBalance::with(['medicine.unit', 'location'])
                ->whereHas('medicine', fn ($query) => $this->applyOperationalMedicineScope($query))
                ->where('quantity', '>', 0)
                ->orderBy('medicine_id')
                ->get(),
            'rows' => InventoryMovement::with(['medicine.unit', 'batch', 'sourceLocation', 'destinationLocation', 'creator'])
                ->where('type', 'transfer')->latest('occurred_at')->paginate(20),
        ]);
    }

    public function storeTransfer(Request $request, InventoryStockService $stock)
    {
        $data = $request->validate([
            'reference' => ['required', 'string', 'max:100', Rule::unique('inventory_movements', 'reference')],
            'medicine_id' => ['required', 'exists:medicines,id'],
            'source_location_id' => ['required', 'exists:locations,id'],
            'destination_location_id' => ['required', 'different:source_location_id', 'exists:locations,id'],
            'batch_id' => ['nullable', 'exists:batches,id'],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'occurred_at' => ['required', 'date_format:Y-m-d\\TH:i'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);
        $medicine = Medicine::with('unit')->findOrFail($data['medicine_id']);
        if (! $this->isOperationalMedicine($medicine)) {
            throw ValidationException::withMessages(['medicine_id' => 'Obat harus aktif, punya bentuk sediaan, dan satuan dasar sebelum transfer.']);
        }
        if (! empty($data['batch_id']) && ! Batch::where('id', $data['batch_id'])->where('medicine_id', $medicine->id)->exists()) {
            throw ValidationException::withMessages(['batch_id' => 'Batch tidak sesuai dengan obat yang dipilih.']);
        }

        DB::transaction(function () use ($data, $medicine, $request, $stock) {
            $stock->add($medicine->id, (int) $data['source_location_id'], -(float) $data['quantity']);
            $stock->add($medicine->id, (int) $data['destination_location_id'], (float) $data['quantity']);
            InventoryMovement::create([
                'type' => 'transfer', 'reference' => $data['reference'], 'medicine_id' => $medicine->id,
                'batch_id' => $data['batch_id'] ?? null, 'source_location_id' => $data['source_location_id'],
                'destination_location_id' => $data['destination_location_id'], 'quantity' => $data['quantity'],
                'occurred_at' => Carbon::parse($data['occurred_at'], 'Asia/Jakarta'), 'notes' => $data['notes'] ?? null,
                'created_by' => $request->user()->id,
            ]);
        });

        return back()->with('status', 'Transfer diposting. Total persediaan tetap, saldo lokasi sudah diperbarui.');
    }

    public function expiry()
    {
        $batches = Batch::with(['medicine.unit', 'medicine.balances.location'])->whereNotNull('expiry_date')->orderBy('expiry_date')->paginate(30);
        $today = now('Asia/Jakarta')->startOfDay();

        return view('inventory.expiry', [
            'batches' => $batches,
            'counts' => [
                'expired' => Batch::whereDate('expiry_date', '<', $today)->count(),
                'near' => Batch::whereBetween('expiry_date', [$today, $today->copy()->addMonths(3)])->count(),
                'safe' => Batch::whereDate('expiry_date', '>', $today->copy()->addMonths(3))->count(),
            ],
        ]);
    }

    public function reports()
    {
        return view('inventory.reports', [
            'balances' => StockBalance::with(['medicine.unit', 'location'])->orderBy('medicine_id')->get(),
            'movements' => InventoryMovement::with(['medicine.unit', 'sourceLocation', 'destinationLocation'])->latest('occurred_at')->limit(50)->get(),
            'checks' => DB::table('nightly_checks as c')->join('locations as l', 'l.id', '=', 'c.location_id')
                ->select('c.*', 'l.name as location')->where('c.status', 'approved')->orderByDesc('c.date')->limit(20)->get(),
        ]);
    }

    public function opname(Request $request)
    {
        $data = $request->validate(['period' => ['nullable', 'date_format:Y-m']]);
        $period = $data['period'] ?? now('Asia/Jakarta')->format('Y-m');
        $start = Carbon::createFromFormat('!Y-m', $period, 'Asia/Jakarta')->startOfMonth();
        $end = $start->copy()->endOfMonth();
        $rows = DB::table('nightly_check_items as i')
            ->join('nightly_checks as c', 'c.id', '=', 'i.nightly_check_id')
            ->join('locations as l', 'l.id', '=', 'c.location_id')
            ->where('c.status', 'approved')->whereBetween('c.date', [$start->toDateString(), $end->toDateString()])
            ->select('i.*', 'c.date', 'c.id as check_id', 'l.name as location')
            ->orderByDesc('c.date')->orderBy('l.name')->orderBy('i.name')->paginate(30)->withQueryString();

        return view('inventory.opname', [
            'rows' => $rows, 'period' => $period,
            'periodLabel' => $start->locale('id')->translatedFormat('F Y'),
        ]);
    }

    private function operationalMedicines()
    {
        return $this->applyOperationalMedicineScope(Medicine::query());
    }

    private function applyOperationalMedicineScope($query)
    {
        return $query
            ->where('is_active', true)
            ->whereNotNull('unit_id')
            ->whereNotNull('dosage_form')
            ->where('dosage_form', '<>', '');
    }

    private function isOperationalMedicine(Medicine $medicine): bool
    {
        return $medicine->is_active && filled($medicine->unit_id) && filled($medicine->dosage_form);
    }

    private function nextReference(string $type, string $prefix): string
    {
        $year = now('Asia/Jakarta')->format('Y');
        $base = "{$prefix}-{$year}-";
        $lastNumber = InventoryMovement::where('type', $type)
            ->where('reference', 'like', $base.'%')
            ->pluck('reference')
            ->map(function (string $reference) use ($base) {
                if (! preg_match('/^'.preg_quote($base, '/').'(\d+)$/', $reference, $match)) {
                    return 0;
                }

                return (int) $match[1];
            })
            ->max() ?? 0;

        return $base.str_pad((string) ($lastNumber + 1), 3, '0', STR_PAD_LEFT);
    }
}
