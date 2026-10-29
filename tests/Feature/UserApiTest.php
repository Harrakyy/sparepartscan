<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserApiTest extends TestCase
{
    use RefreshDatabase;

    private function newUser(array $overrides = [])
    {
        return $this->postJson('/api/users', array_merge([
            'name' => 'Budi Gudang', 'email' => 'budi@contoh.test', 'role' => 'admin', 'password' => 'rahasia123',
        ], $overrides));
    }

    public function test_admin_cannot_manage_users(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $this->getJson('/api/users')->assertStatus(403)->assertJsonPath('error', 'Hanya superadmin yang boleh melakukan ini');
        $this->newUser()->assertStatus(403);
        $this->get('/pengguna')->assertForbidden();
        $this->assertDatabaseMissing('users', ['email' => 'budi@contoh.test']);
    }

    public function test_superadmin_lists_users_without_secrets(): void
    {
        User::factory()->admin()->create(['name' => 'Ani']);

        $r = $this->getJson('/api/users')->assertOk()->assertJsonCount(2, 'data');
        $this->assertSame(['id', 'name', 'email', 'role', 'is_active', 'last_login_at'], array_keys($r->json('data.0')));
        $this->get('/pengguna')->assertOk();
    }

    public function test_superadmin_adds_a_user_who_can_then_sign_in(): void
    {
        $this->newUser()->assertCreated()->assertJsonPath('data.email', 'budi@contoh.test')->assertJsonPath('data.role', 'admin');

        $this->assertTrue(Hash::check('rahasia123', User::where('email', 'budi@contoh.test')->value('password')));
    }

    public function test_new_user_is_validated(): void
    {
        $this->newUser(['name' => ''])->assertStatus(422)->assertJsonPath('error', 'Nama wajib diisi');
        $this->newUser(['password' => 'pendek'])->assertStatus(422)->assertJsonPath('error', 'Password minimal 8 karakter');
        $this->newUser(['role' => 'raja'])->assertStatus(422)->assertJsonPath('error', 'Peran tidak dikenal');
        $this->newUser()->assertCreated();
        $this->newUser(['name' => 'Budi Lain'])->assertStatus(422)->assertJsonPath('error', 'Email sudah terdaftar');
    }

    public function test_superadmin_changes_role_and_deactivates(): void
    {
        $user = User::factory()->admin()->create();

        $this->patchJson("/api/users/{$user->id}", ['role' => 'superadmin'])->assertOk()->assertJsonPath('data.role', 'superadmin');
        $this->patchJson("/api/users/{$user->id}", ['is_active' => false])->assertOk()->assertJsonPath('data.is_active', false);
    }

    public function test_setting_a_new_password_signs_the_user_out_everywhere(): void
    {
        $user = User::factory()->admin()->create(['remember_token' => 'token-lama']);

        $this->patchJson("/api/users/{$user->id}", ['password' => 'baru12345'])->assertOk();

        $user->refresh();
        $this->assertTrue(Hash::check('baru12345', $user->password));
        $this->assertNotSame('token-lama', $user->remember_token);
    }

    public function test_superadmin_cannot_demote_or_deactivate_themselves(): void
    {
        $me = Auth::user();

        $this->patchJson("/api/users/{$me->id}", ['role' => 'admin'])->assertStatus(422)
            ->assertJsonPath('error', 'Tidak bisa mengubah peran atau menonaktifkan akun sendiri');
        $this->patchJson("/api/users/{$me->id}", ['is_active' => false])->assertStatus(422);
        $this->patchJson("/api/users/{$me->id}", ['password' => 'baru12345'])->assertOk();
        $this->assertSame(['superadmin', true], [$me->fresh()->role, $me->fresh()->is_active]);
    }
}
