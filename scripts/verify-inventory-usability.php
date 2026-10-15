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
    echo $routeName.($query ? ' (history)' : '').": render OK\n";
}
