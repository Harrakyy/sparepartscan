<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\DomainRuleException;
use App\Http\Controllers\Controller;
use App\Models\GoodsReturn;
use App\Models\Invoice;
use App\Services\ReturnService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReturnController extends Controller
{
    private const TABS = ['open', 'done', 'zahir_pending'];

    private const CONDITION_RULE = ['required', 'in:good,bad'];

    private const CONDITION_MESSAGE = ['condition.*' => 'Kondisi harus bagus atau jelek'];

    public function __construct(private readonly ReturnService $returns) {}

    public function index(Request $request): JsonResponse
    {
        $tab = in_array($request->query('tab'), self::TABS, true) ? $request->query('tab') : 'open';

        return ApiResponse::ok($this->returns->list($tab));
    }

    public function summary(): JsonResponse
    {
        return ApiResponse::ok($this->returns->counts());
    }

    public function invoices(Request $request): JsonResponse
    {
        return ApiResponse::ok($this->returns->eligibleInvoices((string) $request->query('q', '')));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate(
            ['invoice_id' => ['required', 'integer', 'exists:invoices,id']],
            ['invoice_id.*' => 'Faktur tidak ditemukan'],
        );

        return $this->attempt(function () use ($data) {
            ['return' => $return, 'existing' => $existing] = $this->returns->open(Invoice::findOrFail($data['invoice_id']));

            return ApiResponse::ok(array_merge($this->returns->detail($return), ['existing' => $existing]), $existing ? 200 : 201);
        });
    }

    public function show(GoodsReturn $goodsReturn): JsonResponse
    {
        return ApiResponse::ok($this->returns->detail($goodsReturn));
    }

    public function update(Request $request, GoodsReturn $goodsReturn): JsonResponse
    {
        $data = $request->validate(
            ['note' => ['nullable', 'string', 'max:500']],
            ['note.max' => 'Catatan maksimal 500 karakter'],
        );

        return $this->attempt(fn () => $this->returns->updateNote($goodsReturn, $data['note'] ?? null), $goodsReturn);
    }

    public function scan(Request $request, GoodsReturn $goodsReturn): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:64'],
            'condition' => self::CONDITION_RULE,
            'manual' => ['sometimes', 'boolean'],
        ], [
            'code.required' => 'Kode part kosong',
            'code.max' => 'Kode part terlalu panjang',
            ...self::CONDITION_MESSAGE,
        ]);

        return $this->attempt(function () use ($goodsReturn, $data) {
            $scan = $this->returns->scan($goodsReturn, $data['code'], $data['condition'], (bool) ($data['manual'] ?? false));

            return ApiResponse::ok(['scan' => $scan, 'return' => $this->returns->detail($goodsReturn)]);
        });
    }

    public function decrement(Request $request, GoodsReturn $goodsReturn): JsonResponse
    {
        $data = $request->validate(
            ['invoice_item_id' => ['required', 'integer'], 'condition' => self::CONDITION_RULE],
            ['invoice_item_id.*' => 'Barang tidak dikenal', ...self::CONDITION_MESSAGE],
        );

        return $this->attempt(fn () => $this->returns->decrement($goodsReturn, $data['invoice_item_id'], $data['condition']), $goodsReturn);
    }

    public function complete(GoodsReturn $goodsReturn): JsonResponse
    {
        return $this->attempt(fn () => $this->returns->complete($goodsReturn), $goodsReturn);
    }

    public function destroy(GoodsReturn $goodsReturn): JsonResponse
    {
        return $this->attempt(function () use ($goodsReturn) {
            $this->returns->cancel($goodsReturn);

            return ApiResponse::ok(['deleted' => true]);
        });
    }

    public function zahir(Request $request, GoodsReturn $goodsReturn): JsonResponse
    {
        $data = $request->validate(['entered' => ['required', 'boolean']], ['entered.*' => 'Status Zahir tidak valid']);

        return $this->attempt(fn () => $this->returns->setZahirEntered($goodsReturn, $data['entered']), $goodsReturn);
    }

    /**
     * Jalankan aksi; pelanggaran aturan jadi 409. Bila `$detailOf` diberikan, balas dengan detail retur
     * itu, selain itu aksi sendiri yang membuat respons.
     */
    private function attempt(callable $action, ?GoodsReturn $detailOf = null): JsonResponse
    {
        try {
            $response = $action();
        } catch (DomainRuleException $e) {
            return ApiResponse::fail($e->getMessage(), 409);
        }

        return $detailOf ? ApiResponse::ok($this->returns->detail($detailOf)) : $response;
    }
}
