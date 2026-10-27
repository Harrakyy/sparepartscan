<?php

namespace Tests\Feature;

use App\Exceptions\DomainRuleException;
use App\Models\GoodsReturn;
use App\Models\Invoice;
use App\Services\ReturnService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReturnServiceTest extends TestCase
{
    use RefreshDatabase;

    private ReturnService $returns;

    private Invoice $invoice;

    protected function setUp(): void
    {
        parent::setUp();
        $this->returns = app(ReturnService::class);
        $this->invoice = self::invoiceWithItems('picked');
    }

    public static function invoiceWithItems(string $status): Invoice
    {
        $invoice = Invoice::factory()->create(['status' => $status]);
        $invoice->items()->createMany(array_map(
            fn ($item, $i) => ['line_no' => $i + 1, 'part_code' => $item['code'], 'part_name' => $item['name'], 'qty' => $item['qty']],
            InvoiceApiTest::sampleItems(),
            array_keys(InvoiceApiTest::sampleItems()),
        ));

        return $invoice;
    }

    private function openReturn(?Invoice $invoice = null): GoodsReturn
    {
        return $this->returns->open($invoice ?? $this->invoice)['return'];
    }

    private function line(GoodsReturn $return, string $code): array
    {
        return collect($this->returns->detail($return)['items'])->firstWhere('part_code', $code);
    }

    private function assertRule(string $message, callable $action): void
    {
        try {
            $action();
            $this->fail("Expected DomainRuleException: {$message}");
        } catch (DomainRuleException $e) {
            $this->assertSame($message, $e->getMessage());
        }
    }

    public function test_open_requires_finished_first_scan(): void
    {
        $waiting = self::invoiceWithItems('waiting');

        $this->assertRule('Faktur ini belum selesai scan pertama', fn () => $this->returns->open($waiting));
    }

    public function test_open_returns_existing_open_return(): void
    {
        $first = $this->returns->open($this->invoice);
        $again = $this->returns->open($this->invoice);

        $this->assertFalse($first['existing']);
        $this->assertTrue($again['existing']);
        $this->assertSame($first['return']->id, $again['return']->id);

        $this->returns->scan($first['return'], 'S-SCS-002', 'good', false);
        $this->returns->complete($first['return']);

        $next = $this->returns->open($this->invoice);
        $this->assertFalse($next['existing']);
        $this->assertNotSame($first['return']->id, $next['return']->id);
    }

    public function test_scan_counts_by_condition(): void
    {
        $return = $this->openReturn();

        $this->assertSame('ok', $this->returns->scan($return, 'S-SCS-002', 'good', false)['result']);
        $this->assertSame('ok', $this->returns->scan($return, 'S-SCS-002', 'good', false)['result']);
        $this->assertSame('over_qty', $this->returns->scan($return, 'S-SCS-002', 'bad', false)['result']);

        $line = $this->line($return, 'S-SCS-002');
        $this->assertSame([2, 2, 0, 0, 0], [$line['shipped'], $line['good'], $line['bad'], $line['returned_before'], $line['remaining']]);
    }

    public function test_returns_never_exceed_invoice_qty_across_returns(): void
    {
        $first = $this->openReturn();
        $this->returns->scan($first, 'S-SCS-002', 'good', false);
        $this->returns->complete($first);

        $second = $this->openReturn();
        $this->assertSame('ok', $this->returns->scan($second, 'S-SCS-002', 'bad', false)['result']);
        $over = $this->returns->scan($second, 'S-SCS-002', 'bad', false);

        $this->assertSame('over_qty', $over['result']);
        $this->assertSame('S-SCS-002 sudah diretur semua (2/2)', $over['message']);
        $line = $this->line($second, 'S-SCS-002');
        $this->assertSame([1, 1, 0], [$line['returned_before'], $line['bad'], $line['remaining']]);
    }

    public function test_unknown_code_is_not_in_invoice_and_logged(): void
    {
        $return = $this->openReturn();

        $scan = $this->returns->scan($return, 'X-NOPE-1', 'good', true);

        $this->assertSame(['result' => 'not_in_invoice', 'message' => 'X-NOPE-1 tidak ada di faktur', 'item_id' => null], $scan);
        $this->assertDatabaseHas('goods_return_scans', ['goods_return_id' => $return->id, 'code' => 'X-NOPE-1', 'invoice_item_id' => null, 'manual' => true]);
        $this->assertSame(1, $return->scans()->count());
    }

    public function test_code_is_normalised(): void
    {
        $this->assertSame('ok', $this->returns->scan($this->openReturn(), ' s-scs-002 ', 'good', false)['result']);
    }

    public function test_decrement_below_zero_is_rejected(): void
    {
        $return = $this->openReturn();
        $itemId = $this->invoice->items()->where('part_code', 'S-SCS-002')->value('id');

        $this->assertRule('Tidak ada yang bisa dikurangi', fn () => $this->returns->decrement($return, $itemId, 'good'));

        $this->returns->scan($return, 'S-SCS-002', 'good', false);
        $this->returns->decrement($return, $itemId, 'good');

        $this->assertSame(0, $return->items()->count());
        $this->assertSame(2, $this->line($return, 'S-SCS-002')['remaining']);
    }

    public function test_done_return_is_locked(): void
    {
        $return = $this->openReturn();
        $this->returns->scan($return, 'S-SCS-002', 'good', false);
        $this->returns->complete($return);
        $itemId = $this->invoice->items()->where('part_code', 'S-SCS-002')->value('id');

        $this->assertRule('Retur ini sudah selesai', fn () => $this->returns->scan($return, 'S-SCS-002', 'good', false));
        $this->assertRule('Retur ini sudah selesai', fn () => $this->returns->decrement($return, $itemId, 'good'));
        $this->assertRule('Retur ini sudah selesai', fn () => $this->returns->updateNote($return, 'x'));
        $this->assertSame('done', $return->fresh()->status);
        $this->assertNotNull($return->fresh()->done_at);
    }

    public function test_complete_requires_an_item(): void
    {
        $this->assertRule('Belum ada barang yang diretur', fn () => $this->returns->complete($this->openReturn()));
    }

    public function test_cancel_only_empty_open_return(): void
    {
        $empty = $this->openReturn();
        $this->returns->cancel($empty);
        $this->assertModelMissing($empty);

        $filled = $this->openReturn();
        $this->returns->scan($filled, 'S-SCS-002', 'good', false);
        $this->assertRule('Retur yang sudah berisi barang tidak bisa dibatalkan', fn () => $this->returns->cancel($filled));
    }

    public function test_note_is_saved_while_open(): void
    {
        $return = $this->openReturn();

        $this->returns->updateNote($return, '  salah kirim tipe  ');
        $this->assertSame('salah kirim tipe', $return->fresh()->note);

        $this->returns->updateNote($return, '   ');
        $this->assertNull($return->fresh()->note);
    }

    public function test_zahir_flag_only_for_done_and_can_be_cleared(): void
    {
        $return = $this->openReturn();
        $this->assertRule('Selesaikan retur dulu', fn () => $this->returns->setZahirEntered($return, true));

        $this->returns->scan($return, 'S-SCS-002', 'good', false);
        $this->returns->complete($return);
        $this->returns->setZahirEntered($return, true);
        $this->assertNotNull($return->fresh()->zahir_entered_at);

        $this->returns->setZahirEntered($return, false);
        $this->assertNull($return->fresh()->zahir_entered_at);
    }

    public function test_list_tabs_and_eligible_invoices(): void
    {
        $open = $this->openReturn();

        $done = $this->openReturn(self::invoiceWithItems('qc_done'));
        $this->returns->scan($done, 'S-SCS-002', 'good', false);
        $this->returns->scan($done, 'H-KTE-001', 'bad', false);
        $this->returns->complete($done);

        $entered = $this->openReturn(self::invoiceWithItems('qc'));
        $this->returns->scan($entered, 'S-SCS-002', 'good', false);
        $this->returns->complete($entered);
        $this->returns->setZahirEntered($entered, true);

        $this->assertSame([$open->id], $this->returns->list('open')->pluck('id')->all());
        $this->assertSame([$entered->id, $done->id], $this->returns->list('done')->pluck('id')->all());
        $this->assertSame([$done->id], $this->returns->list('zahir_pending')->pluck('id')->all());
        $row = $this->returns->list('zahir_pending')->first();
        $this->assertSame([1, 1], [$row['good_total'], $row['bad_total']]);

        $waiting = self::invoiceWithItems('waiting');
        $eligible = $this->returns->eligibleInvoices('');
        $this->assertNotContains($waiting->id, $eligible->pluck('id')->all());
        $this->assertSame($open->id, $eligible->firstWhere('id', $this->invoice->id)['open_return_id']);
        $this->assertSame([$this->invoice->id], $this->returns->eligibleInvoices($this->invoice->number)->pluck('id')->all());
    }
}
