<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\ConfirmationRequiredException;
use App\Exceptions\DomainRuleException;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreInvoiceRequest;
use App\Models\Invoice;
use App\Services\InvoiceService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InvoiceController extends Controller
{
    private const LIST_LIMIT = 50;

    public function __construct(private readonly InvoiceService $invoices) {}

    public function index(Request $request): JsonResponse
    {
        $statuses = $request->query('status') === 'done' ? Invoice::DONE_STATUSES : Invoice::OPEN_STATUSES;
        $q = trim((string) $request->query('q', ''));

        $list = Invoice::query()
            ->whereIn('status', $statuses)
            ->when($q !== '', fn ($query) => $query->where(fn ($w) => $w
                ->where('number', 'like', '%'.addcslashes($q, '%_\\').'%')
                ->orWhere('customer_name', 'like', '%'.addcslashes($q, '%_\\').'%')))
            ->withCount('items')
            ->withSum('items as total_qty', 'qty')
            ->withSum('items as picked_total', 'picked_qty')
            ->latest('id')
            ->limit(self::LIST_LIMIT)
            ->get()
            ->map(fn (Invoice $i) => array_merge($i->toArray(), [
                'total_qty' => (int) $i->total_qty,
                'picked_total' => (int) $i->picked_total,
            ]));

        return ApiResponse::ok($list);
    }

    /** Jumlah sesi per kelompok status, untuk badge menu Gudang (tanpa batas 50 baris daftar). */
    public function summary(): JsonResponse
    {
        return ApiResponse::ok([
            'open' => Invoice::whereIn('status', Invoice::OPEN_STATUSES)->count(),
            'done' => Invoice::whereIn('status', Invoice::DONE_STATUSES)->count(),
        ]);
    }

    public function store(StoreInvoiceRequest $request): JsonResponse
    {
        try {
            $invoice = $this->invoices->save($request->validated(), $request->file('file'));
        } catch (ConfirmationRequiredException $e) {
            return ApiResponse::fail($e->getMessage(), 409, ['needs_confirmation' => true]);
        } catch (DomainRuleException $e) {
            return ApiResponse::fail($e->getMessage(), 409);
        }

        return ApiResponse::ok($this->invoices->detail($invoice), 201);
    }

    public function show(Invoice $invoice): JsonResponse
    {
        return ApiResponse::ok($this->invoices->detail($invoice));
    }
}
