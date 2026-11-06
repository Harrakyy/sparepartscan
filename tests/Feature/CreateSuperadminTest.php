<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CreateSuperadminTest extends TestCase
{
    use RefreshDatabase;

    protected bool $signIn = false;

    public function test_it_creates_a_superadmin(): void
    {
        $this->artisan('app:superadmin', ['username' => 'bos', '--name' => 'Bos'])
            ->expectsQuestion('Password (min. 8 karakter)', 'rahasia123')
            ->expectsQuestion('Ulangi password', 'rahasia123')
            ->assertSuccessful();

        $this->assertTrue(User::where('username', 'bos')->first()->isSuperadmin());
    }

    public function test_reset_sets_a_new_password_and_unlocks(): void
    {
        $user = User::factory()->superadmin()->create([
            'username' => 'bos',
            'is_active' => false,
            'failed_logins' => 9,
            'last_failed_login_at' => now(),
            'locked_until' => now()->addDay(),
        ]);
        $oldToken = $user->remember_token;

        $this->artisan('app:superadmin', ['--reset' => true])
            ->expectsQuestion('Username superadmin', 'bos')
            ->expectsQuestion('Password (min. 8 karakter)', 'baru12345')
            ->expectsQuestion('Ulangi password', 'baru12345')
            ->assertSuccessful();

        $user->refresh();
        $this->assertTrue(Hash::check('baru12345', $user->password));
        $this->assertTrue($user->is_active);
        $this->assertSame(0, $user->failed_logins);
        $this->assertNull($user->locked_until);
        $this->assertNull($user->last_failed_login_at);
        $this->assertNotSame($oldToken, $user->remember_token);
    }

    public function test_reset_refuses_an_unknown_or_non_superadmin_email(): void
    {
        User::factory()->admin()->create(['username' => 'gudang']);

        $this->artisan('app:superadmin', ['username' => 'gudang', '--reset' => true])->assertFailed();
        $this->artisan('app:superadmin', ['username' => 'tidak-ada', '--reset' => true])->assertFailed();
    }
}
