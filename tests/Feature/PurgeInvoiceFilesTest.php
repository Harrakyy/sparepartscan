<?php

namespace Tests\Feature;

use App\Models\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PurgeInvoiceFilesTest extends TestCase
{
    use RefreshDatabase;

    private function invoiceWithFile(int $ageDays): Invoice
    {
        $path = "invoices/{$ageDays}.pdf";
        Storage::disk('local')->put($path, 'pdf');
        $invoice = Invoice::factory()->create(['file_path' => $path]);
        $invoice->forceFill(['created_at' => now()->subDays($ageDays)])->save();

        return $invoice;
    }

    public function test_removes_files_older_than_sixty_days_only(): void
    {
        Storage::fake('local');
        $old = $this->invoiceWithFile(61);
        $recent = $this->invoiceWithFile(59);

        $this->artisan('invoices:purge-files')->expectsOutput('1 file faktur dihapus')->assertSuccessful();

        Storage::disk('local')->assertMissing('invoices/61.pdf');
        Storage::disk('local')->assertExists('invoices/59.pdf');
        $this->assertNull($old->fresh()->file_path);
        $this->assertNotNull($old->fresh()->file_purged_at);
        $this->assertSame('invoices/59.pdf', $recent->fresh()->file_path);
    }

    public function test_file_route_serves_file_and_404_after_purge(): void
    {
        Storage::fake('local');
        $invoice = $this->invoiceWithFile(1);

        $this->get("/files/invoices/{$invoice->id}")->assertOk();

        $invoice->update(['file_path' => null]);
        $this->get("/files/invoices/{$invoice->id}")->assertNotFound();
    }
}
