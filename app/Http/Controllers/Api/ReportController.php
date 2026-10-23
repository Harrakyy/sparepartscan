<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class ReportController extends Controller
{
    private const TIMEZONE = 'Asia/Jakarta';

    /** Barang keluar = faktur yang scan pertamanya sudah selesai, dalam rentang tanggal WIB. */
    public function outgoing(Request $request): JsonResponse
    {
        $data = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
            'q' => ['nullable', 'string', 'max:150'],
        ], [
            'from.*' => 'Tanggal awal harus berformat YYYY-MM-DD',
            'to.*' => 'Tanggal akhir harus berformat YYYY-MM-DD',
        ]);

        $today = Carbon::now(self::TIMEZONE)->toDateString();
        $from = Carbon::parse($data['from'] ?? $today, self::TIMEZONE)->startOfDay()->utc();
        $to = Carbon::parse($data['to'] ?? $today, self::TIMEZONE)->endOfDay()->utc();
        $q = trim($data['q'] ?? '');

        $invoices = Invoice::query()
            ->whereIn('status', Invoice::DONE_STATUSES)
            ->whereBetween('picked_at', [$from, $to])
            ->when($q !== '', fn ($query) => $query->where('customer_name', 'like', '%'.addcslashes($q, '%_\\').'%'))
            ->with('items')
            ->orderByDesc('picked_at')
            ->get();

        $totals = $invoices->flatMap->items
            ->groupBy('part_code')
            ->map(fn ($rows, $code) => ['part_code' => $code, 'part_name' => $rows->first()->part_name, 'qty' => (int) $rows->sum('qty')])
            ->sortBy('part_code')
            ->values();

        return ApiResponse::ok([
            'invoices' => $invoices->map(fn (Invoice $i) => [
                'id' => $i->id,
                'number' => $i->number,
                'customer_name' => $i->customer_name,
                'picked_at' => $i->picked_at,
                'items' => $i->items->map->only(['part_code', 'part_name', 'qty'])->values(),
            ])->values(),
            'totals' => $totals,
        ]);
    }
}
