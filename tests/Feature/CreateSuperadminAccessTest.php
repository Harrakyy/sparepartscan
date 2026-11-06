<?php

namespace Tests\Feature;

use Tests\TestCase;

/** Tanpa RefreshDatabase: koneksi default sengaja diarahkan ke database yang tak bisa dibuka. */
class CreateSuperadminAccessTest extends TestCase
{
    public function test_unreadable_database_tells_to_run_as_administrator(): void
    {
        // Akun Windows biasa tidak bisa membuka data\ di komputer master: SQLite melaporkan "tidak ada".
        config([
            'database.connections.rusak' => ['driver' => 'sqlite', 'database' => sys_get_temp_dir().'/tidak-ada-'.uniqid().'.sqlite', 'prefix' => ''],
            'database.default' => 'rusak',
        ]);

        $this->artisan('app:superadmin', ['username' => 'bos'])
            ->expectsQuestion('Password (min. 8 karakter)', 'rahasia123')
            ->expectsQuestion('Ulangi password', 'rahasia123')
            ->expectsOutputToContain('Jalankan sebagai administrator')
            ->assertFailed();
    }
}
