<?php

namespace Tests\Feature;

use App\Services\License\Keys;
use App\Services\License\LicenseFile;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class LicenseFormTest extends TestCase
{
    private string $dir;

    private Keys $keys;

    private const ID = '51d87273c2f05e00f7e797cc8eef3f4748e39a6dff3a2ef81b9b8c91cff1e261';

    protected function setUp(): void
    {
        parent::setUp();
        require_once base_path('tools/buat-lisensi/LicenseForm.php');
        $this->dir = sys_get_temp_dir().'/form-lisensi-'.uniqid();
        $this->keys = new Keys($this->dir);
        $this->keys->generate();
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->dir.'/*') ?: []);
        @rmdir($this->dir);
        parent::tearDown();
    }

    private function history(): string
    {
        return $this->dir.'/riwayat-lisensi.csv';
    }

    public function test_form_issues_a_forever_license_and_records_it(): void
    {
        $result = \LicenseForm::handle(['id' => strtoupper(self::ID).' ', 'untuk' => 'PT Mitra — komputer master'], $this->keys, $this->history());

        $this->assertSame('lisensi PT Mitra - komputer master.lic', $result['filename']);
        $file = new LicenseFile($this->keys);
        $parsed = $file->parse($result['content']);
        $this->assertSame('', $parsed['payload']['expires_at']);
        $this->assertTrue($file->valid($parsed, self::ID, Carbon::now()->addYears(50))['ok']);
        $this->assertStringContainsString(self::ID, file_get_contents($this->history()));
    }

    public function test_bad_input_is_rejected(): void
    {
        $this->expectExceptionMessage('ID komputer harus 64 karakter 0-9/a-f');
        \LicenseForm::handle(['id' => '123', 'untuk' => 'Uji'], $this->keys, $this->history());
    }

    public function test_description_is_required(): void
    {
        $this->expectExceptionMessage('Isi keterangan lisensi');
        \LicenseForm::handle(['id' => self::ID, 'untuk' => '  '], $this->keys, $this->history());
    }

    public function test_without_the_secret_key_it_refuses(): void
    {
        unlink($this->dir.'/secret.key');
        $this->expectExceptionMessage('Kunci rahasia tidak ada di laptop ini');
        \LicenseForm::handle(['id' => self::ID, 'untuk' => 'Uji'], $this->keys, $this->history());
    }
}
