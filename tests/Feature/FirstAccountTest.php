<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FirstAccountTest extends TestCase
{
    use RefreshDatabase;

    protected bool $signIn = false;

    private const FORM = ['name' => 'Bos', 'username' => 'Bos', 'password' => 'rahasia123', 'password_confirmation' => 'rahasia123'];

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.edition' => 'offline']);
    }

    public function test_empty_install_shows_the_first_account_page(): void
    {
        $this->get('/akun-pertama')->assertOk()->assertSee('Buat akun superadmin');
        $this->get('/login')->assertRedirect('/akun-pertama');
    }

    public function test_first_account_becomes_a_signed_in_superadmin(): void
    {
        $this->post('/akun-pertama', self::FORM)->assertRedirect('/gudang');

        $user = User::where('username', 'bos')->first();
        $this->assertTrue($user->isSuperadmin());
        $this->assertTrue($user->is_active);
        $this->assertAuthenticatedAs($user);
    }

    public function test_the_page_closes_once_an_account_exists(): void
    {
        $this->post('/akun-pertama', self::FORM);
        auth()->logout();

        // Klik dua kali / tab kedua: arahkan ke halaman masuk dengan pesan jelas, bukan 404.
        $this->post('/akun-pertama', array_merge(self::FORM, ['username' => 'penyusup']))
            ->assertRedirect('/login')->assertSessionHas('status', 'Akun superadmin sudah dibuat, silakan masuk.');
        $this->get('/akun-pertama')->assertNotFound();
        $this->get('/login')->assertOk();
        $this->assertSame(1, User::count());
    }

    public function test_the_form_is_validated(): void
    {
        $this->post('/akun-pertama', array_merge(self::FORM, ['password_confirmation' => 'lain12345']))
            ->assertSessionHasErrors(['password' => 'Password tidak sama']);
        $this->post('/akun-pertama', array_merge(self::FORM, ['password' => 'pendek', 'password_confirmation' => 'pendek']))
            ->assertSessionHasErrors(['password' => 'Password minimal 8 karakter']);
        $this->post('/akun-pertama', array_merge(self::FORM, ['username' => 'bos besar']))
            ->assertSessionHasErrors(['username' => User::USERNAME_MESSAGE]);
        $this->assertSame(0, User::count());
    }

    public function test_online_edition_has_no_first_account_page(): void
    {
        config(['app.edition' => 'online']);

        $this->get('/akun-pertama')->assertNotFound();
        $this->get('/login')->assertOk();
    }

    public function test_activation_leads_to_the_first_account_page(): void
    {
        $this->assertSame('/akun-pertama', $this->afterActivationPath());
        User::factory()->create();
        $this->assertSame('/login', $this->afterActivationPath());
    }

    private function afterActivationPath(): string
    {
        return app(\App\Http\Controllers\LicenseController::class)->afterActivationPath();
    }
}
