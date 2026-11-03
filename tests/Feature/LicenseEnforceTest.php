<?php

namespace Tests\Feature;

use App\Contracts\HardwareFingerprint;
use App\Services\License\Keys;
use App\Services\License\LicenseFile;
use App\Services\License\MachineId;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;

class LicenseEnforceTest extends TestCase
{
    use DatabaseMigrations;

    private string $keysDir;

    private string $licPath;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.edition' => 'offline', 'license.enforce' => true]);
        $this->keysDir = sys_get_temp_dir().'/enf-keys-'.uniqid();
        $this->licPath = sys_get_temp_dir().'/enf-'.uniqid().'.lic';

        $keys = new Keys($this->keysDir);
        $keys->generate();
        $this->app->instance(Keys::class, $keys);
        $this->app->instance(LicenseFile::class, new LicenseFile($keys, $this->licPath));
        $this->app->instance(MachineId::class, new MachineId(new class implements HardwareFingerprint
        {
            public function raw(): string
            {
                return 'MESIN-UJI';
            }
        }));
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->keysDir.'/*') ?: []);
        @rmdir($this->keysDir);
        @unlink($this->licPath);
        parent::tearDown();
    }

    public function test_offline_without_a_license_redirects_to_activation(): void
    {
        $this->get('/gudang')->assertRedirect('/aktivasi');
    }

    public function test_offline_with_a_valid_license_passes(): void
    {
        app(LicenseFile::class)->issue([
            'machine_id' => app(MachineId::class)->current(),
            'expires_at' => '2999-12-31',
        ]);

        $this->get('/gudang')->assertOk();
    }

    public function test_offline_with_a_license_for_another_machine_still_blocks(): void
    {
        app(LicenseFile::class)->issue([
            'machine_id' => 'KOMPUTER-LAIN',
            'expires_at' => '2999-12-31',
        ]);

        $this->get('/gudang')->assertRedirect('/aktivasi');
    }

    public function test_activation_page_is_always_reachable_offline(): void
    {
        $this->get('/aktivasi')->assertOk();
    }

    public function test_online_never_checks_the_license(): void
    {
        config(['app.edition' => 'online']);
        $this->get('/gudang')->assertOk();
    }
}
