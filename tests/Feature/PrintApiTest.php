<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PrintApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_print_is_rejected_before_first_scan_is_done(): void
    {
        $invoice = ReturnServiceTest::invoiceWithItems('picking');

        $this->postJson("/api/invoices/{$invoice->id}/prints")
            ->assertStatus(409)
            ->assertJsonPath('error', 'Selesaikan scan pertama dulu');
        $this->assertNull($invoice->fresh()->printed_at);
        $this->assertSame(0, $invoice->printLogs()->count());
    }

    public function test_first_print_sets_printed_at_and_reprint_keeps_it(): void
    {
        $invoice = ReturnServiceTest::invoiceWithItems('picked');
        $this->travelTo('2026-10-27 03:00:00');

        $first = $this->postJson("/api/invoices/{$invoice->id}/prints")->assertOk()
            ->assertJsonCount(1, 'data.print_logs')
            ->json('data.printed_at');
        $this->assertNotNull($first);

        $this->travelTo('2026-10-27 05:00:00');
        $this->postJson("/api/invoices/{$invoice->id}/prints")->assertOk()
            ->assertJsonPath('data.printed_at', $first)
            ->assertJsonCount(2, 'data.print_logs')
            ->assertJsonPath('data.print_logs.0.created_at', now()->toJSON());

        $this->getJson("/api/invoices/{$invoice->id}")->assertJsonCount(2, 'data.print_logs');
    }
}
