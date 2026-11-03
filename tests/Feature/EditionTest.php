<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Rute & menu Backup hanya ada di edisi offline; edisi online menyembunyikannya (404). */
class EditionTest extends TestCase
{
    use RefreshDatabase;

    public function test_backup_routes_are_hidden_on_the_online_edition(): void
    {
        config(['app.edition' => 'online']);

        // Superadmin bawaan dari TestCase; tetap 404 karena fitur ini tak ada di edisi online.
        $this->getJson('/api/backups')->assertNotFound();
    }

    public function test_superadmin_reaches_backups_on_the_offline_edition(): void
    {
        config(['app.edition' => 'offline']);

        $this->getJson('/api/backups')->assertOk();
    }

    public function test_admin_is_forbidden_from_backups_on_the_offline_edition(): void
    {
        config(['app.edition' => 'offline']);
        $this->actingAs(User::factory()->admin()->create());

        $this->getJson('/api/backups')->assertStatus(403);
    }
}
