<?php

use App\Enums\Role;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\NightlyCheckController;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

// Read-only rendering checks against local data; no login or stock changes.
$user = User::where('role', Role::Pharmacy->value)->where('is_active', true)->firstOrFail();
Auth::setUser($user);
$app['view']->share('errors', new Illuminate\Support\ViewErrorBag);
if (! Schema::hasColumn('inventory_receipts', 'supplier_invoice_number')) {
    throw new RuntimeException('Supplier invoice migration has not run.');
}

foreach ([
    [InventoryController::class, 'receipts', 'preview.receipts', [], 'Nomor faktur supplier'],
    [InventoryController::class, 'receipts', 'preview.receipts', ['tab' => 'riwayat'], 'Dokumen Penerimaan'],
    [InventoryController::class, 'transfers', 'preview.transfers', [], 'data-stock-entry-context'],
    [InventoryController::class, 'issues', 'preview.issues', [], 'data-stock-entry-context'],
    [InventoryController::class, 'returns', 'preview.returns', [], 'data-stock-entry-context'],
    [InventoryController::class, 'specialAdjustments', 'special-adjustments.index', [], 'data-adjust-save'],
    [NightlyCheckController::class, 'shortages', 'shortages.index', [], 'Stok Sistem pada atau di Bawah Minimum'],
] as [$controller, $method, $routeName, $query, $expected]) {
    $request = Request::create(route($routeName, $query), 'GET');
    $request->setUserResolver(fn () => $user);
    $route = $app['router']->getRoutes()->getByName($routeName)->bind($request);
    $request->setRouteResolver(fn () => $route);
    $request->setLaravelSession($app['session']->driver());
    $app->instance('request', $request);
    $html = $app->call([app($controller), $method], ['request' => $request, 'r' => $request])->render();
    if (! str_contains($html, $expected)) {
        throw new RuntimeException('Missing expected content on '.$routeName);
    }
    if ($method === 'receipts' && ! $query && str_contains($html, 'Item Terposting Terbaru')) {
        throw new RuntimeException('Receipt history is still shown on the input tab.');
    }
    if (in_array('--fixtures', $argv, true)) {
        $directory = storage_path('app/qa/inventory');
        if (! is_dir($directory)) mkdir($directory, 0777, true);
        file_put_contents($directory.'/'.$method.($query ? '-history' : '').'.html', $html);
    }
    echo $routeName.($query ? ' (history)' : '').": render OK\n";
}

if (in_array('--fixtures', $argv, true)) {
    $balance = App\Models\StockBalance::with(['medicine.unit', 'location'])->where('quantity', '>', 0)
        ->whereHas('medicine', fn ($query) => $query->where('is_active', true)->whereNotNull('unit_id'))
        ->whereHas('medicine.batches')->first();
    if ($balance) {
        // Synthetic report metadata avoids creating a real daily check just to test the browser.
        $check = (object) ['id' => 999999, 'date' => now('Asia/Jakarta')->toDateString(), 'location_id' => $balance->location_id,
            'location' => $balance->location->name, 'created_by' => $user->id, 'creator' => $user->name, 'status' => 'draft', 'review_note' => null];
        $item = (object) ['id' => 999999, 'medicine_id' => $balance->medicine_id, 'code' => $balance->medicine->code, 'name' => $balance->medicine->name,
            'quantity' => null, 'unit' => $balance->medicine->unit->name, 'is_low' => false, 'requested_quantity' => null, 'note' => null, 'saved_batch_counts' => []];
        $request = Request::create(route('nightly.show', $check->id));
        $route = $app['router']->getRoutes()->getByName('nightly.show')->bind($request);
        $request->setRouteResolver(fn () => $route);
        $request->setUserResolver(fn () => $user);
        $request->setLaravelSession($app['session']->driver());
        $app->instance('request', $request);
        $html = view('nightly.show', [
            'check' => $check, 'items' => collect([$item]), 'previous' => null, 'baseline' => collect(), 'shortages' => collect(), 'summary' => 'QA hitungan batch',
            'systemBalances' => collect([$balance->medicine_id => $balance->quantity]),
            'batchOptions' => App\Models\Batch::where('medicine_id', $balance->medicine_id)->get()->groupBy('medicine_id'),
            'batchBalances' => Illuminate\Support\Facades\DB::table('batch_stock_balances')->where('location_id', $balance->location_id)->pluck('quantity', 'batch_id'),
            'observedRevision' => 0,
        ])->render();
        file_put_contents(storage_path('app/qa/inventory/nightly.html'), $html);
        echo "Synthetic nightly batch-count fixture: render OK\n";
    }
}
