<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\DomainRuleException;
use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Services\InvoiceService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class PrintController extends Controller
{
    public function __construct(private readonly InvoiceService $invoices) {}

    public function store(Invoice $invoice): JsonResponse
    {
        try {
            $invoice = $this->invoices->recordPrint($invoice);
        } catch (DomainRuleException $e) {
            return ApiResponse::fail($e->getMessage(), 409);
        }

        return ApiResponse::ok($this->invoices->detail($invoice));
    }
}
