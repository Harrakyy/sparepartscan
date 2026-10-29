<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    protected bool $signIn = false;

    private function user(array $attributes = []): User
    {
        return User::factory()->admin()->create(array_merge(['email' => 'gudang@contoh.test', 'password' => 'rahasia123'], $attributes));
    }

    private function login(string $password = 'rahasia123', bool $remember = false)
    {
        return $this->post('/login', ['email' => 'gudang@contoh.test', 'password' => $password, 'remember' => $remember ? '1' : null]);
    }

    public function test_guests_are_sent_to_login_and_the_api_answers_401(): void
    {
        $this->get('/gudang')->assertRedirect('/login');
        $this->get('/laporan')->assertRedirect('/login');
        $this->getJson('/api/invoices')->assertStatus(401)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error', 'Sesi berakhir, silakan masuk lagi');
    }

    public function test_login_page_is_public(): void
    {
        $this->get('/login')->assertOk()->assertSee('Masuk');
    }

    public function test_correct_password_signs_in_and_records_last_login(): void
    {
        $user = $this->user();

        $this->login()->assertRedirect('/gudang');

        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->last_login_at);
    }

    public function test_wrong_password_gets_a_generic_message(): void
    {
        $this->user();

        $this->from('/login')->login('salah-salah')
            ->assertRedirect('/login')
            ->assertSessionHasErrors(['email' => 'Email atau password salah']);
        $this->assertGuest();
    }

    public function test_inactive_account_cannot_sign_in(): void
    {
        $this->user(['is_active' => false]);

        $this->login()->assertSessionHasErrors(['email' => 'Akun ini dinonaktifkan, hubungi superadmin']);
        $this->assertGuest();
    }

    public function test_remember_me_sets_the_remember_cookie(): void
    {
        $this->user();

        $this->login(remember: true)->assertCookie(Auth::guard()->getRecallerName());
    }

    public function test_repeated_failures_are_throttled(): void
    {
        $this->user();
        for ($i = 0; $i < 5; $i++) {
            $this->login('salah-salah');
        }

        $this->login()->assertSessionHasErrors('email');
        $this->assertStringStartsWith('Terlalu banyak percobaan', session('errors')->first('email'));
        $this->assertGuest();
    }

    public function test_logout_ends_the_session(): void
    {
        $this->actingAs($this->user());

        $this->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_an_account_deactivated_while_signed_in_is_turned_away(): void
    {
        $user = $this->user();
        $this->actingAs($user);
        $user->update(['is_active' => false]);

        $this->getJson('/api/invoices')->assertStatus(401);
        $this->get('/gudang')->assertRedirect('/login');
    }

    public function test_superadmin_command_creates_the_first_account(): void
    {
        $this->artisan('app:superadmin', ['email' => 'boss@contoh.test', '--name' => 'Pak Bos'])
            ->expectsQuestion('Password (min. 8 karakter)', 'rahasia123')
            ->expectsQuestion('Ulangi password', 'rahasia123')
            ->assertSuccessful();

        $user = User::where('email', 'boss@contoh.test')->first();
        $this->assertSame(['Pak Bos', 'superadmin', true], [$user->name, $user->role, $user->is_active]);
        $this->assertTrue(Hash::check('rahasia123', $user->password));
    }

    public function test_superadmin_command_rejects_a_short_password(): void
    {
        $this->artisan('app:superadmin', ['email' => 'boss@contoh.test'])
            ->expectsQuestion('Password (min. 8 karakter)', 'pendek')
            ->expectsQuestion('Ulangi password', 'pendek')
            ->assertFailed();

        $this->assertSame(0, User::count());
    }
}
