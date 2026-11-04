<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class UsernameMigrationTest extends TestCase
{
    use RefreshDatabase;

    protected bool $signIn = false;

    public function test_emails_become_unique_usernames(): void
    {
        $migration = require base_path('database/migrations/2026_11_04_000001_replace_email_with_username.php');
        $migration->down();

        $ids = [];
        foreach (['Budi@a.com', 'budi@b.com', 'b@x', 'ana.k_1@x.com'] as $email) {
            $ids[] = DB::table('users')->insertGetId([
                'name' => 'Uji', 'email' => $email, 'password' => Hash::make('rahasia123'),
                'role' => 'admin', 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $migration->up();

        $usernames = DB::table('users')->whereIn('id', $ids)->orderBy('id')->pluck('username')->all();
        $this->assertSame(['budi', 'budi-2', 'user'.$ids[2], 'ana.k_1'], $usernames);
        $this->assertFalse(Schema::hasColumn('users', 'email'));
    }
}
