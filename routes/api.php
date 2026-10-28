<?php

use App\Http\Controllers\Api\InvoiceController;
use App\Http\Controllers\Api\PrintController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\ReturnController;
use App\Http\Controllers\Api\ScanController;
use Illuminate\Support\Facades\Route;

Route::get('/invoices', [InvoiceController::class, 'index']);
Route::post('/invoices', [InvoiceController::class, 'store']);
Route::get('/invoices/summary', [InvoiceController::class, 'summary']);
Route::get('/invoices/existing', [InvoiceController::class, 'existing']);
Route::post('/invoices/bulk', [InvoiceController::class, 'storeMany']);
Route::get('/invoices/{invoice}', [InvoiceController::class, 'show']);
Route::post('/invoices/{invoice}/scans', [ScanController::class, 'store']);
Route::post('/invoices/{invoice}/complete', [ScanController::class, 'complete']);
Route::post('/invoices/{invoice}/prints', [PrintController::class, 'store']);
Route::get('/reports/outgoing', [ReportController::class, 'outgoing']);
Route::get('/reports/outgoing-days', [ReportController::class, 'outgoingDays']);

Route::get('/returns', [ReturnController::class, 'index']);
Route::post('/returns', [ReturnController::class, 'store']);
Route::get('/returns/summary', [ReturnController::class, 'summary']);
Route::get('/returns/invoices', [ReturnController::class, 'invoices']);
Route::get('/returns/{goodsReturn}', [ReturnController::class, 'show']);
Route::patch('/returns/{goodsReturn}', [ReturnController::class, 'update']);
Route::delete('/returns/{goodsReturn}', [ReturnController::class, 'destroy']);
Route::post('/returns/{goodsReturn}/scans', [ReturnController::class, 'scan']);
Route::post('/returns/{goodsReturn}/decrement', [ReturnController::class, 'decrement']);
Route::post('/returns/{goodsReturn}/complete', [ReturnController::class, 'complete']);
Route::post('/returns/{goodsReturn}/zahir', [ReturnController::class, 'zahir']);
