<?php

use App\Http\Controllers\PrototypeController;
use App\Http\Controllers\SessionController;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\NightlyCheckController;
use App\Livewire\MedicineIndex;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route(auth()->check() ? 'dashboard' : 'login'));
Route::middleware('guest')->group(function () {
    Route::get('/login', [SessionController::class, 'create'])->name('login');
    Route::post('/login', [SessionController::class, 'store']);
});
Route::middleware(['auth', 'active'])->group(function () {
    Route::post('/logout', [SessionController::class, 'destroy'])->name('logout');
    Route::get('/dashboard', [PrototypeController::class, 'dashboard'])->middleware('can:view-dashboard')->name('dashboard');
    Route::get('/obat', MedicineIndex::class)->middleware('can:view-medicines')->name('medicines.index');
    Route::post('admin/pengguna/hak-akses', [UserController::class, 'updatePermissions'])
        ->middleware('can:manage-permissions')
        ->name('admin.users.permissions.update');
    Route::get('admin/audit-log', [AuditLogController::class, 'index'])
        ->middleware('can:view-audit-logs')
        ->name('admin.audit-logs.index');
    Route::resource('admin/pengguna', UserController::class)
        ->except('show')
        ->middleware('can:manage-users')
        ->names('admin.users');

    Route::group([], function () {
        Route::get('/obat-masuk', [InventoryController::class, 'receipts'])->middleware('can:view-receipts')->name('preview.receipts');
        Route::post('/obat-masuk', [InventoryController::class, 'storeReceipt'])->middleware(['can:view-receipts', 'can:operate-inventory'])->name('receipts.store');
        Route::post('/obat-masuk/{receipt}/post', [InventoryController::class, 'postReceipt'])->middleware(['can:view-receipts', 'can:operate-inventory'])->whereNumber('receipt')->name('receipts.post');
        Route::delete('/obat-masuk/{receipt}', [InventoryController::class, 'destroyReceipt'])->middleware(['can:view-receipts', 'can:operate-inventory'])->whereNumber('receipt')->name('receipts.destroy');
        Route::get('/transfer', [InventoryController::class, 'transfers'])->middleware('can:view-transfers')->name('preview.transfers');
        Route::post('/transfer', [InventoryController::class, 'storeTransfer'])->middleware(['can:view-transfers', 'can:operate-inventory'])->name('transfers.store');
        Route::get('/retur-pbf', [InventoryController::class, 'returns'])->middleware('can:view-returns')->name('preview.returns');
        Route::post('/retur-pbf', [InventoryController::class, 'storeReturn'])->middleware(['can:view-returns', 'can:operate-inventory'])->name('returns.store');
        Route::get('/kartu-stok', [InventoryController::class, 'stockCard'])->middleware('can:view-stock-card')->name('stock-card.index');
        Route::get('/kondisi-khusus', [InventoryController::class, 'specialAdjustments'])->middleware('can:view-special-adjustments')->name('special-adjustments.index');
        Route::post('/kondisi-khusus', [InventoryController::class, 'storeSpecialAdjustment'])->middleware(['can:view-special-adjustments', 'can:operate-inventory'])->name('special-adjustments.store');
        Route::get('/stock-opname', [InventoryController::class, 'opname'])->middleware('can:view-opname')->name('preview.opname');
        Route::get('/kedaluwarsa', [InventoryController::class, 'expiry'])->middleware('can:view-expiry')->name('preview.expiry');
        Route::get('/laporan', [InventoryController::class, 'reports'])->middleware('can:view-reports')->name('preview.reports');
        Route::get('/riwayat-stok', [NightlyCheckController::class, 'history'])->middleware('can:view-nightly-history')->name('nightly.history');
        Route::get('/kekurangan-permintaan', [NightlyCheckController::class, 'shortages'])->middleware('can:view-shortages')->name('shortages.index');
        Route::get('/pengecekan-malam', [NightlyCheckController::class, 'index'])->middleware('can:view-nightly-checks')->name('preview.nightly');
        Route::post('/pengecekan-malam', [NightlyCheckController::class, 'store'])->middleware(['can:view-nightly-checks', 'can:operate-inventory'])->name('nightly.store');
        Route::get('/pengecekan-malam/{id}', [NightlyCheckController::class, 'show'])->middleware('can:view-nightly-checks')->whereNumber('id')->name('nightly.show');
        Route::post('/pengecekan-malam/{id}', [NightlyCheckController::class, 'save'])->middleware(['can:view-nightly-checks', 'can:operate-inventory'])->whereNumber('id')->name('nightly.save');
        Route::delete('/pengecekan-malam/{id}', [NightlyCheckController::class, 'destroy'])->middleware(['can:view-nightly-checks', 'can:operate-inventory'])->whereNumber('id')->name('nightly.destroy');
        Route::post('/pengecekan-malam/{id}/review', [NightlyCheckController::class, 'review'])->middleware(['can:view-nightly-checks', 'can:finalize-opname'])->whereNumber('id')->name('nightly.review');
    });
});
