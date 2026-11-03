<?php

namespace Tests\Feature;

use App\Services\License\Keys;
use App\Services\License\LicenseFile;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class LicenseIssueTest extends TestCase
{
    private string $keysDir;

    private string $licPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->keysDir = sys_get_temp_dir().'/lic-keys-'.uniqid();
        $this->licPath = sys_get_temp_dir().'/license-'.uniqid().'.lic';
        $keys = new Keys($this->keysDir);
        $keys->generate();
        $this->app->instance(Keys::class, $keys);
        $this->app->instance(LicenseFile::class, new LicenseFile($keys, $this->licPath));
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->keysDir.'/*') ?: []);
        @rmdir($this->keysDir);
        @unlink($this->licPath);
        parent::tearDown();
    }

    private function parsed(): array
    {
        return app(LicenseFile::class)->parse(file_get_contents($this->licPath));
    }

    public function test_buat_writes_a_license_file(): void
    {
        $this->artisan('license:buat', ['machine_id' => 'ABC', '--sampai' => '2026-12-31'])
            ->assertSuccessful();
        $this->assertFileExists($this->licPath);
    }

    public function test_valid_for_the_right_machine_before_expiry(): void
    {
        $this->artisan('license:buat', ['machine_id' => 'ABC', '--sampai' => '2026-12-31']);
        $this->assertTrue(app(LicenseFile::class)->valid($this->parsed(), 'ABC', Carbon::parse('2026-06-01'))['ok']);
    }

    public function test_wrong_machine_is_rejected(): void
    {
        $this->artisan('license:buat', ['machine_id' => 'ABC', '--sampai' => '2026-12-31']);
        $r = app(LicenseFile::class)->valid($this->parsed(), 'XYZ', Carbon::parse('2026-06-01'));
        $this->assertFalse($r['ok']);
        $this->assertSame('machine', $r['reason']);
    }

    public function test_expired_license_is_rejected(): void
    {
        $this->artisan('license:buat', ['machine_id' => 'ABC', '--sampai' => '2026-12-31']);
        $r = app(LicenseFile::class)->valid($this->parsed(), 'ABC', Carbon::parse('2027-01-01'));
        $this->assertFalse($r['ok']);
        $this->assertSame('expired', $r['reason']);
    }

    public function test_tampered_signature_is_rejected(): void
    {
        $this->artisan('license:buat', ['machine_id' => 'ABC', '--sampai' => '2026-12-31']);
        $bad = $this->parsed();
        $bad['signature'] = base64_encode(random_bytes(64));
        $r = app(LicenseFile::class)->valid($bad, 'ABC', Carbon::parse('2026-06-01'));
        $this->assertFalse($r['ok']);
        $this->assertSame('signature', $r['reason']);
    }

    public function test_buat_fails_without_the_secret_key(): void
    {
        unlink($this->keysDir.'/secret.key');
        $this->artisan('license:buat', ['machine_id' => 'ABC', '--sampai' => '2026-12-31'])
            ->assertFailed();
    }
}
