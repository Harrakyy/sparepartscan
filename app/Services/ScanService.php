<?php

namespace App\Services;

use App\Exceptions\DomainRuleException;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use Illuminate\Support\Facades\DB;

class ScanService
{
    private const COLUMN = ['pick' => 'picked_qty', 'qc' => 'qc_qty'];

    /**
     * Catat satu scan. Hasil `over_qty` dan `not_in_invoice` bukan error:
     * keduanya dicatat dan dikembalikan agar layar gudang bisa menandainya.
     *
     * @return array{result: string, message: ?string, item_id: ?int}
     */
    public function scan(Invoice $invoice, string $code, string $stage, bool $manual): array
    {
        $code = strtoupper(trim($code));
        $column = self::COLUMN[$stage];

        return DB::transaction(function () use ($invoice, $code, $stage, $manual, $column) {
            $invoice = Invoice::lockForUpdate()->findOrFail($invoice->id);
            $this->assertStageOpen($invoice, $stage);

            $item = $invoice->items()->where('part_code', $code)->first();
            if (! $item) {
                $outcome = ['result' => 'not_in_invoice', 'message' => "{$code} tidak ada di faktur", 'item_id' => null];
            } else {
                // Penambahan bersyarat: dua scan yang hampir bersamaan tidak bisa melewati jumlah faktur.
                $added = InvoiceItem::whereKey($item->id)->whereColumn($column, '<', 'qty')->increment($column);
                $outcome = $added
                    ? ['result' => 'ok', 'message' => null, 'item_id' => $item->id]
                    : ['result' => 'over_qty', 'message' => "{$code} sudah lengkap ({$item->qty}/{$item->qty})", 'item_id' => $item->id];
            }

            $invoice->scanEvents()->create([
                'invoice_item_id' => $outcome['item_id'],
                'stage' => $stage,
                'code' => $code,
                'result' => $outcome['result'],
                'manual' => $manual,
            ]);

            $next = ['pick' => ['waiting' => 'picking'], 'qc' => ['picked' => 'qc']][$stage][$invoice->status] ?? null;
            if ($next) {
                $invoice->update(['status' => $next]);
            }

            return $outcome;
        });
    }

    public function complete(Invoice $invoice, string $stage): Invoice
    {
        $column = self::COLUMN[$stage];

        return DB::transaction(function () use ($invoice, $stage, $column) {
            $invoice = Invoice::lockForUpdate()->findOrFail($invoice->id);
            $this->assertStageOpen($invoice, $stage);

            if ($invoice->items()->whereColumn($column, '<', 'qty')->exists()) {
                throw new DomainRuleException('Masih ada barang yang belum lengkap');
            }

            $invoice->update($stage === 'pick'
                ? ['status' => 'picked', 'picked_at' => now()]
                : ['status' => 'qc_done', 'qc_done_at' => now()]);

            return $invoice;
        });
    }

    private function assertStageOpen(Invoice $invoice, string $stage): void
    {
        if ($stage === 'pick' && ! in_array($invoice->status, ['waiting', 'picking'], true)) {
            throw new DomainRuleException('Scan pertama sudah selesai');
        }
        if ($stage === 'qc' && $invoice->status === 'qc_done') {
            throw new DomainRuleException('Scan QC sudah selesai');
        }
        if ($stage === 'qc' && ! in_array($invoice->status, ['picked', 'qc'], true)) {
            throw new DomainRuleException('Selesaikan scan pertama dulu');
        }
    }
}
