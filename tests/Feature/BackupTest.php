<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Services\BackupService;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;

class BackupTest extends TestCase
{
    // VACUUM INTO tak bisa jalan di dalam transaksi pembungkus RefreshDatabase; migrasi fresh tanpa transaksi.
    use DatabaseMigrations;

    private BackupService $backup;

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir().'/backup-test-'.uniqid();
        config(['backup.dir' => $this->dir]);
        $this->backup = app(BackupService::class);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->dir)) {
            array_map('unlink', glob($this->dir.'/*/*') ?: []);
        }
        parent::tearDown();
    }

    private function dailyFiles(): array
    {
        return glob($this->dir.'/harian/*.sqlite') ?: [];
    }

    public function test_a_backup_is_a_valid_sqlite_file(): void
    {
        $path = $this->backup->create('harian');

        $this->assertNotNull($path);
        $pdo = new \PDO('sqlite:'.$path);
        $this->assertSame('ok', $pdo->query('PRAGMA integrity_check')->fetchColumn());
    }

    public function test_an_unchanged_database_is_not_backed_up_twice(): void
    {
        $this->backup->create('harian');

        $this->assertNull($this->backup->create('harian'));
        $this->assertCount(1, $this->dailyFiles());
    }

    public function test_a_change_produces_a_new_backup(): void
    {
        $this->backup->create('harian');
        $this->travel(1)->hours();
        Invoice::factory()->create();

        $this->assertNotNull($this->backup->create('harian'));
        $this->assertCount(2, $this->dailyFiles());
    }

    public function test_hourly_backups_keep_only_the_newest_48(): void
    {
        for ($i = 0; $i < 49; $i++) {
            $this->travel(1)->hours();
            Invoice::factory()->create();
            $this->backup->create('perjam');
        }

        $this->assertCount(48, glob($this->dir.'/perjam/*.sqlite') ?: []);
    }

    public function test_list_reports_each_backup_with_its_invoice_count(): void
    {
        Invoice::factory()->count(3)->create();
        $this->backup->create('harian');

        $row = collect($this->backup->list())->firstWhere('jenis', 'harian');
        $this->assertSame(3, $row['jumlah_faktur']);
        $this->assertGreaterThan(0, $row['ukuran']);
    }
}
