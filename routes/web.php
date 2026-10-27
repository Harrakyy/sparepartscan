<?php

use App\Models\Invoice;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

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

Route::view('/laporan', 'reports.outgoing');
