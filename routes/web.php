<?php

use App\Http\Controllers\Api\BackupController;
use App\Http\Controllers\Auth\FirstAccountController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\LicenseController;
use App\Models\GoodsReturn;
use App\Models\Invoice;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

// Komputer scan mengunduh salinan backup terbaru dari master (tanpa login, dijaga kode pemasangan).
// Hanya ada di edisi offline; batas 10 permintaan/menit.
Route::get('/backup/latest', [BackupController::class, 'latest'])
    ->middleware(['edition.offline', 'throttle:10,1']);

// Aktivasi lisensi (edisi offline): selalu terbuka, tanpa login, dilewati EnsureLicensed.
Route::middleware('edition.offline')->group(function () {
    Route::get('/aktivasi', [LicenseController::class, 'show'])->name('aktivasi');
    Route::post('/aktivasi', [LicenseController::class, 'store'])->name('aktivasi.store');

    // Akun superadmin pertama lewat web (setelah lisensi aktif); tertutup begitu ada akun.
    Route::get('/akun-pertama', [FirstAccountController::class, 'show'])->name('akun-pertama');
    Route::post('/akun-pertama', [FirstAccountController::class, 'store']);
});

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'login']);

    // Petugas mendaftar sendiri (edisi offline, bila diizinkan superadmin).
    Route::middleware('edition.offline')->group(function () {
        Route::get('/daftar', [RegisterController::class, 'show']);
        Route::post('/daftar', [RegisterController::class, 'store'])->middleware('throttle:5,1');
    });
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

    // Menu Backup: hanya superadmin, hanya edisi offline.
    Route::get('/backup', fn () => view('backup.index', [
        'lastDisk' => app(\App\Services\BackupService::class)->lastDiskBackup(),
    ]))->middleware(['can:superadmin', 'edition.offline']);
});
