<?php

use App\Http\Controllers\Api\InvoiceController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\ScanController;
use Illuminate\Support\Facades\Route;

Route::get('/invoices', [InvoiceController::class, 'index']);
Route::post('/invoices', [InvoiceController::class, 'store']);
Route::get('/invoices/summary', [InvoiceController::class, 'summary']);
Route::get('/invoices/{invoice}', [InvoiceController::class, 'show']);
Route::post('/invoices/{invoice}/scans', [ScanController::class, 'store']);
Route::post('/invoices/{invoice}/complete', [ScanController::class, 'complete']);
Route::get('/reports/outgoing', [ReportController::class, 'outgoing']);
