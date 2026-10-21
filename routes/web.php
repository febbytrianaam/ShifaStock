<?php

use App\Http\Controllers\PrototypeController;
use App\Http\Controllers\SessionController;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\SystemReferenceController;
use App\Http\Controllers\Admin\SystemSettingController;
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
    Route::post('/panduan/selesai', [\App\Http\Controllers\OnboardingController::class, 'finish'])->name('onboarding.finish');
    Route::post('/logout', [SessionController::class, 'destroy'])->name('logout');
    Route::get('/dashboard', [PrototypeController::class, 'dashboard'])->middleware('can:view-dashboard')->name('dashboard');
    Route::get('/obat', MedicineIndex::class)->middleware('can:view-medicines')->name('medicines.index');
    Route::post('admin/pengguna/hak-akses', [UserController::class, 'updatePermissions'])
        ->middleware('can:manage-permissions')
        ->name('admin.users.permissions.update');
    Route::post('admin/pengguna/hak-akses/reset-default', [UserController::class, 'resetPermissions'])
        ->middleware('can:manage-permissions')
        ->name('admin.users.permissions.reset');
    Route::get('admin/audit-log', [AuditLogController::class, 'index'])
        ->middleware('can:view-audit-logs')
        ->name('admin.audit-logs.index');
    Route::get('admin/referensi-sistem', [SystemReferenceController::class, 'index'])
        ->middleware('can:manage-system-references')
        ->name('admin.references.index');
    Route::post('admin/referensi-sistem/lokasi', [SystemReferenceController::class, 'storeLocation'])
        ->middleware('can:manage-system-references')
        ->name('admin.references.locations.store');
    Route::put('admin/referensi-sistem/lokasi/{location}', [SystemReferenceController::class, 'updateLocation'])
        ->middleware('can:manage-system-references')
        ->name('admin.references.locations.update');
    Route::post('admin/referensi-sistem/satuan', [SystemReferenceController::class, 'storeUnit'])
        ->middleware('can:manage-system-references')
        ->name('admin.references.units.store');
    Route::put('admin/referensi-sistem/satuan/{unit}', [SystemReferenceController::class, 'updateUnit'])
        ->middleware('can:manage-system-references')
        ->name('admin.references.units.update');
    Route::get('admin/pengaturan-sistem', [SystemSettingController::class, 'edit'])
        ->middleware('can:manage-system-settings')
        ->name('admin.settings.index');
    Route::post('admin/pengaturan-sistem', [SystemSettingController::class, 'update'])
        ->middleware('can:manage-system-settings')
        ->name('admin.settings.update');
    Route::resource('admin/pengguna', UserController::class)
        ->except('show')
        ->middleware('can:manage-users')
        ->names('admin.users');

    Route::group([], function () {
        Route::get('/obat-masuk', [InventoryController::class, 'receipts'])->middleware('can:view-receipts')->name('preview.receipts');
        Route::get('/obat-masuk/cek-faktur', [InventoryController::class, 'checkReceiptInvoice'])->middleware(['can:view-receipts', 'can:operate-inventory'])->name('receipts.check-invoice');
        Route::post('/obat-masuk', [InventoryController::class, 'storeReceipt'])->middleware(['can:view-receipts', 'can:operate-inventory'])->name('receipts.store');
        Route::get('/obat-masuk/{receipt}/edit', [InventoryController::class, 'editReceipt'])->middleware(['can:view-receipts', 'can:operate-inventory'])->whereNumber('receipt')->name('receipts.edit');
        Route::put('/obat-masuk/{receipt}', [InventoryController::class, 'updateReceipt'])->middleware(['can:view-receipts', 'can:operate-inventory'])->whereNumber('receipt')->name('receipts.update');
        Route::post('/obat-masuk/{receipt}/post', [InventoryController::class, 'postReceipt'])->middleware(['can:view-receipts', 'can:operate-inventory'])->whereNumber('receipt')->name('receipts.post');
        Route::delete('/obat-masuk/{receipt}', [InventoryController::class, 'destroyReceipt'])->middleware(['can:view-receipts', 'can:operate-inventory'])->whereNumber('receipt')->name('receipts.destroy');
        Route::get('/transfer', [InventoryController::class, 'transfers'])->middleware('can:view-transfers')->name('preview.transfers');
        Route::post('/transfer', [InventoryController::class, 'storeTransfer'])->middleware(['can:view-transfers', 'can:operate-inventory'])->name('transfers.store');
        Route::get('/obat-keluar', [InventoryController::class, 'issues'])->middleware('can:view-issues')->name('preview.issues');
        Route::post('/obat-keluar', [InventoryController::class, 'storeIssue'])->middleware(['can:view-issues', 'can:operate-inventory'])->name('issues.store');
        Route::get('/saldo-awal', fn () => redirect('/stok-awal'))->middleware('can:view-opening-balances');
        Route::post('/saldo-awal', [InventoryController::class, 'storeOpeningBalance'])->middleware(['can:view-opening-balances', 'can:operate-inventory']);
        Route::get('/stok-awal', [InventoryController::class, 'openingBalances'])->middleware('can:view-opening-balances')->name('opening-balances.index');
        Route::post('/stok-awal', [InventoryController::class, 'storeOpeningBalance'])->middleware(['can:view-opening-balances', 'can:operate-inventory'])->name('opening-balances.store');
        Route::get('/parameter-monitoring', [InventoryController::class, 'monitoringParameters'])->middleware('can:view-monitoring-parameters')->name('monitoring-parameters.index');
        Route::post('/parameter-monitoring', [InventoryController::class, 'saveMonitoringParameters'])->middleware(['can:view-monitoring-parameters', 'can:manage-monitoring-parameters'])->name('monitoring-parameters.store');
        Route::get('/retur-pbf', [InventoryController::class, 'returns'])->middleware('can:view-returns')->name('preview.returns');
        Route::post('/retur-pbf', [InventoryController::class, 'storeReturn'])->middleware(['can:view-returns', 'can:operate-inventory'])->name('returns.store');
        Route::get('/kartu-stok', [InventoryController::class, 'stockCard'])->middleware('can:view-stock-card')->name('stock-card.index');
        Route::get('/kartu-stok/cetak', [InventoryController::class, 'stockCardPrint'])->middleware('can:view-stock-card')->name('stock-card.print');
        Route::post('/mutasi/{movement}/reversal', [InventoryController::class, 'reverseMovement'])->middleware(['can:view-stock-card', 'can:reverse-inventory-movements'])->whereNumber('movement')->name('movements.reverse');
        Route::get('/kondisi-khusus', [InventoryController::class, 'specialAdjustments'])->middleware('can:view-special-adjustments')->name('special-adjustments.index');
        Route::post('/kondisi-khusus', [InventoryController::class, 'storeSpecialAdjustment'])->middleware(['can:view-special-adjustments', 'can:operate-inventory'])->name('special-adjustments.store');
        Route::get('/stock-opname', [InventoryController::class, 'opname'])->middleware('can:view-opname')->name('preview.opname');
        Route::post('/stock-opname', [InventoryController::class, 'storeOpnameSession'])->middleware(['can:view-opname', 'can:operate-inventory'])->name('opname.store');
        Route::post('/stock-opname/{session}/save', [InventoryController::class, 'saveOpnameSession'])->middleware(['can:view-opname', 'can:operate-inventory'])->whereNumber('session')->name('opname.save');
        Route::post('/stock-opname/{session}/recount', [InventoryController::class, 'recountOpnameSession'])->middleware(['can:view-opname', 'can:operate-inventory'])->whereNumber('session')->name('opname.recount');
        Route::post('/stock-opname/{session}/finalize', [InventoryController::class, 'finalizeOpnameSession'])->middleware(['can:view-opname', 'can:finalize-opname'])->whereNumber('session')->name('opname.finalize');
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
