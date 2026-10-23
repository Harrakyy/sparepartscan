<?php

namespace App\Services;

use App\Exceptions\DomainRuleException;
use App\Models\Invoice;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class InvoiceService
{
    private const DETAIL_EVENT_LIMIT = 30;

    /**
     * Simpan faktur hasil konfirmasi admin. Nomor yang sudah ada diganti
     * selama belum ada scan; kalau sudah ada scan, ditolak.
     *
     * @param  array{number: string, customer_name: string, invoice_date?: ?string, source_type: string, items: array<int, array{code: string, name: string, qty: int}>}  $data
     */
    public function save(array $data, UploadedFile $file): Invoice
    {
        $items = $this->mergeItems($data['items']);
        $path = $file->store('invoices', 'local');

        try {
            [$invoice, $oldPath] = DB::transaction(function () use ($data, $items, $path) {
                $invoice = Invoice::where('number', $data['number'])->lockForUpdate()->first();
                if ($invoice && $invoice->scanEvents()->exists()) {
                    throw new DomainRuleException("Faktur {$data['number']} sudah mulai di-scan, tidak bisa diganti");
                }
                $oldPath = $invoice?->file_path;
                $invoice ??= new Invoice(['number' => $data['number']]);
                $invoice->fill([
                    'customer_name' => $data['customer_name'],
                    'invoice_date' => $data['invoice_date'] ?? null,
                    'source_type' => $data['source_type'],
                    'file_path' => $path,
                    'file_purged_at' => null,
                    'status' => 'waiting',
                ])->save();
                $invoice->items()->delete();
                $invoice->items()->createMany($items);

                return [$invoice, $oldPath];
            });
        } catch (Throwable $e) {
            Storage::disk('local')->delete($path);
            throw $e;
        }

        if ($oldPath) {
            Storage::disk('local')->delete($oldPath);
        }

        return $invoice;
    }

    /**
     * Kode dirapikan (huruf besar, tanpa spasi tepi); kode yang muncul lebih dari
     * sekali digabung: jumlah ditambahkan, nama dari baris pertama.
     *
     * @return array<int, array{line_no: int, part_code: string, part_name: string, qty: int}>
     */
    public function mergeItems(array $items): array
    {
        $merged = [];
        foreach ($items as $item) {
            $code = strtoupper(trim($item['code']));
            if (isset($merged[$code])) {
                $merged[$code]['qty'] += (int) $item['qty'];

                continue;
            }
            $merged[$code] = [
                'line_no' => count($merged) + 1,
                'part_code' => $code,
                'part_name' => trim($item['name']),
                'qty' => (int) $item['qty'],
            ];
        }

        return array_values($merged);
    }

    public function detail(Invoice $invoice): array
    {
        $invoice->load('items');
        $events = $invoice->scanEvents()->latest('id')->limit(self::DETAIL_EVENT_LIMIT)->get();

        return array_merge($invoice->toArray(), [
            'has_file' => $invoice->file_path !== null,
            'scan_events' => $events->toArray(),
        ]);
    }
}
