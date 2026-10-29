<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/** Jumlah keluar dikurangi setelah retur, supaya sama dengan Zahir (contoh: keluar 2, retur 1 → 1). */
class QtyAdjustTest extends TestCase
{
    use RefreshDatabase;

    private Invoice $invoice;

    protected function setUp(): void
    {
        parent::setUp();
        $this->invoice = ReturnServiceTest::invoiceWithItems('picked');
        $this->invoice->update(['picked_at' => now(), 'printed_at' => now()]);
    }

    private function itemId(string $code): int
    {
        return $this->invoice->items()->where('part_code', $code)->value('id');
    }

    private function adjust(array $items, ?string $note = 'retur 1 pcs')
    {
        return $this->postJson("/api/invoices/{$this->invoice->id}/adjust", [
            'note' => $note,
            'items' => array_map(fn ($code, $qty) => ['invoice_item_id' => $this->itemId($code), 'qty' => $qty], array_keys($items), $items),
        ]);
    }

    public function test_admin_cannot_adjust(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $this->adjust(['S-SCS-002' => 1])->assertStatus(403);
        $this->assertNull($this->invoice->items()->where('part_code', 'S-SCS-002')->value('out_qty'));
    }

    public function test_not_allowed_before_the_first_scan_is_finished(): void
    {
        $this->invoice->update(['status' => 'picking', 'picked_at' => null]);

        $this->adjust(['S-SCS-002' => 1])->assertStatus(409)->assertJsonPath('error', 'Selesaikan scan pertama dulu');
    }

    public function test_reducing_records_who_changed_what_and_why(): void
    {
        $r = $this->adjust(['S-SCS-002' => 1, 'H-KTE-001' => 2])->assertOk();

        $item = collect($r->json('data.items'))->firstWhere('part_code', 'S-SCS-002');
        $this->assertSame([2, 1], [$item['qty'], $item['out_qty']]);
        $r->assertJsonCount(1, 'data.adjustments')
            ->assertJsonPath('data.adjustments.0.part_code', 'S-SCS-002')
            ->assertJsonPath('data.adjustments.0.from_qty', 2)
            ->assertJsonPath('data.adjustments.0.to_qty', 1)
            ->assertJsonPath('data.adjustments.0.note', 'retur 1 pcs')
            ->assertJsonPath('data.adjustments.0.user_name', Auth::user()->name);
    }

    public function test_can_be_edited_again_and_the_history_keeps_every_step(): void
    {
        $this->adjust(['S-SCS-002' => 1])->assertOk();
        $this->adjust(['S-SCS-002' => 0], 'retur sisa')->assertOk()
            ->assertJsonCount(2, 'data.adjustments')
            ->assertJsonPath('data.adjustments.0.from_qty', 1)
            ->assertJsonPath('data.adjustments.0.to_qty', 0);
    }

    public function test_only_reductions_within_the_invoice_quantity_are_allowed(): void
    {
        $this->adjust(['S-SCS-002' => 3])->assertStatus(422)->assertJsonPath('error', 'Jumlah keluar S-SCS-002 harus 0 sampai 2');
        $this->adjust(['S-SCS-002' => -1])->assertStatus(422);
        $this->assertDatabaseCount('qty_adjustments', 0);
    }

    public function test_a_reason_and_a_real_change_are_required(): void
    {
        $this->adjust(['S-SCS-002' => 1], '')->assertStatus(422)->assertJsonPath('error', 'Alasan wajib diisi');
        $this->adjust(['S-SCS-002' => 2])->assertStatus(422)->assertJsonPath('error', 'Tidak ada jumlah yang berubah');
    }

    public function test_items_of_another_invoice_are_rejected(): void
    {
        $other = ReturnServiceTest::invoiceWithItems('picked');

        $this->postJson("/api/invoices/{$this->invoice->id}/adjust", [
            'note' => 'x',
            'items' => [['invoice_item_id' => $other->items()->value('id'), 'qty' => 0]],
        ])->assertStatus(422)->assertJsonPath('error', 'Barang tidak dikenal');
    }

    public function test_the_outgoing_report_uses_the_adjusted_quantity(): void
    {
        $this->invoice->update(['printed_at' => Carbon::parse('2026-10-23 03:00:00', 'UTC')]);
        $this->adjust(['S-SCS-002' => 1, 'S-EFP-EP-T11' => 0])->assertOk();

        $r = $this->getJson('/api/reports/outgoing?from=2026-10-23&to=2026-10-23')->assertOk();

        $items = collect($r->json('data.invoices.0.items'))->pluck('qty', 'part_code');
        $this->assertSame(1, $items['S-SCS-002']);
        $this->assertSame(0, $items['S-EFP-EP-T11']);
        $this->assertSame(1, collect($r->json('data.totals'))->firstWhere('part_code', 'S-SCS-002')['qty']);
    }
}
