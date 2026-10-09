<?php

use App\Http\Controllers\PrototypeController;
use App\Http\Controllers\SessionController;
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
    Route::get('/dashboard', [PrototypeController::class, 'dashboard'])->name('dashboard');
    Route::get('/obat', MedicineIndex::class)->middleware('can:view-inventory')->name('medicines.index');
    Route::resource('admin/pengguna', UserController::class)
        ->except('show')
        ->middleware('can:manage-users')
        ->names('admin.users');

    Route::middleware('can:view-inventory')->group(function () {
        Route::get('/obat-masuk', [InventoryController::class, 'receipts'])->name('preview.receipts');
        Route::post('/obat-masuk', [InventoryController::class, 'storeReceipt'])->middleware('can:operate-inventory')->name('receipts.store');
        Route::get('/transfer', [InventoryController::class, 'transfers'])->name('preview.transfers');
        Route::post('/transfer', [InventoryController::class, 'storeTransfer'])->middleware('can:operate-inventory')->name('transfers.store');
        Route::get('/stock-opname', [InventoryController::class, 'opname'])->name('preview.opname');
        Route::get('/kedaluwarsa', [InventoryController::class, 'expiry'])->name('preview.expiry');
        Route::get('/laporan', [InventoryController::class, 'reports'])->name('preview.reports');
        Route::get('/riwayat-stok', [NightlyCheckController::class, 'history'])->name('nightly.history');
        Route::get('/kekurangan-permintaan', [NightlyCheckController::class, 'shortages'])->name('shortages.index');
        Route::get('/pengecekan-malam', [NightlyCheckController::class, 'index'])->name('preview.nightly');
        Route::post('/pengecekan-malam', [NightlyCheckController::class, 'store'])->middleware('can:operate-inventory')->name('nightly.store');
        Route::get('/pengecekan-malam/{id}', [NightlyCheckController::class, 'show'])->whereNumber('id')->name('nightly.show');
        Route::post('/pengecekan-malam/{id}', [NightlyCheckController::class, 'save'])->middleware('can:operate-inventory')->whereNumber('id')->name('nightly.save');
        Route::delete('/pengecekan-malam/{id}', [NightlyCheckController::class, 'destroy'])->middleware('can:operate-inventory')->whereNumber('id')->name('nightly.destroy');
        Route::post('/pengecekan-malam/{id}/review', [NightlyCheckController::class, 'review'])->middleware('can:finalize-opname')->whereNumber('id')->name('nightly.review');
    });
});
