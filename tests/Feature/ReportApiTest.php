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

    /** Barang keluar = faktur yang sudah dicetak; `$printedAt` null = selesai di-scan tapi belum dicetak. */
    private function invoice(string $customer, string $status, ?Carbon $printedAt, array $items): Invoice
    {
        $invoice = Invoice::factory()->create([
            'customer_name' => $customer,
            'status' => $status,
            'picked_at' => in_array($status, Invoice::DONE_STATUSES, true) ? ($printedAt ?? now())->copy()->subHour() : null,
            'printed_at' => $printedAt,
        ]);
        foreach ($items as $i => [$code, $qty]) {
            $invoice->items()->create(['line_no' => $i + 1, 'part_code' => $code, 'part_name' => "Barang {$code}", 'qty' => $qty, 'picked_qty' => $qty]);
        }

        return $invoice;
    }

    public function test_lists_only_invoices_printed_today_in_wib(): void
    {
        $this->invoice('AKBAR JAYA MOTOR HSF', 'picked', now(), [['S-SCS-002', 2]]);
        $this->invoice('TOKO SEDANG', 'picking', null, [['S-SCS-002', 1]]);
        $this->invoice('TOKO BELUM CETAK', 'picked', null, [['S-SCS-002', 3]]);
        $this->invoice('TOKO KEMARIN', 'qc_done', now()->subDay(), [['S-SCS-002', 5]]);
        // 22 Okt 23:30 WIB → masih "kemarin" walaupun tanggal UTC-nya sama.
        $this->invoice('TOKO MALAM', 'picked', Carbon::parse('2026-10-22 16:30:00', 'UTC'), [['S-SCS-002', 7]]);

        $r = $this->getJson('/api/reports/outgoing')->assertOk();

        $r->assertJsonCount(1, 'data.invoices')
            ->assertJsonPath('data.invoices.0.customer_name', 'AKBAR JAYA MOTOR HSF')
            ->assertJsonPath('data.invoices.0.printed_at', now()->toJSON());
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

    public function test_outgoing_days_lists_wib_dates_with_printed_invoices(): void
    {
        $this->invoice('A', 'picked', now(), [['S-SCS-002', 1]]);
        $this->invoice('B', 'qc_done', now()->addHour(), [['S-SCS-002', 1]]);
        $this->invoice('MALAM', 'picked', Carbon::parse('2026-10-22 16:30:00', 'UTC'), [['S-SCS-002', 1]]);
        $this->invoice('BELUM CETAK', 'picked', null, [['S-SCS-002', 1]]);
        $this->invoice('BULAN LALU', 'picked', Carbon::parse('2026-09-15 03:00:00', 'UTC'), [['S-SCS-002', 1]]);

        $this->getJson('/api/reports/outgoing-days?from=2026-10-01&to=2026-10-31')->assertOk()
            ->assertExactJson(['success' => true, 'data' => ['2026-10-22', '2026-10-23'], 'error' => null]);
    }

    public function test_invalid_date_is_rejected(): void
    {
        $this->getJson('/api/reports/outgoing?from=kemarin')->assertStatus(422)->assertJsonPath('success', false);
        $this->getJson('/api/reports/outgoing-days?from=kemarin&to=2026-10-31')->assertStatus(422);
    }
}
