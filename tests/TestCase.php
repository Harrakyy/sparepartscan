<?php

namespace Tests;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /** Semua halaman wajib login: tes berjalan sebagai superadmin, kecuali tes yang mematikan ini. */
    protected bool $signIn = true;

    protected function setUp(): void
    {
        parent::setUp();

        if ($this->signIn && in_array(RefreshDatabase::class, class_uses_recursive($this), true)) {
            $this->actingAs(User::factory()->superadmin()->create());
        }
    }
}
