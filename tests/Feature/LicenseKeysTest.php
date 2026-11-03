<?php

namespace Tests\Feature;

use App\Contracts\HardwareFingerprint;
use App\Services\License\Keys;
use App\Services\License\MachineId;
use Tests\TestCase;

class LicenseKeysTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir().'/keys-'.uniqid();
        $this->app->instance(Keys::class, new Keys($this->dir));
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->dir.'/*') ?: []);
        @rmdir($this->dir);
        parent::tearDown();
    }

    public function test_keygen_creates_a_keypair(): void
    {
        $this->artisan('license:keygen')->assertSuccessful();
        $this->assertFileExists($this->dir.'/secret.key');
        $this->assertFileExists($this->dir.'/public.key');
    }

    public function test_sign_and_verify_match(): void
    {
        $keys = app(Keys::class);
        $keys->generate();
        $this->assertTrue($keys->verify('halo-dunia', $keys->sign('halo-dunia')));
    }

    public function test_verify_rejects_a_tampered_message(): void
    {
        $keys = app(Keys::class);
        $keys->generate();
        $sig = $keys->sign('asli');
        $this->assertFalse($keys->verify('diubah', $sig));
    }

    public function test_keygen_refuses_when_keys_already_exist(): void
    {
        $this->artisan('license:keygen')->assertSuccessful();
        $this->artisan('license:keygen')->assertFailed();
    }

    public function test_machine_id_is_stable_for_the_same_hardware(): void
    {
        $this->app->instance(HardwareFingerprint::class, new class implements HardwareFingerprint
        {
            public function raw(): string
            {
                return 'UUID-123|BOARD-456|DISK-789';
            }
        });

        $machine = app(MachineId::class);
        $this->assertSame($machine->current(), $machine->current());
        $this->assertSame(hash('sha256', 'UUID-123|BOARD-456|DISK-789'), $machine->current());
    }
}
