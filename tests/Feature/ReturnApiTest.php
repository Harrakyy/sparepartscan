<?php

namespace Tests\Feature;

use App\Models\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReturnApiTest extends TestCase
{
    use RefreshDatabase;

    private Invoice $invoice;

    protected function setUp(): void
    {
        parent::setUp();
        $this->invoice = ReturnServiceTest::invoiceWithItems('picked');
    }

    private function create(?Invoice $invoice = null)
    {
        return $this->postJson('/api/returns', ['invoice_id' => ($invoice ?? $this->invoice)->id]);
    }

    private function scan(int $returnId, string $code, string $condition = 'good')
    {
        return $this->postJson("/api/returns/{$returnId}/scans", ['code' => $code, 'condition' => $condition, 'manual' => false]);
    }

    public function test_full_flow_create_scan_complete_and_mark_zahir(): void
    {
        $id = $this->create()->assertCreated()
            ->assertJsonPath('data.existing', false)
            ->assertJsonPath('data.invoice.number', $this->invoice->number)
            ->json('data.id');

        $this->scan($id, 'S-SCS-002')->assertOk()
            ->assertJsonPath('data.scan.result', 'ok')
            ->assertJsonPath('data.return.items.0.good', 1);
        $this->scan($id, 'H-KTE-001', 'bad')->assertJsonPath('data.scan.result', 'ok');
        $this->patchJson("/api/returns/{$id}", ['note' => 'salah kirim tipe'])->assertJsonPath('data.note', 'salah kirim tipe');

        $this->postJson("/api/returns/{$id}/complete")->assertOk()->assertJsonPath('data.status', 'done');
        $this->getJson('/api/returns?tab=zahir_pending')->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.good_total', 1)
            ->assertJsonPath('data.0.bad_total', 1);

        $this->postJson("/api/returns/{$id}/zahir", ['entered' => true])->assertOk();
        $this->assertNotNull($this->getJson("/api/returns/{$id}")->json('data.zahir_entered_at'));
        $this->getJson('/api/returns?tab=zahir_pending')->assertJsonCount(0, 'data');
        $this->getJson('/api/returns?tab=done')->assertJsonCount(1, 'data');
    }

    public function test_creating_again_returns_existing_open_return(): void
    {
        $id = $this->create()->json('data.id');

        $this->create()->assertOk()
            ->assertJsonPath('data.existing', true)
            ->assertJsonPath('data.id', $id);
    }

    public function test_waiting_invoice_cannot_get_a_return(): void
    {
        $this->create(ReturnServiceTest::invoiceWithItems('waiting'))
            ->assertStatus(409)
            ->assertJsonPath('error', 'Faktur ini belum selesai scan pertama');
    }

    public function test_scan_validation_and_locked_return(): void
    {
        $id = $this->create()->json('data.id');

        $this->scan($id, 'S-SCS-002', 'x')->assertStatus(422);
        $this->scan($id, 'S-SCS-002')->assertOk();
        $this->postJson("/api/returns/{$id}/complete")->assertOk();

        $this->scan($id, 'S-SCS-002')->assertStatus(409)->assertJsonPath('error', 'Retur ini sudah selesai');
    }

    public function test_decrement_corrects_a_wrong_scan(): void
    {
        $id = $this->create()->json('data.id');
        $itemId = $this->scan($id, 'S-SCS-002')->json('data.scan.item_id');

        $this->postJson("/api/returns/{$id}/decrement", ['invoice_item_id' => $itemId, 'condition' => 'good'])
            ->assertOk()
            ->assertJsonPath('data.items.0.good', 0);
        $this->postJson("/api/returns/{$id}/decrement", ['invoice_item_id' => $itemId, 'condition' => 'good'])
            ->assertStatus(409);
    }

    public function test_cancel_empty_return(): void
    {
        $id = $this->create()->json('data.id');
        $this->deleteJson("/api/returns/{$id}")->assertOk()->assertJsonPath('data.deleted', true);
        $this->getJson("/api/returns/{$id}")->assertNotFound();

        $filled = $this->create()->json('data.id');
        $this->scan($filled, 'S-SCS-002');
        $this->deleteJson("/api/returns/{$filled}")->assertStatus(409);
    }

    public function test_invoice_search_only_lists_eligible_invoices(): void
    {
        $waiting = ReturnServiceTest::invoiceWithItems('waiting');
        $openId = $this->create()->json('data.id');

        $ids = collect($this->getJson('/api/returns/invoices')->assertOk()->json('data'))->pluck('id');
        $this->assertTrue($ids->contains($this->invoice->id));
        $this->assertFalse($ids->contains($waiting->id));

        $this->getJson('/api/returns/invoices?q='.$this->invoice->number)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.open_return_id', $openId);
    }
}
