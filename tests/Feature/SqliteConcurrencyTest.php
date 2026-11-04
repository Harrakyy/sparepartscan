<?php

namespace Tests\Feature;

use App\Models\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** SQLite siap produksi: WAL + busy_timeout supaya dua titik scan menulis bersamaan tanpa galat. */
class SqliteConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_file_sqlite_connection_uses_wal_and_busy_timeout(): void
    {
        // WAL tak berlaku untuk :memory:; pakai file sementara untuk memeriksanya.
        $file = tempnam(sys_get_temp_dir(), 'sqlite').'.sqlite';
        touch($file);
        config(['database.connections.sqlite_file' => [
            'driver' => 'sqlite', 'database' => $file, 'prefix' => '', 'foreign_key_constraints' => true,
        ]]);

        try {
            $this->assertSame('wal', strtolower(DB::connection('sqlite_file')->select('PRAGMA journal_mode')[0]->journal_mode));
            $this->assertSame(5000, (int) DB::connection('sqlite_file')->select('PRAGMA busy_timeout')[0]->timeout);
            $this->assertSame(1, (int) DB::connection('sqlite_file')->select('PRAGMA foreign_keys')[0]->foreign_keys);
        } finally {
            DB::disconnect('sqlite_file');
            @unlink($file);
        }
    }

    public function test_two_fast_scans_on_a_single_item_give_one_ok_and_one_over(): void
    {
        $this->assertSame(5000, (int) DB::select('PRAGMA busy_timeout')[0]->timeout);

        $invoice = Invoice::factory()->create(['number' => '00099001', 'status' => 'waiting']);
        $invoice->items()->create(['line_no' => 1, 'part_code' => 'S-SCS-002', 'part_name' => 'SAKLAR', 'qty' => 1]);

        $this->postJson("/api/invoices/{$invoice->id}/scans", ['code' => 'S-SCS-002', 'stage' => 'pick', 'manual' => false])
            ->assertJsonPath('data.scan.result', 'ok');
        $this->postJson("/api/invoices/{$invoice->id}/scans", ['code' => 'S-SCS-002', 'stage' => 'pick', 'manual' => false])
            ->assertJsonPath('data.scan.result', 'over_qty');

        $this->assertSame(1, $invoice->items()->first()->picked_qty);
    }

    public function test_write_transactions_take_the_write_lock_up_front(): void
    {
        // Transaksi DEFERRED membaca dulu lalu minta kunci tulis; bila titik scan lain sudah menulis di
        // antaranya, SQLite langsung "database is locked" tanpa menunggu busy_timeout (terbukti di uji
        // beban 2 x 60 scan). IMMEDIATE mengambil kunci di awal, jadi permintaan kedua mengantre.
        $this->assertSame('IMMEDIATE', config('database.connections.sqlite.transaction_mode'));
    }
}
