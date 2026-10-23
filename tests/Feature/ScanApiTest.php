<?php

namespace Tests\Feature;

use App\Models\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScanApiTest extends TestCase
{
    use RefreshDatabase;

    private Invoice $invoice;

    protected function setUp(): void
    {
        parent::setUp();
        $this->invoice = Invoice::factory()->create(['number' => '00066365']);
        $this->invoice->items()->createMany(array_map(
            fn ($item, $i) => ['line_no' => $i + 1, 'part_code' => $item['code'], 'part_name' => $item['name'], 'qty' => $item['qty']],
            InvoiceApiTest::sampleItems(),
            array_keys(InvoiceApiTest::sampleItems()),
        ));
    }

    private function scan(string $code, string $stage = 'pick', bool $manual = false)
    {
        return $this->postJson("/api/invoices/{$this->invoice->id}/scans", compact('code', 'stage', 'manual'));
    }

    private function item(string $code)
    {
        return $this->invoice->items()->where('part_code', $code)->first();
    }

    private function scanEverything(string $stage): void
    {
        foreach (InvoiceApiTest::sampleItems() as $item) {
            for ($n = 0; $n < $item['qty']; $n++) {
                $this->scan($item['code'], $stage)->assertJsonPath('data.scan.result', 'ok');
            }
        }
    }

    public function test_matching_code_counts_and_starts_picking(): void
    {
        $this->scan('S-SCS-002')->assertOk()
            ->assertJsonPath('data.scan.result', 'ok')
            ->assertJsonPath('data.invoice.status', 'picking');

        $this->assertSame(1, $this->item('S-SCS-002')->picked_qty);
        $this->assertSame(1, $this->invoice->scanEvents()->count());
    }

    public function test_scanning_a_complete_item_again_is_over_quantity(): void
    {
        $this->scan('S-MCS-005')->assertJsonPath('data.scan.result', 'ok');

        $this->scan('S-MCS-005')->assertOk()
            ->assertJsonPath('data.scan.result', 'over_qty')
            ->assertJsonPath('data.scan.message', 'S-MCS-005 sudah lengkap (1/1)');
        $this->assertSame(1, $this->item('S-MCS-005')->picked_qty);
    }

    public function test_code_is_normalized(): void
    {
        $this->scan(' s-tks-002 ')->assertJsonPath('data.scan.result', 'ok');

        $this->assertSame(1, $this->item('S-TKS-002')->picked_qty);
    }

    public function test_code_with_slash_matches(): void
    {
        $this->scan('H-MTE-009/010')->assertJsonPath('data.scan.result', 'ok');
    }

    public function test_unknown_code_is_not_in_invoice(): void
    {
        $this->scan('H-MTE-005')->assertOk()
            ->assertJsonPath('data.scan.result', 'not_in_invoice')
            ->assertJsonPath('data.scan.message', 'H-MTE-005 tidak ada di faktur')
            ->assertJsonPath('data.invoice.scan_events.0.invoice_item_id', null);
    }

    public function test_manual_flag_is_recorded(): void
    {
        $this->scan('S-SCS-002', 'pick', true);

        $this->assertTrue($this->invoice->scanEvents()->first()->manual);
    }

    public function test_complete_is_rejected_until_everything_is_scanned(): void
    {
        $this->scan('S-SCS-002');

        $this->postJson("/api/invoices/{$this->invoice->id}/complete", ['stage' => 'pick'])
            ->assertStatus(409)->assertJsonPath('error', 'Masih ada barang yang belum lengkap');
    }

    public function test_complete_pick_marks_invoice_picked(): void
    {
        $this->scanEverything('pick');

        $this->postJson("/api/invoices/{$this->invoice->id}/complete", ['stage' => 'pick'])
            ->assertOk()->assertJsonPath('data.status', 'picked');
        $this->assertNotNull($this->invoice->fresh()->picked_at);
    }

    public function test_pick_scan_after_picked_is_rejected(): void
    {
        $this->scanEverything('pick');
        $this->postJson("/api/invoices/{$this->invoice->id}/complete", ['stage' => 'pick']);

        $this->scan('S-SCS-002')->assertStatus(409)->assertJsonPath('error', 'Scan pertama sudah selesai');
    }

    public function test_qc_scan_before_picked_is_rejected(): void
    {
        $this->scan('S-SCS-002', 'qc')->assertStatus(409)->assertJsonPath('error', 'Selesaikan scan pertama dulu');
    }

    public function test_qc_counts_separately_and_completes(): void
    {
        $this->scanEverything('pick');
        $this->postJson("/api/invoices/{$this->invoice->id}/complete", ['stage' => 'pick']);

        $this->scan('S-SCS-002', 'qc')->assertJsonPath('data.invoice.status', 'qc');
        $this->assertSame(1, $this->item('S-SCS-002')->qc_qty);
        $this->assertSame(2, $this->item('S-SCS-002')->picked_qty);

        $this->scan('S-SCS-002', 'qc');
        foreach (array_slice(InvoiceApiTest::sampleItems(), 1) as $item) {
            for ($n = 0; $n < $item['qty']; $n++) {
                $this->scan($item['code'], 'qc');
            }
        }
        $this->postJson("/api/invoices/{$this->invoice->id}/complete", ['stage' => 'qc'])
            ->assertOk()->assertJsonPath('data.status', 'qc_done');
        $this->assertNotNull($this->invoice->fresh()->qc_done_at);
    }

    public function test_invalid_stage_is_rejected(): void
    {
        $this->scan('S-SCS-002', 'abc')->assertStatus(422)->assertJsonPath('success', false);
    }

    public function test_empty_code_is_rejected(): void
    {
        $this->scan('   ')->assertStatus(422)->assertJsonPath('success', false);
    }
}
