<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\User;
use App\Services\ReturnService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class InvoiceDeleteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function remove(Invoice $invoice, ?string $confirm = null)
    {
        return $this->deleteJson("/api/invoices/{$invoice->id}", ['confirm_number' => $confirm ?? $invoice->number]);
    }

    public function test_admin_cannot_delete(): void
    {
        $invoice = ReturnServiceTest::invoiceWithItems('picked');
        $this->actingAs(User::factory()->admin()->create());

        $this->remove($invoice)->assertStatus(403)->assertJsonPath('error', 'Hanya superadmin yang boleh melakukan ini');
        $this->assertModelExists($invoice);
    }

    public function test_confirmation_must_match_the_invoice_number(): void
    {
        $invoice = ReturnServiceTest::invoiceWithItems('picked');

        $this->remove($invoice, '123')->assertStatus(422)->assertJsonPath('error', 'Nomor konfirmasi tidak cocok');
        $this->assertModelExists($invoice);
    }

    public function test_everything_belonging_to_the_invoice_is_removed(): void
    {
        $invoice = ReturnServiceTest::invoiceWithItems('picked');
        $item = $invoice->items()->first();
        $invoice->scanEvents()->create(['invoice_item_id' => $item->id, 'stage' => 'pick', 'code' => $item->part_code, 'result' => 'ok', 'manual' => false]);
        $invoice->printLogs()->create();
        $return = app(ReturnService::class)->open($invoice)['return'];
        app(ReturnService::class)->scan($return, $item->part_code, 'good', false);

        $this->remove($invoice)->assertOk()->assertJsonPath('data.deleted', true);

        $this->assertModelMissing($invoice);
        foreach (['invoice_items', 'scan_events', 'print_logs', 'goods_returns', 'goods_return_items', 'goods_return_scans'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
    }

    public function test_its_own_file_is_deleted_but_a_shared_report_file_is_kept(): void
    {
        Storage::disk('local')->put('invoices/sendiri.pdf', 'x');
        Storage::disk('local')->put('invoices/laporan.pdf', 'x');
        $own = Invoice::factory()->create(['file_path' => 'invoices/sendiri.pdf']);
        $sharedA = Invoice::factory()->create(['file_path' => 'invoices/laporan.pdf']);
        Invoice::factory()->create(['file_path' => 'invoices/laporan.pdf']);

        $this->remove($own)->assertOk();
        $this->remove($sharedA)->assertOk();

        Storage::disk('local')->assertMissing('invoices/sendiri.pdf');
        Storage::disk('local')->assertExists('invoices/laporan.pdf');
    }

    public function test_the_number_can_be_uploaded_again_after_deleting(): void
    {
        $invoice = ReturnServiceTest::invoiceWithItems('picked');
        $invoice->scanEvents()->create(['stage' => 'pick', 'code' => 'X', 'result' => 'not_in_invoice', 'manual' => false]);
        $this->remove($invoice)->assertOk();

        $this->post('/api/invoices', [
            'file' => UploadedFile::fake()->create('faktur.pdf', 10, 'application/pdf'),
            'payload' => json_encode([
                'number' => $invoice->number, 'customer_name' => 'TOKO A', 'source_type' => 'pdf',
                'items' => [['code' => 'S-SCS-002', 'name' => 'SAKLAR', 'qty' => 1]],
            ]),
        ], ['Accept' => 'application/json'])->assertCreated()->assertJsonPath('data.items.0.qty', 1);
    }
}
