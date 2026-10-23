<?php

namespace Tests\Feature;

use App\Models\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class InvoiceApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public static function sampleItems(): array
    {
        return [
            ['code' => 'S-SCS-002', 'name' => 'SAKLAR SIGN FUTURA 91-18/ T 120 SS 91-18/ KATANA', 'qty' => 2],
            ['code' => 'H-KTE-001', 'name' => 'TIE ROD KIA TIMOR/CARENS/MAZDA (0K9A2-32-280)', 'qty' => 2],
            ['code' => 'S-MCS-005', 'name' => 'SAKLAR SIGN MITS.CANTER/JUNGKIT (MB 302736)', 'qty' => 1],
            ['code' => 'H-MYJ-005', 'name' => 'YOKE JOINT MITSUBISHI PS120/135/110/125/136', 'qty' => 1],
            ['code' => 'H-MTE-009/010', 'name' => 'TIE ROD PS 110 CANTER PS 125', 'qty' => 1],
            ['code' => 'H-SYJ-002', 'name' => 'YOKE JOINT SUZUKI ST100', 'qty' => 1],
            ['code' => 'H-TCB-N13', 'name' => 'CENTER BEARING ASSY SET KIJANG NEW KF 40 (5K)', 'qty' => 1],
            ['code' => 'S-TKS-002', 'name' => 'CLOCK SPRING GRAND NEW AVANZA TYPE G ( 2017 - on ) (84306-BZ200)', 'qty' => 2],
            ['code' => 'S-EFP-EP-T11', 'name' => 'FUEL PUMP ZEBRA S89 86-95 (23100-87515)', 'qty' => 3],
        ];
    }

    private function upload(array $overrides = [], ?UploadedFile $file = null)
    {
        $payload = array_merge([
            'number' => '00066365',
            'customer_name' => 'AKBAR JAYA MOTOR HSF',
            'invoice_date' => 'Thursday, October 22, 2026',
            'source_type' => 'pdf',
            'items' => self::sampleItems(),
        ], $overrides);

        return $this->post('/api/invoices', [
            'file' => $file ?? UploadedFile::fake()->create('faktur.pdf', 100, 'application/pdf'),
            'payload' => json_encode($payload),
        ], ['Accept' => 'application/json']);
    }

    public function test_stores_invoice_with_items_and_file(): void
    {
        $r = $this->upload();

        $r->assertStatus(201)->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'waiting')
            ->assertJsonCount(9, 'data.items');
        $invoice = Invoice::first();
        $this->assertStringStartsWith('invoices/', $invoice->file_path);
        Storage::disk('local')->assertExists($invoice->file_path);
    }

    public function test_normalizes_part_codes(): void
    {
        $this->upload(['items' => [['code' => ' s-tks-002 ', 'name' => 'Clock spring', 'qty' => 2]]])->assertStatus(201);

        $this->assertSame('S-TKS-002', Invoice::first()->items()->first()->part_code);
    }

    public function test_merges_duplicate_codes(): void
    {
        $this->upload(['items' => [
            ['code' => 'S-TKS-002', 'name' => 'Clock spring', 'qty' => 1],
            ['code' => 'S-TKS-002', 'name' => 'Clock spring lagi', 'qty' => 2],
        ]])->assertStatus(201);

        $items = Invoice::first()->items;
        $this->assertCount(1, $items);
        $this->assertSame(3, $items[0]->qty);
        $this->assertSame('Clock spring', $items[0]->part_name);
    }

    public function test_reupload_asks_for_confirmation_before_replacing(): void
    {
        $this->upload();
        $oldPath = Invoice::first()->file_path;

        $r = $this->upload(['customer_name' => 'TOKO LAIN', 'items' => [['code' => 'S-SCS-002', 'name' => 'Saklar', 'qty' => 5]]]);

        $r->assertStatus(409)
            ->assertJsonPath('error', 'Faktur 00066365 (Kepada AKBAR JAYA MOTOR HSF) sudah ada. Ganti dengan faktur ini?')
            ->assertJsonPath('data.needs_confirmation', true);
        $this->assertSame(9, Invoice::first()->items()->count());
        Storage::disk('local')->assertExists($oldPath);
    }

    public function test_confirmed_reupload_replaces_items_and_old_file(): void
    {
        $first = $this->upload()->json('data.id');
        $oldPath = Invoice::first()->file_path;

        $r = $this->upload(['replace' => true, 'items' => [['code' => 'S-SCS-002', 'name' => 'Saklar', 'qty' => 5]]]);

        $r->assertStatus(201)->assertJsonPath('data.id', $first)->assertJsonCount(1, 'data.items');
        $this->assertSame(1, Invoice::count());
        Storage::disk('local')->assertMissing($oldPath);
        Storage::disk('local')->assertExists(Invoice::first()->file_path);
    }

    public function test_detail_contains_all_scan_events(): void
    {
        $id = $this->upload()->json('data.id');
        $invoice = Invoice::find($id);
        for ($i = 0; $i < 35; $i++) {
            $invoice->scanEvents()->create(['stage' => 'pick', 'code' => "X-{$i}", 'result' => 'not_in_invoice', 'manual' => false]);
        }

        $this->getJson("/api/invoices/{$id}")->assertJsonCount(35, 'data.scan_events');
    }

    public function test_expired_session_returns_envelope_with_reload_message(): void
    {
        \Illuminate\Support\Facades\Route::middleware('web')->post('/api/_expired', function () {
            throw new \Illuminate\Session\TokenMismatchException;
        });

        $this->postJson('/api/_expired')->assertStatus(419)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error', 'Sesi halaman kedaluwarsa. Muat ulang halaman (F5) lalu coba lagi.');
    }

    public function test_reupload_after_scan_is_rejected_and_keeps_data(): void
    {
        $id = $this->upload()->json('data.id');
        $invoice = Invoice::find($id);
        $invoice->scanEvents()->create(['stage' => 'pick', 'code' => 'S-SCS-002', 'result' => 'ok', 'manual' => false]);

        $r = $this->upload(['items' => [['code' => 'X-1', 'name' => 'Lain', 'qty' => 1]]]);

        $r->assertStatus(409)->assertJsonPath('success', false)
            ->assertJsonPath('error', 'Faktur 00066365 sudah mulai di-scan, tidak bisa diganti');
        $this->assertSame(9, $invoice->items()->count());
    }

    public function test_rejects_zero_quantity(): void
    {
        $this->upload(['items' => [['code' => 'S-SCS-002', 'name' => 'Saklar', 'qty' => 0]]])
            ->assertStatus(422)->assertJsonPath('success', false);
    }

    public function test_rejects_disallowed_file_type(): void
    {
        $this->upload([], UploadedFile::fake()->create('virus.exe', 10, 'application/x-msdownload'))
            ->assertStatus(422)->assertJsonPath('success', false);
    }

    public function test_rejects_missing_items(): void
    {
        $this->upload(['items' => []])->assertStatus(422)->assertJsonPath('success', false);
    }

    public function test_lists_open_invoices_with_search(): void
    {
        $this->upload();

        $this->getJson('/api/invoices?status=open')->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.total_qty', 14)->assertJsonPath('data.0.picked_total', 0);
        $this->getJson('/api/invoices?status=open&q=akbar')->assertJsonCount(1, 'data');
        $this->getJson('/api/invoices?status=open&q=xyz')->assertJsonCount(0, 'data');
        $this->getJson('/api/invoices?status=done')->assertJsonCount(0, 'data');
    }

    public function test_shows_invoice_detail(): void
    {
        $id = $this->upload()->json('data.id');

        $this->getJson("/api/invoices/{$id}")->assertOk()
            ->assertJsonPath('data.number', '00066365')
            ->assertJsonPath('data.items.4.part_code', 'H-MTE-009/010')
            ->assertJsonPath('data.scan_events', []);
    }

    public function test_unknown_invoice_returns_404_envelope(): void
    {
        $this->getJson('/api/invoices/999')->assertStatus(404)->assertJsonPath('success', false);
    }
}
