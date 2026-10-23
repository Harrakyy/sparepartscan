<?php

namespace Tests\Feature;

use App\Models\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ReportApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // 23 Okt 2026 10:00 WIB = 03:00 UTC.
        Carbon::setTestNow(Carbon::parse('2026-10-23 03:00:00', 'UTC'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function invoice(string $customer, string $status, ?Carbon $pickedAt, array $items): Invoice
    {
        $invoice = Invoice::factory()->create(['customer_name' => $customer, 'status' => $status, 'picked_at' => $pickedAt]);
        foreach ($items as $i => [$code, $qty]) {
            $invoice->items()->create(['line_no' => $i + 1, 'part_code' => $code, 'part_name' => "Barang {$code}", 'qty' => $qty, 'picked_qty' => $qty]);
        }

        return $invoice;
    }

    public function test_lists_only_invoices_picked_today_in_wib(): void
    {
        $this->invoice('AKBAR JAYA MOTOR HSF', 'picked', now(), [['S-SCS-002', 2]]);
        $this->invoice('TOKO SEDANG', 'picking', null, [['S-SCS-002', 1]]);
        $this->invoice('TOKO KEMARIN', 'qc_done', now()->subDay(), [['S-SCS-002', 5]]);
        // 22 Okt 23:30 WIB → masih "kemarin" walaupun tanggal UTC-nya sama.
        $this->invoice('TOKO MALAM', 'picked', Carbon::parse('2026-10-22 16:30:00', 'UTC'), [['S-SCS-002', 7]]);

        $r = $this->getJson('/api/reports/outgoing')->assertOk();

        $r->assertJsonCount(1, 'data.invoices')->assertJsonPath('data.invoices.0.customer_name', 'AKBAR JAYA MOTOR HSF');
    }

    public function test_totals_sum_quantities_across_invoices(): void
    {
        $this->invoice('AKBAR JAYA MOTOR HSF', 'picked', now(), [['S-SCS-002', 2], ['H-KTE-001', 1]]);
        $this->invoice('SUMBER JAYA', 'qc_done', now(), [['S-SCS-002', 3]]);

        $totals = collect($this->getJson('/api/reports/outgoing')->json('data.totals'))->keyBy('part_code');

        $this->assertSame(5, $totals['S-SCS-002']['qty']);
        $this->assertSame(1, $totals['H-KTE-001']['qty']);
    }

    public function test_date_range_and_search(): void
    {
        $this->invoice('AKBAR JAYA MOTOR HSF', 'picked', now(), [['S-SCS-002', 2]]);
        $this->invoice('TOKO KEMARIN', 'picked', now()->subDay(), [['S-SCS-002', 5]]);

        $this->getJson('/api/reports/outgoing?from=2026-10-22&to=2026-10-23')->assertJsonCount(2, 'data.invoices');
        $this->getJson('/api/reports/outgoing?from=2026-10-22&to=2026-10-23&q=akbar')->assertJsonCount(1, 'data.invoices');
    }

    public function test_invalid_date_is_rejected(): void
    {
        $this->getJson('/api/reports/outgoing?from=kemarin')->assertStatus(422)->assertJsonPath('success', false);
    }
}
