<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegisterTest extends TestCase
{
    use RefreshDatabase;

    protected bool $signIn = false;

    private const FORM = ['name' => 'Budi', 'username' => 'budi', 'password' => 'rahasia123', 'password_confirmation' => 'rahasia123'];

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.edition' => 'offline']);
        User::factory()->superadmin()->create(['username' => 'bos']);
    }

    public function test_staff_sign_up_and_are_signed_in_as_warehouse_staff(): void
    {
        $this->get('/login')->assertOk()->assertSee('/daftar');
        $this->get('/daftar')->assertOk();

        $this->post('/daftar', self::FORM)->assertRedirect('/gudang');

        $user = User::where('username', 'budi')->first();
        $this->assertSame('admin', $user->role);
        $this->assertTrue($user->is_active);
        $this->assertAuthenticatedAs($user);
    }

    public function test_switching_sign_up_off_closes_it(): void
    {
        Setting::set('allow_registration', '0');

        $this->get('/daftar')->assertNotFound();
        $this->post('/daftar', self::FORM)->assertNotFound();
        $this->get('/login')->assertOk()->assertDontSee('/daftar');
    }

    public function test_taken_username_is_rejected(): void
    {
        $this->post('/daftar', array_merge(self::FORM, ['username' => 'Bos']))
            ->assertSessionHasErrors(['username' => 'Username sudah dipakai']);
    }

    public function test_sign_up_is_rate_limited(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->post('/daftar', array_merge(self::FORM, ['username' => 'x']))->assertStatus(302);
        }
        $this->post('/daftar', array_merge(self::FORM, ['username' => 'x']))->assertStatus(429);
    }

    public function test_online_edition_has_no_sign_up(): void
    {
        config(['app.edition' => 'online']);

        $this->get('/daftar')->assertNotFound();
        $this->get('/login')->assertDontSee('/daftar');
    }

    public function test_only_superadmin_switches_sign_up(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $this->patchJson('/api/settings', ['allow_registration' => false])->assertStatus(403);

        $this->actingAs(User::where('username', 'bos')->first());
        $this->getJson('/api/settings')->assertOk()->assertJsonPath('data.allow_registration', true);
        $this->patchJson('/api/settings', ['allow_registration' => false])->assertOk()->assertJsonPath('data.allow_registration', false);
        $this->assertSame('0', Setting::get('allow_registration'));
    }
}
