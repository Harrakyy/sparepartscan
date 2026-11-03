<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Services\BackupService;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;

class BackupLatestTest extends TestCase
{
    use DatabaseMigrations;

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.edition' => 'offline', 'app.pairing_code' => 'KODE-RAHASIA']);
        $this->dir = sys_get_temp_dir().'/latest-'.uniqid();
        config(['backup.dir' => $this->dir]);

        Invoice::factory()->create();
        app(BackupService::class)->create('harian');
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->dir.'/*/*') ?: []);
        parent::tearDown();
    }

    public function test_correct_code_downloads_the_latest_backup(): void
    {
        $this->get('/backup/latest', ['X-Pairing-Code' => 'KODE-RAHASIA'])
            ->assertOk()
            ->assertHeader('content-disposition', 'attachment; filename=faktur-terbaru.sqlite');
    }

    public function test_wrong_code_is_forbidden(): void
    {
        $this->get('/backup/latest', ['X-Pairing-Code' => 'salah'])->assertForbidden();
        $this->get('/backup/latest')->assertForbidden();
    }

    public function test_endpoint_is_rate_limited(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->get('/backup/latest', ['X-Pairing-Code' => 'KODE-RAHASIA'])->assertOk();
        }
        $this->get('/backup/latest', ['X-Pairing-Code' => 'KODE-RAHASIA'])->assertStatus(429);
    }
}
