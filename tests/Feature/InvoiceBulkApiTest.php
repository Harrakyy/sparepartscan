<?php

namespace Tests\Feature;

use App\Models\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Satu PDF laporan "Penjualan - Rincian" → satu sesi scan per No. Reference. */
class InvoiceBulkApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private static function invoice(string $number, string $customer, array $items): array
    {
        return [
            'number' => $number,
            'customer_name' => $customer,
            'invoice_date' => '27/10/2026',
            'items' => array_map(fn ($i) => ['code' => $i[0], 'name' => "Barang {$i[0]}", 'qty' => $i[1]], $items),
        ];
    }

    private function upload(array $invoices)
    {
        return $this->post('/api/invoices/bulk', [
            'file' => UploadedFile::fake()->create('penjualan-rincian.pdf', 50, 'application/pdf'),
            'payload' => json_encode(['invoices' => $invoices]),
        ], ['Accept' => 'application/json']);
    }

    public function test_creates_one_session_per_invoice_sharing_the_report_file(): void
    {
        $r = $this->upload([
            self::invoice('00066660', 'YSR AUTOPART', [['F-DBA-B20', 2], ['H-DBJ-001', 2]]),
            self::invoice('00066718', 'HERI JAYA MOTOR HSF', [['P-SRD-LC40', 2], ['P-TRD-LC37', 1], ['P-TRD-LC37', 2]]),
        ])->assertCreated();

        $r->assertJsonPath('data.skipped', [])->assertJsonCount(2, 'data.created');
        $invoices = Invoice::with('items')->orderBy('number')->get();
        $this->assertSame(['00066660', '00066718'], $invoices->pluck('number')->all());
        $this->assertSame(['waiting', 'waiting'], $invoices->pluck('status')->all());
        $this->assertSame(['P-SRD-LC40' => 2, 'P-TRD-LC37' => 3], $invoices[1]->items->pluck('qty', 'part_code')->all());
        $this->assertSame($invoices[0]->file_path, $invoices[1]->file_path);
        Storage::disk('local')->assertExists($invoices[0]->file_path);
    }

    public function test_numbers_already_in_the_app_are_skipped_not_replaced(): void
    {
        Invoice::factory()->create(['number' => '00066660', 'customer_name' => 'LAMA']);

        $this->upload([
            self::invoice('00066660', 'BARU', [['F-DBA-B20', 2]]),
            self::invoice('00066661', 'ASEAN MOTOR HSF', [['F-NSM-008', 2]]),
        ])->assertCreated()
            ->assertJsonPath('data.skipped', ['00066660'])
            ->assertJsonPath('data.created.0.number', '00066661');

        $this->assertSame('LAMA', Invoice::where('number', '00066660')->value('customer_name'));
        $this->assertSame(2, Invoice::count());
    }

    public function test_when_every_invoice_is_skipped_the_file_is_not_kept(): void
    {
        Invoice::factory()->create(['number' => '00066660']);

        $this->upload([self::invoice('00066660', 'X', [['F-DBA-B20', 1]])])
            ->assertCreated()
            ->assertJsonPath('data.created', [])
            ->assertJsonPath('data.skipped', ['00066660']);

        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_invalid_invoices_are_rejected(): void
    {
        $this->upload([self::invoice('ABC', 'X', [['F-DBA-B20', 1]])])->assertStatus(422);
        $this->upload([self::invoice('00066660', 'X', [])])->assertStatus(422);
        $this->upload([])->assertStatus(422);
        $this->assertSame(0, Invoice::count());
    }

    public function test_existing_endpoint_lists_numbers_already_in_the_app(): void
    {
        Invoice::factory()->create(['number' => '00066660']);
        Invoice::factory()->create(['number' => '00066662']);

        $this->getJson('/api/invoices/existing?numbers=00066660,00066661,00066662')
            ->assertOk()
            ->assertJsonPath('data', ['00066660', '00066662']);
    }

    public function test_replacing_one_report_invoice_keeps_the_shared_file(): void
    {
        $this->upload([
            self::invoice('00066660', 'A', [['F-DBA-B20', 1]]),
            self::invoice('00066661', 'B', [['F-NSM-008', 1]]),
        ])->assertCreated();
        $shared = Invoice::where('number', '00066661')->value('file_path');

        $this->post('/api/invoices', [
            'file' => UploadedFile::fake()->create('faktur.pdf', 10, 'application/pdf'),
            'payload' => json_encode([
                'number' => '00066660', 'customer_name' => 'A2', 'source_type' => 'pdf', 'replace' => true,
                'items' => [['code' => 'F-DBA-B20', 'name' => 'X', 'qty' => 1]],
            ]),
        ], ['Accept' => 'application/json'])->assertCreated();

        Storage::disk('local')->assertExists($shared);
    }
}
