<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class ReportController extends Controller
{
    private const TIMEZONE = 'Asia/Jakarta';

    private const DATE_MESSAGES = [
        'from.*' => 'Tanggal awal harus berformat YYYY-MM-DD',
        'to.*' => 'Tanggal akhir harus berformat YYYY-MM-DD',
    ];

    /** Barang keluar = faktur yang sudah dicetak, menurut tanggal cetak pertama (WIB). */
    public function outgoing(Request $request): JsonResponse
    {
        $data = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
            'q' => ['nullable', 'string', 'max:150'],
        ], self::DATE_MESSAGES);

        $today = Carbon::now(self::TIMEZONE)->toDateString();
        $q = trim($data['q'] ?? '');

        $invoices = Invoice::query()
            ->whereBetween('printed_at', $this->range($data['from'] ?? $today, $data['to'] ?? $today))
            ->when($q !== '', fn ($query) => $query->where('customer_name', 'like', '%'.addcslashes($q, '%_\\').'%'))
            ->with('items')
            ->orderByDesc('printed_at')
            ->get();

        // Jumlah keluar = jumlah faktur, atau hasil edit superadmin setelah retur (sama dengan Zahir).
        $totals = $invoices->flatMap->items
            ->groupBy('part_code')
            ->map(fn ($rows, $code) => ['part_code' => $code, 'part_name' => $rows->first()->part_name, 'qty' => (int) $rows->sum(fn (InvoiceItem $i) => $i->outQty())])
            ->sortBy('part_code')
            ->values();

        return ApiResponse::ok([
            'invoices' => $invoices->map(fn (Invoice $i) => [
                'id' => $i->id,
                'number' => $i->number,
                'customer_name' => $i->customer_name,
                'picked_at' => $i->picked_at,
                'printed_at' => $i->printed_at,
                'items' => $i->items->map(fn (InvoiceItem $item) => [
                    'part_code' => $item->part_code,
                    'part_name' => $item->part_name,
                    'qty' => $item->outQty(),
                ])->values(),
            ])->values(),
            'totals' => $totals,
        ]);
    }

    /** Tanggal (WIB, YYYY-MM-DD) yang ada barang keluarnya, untuk menandai kalender laporan. */
    public function outgoingDays(Request $request): JsonResponse
    {
        $data = $request->validate([
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d'],
        ], self::DATE_MESSAGES);

        $days = Invoice::query()
            ->whereBetween('printed_at', $this->range($data['from'], $data['to']))
            ->pluck('printed_at')
            ->map(fn (Carbon $at) => $at->copy()->timezone(self::TIMEZONE)->toDateString())
            ->unique()
            ->sort()
            ->values();

        return ApiResponse::ok($days);
    }

    /** Rentang tanggal WIB (awal hari s.d. akhir hari) dalam UTC, sesuai penyimpanan waktu. */
    private function range(string $from, string $to): array
    {
        return [
            Carbon::parse($from, self::TIMEZONE)->startOfDay()->utc(),
            Carbon::parse($to, self::TIMEZONE)->endOfDay()->utc(),
        ];
    }
}
