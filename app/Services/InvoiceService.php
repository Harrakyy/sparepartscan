<?php

namespace App\Services;

use App\Exceptions\ConfirmationRequiredException;
use App\Exceptions\DomainRuleException;
use App\Models\Invoice;
use App\Models\QtyAdjustment;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

class InvoiceService
{
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
                // Nomor bisa salah baca OCR dan menimpa faktur lain, jadi penggantian harus dikonfirmasi.
                if ($invoice && ! ($data['replace'] ?? false)) {
                    throw new ConfirmationRequiredException(
                        "Faktur {$data['number']} (Kepada {$invoice->customer_name}) sudah ada. Ganti dengan faktur ini?"
                    );
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

        // File laporan "Penjualan - Rincian" dipakai bersama banyak faktur: hapus hanya bila tak terpakai lagi.
        if ($oldPath && ! Invoice::where('file_path', $oldPath)->exists()) {
            Storage::disk('local')->delete($oldPath);
        }

        return $invoice;
    }

    /**
     * Simpan banyak faktur dari satu PDF laporan; semua memakai file yang sama. Nomor yang sudah
     * ada di app dilewati (tidak pernah diganti), sehingga laporan yang sama aman diupload ulang.
     *
     * @param  array<int, array{number: string, customer_name: string, invoice_date?: ?string, items: array<int, array{code: string, name: string, qty: int}>}>  $invoices
     * @return array{created: array<int, array{id: int, number: string}>, skipped: array<int, string>}
     */
    public function saveMany(array $invoices, UploadedFile $file): array
    {
        $path = $file->store('invoices', 'local');

        try {
            $result = DB::transaction(function () use ($invoices, $path) {
                $existing = Invoice::whereIn('number', array_column($invoices, 'number'))->lockForUpdate()->pluck('number')->all();
                $created = [];
                $skipped = [];
                foreach ($invoices as $data) {
                    if (in_array($data['number'], $existing, true) || isset($created[$data['number']])) {
                        $skipped[] = $data['number'];

                        continue;
                    }
                    $invoice = Invoice::create([
                        'number' => $data['number'],
                        'customer_name' => $data['customer_name'],
                        'invoice_date' => $data['invoice_date'] ?? null,
                        'source_type' => 'pdf',
                        'file_path' => $path,
                        'status' => 'waiting',
                    ]);
                    $invoice->items()->createMany($this->mergeItems($data['items']));
                    $created[$data['number']] = ['id' => $invoice->id, 'number' => $invoice->number];
                }

                return ['created' => array_values($created), 'skipped' => $skipped];
            });
        } catch (Throwable $e) {
            Storage::disk('local')->delete($path);
            throw $e;
        }

        if (! $result['created']) {
            Storage::disk('local')->delete($path);
        }

        return $result;
    }

    /**
     * Hapus faktur total (barang, scan, cetak, retur ikut terhapus lewat foreign key cascade),
     * supaya faktur pengganti bernomor sama dari Zahir bisa diupload ulang.
     */
    public function delete(Invoice $invoice, string $confirmNumber): void
    {
        if (trim($confirmNumber) !== $invoice->number) {
            throw ValidationException::withMessages(['confirm_number' => 'Nomor konfirmasi tidak cocok']);
        }

        $path = $invoice->file_path;
        $invoice->delete();

        // File laporan bersama tetap disimpan selama masih dipakai faktur lain.
        if ($path && ! Invoice::where('file_path', $path)->exists()) {
            Storage::disk('local')->delete($path);
        }
    }

    /** @return array<int, string> nomor dari `$numbers` yang sudah ada di app */
    public function existingNumbers(array $numbers): array
    {
        return Invoice::whereIn('number', $numbers)->orderBy('number')->pluck('number')->all();
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
        // Semua kejadian dikirim: daftar "tidak ada di faktur" di layar gudang dibangun dari sini.
        $events = $invoice->scanEvents()->latest('id')->get();

        return array_merge($invoice->toArray(), [
            'has_file' => $invoice->file_path !== null,
            'scan_events' => $events->toArray(),
            'print_logs' => $invoice->printLogs()->latest('id')->get(['id', 'created_at'])->toArray(),
            'adjustments' => $invoice->qtyAdjustments()->with(['item:id,part_code', 'user:id,name'])->latest('id')->get()
                ->map(fn (QtyAdjustment $a) => [
                    'created_at' => $a->created_at,
                    'user_name' => $a->user?->name,
                    'part_code' => $a->item->part_code,
                    'from_qty' => $a->from_qty,
                    'to_qty' => $a->to_qty,
                    'note' => $a->note,
                ])->all(),
        ]);
    }

    /**
     * Kurangi jumlah keluar setelah retur, supaya sama dengan Zahir (keluar 2, retur 1 → 1).
     * Hanya mengurangi (0 sampai jumlah faktur); setiap perubahan dicatat beserta alasan dan pengubahnya.
     *
     * @param  array<int, array{invoice_item_id: int, qty: int}>  $changes
     */
    public function adjust(Invoice $invoice, array $changes, string $note, User $user): Invoice
    {
        return DB::transaction(function () use ($invoice, $changes, $note, $user) {
            $invoice = Invoice::lockForUpdate()->findOrFail($invoice->id);
            if ($invoice->picked_at === null) {
                throw new DomainRuleException('Selesaikan scan pertama dulu');
            }

            $items = $invoice->items()->get()->keyBy('id');
            $changed = 0;
            foreach ($changes as $change) {
                $item = $items->get($change['invoice_item_id']);
                if (! $item) {
                    throw ValidationException::withMessages(['items' => 'Barang tidak dikenal']);
                }
                if ($change['qty'] < 0 || $change['qty'] > $item->qty) {
                    throw ValidationException::withMessages(['items' => "Jumlah keluar {$item->part_code} harus 0 sampai {$item->qty}"]);
                }
                if ($change['qty'] === $item->outQty()) {
                    continue;
                }
                $invoice->qtyAdjustments()->create([
                    'invoice_item_id' => $item->id,
                    'user_id' => $user->id,
                    'from_qty' => $item->outQty(),
                    'to_qty' => $change['qty'],
                    'note' => $note,
                ]);
                $item->update(['out_qty' => $change['qty']]);
                $changed++;
            }
            if ($changed === 0) {
                throw ValidationException::withMessages(['items' => 'Tidak ada jumlah yang berubah']);
            }

            return $invoice;
        });
    }

    /**
     * Catat satu cetakan struk. Cetak pertama mengisi `printed_at`: sejak saat itu barang
     * dianggap keluar (masuk laporan). Cetak ulang hanya menambah riwayat.
     */
    public function recordPrint(Invoice $invoice): Invoice
    {
        return DB::transaction(function () use ($invoice) {
            $invoice = Invoice::lockForUpdate()->findOrFail($invoice->id);
            if (! in_array($invoice->status, Invoice::DONE_STATUSES, true)) {
                throw new DomainRuleException('Selesaikan scan pertama dulu');
            }

            $invoice->printLogs()->create();
            if ($invoice->printed_at === null) {
                $invoice->update(['printed_at' => now()]);
            }

            return $invoice;
        });
    }
}
