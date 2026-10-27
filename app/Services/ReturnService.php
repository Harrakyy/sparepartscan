<?php

namespace App\Services;

use App\Exceptions\DomainRuleException;
use App\Models\GoodsReturn;
use App\Models\GoodsReturnItem;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Retur barang dari pelanggan. Stok resmi ada di Zahir: di sini hanya dicatat barang yang
 * kembali (bagus/jelek) dan apakah retur sudah diinput ke Zahir. Faktur asli tidak diubah.
 */
class ReturnService
{
    private const LIST_LIMIT = 50;

    private const SEARCH_LIMIT = 20;

    /**
     * Retur open yang sudah ada untuk faktur ini, atau retur baru.
     *
     * @return array{return: GoodsReturn, existing: bool}
     */
    public function open(Invoice $invoice): array
    {
        return DB::transaction(function () use ($invoice) {
            $invoice = Invoice::lockForUpdate()->findOrFail($invoice->id);
            if (! in_array($invoice->status, Invoice::DONE_STATUSES, true)) {
                throw new DomainRuleException('Faktur ini belum selesai scan pertama');
            }

            $existing = $invoice->goodsReturns()->where('status', 'open')->first();

            return $existing
                ? ['return' => $existing, 'existing' => true]
                : ['return' => $invoice->goodsReturns()->create(['status' => 'open']), 'existing' => false];
        });
    }

    /**
     * Catat satu scan. `over_qty` dan `not_in_invoice` bukan error: dicatat dan dikembalikan
     * agar layar bisa menandainya, sama seperti scan keluar.
     *
     * @return array{result: string, message: ?string, item_id: ?int}
     */
    public function scan(GoodsReturn $return, string $code, string $condition, bool $manual): array
    {
        $code = strtoupper(trim($code));

        return DB::transaction(function () use ($return, $code, $condition, $manual) {
            $return = $this->lockOpen($return);
            $item = InvoiceItem::where('invoice_id', $return->invoice_id)->where('part_code', $code)->first();

            if (! $item) {
                $outcome = ['result' => 'not_in_invoice', 'message' => "{$code} tidak ada di faktur", 'item_id' => null];
            } elseif (($returned = $this->returnedQty($item->id)) >= $item->qty) {
                $outcome = ['result' => 'over_qty', 'message' => "{$code} sudah diretur semua ({$returned}/{$item->qty})", 'item_id' => $item->id];
            } else {
                $return->items()
                    ->firstOrCreate(['invoice_item_id' => $item->id, 'condition' => $condition], ['qty' => 0])
                    ->increment('qty');
                $outcome = ['result' => 'ok', 'message' => null, 'item_id' => $item->id];
            }

            $return->scans()->create([
                'invoice_item_id' => $outcome['item_id'],
                'code' => $code,
                'condition' => $condition,
                'result' => $outcome['result'],
                'manual' => $manual,
            ]);

            return $outcome;
        });
    }

    /** Koreksi salah scan: kurangi satu barang pada kondisi tertentu. */
    public function decrement(GoodsReturn $return, int $invoiceItemId, string $condition): void
    {
        DB::transaction(function () use ($return, $invoiceItemId, $condition) {
            $line = $this->lockOpen($return)->items()
                ->where('invoice_item_id', $invoiceItemId)
                ->where('condition', $condition)
                ->first();

            if (! $line) {
                throw new DomainRuleException('Tidak ada yang bisa dikurangi');
            }
            $line->qty > 1 ? $line->decrement('qty') : $line->delete();
        });
    }

    public function updateNote(GoodsReturn $return, ?string $note): void
    {
        DB::transaction(function () use ($return, $note) {
            $note = trim((string) $note);
            $this->lockOpen($return)->update(['note' => $note === '' ? null : $note]);
        });
    }

    public function complete(GoodsReturn $return): void
    {
        DB::transaction(function () use ($return) {
            $return = $this->lockOpen($return);
            if (! $return->items()->exists()) {
                throw new DomainRuleException('Belum ada barang yang diretur');
            }
            $return->update(['status' => 'done', 'done_at' => now()]);
        });
    }

    /** Hapus retur open yang masih kosong, misalnya karena salah pilih faktur. */
    public function cancel(GoodsReturn $return): void
    {
        DB::transaction(function () use ($return) {
            $return = $this->lockOpen($return);
            if ($return->items()->exists()) {
                throw new DomainRuleException('Retur yang sudah berisi barang tidak bisa dibatalkan');
            }
            $return->delete();
        });
    }

    public function setZahirEntered(GoodsReturn $return, bool $entered): void
    {
        $return = GoodsReturn::findOrFail($return->id);
        if ($return->status !== 'done') {
            throw new DomainRuleException('Selesaikan retur dulu');
        }
        $return->update(['zahir_entered_at' => $entered ? now() : null]);
    }

    public function detail(GoodsReturn $return): array
    {
        $return = GoodsReturn::with(['invoice.items', 'items', 'scans' => fn ($q) => $q->latest('id')])->findOrFail($return->id);
        $items = $return->invoice->items;
        $returnedTotals = GoodsReturnItem::whereIn('invoice_item_id', $items->pluck('id'))
            ->groupBy('invoice_item_id')
            ->selectRaw('invoice_item_id, SUM(qty) as total')
            ->pluck('total', 'invoice_item_id');
        $mine = $return->items->groupBy('invoice_item_id');

        return array_merge($return->only(['id', 'status', 'note', 'created_at', 'done_at', 'zahir_entered_at']), [
            'invoice' => $return->invoice->only(['id', 'number', 'customer_name', 'invoice_date']),
            'items' => $items->map(function (InvoiceItem $item) use ($mine, $returnedTotals) {
                $lines = $mine->get($item->id, collect());
                $good = (int) $lines->where('condition', 'good')->sum('qty');
                $bad = (int) $lines->where('condition', 'bad')->sum('qty');
                $total = (int) ($returnedTotals[$item->id] ?? 0);

                return [
                    'invoice_item_id' => $item->id,
                    'part_code' => $item->part_code,
                    'part_name' => $item->part_name,
                    'shipped' => $item->qty,
                    'returned_before' => $total - $good - $bad,
                    'good' => $good,
                    'bad' => $bad,
                    'remaining' => $item->qty - $total,
                ];
            })->values()->all(),
            'scans' => $return->scans->map->only(['code', 'condition', 'result', 'manual', 'created_at'])->values()->all(),
        ]);
    }

    /** @param string $tab open | done | zahir_pending */
    public function list(string $tab): Collection
    {
        return GoodsReturn::query()
            ->with('invoice:id,number,customer_name')
            ->where('status', $tab === 'open' ? 'open' : 'done')
            ->when($tab === 'zahir_pending', fn ($q) => $q->whereNull('zahir_entered_at'))
            ->withSum(['items as good_total' => fn ($q) => $q->where('condition', 'good')], 'qty')
            ->withSum(['items as bad_total' => fn ($q) => $q->where('condition', 'bad')], 'qty')
            ->latest('id')
            ->limit(self::LIST_LIMIT)
            ->get()
            ->map(fn (GoodsReturn $r) => array_merge($r->only(['id', 'status', 'created_at', 'done_at', 'zahir_entered_at']), [
                'invoice_number' => $r->invoice->number,
                'customer_name' => $r->invoice->customer_name,
                'good_total' => (int) $r->good_total,
                'bad_total' => (int) $r->bad_total,
            ]));
    }

    /** Faktur yang bisa diretur (scan pertama selesai), dengan retur open-nya bila ada. */
    public function eligibleInvoices(string $q): Collection
    {
        $like = '%'.addcslashes(trim($q), '%_\\').'%';

        return Invoice::query()
            ->whereIn('status', Invoice::DONE_STATUSES)
            ->when(trim($q) !== '', fn ($query) => $query->where(fn ($w) => $w
                ->where('number', 'like', $like)
                ->orWhere('customer_name', 'like', $like)))
            ->with(['goodsReturns' => fn ($r) => $r->where('status', 'open')])
            ->latest('id')
            ->limit(self::SEARCH_LIMIT)
            ->get()
            ->map(fn (Invoice $i) => array_merge($i->only(['id', 'number', 'customer_name', 'picked_at']), [
                'open_return_id' => $i->goodsReturns->first()?->id,
            ]));
    }

    /** Kunci faktur retur agar dua scan bersamaan tidak melewati batas, lalu muat ulang retur. */
    private function lockOpen(GoodsReturn $return): GoodsReturn
    {
        Invoice::lockForUpdate()->findOrFail($return->invoice_id);
        $return = GoodsReturn::findOrFail($return->id);
        if ($return->status !== 'open') {
            throw new DomainRuleException('Retur ini sudah selesai');
        }

        return $return;
    }

    /** Total barang ini yang sudah diretur di semua retur faktur (bagus + jelek, open + done). */
    private function returnedQty(int $invoiceItemId): int
    {
        return (int) GoodsReturnItem::where('invoice_item_id', $invoiceItemId)->sum('qty');
    }
}
