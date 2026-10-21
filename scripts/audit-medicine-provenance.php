<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$codes = [];
foreach ([['storage/app/imports/review-satuan-obat.json', 'rows', 'ID Produk'], ['database/data/master_obat_160.json', null, 'ID Klinik']] as [$file, $key, $column]) {
    if (! is_file(base_path($file))) continue;
    $data = json_decode(file_get_contents(base_path($file)), true, flags: JSON_THROW_ON_ERROR);
    foreach ($key ? ($data[$key] ?? []) : $data as $row) {
        $code = trim((string) ($row[$column] ?? ''));
        if ($code !== '') $codes[strtoupper($code)] = true;
    }
}
$medicines = App\Models\Medicine::all();
$outside = $medicines->filter(fn ($m) => ! isset($codes[strtoupper($m->code)]));
$tables = ['medicines', 'inventory_movements', 'inventory_receipts', 'inventory_receipt_items', 'stock_balances', 'batch_stock_balances', 'batches', 'nightly_checks', 'nightly_check_items', 'stock_opname_sessions', 'stock_opname_items'];
$counts = [];
foreach ($tables as $table) $counts[$table] = Illuminate\Support\Facades\DB::table($table)->count();
echo json_encode(['environment' => app()->environment(), 'reference_codes' => count($codes), 'counts' => $counts,
    'provenance' => $medicines->groupBy(fn ($m) => $m->import_source['source'] ?? $m->import_source['file'] ?? '(tanpa sumber impor)')->map->count(),
    'outside_reference' => $outside->map(fn ($m) => ['id' => $m->id, 'code' => $m->code, 'name' => $m->name,
        'source' => $m->import_source['source'] ?? $m->import_source['file'] ?? null, 'movements' => $m->movements()->count(), 'batches' => $m->batches()->count()])->values(),
    'batches' => App\Models\Batch::with('medicine:id,code')->get()->map(fn ($b) => ['medicine' => $b->medicine->code, 'number' => $b->number]),
    'checks' => Illuminate\Support\Facades\DB::table('nightly_checks')->select('id', 'status', 'date')->get(),
    'sessions' => Illuminate\Support\Facades\DB::table('stock_opname_sessions')->select('id', 'status', 'period')->get(),
    'movement_references' => App\Models\InventoryMovement::select('reference', 'type', 'notes')->distinct()->limit(30)->get(),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR).PHP_EOL;
