<?php

namespace Tests;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /** Semua halaman wajib login: tes berjalan sebagai superadmin, kecuali tes yang mematikan ini. */
    protected bool $signIn = true;

    protected function setUp(): void
    {
        parent::setUp();

        $uses = class_uses_recursive($this);
        $migrates = in_array(RefreshDatabase::class, $uses, true) || in_array(DatabaseMigrations::class, $uses, true);
        if ($this->signIn && $migrates) {
            $this->actingAs(User::factory()->superadmin()->create());
        }
    }
}
