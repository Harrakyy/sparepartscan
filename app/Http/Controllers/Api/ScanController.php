<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\DomainRuleException;
use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Services\InvoiceService;
use App\Services\ScanService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ScanController extends Controller
{
    public function __construct(
        private readonly ScanService $scans,
        private readonly InvoiceService $invoices,
    ) {}

    public function store(Request $request, Invoice $invoice): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:64'],
            'stage' => ['required', 'in:pick,qc'],
            'manual' => ['sometimes', 'boolean'],
        ], [
            'code.required' => 'Kode part kosong',
            'code.max' => 'Kode part terlalu panjang',
            'stage.*' => 'Tahap scan tidak dikenal',
        ]);

        try {
            $scan = $this->scans->scan($invoice, $data['code'], $data['stage'], (bool) ($data['manual'] ?? false));
        } catch (DomainRuleException $e) {
            return ApiResponse::fail($e->getMessage(), 409);
        }

        return ApiResponse::ok(['scan' => $scan, 'invoice' => $this->invoices->detail($invoice->fresh())]);
    }

    public function complete(Request $request, Invoice $invoice): JsonResponse
    {
        $data = $request->validate(['stage' => ['required', 'in:pick,qc']], ['stage.*' => 'Tahap scan tidak dikenal']);

        try {
            $invoice = $this->scans->complete($invoice, $data['stage']);
        } catch (DomainRuleException $e) {
            return ApiResponse::fail($e->getMessage(), 409);
        }

        return ApiResponse::ok($this->invoices->detail($invoice));
    }
}
