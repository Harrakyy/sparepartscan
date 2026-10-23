<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class PurgeInvoiceFiles extends Command
{
    private const RETENTION_DAYS = 60;

    protected $signature = 'invoices:purge-files';

    protected $description = 'Hapus file foto/PDF faktur yang berumur lebih dari 60 hari (data sesi tetap disimpan)';

    public function handle(): int
    {
        $count = 0;
        Invoice::whereNotNull('file_path')
            ->where('created_at', '<', now()->subDays(self::RETENTION_DAYS))
            ->each(function (Invoice $invoice) use (&$count) {
                Storage::disk('local')->delete($invoice->file_path);
                $invoice->update(['file_path' => null, 'file_purged_at' => now()]);
                $count++;
            });

        $this->info("{$count} file faktur dihapus");

        return self::SUCCESS;
    }
}
