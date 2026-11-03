<?php

namespace Tests\Feature;

use App\Contracts\RemovableDisks;
use App\Models\Invoice;
use App\Services\BackupService;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;

class BackupDiskTest extends TestCase
{
    use DatabaseMigrations;

    private BackupService $backup;

    private string $dir;

    private string $drive;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.edition' => 'offline']);
        $this->dir = sys_get_temp_dir().'/disk-src-'.uniqid();
        $this->drive = sys_get_temp_dir().'/disk-dst-'.uniqid();
        config(['backup.dir' => $this->dir]);
        mkdir($this->drive, 0775, true);
        $this->backup = app(BackupService::class);
    }

    protected function tearDown(): void
    {
        foreach ([$this->dir, $this->drive] as $d) {
            array_map('unlink', glob($d.'/**/*', GLOB_BRACE) ?: []);
            array_map('unlink', glob($d.'/*/*') ?: []);
        }
        parent::tearDown();
    }

    private function targetDir(): string
    {
        return $this->drive.'/VerifikasiFaktur-Backup';
    }

    public function test_copy_to_disk_copies_recent_backups(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->travel(1)->hours();
            Invoice::factory()->create();
            $this->backup->create('harian');
        }

        $copied = $this->backup->copyToDisk($this->drive);

        $this->assertLessThanOrEqual(8, count($copied));
        $this->assertNotEmpty(glob($this->targetDir().'/*.sqlite'));
    }

    public function test_copy_to_disk_removes_target_files_older_than_seven_days(): void
    {
        mkdir($this->targetDir(), 0775, true);
        $old = $this->targetDir().'/faktur-20200101-0000.sqlite';
        file_put_contents($old, 'x');
        touch($old, now()->subDays(8)->timestamp);

        Invoice::factory()->create();
        $this->backup->create('harian');
        $this->backup->copyToDisk($this->drive);

        $this->assertFileDoesNotExist($old);
    }

    public function test_to_disk_endpoint_needs_a_disk(): void
    {
        $this->postJson('/api/backups/to-disk', ['drive' => ''])
            ->assertStatus(422)->assertJsonPath('error', 'Tidak ada flashdisk');
    }

    public function test_disks_endpoint_lists_removable_disks(): void
    {
        $this->app->bind(RemovableDisks::class, fn () => new class implements RemovableDisks
        {
            public function list(): array
            {
                return [['drive' => 'E:', 'label' => 'SANDISK', 'free' => 32000000000]];
            }
        });

        $this->getJson('/api/backups/disks')->assertOk()->assertJsonPath('data.0.label', 'SANDISK');
    }
}
