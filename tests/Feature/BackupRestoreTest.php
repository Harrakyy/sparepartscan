<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\User;
use App\Services\BackupService;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;

class BackupRestoreTest extends TestCase
{
    use DatabaseMigrations;

    private BackupService $backup;

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir().'/restore-test-'.uniqid();
        config(['backup.dir' => $this->dir]);
        $this->backup = app(BackupService::class);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->dir.'/*/*') ?: []);
        parent::tearDown();
    }

    public function test_restoring_brings_the_data_back_and_keeps_a_safety_backup(): void
    {
        Invoice::factory()->create(['number' => '00000001']);
        $file = basename($this->backup->create('harian'));

        Invoice::factory()->create(['number' => '00000002']);
        $this->assertSame(2, Invoice::count());

        $this->backup->restore($file);

        $this->assertSame(1, Invoice::count());
        $this->assertSame('00000001', Invoice::first()->number);
        $this->assertNotEmpty(glob($this->dir.'/sebelum-pulihkan/*.sqlite'));
    }

    public function test_restore_works_after_a_new_column_is_added(): void
    {
        Invoice::factory()->create(['number' => '00000001']);
        $file = basename($this->backup->create('harian'));

        // App di-update setelah backup dibuat: migrasi menambah kolom baru ke tabel yang sama.
        \Illuminate\Support\Facades\DB::statement('ALTER TABLE invoices ADD COLUMN catatan_baru TEXT');
        Invoice::factory()->create(['number' => '00000002']);

        $this->backup->restore($file);

        $this->assertSame(1, Invoice::count());
        $this->assertSame('00000001', Invoice::first()->number);
    }

    public function test_restore_endpoint_needs_the_confirmation_word(): void
    {
        config(['app.edition' => 'offline']);
        $file = basename($this->backup->create('harian'));

        $this->postJson('/api/backups/restore', ['file' => $file, 'confirm' => 'salah'])
            ->assertStatus(422)->assertJsonPath('error', 'Ketik PULIHKAN untuk konfirmasi');
        $this->postJson('/api/backups/restore', ['file' => $file, 'confirm' => 'PULIHKAN'])
            ->assertOk()->assertJsonPath('data.restored', true);
    }

    public function test_admin_cannot_restore(): void
    {
        config(['app.edition' => 'offline']);
        $file = basename($this->backup->create('harian'));
        $this->actingAs(User::factory()->admin()->create());

        $this->postJson('/api/backups/restore', ['file' => $file, 'confirm' => 'PULIHKAN'])->assertStatus(403);
    }

    public function test_an_unknown_backup_file_is_rejected(): void
    {
        config(['app.edition' => 'offline']);

        $this->postJson('/api/backups/restore', ['file' => 'tidak-ada.sqlite', 'confirm' => 'PULIHKAN'])
            ->assertStatus(422)->assertJsonPath('error', 'Backup tidak ditemukan');
    }
}
