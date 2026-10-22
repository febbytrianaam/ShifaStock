<?php

namespace App\Http\Controllers;

use App\Models\Batch;
use App\Models\BatchStockBalance;
use App\Models\InventoryMovement;
use App\Models\InventoryReceipt;
use App\Models\InventoryReceiptItem;
use App\Models\Location;
use App\Models\Medicine;
use App\Models\StockBalance;
use App\Models\StockOpnameSession;
use App\Models\SystemSetting;
use App\Services\InventoryStockService;
use App\Support\AuditLogger;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class InventoryController extends Controller
{
    public function receipts(Request $request)
    {
        return $this->receiptPage($request);
    }

    public function editReceipt(Request $request, InventoryReceipt $receipt)
    {
        abort_unless($receipt->status === 'draft', 422, 'Penerimaan yang sudah menambah stok tidak bisa diedit.');

        return $this->receiptPage($request, $receipt->load('items'));
    }

    private function receiptPage(Request $request, ?InventoryReceipt $editingReceipt = null)
    {
        return view('inventory.receipts', [
            'editingReceipt' => $editingReceipt,
            'receiptHistory' => ! $editingReceipt && $request->query('tab') === 'riwayat',
            'medicines' => $this->operationalMedicines()->with(['unit', 'packages'])->orderBy('name')->get(),
            'locations' => Location::where('is_active', true)->orderBy('name')->get(),
            'defaultReceiptLocationId' => $this->defaultReceiptLocationId(),
            'nextReference' => $this->nextReference('receipt', 'FKT'),
            'receipts' => InventoryReceipt::with(['items.medicine.unit', 'destinationLocation', 'creator', 'poster'])
                ->latest('received_at')
                ->paginate(12, ['*'], 'dokumen')->withQueryString(),
            'rows' => InventoryMovement::with(['medicine.unit', 'batch', 'destinationLocation', 'creator'])
                ->where('type', 'receipt')->latest('occurred_at')->paginate(20)->withQueryString(),
        ]);
    }

    public function storeReceipt(Request $request, InventoryStockService $stock)
    {
        return $this->saveReceipt($request, $stock);
    }

    public function updateReceipt(Request $request, InventoryReceipt $receipt, InventoryStockService $stock)
    {
        abort_unless($receipt->status === 'draft', 422, 'Hanya draf penerimaan yang bisa diedit.');

        return $this->saveReceipt($request, $stock, $receipt);
    }

    private function saveReceipt(Request $request, InventoryStockService $stock, ?InventoryReceipt $editingReceipt = null)
    {
        $this->normalizeReceiptItems($request);
        $data = $request->validate([
            'action' => ['nullable', Rule::in(['draft', 'post'])],
            'supplier_invoice_number' => ['nullable', 'string', 'max:100'],
            'reference' => ['required', 'string', 'max:100', Rule::unique('inventory_receipts', 'reference')->ignore($editingReceipt?->id), Rule::unique('inventory_movements', 'reference'), ...($editingReceipt ? [Rule::in([$editingReceipt->reference])] : [])],
            'duplicate_invoice_confirmed' => ['nullable', 'boolean'],
            'occurred_at' => ['required', 'date_format:Y-m-d\\TH:i'],
            'counterparty' => ['nullable', 'string', 'max:255'],
            'destination_location_id' => ['required', Rule::exists('locations', 'id')->where('is_active', true)],
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.medicine_id' => ['required', 'exists:medicines,id'],
            'items.*.batch_number' => ['required', 'string', 'max:100'],
            'items.*.expiry_date' => ['required', 'date_format:Y-m-d'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.0001', 'decimal:0,4', 'max:999999999999.9999'],
            'items.*.input_quantity' => ['nullable', 'required_with:items.*.input_unit_name', 'numeric', 'min:0.0001', 'decimal:0,4', 'max:999999999999.9999'],
            'items.*.input_unit_name' => ['nullable', 'required_with:items.*.input_quantity', 'string', 'max:100'],
            'items.*.conversion_factor' => ['nullable', 'required_with:items.*.input_quantity', 'numeric', 'min:0.0001', 'decimal:0,4', 'max:999999999999.9999'],
            'items.*.notes' => ['nullable', 'string', 'max:1000'],
        ], [
            'items.required' => 'Isi minimal satu baris obat.',
            'items.*.medicine_id.required' => 'Baris :position: pilih obat dari daftar.',
            'items.*.medicine_id.exists' => 'Baris :position: obat tidak ditemukan.',
            'items.*.batch_number.required' => 'Baris :position: isi nomor batch.',
            'items.*.expiry_date.required' => 'Baris :position: isi tanggal kedaluwarsa.',
            'items.*.expiry_date.date_format' => 'Baris :position: tanggal kedaluwarsa tidak valid.',
            'items.*.quantity.required' => 'Baris :position: lengkapi jumlah faktur dan isi kemasan.',
            'items.*.quantity.gt' => 'Baris :position: jumlah stok harus lebih dari 0.',
            'items.*.quantity.numeric' => 'Baris :position: jumlah stok harus berupa angka.',
            'items.*.input_quantity.required_with' => 'Baris :position: isi jumlah faktur.',
            'items.*.input_unit_name.required_with' => 'Baris :position: pilih satuan faktur.',
            'items.*.conversion_factor.required_with' => 'Baris :position: isi jumlah satuan stok dalam 1 kemasan.',
            'items.*.input_quantity.gt' => 'Baris :position: jumlah faktur harus lebih dari 0.',
            'items.*.input_quantity.numeric' => 'Baris :position: jumlah faktur harus berupa angka.',
            'items.*.conversion_factor.gt' => 'Baris :position: isi kemasan harus lebih dari 0.',
            'items.*.conversion_factor.numeric' => 'Baris :position: isi kemasan harus berupa angka.',
        ]);
        $this->assertReceiptItemsAreOperational($data['items']);
        $this->assertReceiptInvoiceConfirmed($data, $editingReceipt?->id);
        $status = ($data['action'] ?? 'post') === 'draft' ? 'draft' : 'posted';

        $receipt = \App\Services\InventoryTransaction::run(function () use ($data, $request, $stock, $status, $editingReceipt) {
            $this->assertMovementReferenceAvailable($data['reference']);
            if (InventoryReceipt::where('reference', $data['reference'])->when($editingReceipt, fn ($q) => $q->whereKeyNot($editingReceipt->id))->exists()) {
                throw ValidationException::withMessages(['reference' => 'Referensi penerimaan sudah digunakan. Muat ulang halaman sebelum mengirim ulang.']);
            }
            $receipt = $editingReceipt ? InventoryReceipt::whereKey($editingReceipt->id)->lockForUpdate()->firstOrFail() : new InventoryReceipt;
            abort_if($receipt->exists && $receipt->status !== 'draft', 422, 'Draf sudah diproses. Muat ulang daftar penerimaan.');
            $receipt->fill([
                'reference' => $data['reference'],
                'status' => 'draft',
                'supplier_invoice_number' => $data['supplier_invoice_number'] ?? null,
                'counterparty' => $data['counterparty'] ?? null,
                'destination_location_id' => $data['destination_location_id'],
                'received_at' => Carbon::parse($data['occurred_at'], 'Asia/Jakarta'),
                'notes' => $data['notes'] ?? null,
            ]);
            if (! $receipt->exists) $receipt->created_by = $request->user()->id;
            $receipt->save();
            if ($editingReceipt) $receipt->items()->delete();

            foreach ($data['items'] as $item) {
                $receipt->items()->create([
                    'medicine_id' => $item['medicine_id'],
                    'batch_number' => trim($item['batch_number']),
                    'expiry_date' => $item['expiry_date'],
                    'quantity' => $item['quantity'],
                    'input_quantity' => $item['input_quantity'] ?? $item['quantity'],
                    'input_unit_name' => filled($item['input_unit_name'] ?? null) ? trim($item['input_unit_name']) : null,
                    'conversion_factor' => $item['conversion_factor'] ?? 1,
                    'notes' => $item['notes'] ?? null,
                ]);
            }

            if ($status === 'posted') {
                $this->postReceiptDocument($receipt, $request, $stock);
            } else {
                AuditLogger::record($editingReceipt ? 'inventory.receipt.updated' : 'inventory.receipt.drafted', 'Draf penerimaan '.$receipt->reference.' disimpan.', $receipt, [
                    'reference' => $receipt->reference,
                    'item_count' => $receipt->items()->count(),
                ], $request);
            }

            return $receipt;
        });

        $destinationName = $receipt->destinationLocation?->name ?? 'lokasi tujuan';

        return redirect()->route('preview.receipts', ['tab' => 'riwayat'])->with('status', $receipt->status === 'posted'
            ? 'Penerimaan multi-item diposting. Stok '.$destinationName.' bertambah.'
            : 'Draf penerimaan tersimpan. Buka Edit Draf untuk melanjutkan atau Tambah ke Stok jika sudah benar.');
    }

    public function postReceipt(Request $request, InventoryReceipt $receipt, InventoryStockService $stock)
    {
        \App\Services\InventoryTransaction::run(function () use ($request, $receipt, $stock) {
            $receipt = InventoryReceipt::whereKey($receipt->id)->lockForUpdate()->firstOrFail();
            abort_unless($receipt->status === 'draft', 422, 'Penerimaan ini sudah diproses.');
            try {
                $this->assertReceiptInvoiceConfirmed($receipt->toArray(), $receipt->id);
            } catch (ValidationException $exception) {
                throw $exception->redirectTo(route('receipts.edit', $receipt));
            }
            $this->postReceiptDocument($receipt, $request, $stock);
        });

        return back()->with('status', 'Draft penerimaan '.$receipt->reference.' berhasil diposting.');
    }

    public function destroyReceipt(Request $request, InventoryReceipt $receipt)
    {
        abort_unless($receipt->status === 'draft', 422, 'Hanya draft penerimaan yang bisa dibatalkan.');

        \App\Services\InventoryTransaction::run(function () use ($receipt, $request) {
            $receipt = InventoryReceipt::whereKey($receipt->id)->lockForUpdate()->firstOrFail();
            abort_unless($receipt->status === 'draft', 422, 'Penerimaan ini sudah diproses.');
            AuditLogger::record('inventory.receipt.cancelled', 'Draft penerimaan '.$receipt->reference.' dibatalkan.', $receipt, [
                'reference' => $receipt->reference,
                'item_count' => $receipt->items()->count(),
            ], $request);
            $receipt->delete();
        });

        return back()->with('status', 'Draft penerimaan dibatalkan.');
    }

    public function checkReceiptInvoice(Request $request)
    {
        $data = $request->validate([
            'supplier_invoice_number' => ['nullable', 'string', 'max:100'],
            'counterparty' => ['nullable', 'string', 'max:255'],
            'receipt_id' => ['nullable', 'integer'],
        ]);

        return response()->json(['matches' => $this->duplicateReceiptInvoices($data, $data['receipt_id'] ?? null)
            ->limit(5)->get(['id', 'reference', 'status'])->map(fn ($receipt) => [
                'reference' => $receipt->reference,
                'status' => $receipt->status === 'posted' ? 'Stok ditambahkan' : 'Draf',
            ])]);
    }

    private function duplicateReceiptInvoices(array $data, ?int $excludeId = null)
    {
        $supplier = mb_strtolower(trim((string) ($data['counterparty'] ?? '')));
        $invoice = mb_strtolower(trim((string) ($data['supplier_invoice_number'] ?? '')));

        return InventoryReceipt::query()->whereIn('status', ['draft', 'posted'])
            ->when($excludeId, fn ($query) => $query->where('id', '<>', $excludeId))
            ->whereRaw('LOWER(TRIM(counterparty)) = ?', [$supplier])
            ->whereRaw('LOWER(TRIM(supplier_invoice_number)) = ?', [$invoice])
            ->when($supplier === '' || $invoice === '', fn ($query) => $query->whereRaw('1 = 0'));
    }

    private function assertReceiptInvoiceConfirmed(array $data, ?int $excludeId = null): void
    {
        if (empty($data['duplicate_invoice_confirmed']) && $this->duplicateReceiptInvoices($data, $excludeId)->exists()) {
            throw ValidationException::withMessages([
                'duplicate_invoice' => 'Supplier dan nomor faktur ini sudah tercatat. Periksa riwayat sebelum melanjutkan agar stok tidak terhitung dua kali.',
            ]);
        }
    }

    private function postReceiptDocument(InventoryReceipt $receipt, Request $request, InventoryStockService $stock): void
    {
        $receipt->load(['items.medicine', 'destinationLocation']);
        $destination = $receipt->destinationLocation;
        if (! $destination || ! $destination->is_active) {
            throw ValidationException::withMessages(['destination_location_id' => 'Lokasi tujuan draf sudah tidak aktif. Edit draf dan pilih lokasi tujuan aktif sebelum menambah stok.']);
        }

        foreach ($receipt->items as $item) {
            if (! $this->isOperationalMedicine($item->medicine)) {
                throw ValidationException::withMessages(['items' => 'Semua item harus memakai obat aktif, punya bentuk sediaan, dan satuan dasar.']);
            }

            $batch = Batch::firstOrCreate(
                ['medicine_id' => $item->medicine_id, 'number' => trim($item->batch_number)],
                ['expiry_date' => $item->expiry_date, 'expiry_precision' => 'day'],
            );
            if ($batch->expiry_date?->toDateString() !== $item->expiry_date?->toDateString()) {
                throw ValidationException::withMessages(['items' => 'Tanggal kedaluwarsa berbeda dari batch '.$item->batch_number.' yang sudah tercatat.']);
            }

            $stock->add($item->medicine_id, $destination->id, (float) $item->quantity);
            $stock->addBatch($item->medicine_id, $batch->id, $destination->id, (float) $item->quantity);
            $movement = InventoryMovement::create([
                'type' => 'receipt',
                'reference' => $receipt->reference,
                'medicine_id' => $item->medicine_id,
                'batch_id' => $batch->id,
                'destination_location_id' => $destination->id,
                'quantity' => $item->quantity,
                'occurred_at' => $receipt->received_at,
                'counterparty' => $receipt->counterparty,
                'notes' => $this->receiptItemNotes($item, $receipt),
                'created_by' => $request->user()->id,
            ]);
            $item->update(['batch_id' => $batch->id, 'inventory_movement_id' => $movement->id]);
        }

        $receipt->update([
            'status' => 'posted',
            'posted_at' => now('Asia/Jakarta'),
            'posted_by' => $request->user()->id,
        ]);

        AuditLogger::record('inventory.receipt.posted', 'Penerimaan '.$receipt->reference.' diposting.', $receipt, [
            'reference' => $receipt->reference,
            'item_count' => $receipt->items->count(),
            'total_quantity' => $receipt->items->sum(fn (InventoryReceiptItem $item) => (float) $item->quantity),
            'destination_location_id' => $destination->id,
        ], $request);
    }

    public function transfers(Request $request)
    {
        return view('inventory.transfers', [
            'prefill' => $this->transactionPrefill($request),
            'medicines' => $this->operationalMedicines()->with(['unit', 'batches'])->orderBy('name')->get(),
            'locations' => Location::where('is_active', true)->orderBy('name')->get(),
            'nextReference' => $this->nextReference('transfer', 'TRF'),
            'batchBalances' => BatchStockBalance::with(['medicine.unit', 'batch', 'location'])
                ->where('quantity', '>', 0)
                ->whereHas('batch', fn ($query) => $query->whereNull('expiry_date')->orWhereDate('expiry_date', '>=', now('Asia/Jakarta')->toDateString()))
                ->orderBy('medicine_id')
                ->get(),
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
        $legacy = ! $request->has('items');
        if (! $legacy && is_array($request->input('items'))) {
            $request->validate(['items' => ['array', 'max:50']]);
            $request->merge(['items' => collect($request->input('items'))->filter(fn ($item) => ! is_array($item) || collect($item)->contains(fn ($value) => filled($value)))->values()->all()]);
        }
        $itemRules = [
            'medicine_id' => ['required', 'integer', 'exists:medicines,id'],
            'batch_id' => ['nullable', 'integer', 'exists:batches,id'],
            'quantity' => ['required', 'numeric', 'min:0.0001', 'decimal:0,4', 'max:999999999999.9999'],
        ];
        $rules = [
            'reference' => ['required', 'string', 'max:100', Rule::unique('inventory_movements', 'reference')],
            'source_location_id' => ['required', 'integer', Rule::exists('locations', 'id')->where('is_active', true)],
            'destination_location_id' => ['required', 'integer', 'different:source_location_id', Rule::exists('locations', 'id')->where('is_active', true)],
            'occurred_at' => ['required', 'date_format:Y-m-d\\TH:i'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
        if ($legacy) {
            $rules += $itemRules;
        } else {
            $rules['items'] = ['required', 'array', 'min:1', 'max:50'];
            $rules['items.*'] = ['required', 'array:medicine_id,batch_id,quantity'];
            foreach ($itemRules as $field => $fieldRules) {
                $rules['items.*.'.$field] = $fieldRules;
            }
        }
        $data = $request->validate($rules, [
            'items.required' => 'Isi minimal satu baris obat untuk ditransfer.',
            'items.max' => 'Maksimal 50 baris obat dalam satu transfer.',
            'items.*.medicine_id.required' => 'Baris :position: pilih obat dari daftar.',
            'items.*.medicine_id.exists' => 'Baris :position: obat tidak ditemukan.',
            'items.*.quantity.required' => 'Baris :position: isi jumlah yang dipindahkan.',
            'items.*.quantity.min' => 'Baris :position: jumlah harus lebih dari 0.',
            'items.*.quantity.numeric' => 'Baris :position: jumlah harus berupa angka.',
            'items.*.quantity.decimal' => 'Baris :position: jumlah maksimal empat angka di belakang koma.',
            'items.*.batch_id.exists' => 'Baris :position: batch tidak ditemukan.',
            'destination_location_id.different' => 'Lokasi tujuan harus berbeda dari lokasi sumber.',
        ]);
        $items = $legacy ? [array_intersect_key($data, $itemRules)] : $data['items'];

        \App\Services\InventoryTransaction::run(function () use ($data, $items, $legacy, $request, $stock) {
            $this->assertMovementReferenceAvailable($data['reference']);
            foreach (['source_location_id', 'destination_location_id'] as $field) {
                if (! Location::whereKey($data[$field])->where('is_active', true)->lockForUpdate()->first()) {
                    throw ValidationException::withMessages([$field => 'Lokasi sudah tidak aktif. Pilih lokasi aktif sebelum transfer.']);
                }
            }
            $postedItems = [];
            foreach ($items as $index => $item) {
                try {
                    $medicine = Medicine::with('unit')->whereKey($item['medicine_id'])->lockForUpdate()->firstOrFail();
                    if (! $this->isOperationalMedicine($medicine)) {
                        throw ValidationException::withMessages(['medicine_id' => 'Obat harus aktif, punya bentuk sediaan, dan satuan dasar sebelum transfer.']);
                    }
                    if (! empty($item['batch_id'])) {
                        if (! Batch::whereKey($item['batch_id'])->where('medicine_id', $medicine->id)->exists()) {
                            throw ValidationException::withMessages(['batch_id' => 'Batch tidak sesuai dengan obat yang dipilih.']);
                        }
                        $this->ensureBatchUsable((int) $item['batch_id']);
                        $this->ensureBatchHasStock($medicine->id, (int) $item['batch_id'], (int) $data['source_location_id'], (float) $item['quantity']);
                    }
                    $allocations = ! empty($item['batch_id'])
                        ? [['batch_id' => (int) $item['batch_id'], 'quantity' => (float) $item['quantity']]]
                        : $stock->fefoAllocations($medicine->id, (int) $data['source_location_id'], (float) $item['quantity']);
                    $stock->add($medicine->id, (int) $data['source_location_id'], -(float) $item['quantity']);
                    $stock->add($medicine->id, (int) $data['destination_location_id'], (float) $item['quantity']);
                    foreach ($allocations as $allocation) {
                        $batchId = $allocation['batch_id'];
                        $quantity = (float) $allocation['quantity'];
                        if ($batchId) {
                            $stock->addBatch($medicine->id, (int) $batchId, (int) $data['source_location_id'], -$quantity);
                            $stock->addBatch($medicine->id, (int) $batchId, (int) $data['destination_location_id'], $quantity);
                        }
                        InventoryMovement::create([
                            'type' => 'transfer', 'reference' => $data['reference'], 'medicine_id' => $medicine->id,
                            'batch_id' => $batchId, 'source_location_id' => $data['source_location_id'],
                            'destination_location_id' => $data['destination_location_id'], 'quantity' => $quantity,
                            'occurred_at' => Carbon::parse($data['occurred_at'], 'Asia/Jakarta'), 'notes' => $data['notes'] ?? null,
                            'created_by' => $request->user()->id,
                        ]);
                    }
                    $postedItems[] = ['medicine_id' => $medicine->id, 'quantity' => (float) $item['quantity'], 'allocations' => $allocations];
                } catch (ValidationException $error) {
                    if ($legacy) {
                        throw $error;
                    }
                    $messages = [];
                    foreach ($error->errors() as $field => $errors) {
                        $messages['items.'.$index.'.'.$field] = array_map(fn ($message) => 'Baris '.($index + 1).': '.$message, $errors);
                    }
                    throw ValidationException::withMessages($messages);
                }
            }

            AuditLogger::record('inventory.transfer.posted', 'Transfer '.$data['reference'].' diposting ('.count($postedItems).' baris obat).', null, [
                'reference' => $data['reference'],
                'item_count' => count($postedItems),
                'items' => $postedItems,
                ...($legacy ? $postedItems[0] : []),
                'source_location_id' => (int) $data['source_location_id'],
                'destination_location_id' => (int) $data['destination_location_id'],
            ], $request);
        });

        return back()->with('status', 'Transfer diposting. Total persediaan tetap, stok lokasi sudah diperbarui.');
    }

    public function issues()
    {
        return view('inventory.issues', [
            'medicines' => $this->operationalMedicines()->with('unit')->orderBy('name')->get(),
            'locations' => Location::where('is_active', true)->orderBy('name')->get(),
            'batchBalances' => BatchStockBalance::with(['medicine.unit', 'batch', 'location'])
                ->where('quantity', '>', 0)
                ->whereHas('batch', fn ($query) => $query->whereNull('expiry_date')->orWhereDate('expiry_date', '>=', now('Asia/Jakarta')->toDateString()))
                ->orderBy('medicine_id')
                ->get(),
            'nextReference' => $this->nextReference('issue', 'OUT'),
            'rows' => InventoryMovement::with(['medicine.unit', 'batch', 'sourceLocation', 'creator'])
                ->where('type', 'issue')->latest('occurred_at')->paginate(20),
        ]);
    }

    public function storeIssue(Request $request, InventoryStockService $stock)
    {
        $data = $request->validate([
            'reference' => ['required', 'string', 'max:100', Rule::unique('inventory_movements', 'reference')],
            'medicine_id' => ['required', 'exists:medicines,id'],
            'source_location_id' => ['required', 'exists:locations,id'],
            'batch_id' => ['nullable', 'exists:batches,id'],
            'quantity' => ['required', 'numeric', 'min:0.0001', 'decimal:0,4', 'max:999999999999.9999'],
            'occurred_at' => ['required', 'date_format:Y-m-d\\TH:i'],
            'source_reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);
        $medicine = Medicine::with('unit')->findOrFail($data['medicine_id']);
        if (! $this->isOperationalMedicine($medicine)) {
            throw ValidationException::withMessages(['medicine_id' => 'Obat harus aktif, punya bentuk sediaan, dan satuan dasar sebelum pengeluaran dicatat.']);
        }
        if (! empty($data['batch_id']) && ! Batch::where('id', $data['batch_id'])->where('medicine_id', $medicine->id)->exists()) {
            throw ValidationException::withMessages(['batch_id' => 'Batch tidak sesuai dengan obat yang dipilih.']);
        }
        if (! empty($data['batch_id'])) {
            $this->ensureBatchUsable((int) $data['batch_id']);
            $this->ensureBatchHasStock($medicine->id, (int) $data['batch_id'], (int) $data['source_location_id'], (float) $data['quantity']);
        }

        \App\Services\InventoryTransaction::run(function () use ($data, $medicine, $request, $stock) {
            $this->assertMovementReferenceAvailable($data['reference']);
            $allocations = ! empty($data['batch_id'])
                ? [['batch_id' => (int) $data['batch_id'], 'quantity' => (float) $data['quantity']]]
                : $stock->fefoAllocations($medicine->id, (int) $data['source_location_id'], (float) $data['quantity']);

            $stock->add($medicine->id, (int) $data['source_location_id'], -(float) $data['quantity']);

            foreach ($allocations as $allocation) {
                $batchId = (int) $allocation['batch_id'];
                $quantity = (float) $allocation['quantity'];
                $stock->addBatch($medicine->id, $batchId, (int) $data['source_location_id'], -$quantity);

                InventoryMovement::create([
                    'type' => 'issue',
                    'reference' => $data['reference'],
                    'medicine_id' => $medicine->id,
                    'batch_id' => $batchId,
                    'source_location_id' => $data['source_location_id'],
                    'quantity' => $quantity,
                    'occurred_at' => Carbon::parse($data['occurred_at'], 'Asia/Jakarta'),
                    'counterparty' => $data['source_reference'] ?? null,
                    'notes' => $data['notes'] ?? null,
                    'created_by' => $request->user()->id,
                ]);
            }

            AuditLogger::record('inventory.issue.posted', 'Pengeluaran stok '.$medicine->name.' diposting.', null, [
                'reference' => $data['reference'],
                'medicine_id' => $medicine->id,
                'source_location_id' => (int) $data['source_location_id'],
                'quantity' => (float) $data['quantity'],
                'allocations' => $allocations,
            ], $request);
        });

        return back()->with('status', 'Pengeluaran obat diposting. Stok lokasi dan stok batch sudah berkurang.');
    }

    public function openingBalances()
    {
        return view('inventory.opening-balances', [
            'medicines' => $this->operationalMedicines()->with('unit')->orderBy('name')->get(),
            'locations' => Location::where('is_active', true)->orderBy('name')->get(),
            'nextReference' => $this->nextReference('opening_balance', 'STOK'),
            'rows' => InventoryMovement::with(['medicine.unit', 'batch', 'destinationLocation', 'creator'])
                ->where('type', 'opening_balance')->latest('occurred_at')->paginate(20),
        ]);
    }

    public function storeOpeningBalance(Request $request, InventoryStockService $stock)
    {
        $data = $request->validate([
            'reference' => ['required', 'string', 'max:100', Rule::unique('inventory_movements', 'reference')],
            'medicine_id' => ['required', 'exists:medicines,id'],
            'location_id' => ['required', 'exists:locations,id'],
            'batch_number' => ['required', 'string', 'max:100'],
            'expiry_date' => ['required', 'date_format:Y-m-d'],
            'quantity' => ['required', 'numeric', 'min:0.0001', 'decimal:0,4', 'max:999999999999.9999'],
            'occurred_at' => ['required', 'date_format:Y-m-d\\TH:i'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);
        $medicine = Medicine::findOrFail($data['medicine_id']);
        if (! $this->isOperationalMedicine($medicine)) {
            throw ValidationException::withMessages(['medicine_id' => 'Obat harus aktif, punya bentuk sediaan, dan satuan dasar sebelum stok awal dicatat.']);
        }

        \App\Services\InventoryTransaction::run(function () use ($data, $medicine, $request, $stock) {
            $this->assertMovementReferenceAvailable($data['reference']);
            $batch = Batch::firstOrCreate(
                ['medicine_id' => $medicine->id, 'number' => trim($data['batch_number'])],
                ['expiry_date' => $data['expiry_date'], 'expiry_precision' => 'day'],
            );
            if ($batch->expiry_date?->toDateString() !== $data['expiry_date']) {
                throw ValidationException::withMessages(['batch_number' => 'Tanggal kedaluwarsa berbeda dari batch yang sudah tercatat.']);
            }

            $stock->add($medicine->id, (int) $data['location_id'], (float) $data['quantity']);
            $stock->addBatch($medicine->id, $batch->id, (int) $data['location_id'], (float) $data['quantity']);
            InventoryMovement::create([
                'type' => 'opening_balance',
                'reference' => $data['reference'],
                'medicine_id' => $medicine->id,
                'batch_id' => $batch->id,
                'destination_location_id' => $data['location_id'],
                'quantity' => $data['quantity'],
                'occurred_at' => Carbon::parse($data['occurred_at'], 'Asia/Jakarta'),
                'notes' => $data['notes'] ?? 'Stok awal persediaan.',
                'created_by' => $request->user()->id,
            ]);
            AuditLogger::record('inventory.opening_balance.posted', 'Stok awal '.$medicine->name.' dicatat.', null, [
                'reference' => $data['reference'],
                'medicine_id' => $medicine->id,
                'batch_id' => $batch->id,
                'location_id' => (int) $data['location_id'],
                'quantity' => (float) $data['quantity'],
            ], $request);
        });

        return back()->with('status', 'Stok awal dicatat dan dapat ditelusuri di kartu stok.');
    }

    public function returns(Request $request)
    {
        return view('inventory.returns', [
            'prefill' => $this->transactionPrefill($request),
            'medicines' => $this->operationalMedicines()->with('unit')->orderBy('name')->get(),
            'locations' => Location::where('is_active', true)->orderBy('name')->get(),
            'batchBalances' => BatchStockBalance::with(['medicine.unit', 'batch', 'location'])
                ->where('quantity', '>', 0)
                ->orderBy('medicine_id')
                ->get(),
            'nextReference' => $this->nextReference('supplier_return', 'RET'),
            'rows' => InventoryMovement::with(['medicine.unit', 'batch', 'sourceLocation', 'creator'])
                ->where('type', 'supplier_return')->latest('occurred_at')->paginate(20),
        ]);
    }

    public function storeReturn(Request $request, InventoryStockService $stock)
    {
        $data = $request->validate([
            'reference' => ['required', 'string', 'max:100', Rule::unique('inventory_movements', 'reference')],
            'medicine_id' => ['required', 'exists:medicines,id'],
            'batch_id' => ['required', 'exists:batches,id'],
            'source_location_id' => ['required', 'exists:locations,id'],
            'quantity' => ['required', 'numeric', 'min:0.0001', 'decimal:0,4', 'max:999999999999.9999'],
            'occurred_at' => ['required', 'date_format:Y-m-d\\TH:i'],
            'counterparty' => ['nullable', 'string', 'max:255'],
            'reason' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);
        $medicine = Medicine::findOrFail($data['medicine_id']);
        if (! Batch::where('id', $data['batch_id'])->where('medicine_id', $medicine->id)->exists()) {
            throw ValidationException::withMessages(['batch_id' => 'Batch tidak sesuai dengan obat yang dipilih.']);
        }
        $this->ensureBatchHasStock($medicine->id, (int) $data['batch_id'], (int) $data['source_location_id'], (float) $data['quantity']);

        \App\Services\InventoryTransaction::run(function () use ($data, $medicine, $request, $stock) {
            $this->assertMovementReferenceAvailable($data['reference']);
            $stock->add($medicine->id, (int) $data['source_location_id'], -(float) $data['quantity']);
            $stock->addBatch($medicine->id, (int) $data['batch_id'], (int) $data['source_location_id'], -(float) $data['quantity']);
            InventoryMovement::create([
                'type' => 'supplier_return',
                'reference' => $data['reference'],
                'medicine_id' => $medicine->id,
                'batch_id' => $data['batch_id'],
                'source_location_id' => $data['source_location_id'],
                'quantity' => $data['quantity'],
                'occurred_at' => Carbon::parse($data['occurred_at'], 'Asia/Jakarta'),
                'counterparty' => $data['counterparty'] ?? null,
                'notes' => trim($data['reason'].(! empty($data['notes']) ? ' - '.$data['notes'] : '')),
                'created_by' => $request->user()->id,
            ]);
            AuditLogger::record('inventory.return.posted', 'Retur PBF '.$medicine->name.' diposting.', null, [
                'reference' => $data['reference'],
                'medicine_id' => $medicine->id,
                'batch_id' => (int) $data['batch_id'],
                'quantity' => (float) $data['quantity'],
                'source_location_id' => (int) $data['source_location_id'],
            ], $request);
        });

        return back()->with('status', 'Retur PBF diposting. Stok sumber sudah berkurang.');
    }

    public function specialAdjustments(Request $request)
    {
        return view('inventory.special-adjustments', [
            'prefill' => $this->transactionPrefill($request),
            'medicines' => $this->operationalMedicines()->with('unit')->orderBy('name')->get(),
            'locations' => Location::where('is_active', true)->orderBy('name')->get(),
            'batchBalances' => BatchStockBalance::with(['medicine.unit', 'batch', 'location'])
                ->where('quantity', '>=', 0)
                ->orderBy('medicine_id')
                ->get(),
            'nextReference' => $this->nextReference('special_adjustment', 'ADJ'),
            'rows' => InventoryMovement::with(['medicine.unit', 'batch', 'sourceLocation', 'destinationLocation', 'creator'])
                ->where('type', 'special_adjustment')->latest('occurred_at')->paginate(20),
        ]);
    }

    public function storeSpecialAdjustment(Request $request, InventoryStockService $stock)
    {
        $data = $request->validate([
            'reference' => ['required', 'string', 'max:100', Rule::unique('inventory_movements', 'reference')],
            'medicine_id' => ['required', 'exists:medicines,id'],
            'batch_id' => ['required', 'exists:batches,id'],
            'location_id' => ['required', 'exists:locations,id'],
            'direction' => ['required', Rule::in(['decrease', 'increase'])],
            'condition' => ['required', Rule::in(['rusak', 'hilang', 'terjatuh', 'tidak_diambil', 'expired', 'lainnya'])],
            'quantity' => ['required', 'numeric', 'min:0.0001', 'decimal:0,4', 'max:999999999999.9999'],
            'occurred_at' => ['required', 'date_format:Y-m-d\\TH:i'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);
        if (in_array($data['condition'], ['rusak', 'hilang', 'terjatuh', 'expired'], true)) {
            $data['direction'] = 'decrease';
        }
        $medicine = Medicine::findOrFail($data['medicine_id']);
        if (! empty($data['batch_id']) && ! Batch::where('id', $data['batch_id'])->where('medicine_id', $medicine->id)->exists()) {
            throw ValidationException::withMessages(['batch_id' => 'Batch tidak sesuai dengan obat yang dipilih.']);
        }
        if (($data['direction'] ?? null) === 'decrease' && ! empty($data['batch_id'])) {
            $this->ensureBatchHasStock($medicine->id, (int) $data['batch_id'], (int) $data['location_id'], (float) $data['quantity']);
        }

        \App\Services\InventoryTransaction::run(function () use ($data, $medicine, $request, $stock) {
            $this->assertMovementReferenceAvailable($data['reference']);
            $multiplier = $data['direction'] === 'decrease' ? -1 : 1;
            $quantity = (float) $data['quantity'] * $multiplier;
            $stock->add($medicine->id, (int) $data['location_id'], $quantity);
            $stock->addBatch($medicine->id, (int) $data['batch_id'], (int) $data['location_id'], $quantity);
            InventoryMovement::create([
                'type' => 'special_adjustment',
                'reference' => $data['reference'],
                'medicine_id' => $medicine->id,
                'batch_id' => $data['batch_id'] ?? null,
                'source_location_id' => $data['direction'] === 'decrease' ? $data['location_id'] : null,
                'destination_location_id' => $data['direction'] === 'increase' ? $data['location_id'] : null,
                'quantity' => $data['quantity'],
                'occurred_at' => Carbon::parse($data['occurred_at'], 'Asia/Jakarta'),
                'notes' => $this->conditionLabels()[$data['condition']].(! empty($data['notes']) ? ' - '.$data['notes'] : ''),
                'created_by' => $request->user()->id,
            ]);
            AuditLogger::record('inventory.special_adjustment.posted', 'Kondisi khusus '.$medicine->name.' diposting.', null, [
                'reference' => $data['reference'],
                'medicine_id' => $medicine->id,
                'batch_id' => $data['batch_id'] ?? null,
                'location_id' => (int) $data['location_id'],
                'direction' => $data['direction'],
                'condition' => $data['condition'],
                'quantity' => (float) $data['quantity'],
            ], $request);
        });

        return back()->with('status', 'Kondisi khusus diposting dan stok sudah disesuaikan.');
    }

    public function monitoringParameters()
    {
        return view('inventory.monitoring-parameters', [
            'medicines' => $this->operationalMedicines()->with(['unit', 'stockParameters.location'])->orderBy('name')->get(),
            'locations' => Location::where('is_active', true)->orderBy('name')->get(),
            'expiryWarningMonths' => (int) SystemSetting::value('expiry_warning_months', 3),
        ]);
    }

    public function saveMonitoringParameters(Request $request)
    {
        $data = $request->validate([
            'expiry_warning_months' => ['required', 'integer', 'min:1', 'max:24'],
            'minimums' => ['nullable', 'array'],
            'minimums.*.*' => ['nullable', 'numeric', 'gte:0'],
        ]);

        \App\Services\InventoryTransaction::run(function () use ($data, $request) {
            SystemSetting::put('expiry_warning_months', $data['expiry_warning_months']);

            foreach ($data['minimums'] ?? [] as $medicineId => $locations) {
                foreach ($locations as $locationId => $quantity) {
                    if ($quantity === null || $quantity === '') {
                        DB::table('stock_parameters')
                            ->where('medicine_id', $medicineId)
                            ->where('location_id', $locationId)
                            ->delete();
                        continue;
                    }

                    DB::table('stock_parameters')->updateOrInsert(
                        ['medicine_id' => $medicineId, 'location_id' => $locationId],
                        ['minimum_quantity' => $quantity, 'created_at' => now(), 'updated_at' => now()],
                    );
                }
            }

            AuditLogger::record('inventory.monitoring_parameters.updated', 'Parameter monitoring stok diperbarui.', null, [
                'expiry_warning_months' => (int) $data['expiry_warning_months'],
            ], $request);
        });

        return back()->with('status', 'Parameter monitoring berhasil disimpan.');
    }

    public function stockCard(Request $request)
    {
        $data = $this->stockCardFilters($request);
        [$allRows, $opening] = $this->stockCardRows($data);
        $page = max((int) $request->integer('page', 1), 1);
        $perPage = 30;
        $rows = new LengthAwarePaginator(
            $allRows->forPage($page, $perPage)->values(),
            $allRows->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return view('inventory.stock-card', [
            'rows' => $rows,
            'opening' => $opening,
            'reversedMovementIds' => InventoryMovement::whereNotNull('reversed_movement_id')->pluck('reversed_movement_id')->all(),
            'medicines' => $this->operationalMedicines()->with('unit')->orderBy('name')->get(),
            'locations' => Location::where('is_active', true)->orderBy('name')->get(),
            'batches' => Batch::with('medicine')->orderBy('number')->get(),
            'batchBalances' => BatchStockBalance::with(['medicine.unit', 'batch', 'location'])
                ->where('quantity', '>', 0)
                ->orderBy('medicine_id')
                ->orderBy('location_id')
                ->get(),
            'filters' => $data,
        ]);
    }

    public function stockCardPrint(Request $request)
    {
        $data = $this->stockCardFilters($request, true);
        [$rows, $opening] = $this->stockCardRows($data);

        return view('inventory.stock-card-print', [
            'rows' => $rows,
            'opening' => $opening,
            'medicine' => Medicine::with('unit')->findOrFail((int) $data['medicine_id']),
            'location' => Location::findOrFail((int) $data['location_id']),
            'filters' => $data,
        ]);
    }

    public function reverseMovement(Request $request, InventoryMovement $movement, InventoryStockService $stock)
    {
        if ($movement->type === 'reversal' || $movement->reversed_movement_id) {
            throw ValidationException::withMessages(['movement' => 'Mutasi reversal tidak bisa dibalik lagi.']);
        }
        if (InventoryMovement::where('reversed_movement_id', $movement->id)->exists()) {
            throw ValidationException::withMessages(['movement' => 'Mutasi ini sudah pernah dibalik.']);
        }

        $data = $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        \App\Services\InventoryTransaction::run(function () use ($movement, $request, $stock, $data) {
            $movement = InventoryMovement::whereKey($movement->id)->lockForUpdate()->firstOrFail();
            if (InventoryMovement::where('reversed_movement_id', $movement->id)->exists()) {
                throw ValidationException::withMessages(['movement' => 'Mutasi ini sudah pernah dibalik.']);
            }
            $quantity = (float) $movement->quantity;

            if ($movement->destination_location_id) {
                $stock->add($movement->medicine_id, $movement->destination_location_id, -$quantity);
                if ($movement->batch_id) {
                    $stock->addBatch($movement->medicine_id, $movement->batch_id, $movement->destination_location_id, -$quantity);
                }
            }

            if ($movement->source_location_id) {
                $stock->add($movement->medicine_id, $movement->source_location_id, $quantity);
                if ($movement->batch_id) {
                    $stock->addBatch($movement->medicine_id, $movement->batch_id, $movement->source_location_id, $quantity);
                }
            }

            $reversal = InventoryMovement::create([
                'type' => 'reversal',
                'reversed_movement_id' => $movement->id,
                'reference' => 'REV-'.$movement->id.'-'.now('Asia/Jakarta')->format('YmdHis'),
                'medicine_id' => $movement->medicine_id,
                'batch_id' => $movement->batch_id,
                'source_location_id' => $movement->destination_location_id,
                'destination_location_id' => $movement->source_location_id,
                'quantity' => $quantity,
                'occurred_at' => now('Asia/Jakarta'),
                'counterparty' => $movement->counterparty,
                'notes' => 'Reversal dari '.$movement->reference.'. Alasan: '.$data['reason'],
                'created_by' => $request->user()->id,
            ]);

            AuditLogger::record('inventory.movement.reversed', 'Mutasi '.$movement->reference.' dibalik.', $reversal, [
                'reversed_movement_id' => $movement->id,
                'original_reference' => $movement->reference,
                'reason' => $data['reason'],
            ], $request);
        });

        return back()->with('status', 'Mutasi final berhasil dibalik dengan catatan reversal.');
    }

    public function expiry(Request $request)
    {
        $today = now('Asia/Jakarta')->startOfDay();
        $warningMonths = (int) SystemSetting::value('expiry_warning_months', 3);
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'location_id' => ['nullable', 'exists:locations,id'],
            'status' => ['nullable', Rule::in(['all', 'expired', 'near', 'safe'])],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);
        $filters['status'] = $filters['status'] ?? 'all';

        $baseQuery = Batch::query()
            ->with(['medicine.unit', 'balances.location'])
            ->whereNotNull('expiry_date')
            ->when($filters['search'] ?? null, fn ($query, $search) => $query->where(fn ($inner) => $inner
                ->where('number', 'like', '%'.$search.'%')
                ->orWhereHas('medicine', fn ($medicine) => $medicine
                    ->where('name', 'like', '%'.$search.'%')
                    ->orWhere('code', 'like', '%'.$search.'%'))))
            ->when($filters['location_id'] ?? null, fn ($query, $locationId) => $query->whereHas('balances', fn ($balance) => $balance
                ->where('location_id', $locationId)
                ->where('quantity', '>', 0)))
            ->when($filters['from'] ?? null, fn ($query, $date) => $query->whereDate('expiry_date', '>=', $date))
            ->when($filters['to'] ?? null, fn ($query, $date) => $query->whereDate('expiry_date', '<=', $date));

        $counts = [
            'expired' => (clone $baseQuery)->whereDate('expiry_date', '<', $today)->count(),
            'near' => (clone $baseQuery)->whereBetween('expiry_date', [$today, $today->copy()->addMonths($warningMonths)])->count(),
            'safe' => (clone $baseQuery)->whereDate('expiry_date', '>', $today->copy()->addMonths($warningMonths))->count(),
        ];

        $batches = (clone $baseQuery)
            ->when($filters['status'] === 'expired', fn ($query) => $query->whereDate('expiry_date', '<', $today))
            ->when($filters['status'] === 'near', fn ($query) => $query->whereBetween('expiry_date', [$today, $today->copy()->addMonths($warningMonths)]))
            ->when($filters['status'] === 'safe', fn ($query) => $query->whereDate('expiry_date', '>', $today->copy()->addMonths($warningMonths)))
            ->orderBy('expiry_date')
            ->paginate(30)
            ->withQueryString();

        return view('inventory.expiry', [
            'batches' => $batches,
            'counts' => $counts,
            'filters' => $filters,
            'warningMonths' => $warningMonths,
            'locations' => Location::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function reports(Request $request)
    {
        $filters = $request->validate([
            'start' => ['nullable', 'date_format:Y-m-d'],
            'end' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start'],
            'export' => ['nullable', Rule::in(['csv'])],
        ]);
        $start = isset($filters['start'])
            ? Carbon::createFromFormat('Y-m-d', $filters['start'], 'Asia/Jakarta')->startOfDay()
            : now('Asia/Jakarta')->startOfMonth();
        $end = isset($filters['end'])
            ? Carbon::createFromFormat('Y-m-d', $filters['end'], 'Asia/Jakarta')->endOfDay()
            : now('Asia/Jakarta')->endOfDay();

        $reportRows = $this->periodInventoryReport($start, $end);

        if (($filters['export'] ?? null) === 'csv') {
            return response()->streamDownload(function () use ($reportRows) {
                $handle = fopen('php://output', 'w');
                fputcsv($handle, ['Obat', 'Kode', 'Lokasi', 'Stok Awal', 'Masuk', 'Keluar', 'Stok Akhir', 'Satuan']);
                foreach ($reportRows as $row) {
                    fputcsv($handle, [
                        $row['medicine_name'],
                        $row['medicine_code'],
                        $row['location_name'],
                        $row['opening'],
                        $row['incoming'],
                        $row['outgoing'],
                        $row['ending'],
                        $row['unit_name'],
                    ]);
                }
                fclose($handle);
            }, (app()->environment('uat') ? 'uat-' : '').'laporan-persediaan-'.$start->format('Ymd').'-'.$end->format('Ymd').'.csv', ['Content-Type' => 'text/csv']);
        }

        return view('inventory.reports', [
            'filters' => [
                'start' => $start->toDateString(),
                'end' => $end->toDateString(),
            ],
            'reportSettings' => [
                'clinic_name' => SystemSetting::value('clinic_report_name', 'Klinik Shifa Medika'),
                'footer' => SystemSetting::value('clinic_report_footer'),
            ],
            'periodRows' => $reportRows,
            'balances' => StockBalance::with(['medicine.unit', 'location'])->orderBy('medicine_id')->get(),
            'movements' => InventoryMovement::with(['medicine.unit', 'sourceLocation', 'destinationLocation'])->latest('occurred_at')->limit(50)->get(),
            'checks' => DB::table('nightly_checks as c')->join('locations as l', 'l.id', '=', 'c.location_id')
                ->select('c.*', 'l.name as location')->where('c.status', 'approved')->orderByDesc('c.date')->limit(20)->get(),
        ]);
    }

    public function opname(Request $request)
    {
        $data = $request->validate([
            'period' => ['nullable', 'date_format:Y-m'],
            'session' => ['nullable', 'exists:stock_opname_sessions,id'],
        ]);
        $period = $data['period'] ?? now('Asia/Jakarta')->format('Y-m');
        $start = Carbon::createFromFormat('!Y-m', $period, 'Asia/Jakarta')->startOfMonth();
        $end = $start->copy()->endOfMonth();
        $rows = DB::table('nightly_check_items as i')
            ->join('nightly_checks as c', 'c.id', '=', 'i.nightly_check_id')
            ->join('locations as l', 'l.id', '=', 'c.location_id')
            ->where('c.status', 'approved')->whereNotNull('i.quantity')->whereBetween('c.date', [$start->toDateString(), $end->toDateString()])
            ->select('i.*', 'c.date', 'c.id as check_id', 'l.name as location')
            ->orderByDesc('c.date')->orderBy('l.name')->orderBy('i.name')->paginate(30)->withQueryString();

        $sessions = StockOpnameSession::with(['location', 'creator', 'finalizer'])
            ->where('period', $period)
            ->latest('id')
            ->get();
        $selectedSession = isset($data['session'])
            ? StockOpnameSession::with(['location', 'items.medicine.unit', 'items.batch', 'creator', 'finalizer'])->find($data['session'])
            : $sessions->first()?->load(['location', 'items.medicine.unit', 'items.batch', 'creator', 'finalizer']);

        return view('inventory.opname', [
            'rows' => $rows,
            'sessions' => $sessions,
            'selectedSession' => $selectedSession,
            'locations' => Location::where('is_active', true)->orderBy('name')->get(),
            'period' => $period,
            'periodLabel' => $start->locale('id')->translatedFormat('F Y'),
        ]);
    }

    public function storeOpnameSession(Request $request)
    {
        $data = $request->validate([
            'period' => ['required', 'date_format:Y-m'],
            'location_id' => ['required', 'exists:locations,id', Rule::unique('stock_opname_sessions', 'location_id')->where('period', $request->input('period'))],
            'cutoff_at' => ['required', 'date_format:Y-m-d\\TH:i'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ], [
            'location_id.unique' => 'Sesi stock opname untuk periode dan lokasi ini sudah ada.',
        ]);

        $session = \App\Services\InventoryTransaction::run(function () use ($data, $request) {
            $session = StockOpnameSession::create([
                'reference' => $this->nextReference('stock_opname_adjustment', 'OPN'),
                'period' => $data['period'],
                'location_id' => $data['location_id'],
                'cutoff_at' => Carbon::parse($data['cutoff_at'], 'Asia/Jakarta'),
                'notes' => $data['notes'] ?? null,
                'created_by' => $request->user()->id,
            ]);

            app(\App\Services\OpnameSnapshotService::class)->resetItems($session);

            AuditLogger::record('inventory.opname_session.created', 'Sesi stock opname '.$session->reference.' dibuat.', $session, [
                'period' => $session->period,
                'location_id' => $session->location_id,
                'item_count' => $session->items()->count(),
            ], $request);

            return $session;
        });

        return redirect()->route('preview.opname', ['period' => $session->period, 'session' => $session->id])
            ->with('status', 'Sesi stock opname dibuat dari snapshot stok saat ini.');
    }

    public function saveOpnameSession(Request $request, StockOpnameSession $session)
    {
        abort_unless($session->status === 'draft', 422, 'Sesi yang sudah final tidak bisa diedit.');
        $data = $request->validate([
            'items' => ['required', 'array'],
            'items.*.physical_quantity' => ['nullable', 'numeric', 'gte:0', 'decimal:0,4', 'max:999999999999.9999'],
            'items.*.reason' => ['nullable', 'string', 'max:1000'],
        ]);

        \App\Services\InventoryTransaction::run(function () use ($data, $session, $request) {
            $session = StockOpnameSession::whereKey($session->id)->lockForUpdate()->firstOrFail();
            abort_unless($session->status === 'draft', 422, 'Sesi yang sudah final tidak bisa diedit.');
            $items = $session->items()->get()->keyBy('id');
            foreach ($data['items'] as $itemId => $payload) {
                $item = $items->get((int) $itemId);
                if (! $item) {
                    throw ValidationException::withMessages(['items' => 'Isian tidak sesuai dengan sesi terbaru. Muat ulang halaman dan isi kembali hasil hitung.']);
                }
                $physical = $payload['physical_quantity'] ?? null;
                $physical = $physical === '' || $physical === null ? null : (float) $physical;
                $item->update([
                    'physical_quantity' => $physical,
                    'difference' => $physical === null ? null : $physical - (float) $item->system_quantity,
                    'reason' => $payload['reason'] ?? null,
                ]);
            }

            AuditLogger::record('inventory.opname_session.saved', 'Sesi stock opname '.$session->reference.' disimpan.', $session, [
                'filled_items' => $session->items()->whereNotNull('physical_quantity')->count(),
            ], $request);
        });

        return back()->with('status', 'Isian stock opname disimpan.');
    }

    public function finalizeOpnameSession(Request $request, StockOpnameSession $session, InventoryStockService $stock)
    {
        abort_unless($session->status === 'draft', 422, 'Sesi ini sudah difinalisasi.');
        $session->load(['items', 'location']);
        if ($session->items->contains(fn ($item) => $item->physical_quantity === null)) {
            throw ValidationException::withMessages(['items' => 'Isi jumlah fisik semua item sebelum finalisasi.']);
        }

        \App\Services\InventoryTransaction::run(function () use ($session, $request, $stock) {
            $session = StockOpnameSession::whereKey($session->id)->lockForUpdate()->firstOrFail();
            abort_unless($session->status === 'draft', 422, 'Sesi ini sudah difinalisasi.');
            app(\App\Services\OpnameSnapshotService::class)->assertUnchanged($session);
            $session->load('items');
            if ($session->items->isEmpty()) {
                throw ValidationException::withMessages(['items' => 'Sesi belum memiliki stok batch untuk dihitung. Catat stok terlebih dahulu lalu pilih Mulai Hitung Ulang.']);
            }
            foreach ($session->items as $item) {
                if ($item->physical_quantity === null || ! $item->batch_id) {
                    throw ValidationException::withMessages(['items' => 'Lengkapi jumlah fisik dan rincian batch sebelum finalisasi.']);
                }
                $current = BatchStockBalance::where('batch_id', $item->batch_id)
                    ->where('medicine_id', $item->medicine_id)->where('location_id', $session->location_id)
                    ->lockForUpdate()->value('quantity');
                if ($current === null || round((float) $current, 4) !== round((float) $item->system_quantity, 4)) {
                    throw ValidationException::withMessages(['items' => 'Stok batch berubah sejak sesi dibuat. Jangan finalisasi sebelum stok dan hasil hitung ditinjau ulang.']);
                }
            }
            foreach ($session->items as $item) {
                $difference = (float) $item->physical_quantity - (float) $item->system_quantity;
                if ($difference == 0.0) {
                    continue;
                }

                $stock->add($item->medicine_id, $session->location_id, $difference);
                if ($item->batch_id) {
                    $stock->addBatch($item->medicine_id, $item->batch_id, $session->location_id, $difference);
                }

                InventoryMovement::create([
                    'type' => 'stock_opname_adjustment',
                    'reference' => $session->reference,
                    'medicine_id' => $item->medicine_id,
                    'batch_id' => $item->batch_id,
                    'source_location_id' => $difference < 0 ? $session->location_id : null,
                    'destination_location_id' => $difference > 0 ? $session->location_id : null,
                    'quantity' => abs($difference),
                    'occurred_at' => now('Asia/Jakarta'),
                    'notes' => 'Finalisasi stock opname '.$session->period.'. Alasan: '.($item->reason ?: '-'),
                    'created_by' => $request->user()->id,
                ]);
            }

            $session->update([
                'status' => 'finalized',
                'finalized_by' => $request->user()->id,
                'finalized_at' => now('Asia/Jakarta'),
            ]);

            AuditLogger::record('inventory.opname_session.finalized', 'Sesi stock opname '.$session->reference.' difinalisasi.', $session, [
                'period' => $session->period,
                'location_id' => $session->location_id,
                'adjusted_items' => $session->items()->where('difference', '<>', 0)->count(),
            ], $request);
        });

        return back()->with('status', 'Stock opname difinalisasi. Selisih sudah dicatat sebagai mutasi penyesuaian.');
    }

    public function recountOpnameSession(Request $request, StockOpnameSession $session)
    {
        \App\Services\InventoryTransaction::run(function () use ($session, $request) {
            $session = StockOpnameSession::whereKey($session->id)->lockForUpdate()->firstOrFail();
            abort_unless($session->status === 'draft', 422, 'Sesi yang sudah final tidak bisa dihitung ulang.');
            $before = ['snapshot' => $session->stock_snapshot, 'items' => $session->items()->get()->toArray(), 'cutoff_at' => $session->cutoff_at];
            app(\App\Services\OpnameSnapshotService::class)->resetItems($session);
            $session->update(['cutoff_at' => now('Asia/Jakarta')]);
            AuditLogger::record('inventory.opname_session.recounted', 'Hitung ulang opname '.$session->reference.' dimulai.', $session,
                ['before' => $before, 'item_count' => $session->items()->count()], $request);
        });

        return redirect()->route('preview.opname', ['period' => $session->period, 'session' => $session->id])
            ->with('status', 'Snapshot diperbarui. Isian sebelumnya tersimpan di audit log; hitung dan isi ulang seluruh jumlah fisik.');
    }

    private function operationalMedicines()
    {
        return $this->applyOperationalMedicineScope(Medicine::query());
    }

    private function assertMovementReferenceAvailable(string $reference): void
    {
        if (InventoryMovement::where('reference', $reference)->exists()) {
            throw ValidationException::withMessages(['reference' => 'Referensi transaksi sudah digunakan. Periksa riwayat agar stok tidak tercatat dua kali.']);
        }
    }

    private function defaultReceiptLocationId(): ?int
    {
        $setting = SystemSetting::value('default_receipt_location_id');
        if ($setting && Location::whereKey($setting)->where('is_active', true)->exists()) {
            return (int) $setting;
        }

        return Location::where('code', 'GDG')->where('is_active', true)->value('id')
            ?? Location::where('is_active', true)->value('id');
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

    private function transactionPrefill(Request $request): array
    {
        $data = $request->validate([
            'medicine_id' => ['nullable', 'integer', 'exists:medicines,id'],
            'batch_id' => ['nullable', 'integer', 'exists:batches,id'],
            'location_id' => ['nullable', 'integer', Rule::exists('locations', 'id')->where('is_active', true)],
            'source_location_id' => ['nullable', 'integer', Rule::exists('locations', 'id')->where('is_active', true)],
            'destination_location_id' => ['nullable', 'integer', Rule::exists('locations', 'id')->where('is_active', true)],
            'condition' => ['nullable', Rule::in(array_keys($this->conditionLabels()))],
        ]);
        if (! empty($data['batch_id']) && ! Batch::whereKey($data['batch_id'])->where('medicine_id', $data['medicine_id'] ?? 0)->exists()) {
            throw ValidationException::withMessages(['batch_id' => 'Batch pada pintasan tidak sesuai dengan obat.']);
        }

        return $data;
    }

    private function normalizeReceiptItems(Request $request): void
    {
        $items = $request->input('items');

        if (! is_array($items) && $request->filled('medicine_id')) {
            $items = [[
                'medicine_id' => $request->input('medicine_id'),
                'batch_number' => $request->input('batch_number'),
                'expiry_date' => $request->input('expiry_date'),
                'quantity' => $request->input('quantity'),
                'input_quantity' => $request->input('input_quantity'),
                'input_unit_name' => $request->input('input_unit_name'),
                'conversion_factor' => $request->input('conversion_factor'),
                'notes' => $request->input('notes'),
            ]];
        }

        $items = collect($items ?? [])
            ->map(function (array $item) {
                if (filled($item['medicine_id'] ?? null) && filled($item['input_unit_name'] ?? null)) {
                    $stockUnit = Medicine::with('unit')->find($item['medicine_id'])?->unit?->name;
                    if ($stockUnit && mb_strtolower(trim($item['input_unit_name'])) === mb_strtolower(trim($stockUnit))) {
                        $item['conversion_factor'] = 1;
                    }
                }
                if (filled($item['input_quantity'] ?? null) && is_numeric($item['input_quantity']) && filled($item['conversion_factor'] ?? null) && is_numeric($item['conversion_factor'])) {
                    $item['quantity'] = round((float) $item['input_quantity'] * (float) $item['conversion_factor'], 4);
                }

                return $item;
            })
            ->filter(fn (array $item) => collect($item)->filter(fn ($value) => filled($value))->isNotEmpty())
            ->all();

        $request->merge(['items' => $items]);
    }

    private function receiptItemNotes(InventoryReceiptItem $item, InventoryReceipt $receipt): ?string
    {
        $notes = trim((string) ($item->notes ?: $receipt->notes ?: ''));
        if (filled($receipt->supplier_invoice_number)) {
            $notes = 'Faktur supplier: '.$receipt->supplier_invoice_number.($notes ? ' | '.$notes : '');
        }
        $inputQuantity = $item->input_quantity === null ? null : (float) $item->input_quantity;
        $conversionFactor = $item->conversion_factor === null ? null : (float) $item->conversion_factor;

        if ($inputQuantity && $conversionFactor && ($item->input_unit_name || abs($conversionFactor - 1.0) > 0.0001)) {
            $conversion = 'Input faktur: '.number_format($inputQuantity, 4, ',', '.').' '.($item->input_unit_name ?: 'satuan faktur').' x '.number_format($conversionFactor, 4, ',', '.');
            $notes = $notes ? $notes.' | '.$conversion : $conversion;
        }

        return $notes !== '' ? $notes : null;
    }

    private function assertReceiptItemsAreOperational(array $items): void
    {
        $medicineIds = collect($items)->pluck('medicine_id')->unique()->values();
        $medicines = Medicine::whereIn('id', $medicineIds)->get()->keyBy('id');

        foreach ($items as $index => $item) {
            $medicine = $medicines->get((int) $item['medicine_id']);
            if (! $medicine || ! $this->isOperationalMedicine($medicine)) {
                throw ValidationException::withMessages([
                    'items.'.($index).'.medicine_id' => 'Obat harus aktif, punya bentuk sediaan, dan satuan dasar sebelum penerimaan dicatat.',
                ]);
            }
        }
    }

    private function stockCardFilters(Request $request, bool $required = false): array
    {
        $isFiltering = $required || $request->hasAny(['medicine_id', 'location_id', 'batch_id', 'start', 'end']);

        return $request->validate([
            'medicine_id' => [$isFiltering ? 'required' : 'nullable', 'exists:medicines,id'],
            'location_id' => [$isFiltering ? 'required' : 'nullable', 'exists:locations,id'],
            'batch_id' => ['nullable', 'exists:batches,id'],
            'start' => [$isFiltering ? 'required' : 'nullable', 'date_format:Y-m-d'],
            'end' => [$isFiltering ? 'required' : 'nullable', 'date_format:Y-m-d', 'after_or_equal:start'],
        ], [
            'medicine_id.required' => 'Pilih obat dulu untuk membuat kartu stok.',
            'location_id.required' => 'Pilih lokasi stok dulu.',
            'start.required' => 'Isi tanggal mulai periode kartu stok.',
            'end.required' => 'Isi tanggal selesai periode kartu stok.',
            'end.after_or_equal' => 'Tanggal selesai tidak boleh sebelum tanggal mulai.',
        ]);
    }

    private function stockCardRows(array $data): array
    {
        $query = InventoryMovement::with(['medicine.unit', 'batch', 'sourceLocation', 'destinationLocation', 'creator'])
            ->when($data['medicine_id'] ?? null, fn ($query, $id) => $query->where('medicine_id', $id))
            ->when($data['batch_id'] ?? null, fn ($query, $id) => $query->where('batch_id', $id))
            ->when($data['location_id'] ?? null, fn ($query, $id) => $query->where(fn ($inner) => $inner
                ->where('source_location_id', $id)
                ->orWhere('destination_location_id', $id)))
            ->when($data['start'] ?? null, fn ($query, $date) => $query->whereDate('occurred_at', '>=', $date))
            ->when($data['end'] ?? null, fn ($query, $date) => $query->whereDate('occurred_at', '<=', $date))
            ->orderBy('occurred_at')
            ->orderBy('id');

        $locationId = (int) ($data['location_id'] ?? 0);
        $opening = 0.0;
        if ($locationId && ! empty($data['start'])) {
            $opening = $this->movementQueryBefore($data, $locationId)
                ->get()
                ->sum(fn (InventoryMovement $movement) => $this->movementDelta($movement, $locationId));
        }

        $running = $opening;
        $rows = $query->get()->transform(function (InventoryMovement $movement) use (&$running, $locationId) {
            $delta = $locationId ? $this->movementDelta($movement, $locationId) : 0.0;
            $running += $delta;
            $movement->setAttribute('card_delta', $delta);
            $movement->setAttribute('card_running_balance', $locationId ? $running : null);

            return $movement;
        });

        return [$rows, $opening];
    }

    private function movementQueryBefore(array $filters, int $locationId)
    {
        return InventoryMovement::query()
            ->when($filters['medicine_id'] ?? null, fn ($query, $id) => $query->where('medicine_id', $id))
            ->when($filters['batch_id'] ?? null, fn ($query, $id) => $query->where('batch_id', $id))
            ->whereDate('occurred_at', '<', $filters['start'])
            ->where(fn ($query) => $query->where('source_location_id', $locationId)->orWhere('destination_location_id', $locationId));
    }

    private function movementDelta(InventoryMovement $movement, int $locationId): float
    {
        $quantity = (float) $movement->quantity;

        if ((int) $movement->destination_location_id === $locationId) {
            return $quantity;
        }

        if ((int) $movement->source_location_id === $locationId) {
            return -$quantity;
        }

        return 0.0;
    }

    private function conditionLabels(): array
    {
        return [
            'rusak' => 'Obat rusak',
            'hilang' => 'Obat hilang',
            'terjatuh' => 'Obat terjatuh',
            'tidak_diambil' => 'Obat tidak diambil pasien',
            'expired' => 'Obat expired',
            'lainnya' => 'Kondisi lainnya',
        ];
    }

    private function periodInventoryReport(Carbon $start, Carbon $end)
    {
        $movements = InventoryMovement::with(['medicine.unit', 'sourceLocation', 'destinationLocation'])
            ->where('occurred_at', '<=', $end)
            ->orderBy('occurred_at')
            ->get();
        $keys = collect();

        foreach ($movements as $movement) {
            if ($movement->source_location_id) {
                $keys->push($movement->medicine_id.'|'.$movement->source_location_id);
            }
            if ($movement->destination_location_id) {
                $keys->push($movement->medicine_id.'|'.$movement->destination_location_id);
            }
        }
        StockBalance::query()->get(['medicine_id', 'location_id'])->each(fn ($balance) => $keys->push($balance->medicine_id.'|'.$balance->location_id));

        $medicines = Medicine::with('unit')->whereIn('id', $keys->map(fn ($key) => explode('|', $key)[0])->unique())->get()->keyBy('id');
        $locations = Location::whereIn('id', $keys->map(fn ($key) => explode('|', $key)[1])->unique())->get()->keyBy('id');

        return $keys->unique()->map(function (string $key) use ($movements, $medicines, $locations, $start, $end) {
            [$medicineId, $locationId] = array_map('intval', explode('|', $key));
            $opening = 0.0;
            $incoming = 0.0;
            $outgoing = 0.0;

            foreach ($movements->where('medicine_id', $medicineId) as $movement) {
                $delta = $this->movementDelta($movement, $locationId);
                if ($movement->occurred_at->lt($start)) {
                    $opening += $delta;
                } elseif ($movement->occurred_at->betweenIncluded($start, $end)) {
                    if ($delta > 0) {
                        $incoming += $delta;
                    } elseif ($delta < 0) {
                        $outgoing += abs($delta);
                    }
                }
            }

            $medicine = $medicines->get($medicineId);
            $location = $locations->get($locationId);

            return [
                'medicine_name' => $medicine?->name ?? '-',
                'medicine_code' => $medicine?->code ?? '-',
                'location_name' => $location?->name ?? '-',
                'unit_name' => $medicine?->unit?->name ?? '',
                'opening' => $opening,
                'incoming' => $incoming,
                'outgoing' => $outgoing,
                'ending' => $opening + $incoming - $outgoing,
            ];
        })->sortBy([['medicine_name', 'asc'], ['location_name', 'asc']])->values();
    }

    private function ensureBatchUsable(int $batchId): void
    {
        $batch = Batch::findOrFail($batchId);
        if ($batch->expiry_date && $batch->expiry_date->lt(now('Asia/Jakarta')->startOfDay())) {
            throw ValidationException::withMessages([
                'batch_id' => 'Batch ini sudah kedaluwarsa dan tidak boleh dipakai untuk transfer atau pengeluaran.',
            ]);
        }
    }

    private function ensureBatchHasStock(int $medicineId, int $batchId, int $locationId, float $quantity): void
    {
        $available = (float) (BatchStockBalance::where('medicine_id', $medicineId)
            ->where('batch_id', $batchId)
            ->where('location_id', $locationId)
            ->value('quantity') ?? 0);

        if ($available < $quantity) {
            throw ValidationException::withMessages([
                'batch_id' => 'Batch tidak tersedia atau stok batch di lokasi sumber tidak mencukupi.',
            ]);
        }
    }

    private function nextReference(string $type, string $prefix): string
    {
        $year = now('Asia/Jakarta')->format('Y');
        $base = "{$prefix}-{$year}-";
        $references = InventoryMovement::where('type', $type)
            ->where('reference', 'like', $base.'%')
            ->pluck('reference');

        if ($type === 'receipt' && Schema::hasTable('inventory_receipts')) {
            $references = $references->merge(
                InventoryReceipt::where('reference', 'like', $base.'%')->pluck('reference')
            );
        }

        $lastNumber = $references
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
