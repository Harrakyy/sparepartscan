<?php

use App\Http\Controllers\Auth\LoginController;
use App\Models\GoodsReturn;
use App\Models\Invoice;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'login']);
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'logout']);

    Route::redirect('/', '/gudang');

    Route::view('/faktur/baru', 'invoices.create');

    Route::get('/files/invoices/{invoice}', function (Invoice $invoice) {
        abort_if($invoice->file_path === null || ! Storage::disk('local')->exists($invoice->file_path), 404);

        return Storage::disk('local')->response($invoice->file_path);
    });

    Route::view('/gudang', 'warehouse.index');
    Route::get('/gudang/sesi/{invoice}', fn (Invoice $invoice) => view('warehouse.session', compact('invoice')));

    Route::view('/retur', 'returns.index');
    Route::view('/retur/baru', 'returns.create');
    Route::get('/retur/{goodsReturn}', fn (GoodsReturn $goodsReturn) => view('returns.session', compact('goodsReturn')));

    Route::view('/laporan', 'reports.outgoing');

    Route::view('/pengguna', 'users.index')->middleware('can:superadmin');
});
