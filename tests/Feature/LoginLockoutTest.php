<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Penguncian per akun saat password salah: 3x → 5 menit, 6x → 10 menit, 9x → 15 menit,
 * 12x → sampai besok 00.00 WIB. Username yang tidak terdaftar tidak menghitung apa pun.
 */
class LoginLockoutTest extends TestCase
{
    use RefreshDatabase;

    protected bool $signIn = false;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        // 30 Okt 2026 10:00 WIB.
        $this->travelTo(Carbon::parse('2026-10-30 03:00:00', 'UTC'));
        $this->user = User::factory()->admin()->create(['username' => 'gudang', 'password' => 'rahasia123']);
    }

    private function attempt(string $password, string $email = 'gudang')
    {
        return $this->post('/login', ['username' => $email, 'password' => $password]);
    }

    private function failTimes(int $times): void
    {
        for ($i = 0; $i < $times; $i++) {
            $this->attempt('salah-salah');
        }
    }

    private function lastError(): string
    {
        return session('errors')->first('username');
    }

    public function test_three_wrong_passwords_lock_the_account_for_five_minutes(): void
    {
        $this->failTimes(2);
        $this->assertSame('Username atau password salah', $this->lastError());

        $this->attempt('salah-salah');
        $this->assertSame('Password salah 3 kali. Akun dikunci 5 menit.', $this->lastError());

        // Password benar pun ditolak selama dikunci.
        $this->travel(4)->minutes();
        $this->attempt('rahasia123');
        $this->assertSame('Akun ini dikunci karena terlalu banyak salah password. Coba lagi dalam 1 menit.', $this->lastError());
        $this->assertGuest();

        $this->travel(61)->seconds();
        $this->attempt('rahasia123')->assertRedirect('/gudang');
        $this->assertAuthenticatedAs($this->user);
        $this->assertSame([0, null], [$this->user->fresh()->failed_logins, $this->user->fresh()->locked_until]);
    }

    public function test_the_lock_gets_longer_and_ends_with_tomorrow(): void
    {
        foreach ([5, 10, 15] as $minutes) {
            $this->failTimes(3);
            $this->assertSame("Password salah {$this->user->fresh()->failed_logins} kali. Akun dikunci {$minutes} menit.", $this->lastError());
            $this->travel($minutes)->minutes();
        }

        $this->failTimes(3);
        $this->assertSame('Password salah 12 kali. Akun dikunci sampai besok (00.00 WIB).', $this->lastError());
        // Besok 00.00 WIB = 30 Okt 17:00 UTC.
        $this->assertTrue($this->user->fresh()->locked_until->equalTo(Carbon::parse('2026-10-30 17:00:00', 'UTC')));

        $this->attempt('rahasia123');
        $this->assertSame('Akun ini dikunci karena terlalu banyak salah password. Coba lagi besok.', $this->lastError());

        $this->travelTo(Carbon::parse('2026-10-30 17:00:01', 'UTC'));
        $this->attempt('rahasia123')->assertRedirect('/gudang');
    }

    public function test_after_the_day_lock_the_count_starts_over(): void
    {
        $this->user->forceFill(['failed_logins' => 12, 'locked_until' => now()->subSecond(), 'last_failed_login_at' => now()->subMinute()])->save();

        $this->attempt('salah-salah');

        $this->assertSame([1, null], [$this->user->fresh()->failed_logins, $this->user->fresh()->locked_until]);
    }

    public function test_a_successful_login_resets_the_count(): void
    {
        $this->failTimes(2);
        $this->attempt('rahasia123')->assertRedirect('/gudang');
        $this->post('/logout');

        $this->failTimes(2);

        $this->assertSame(2, $this->user->fresh()->failed_logins);
        $this->assertNull($this->user->fresh()->locked_until);
    }

    public function test_mistakes_older_than_a_day_are_forgotten(): void
    {
        $this->failTimes(2);
        $this->travel(25)->hours();

        $this->attempt('salah-salah');

        $this->assertSame(1, $this->user->fresh()->failed_logins);
        $this->assertNull($this->user->fresh()->locked_until);
    }

    public function test_unknown_emails_never_lock_anything(): void
    {
        for ($i = 0; $i < 15; $i++) {
            $this->attempt('apa-saja', 'tidak-ada@contoh.test');
        }

        $this->assertSame('Username atau password salah', $this->lastError());
        $this->attempt('rahasia123')->assertRedirect('/gudang');
    }

    public function test_superadmin_can_unlock_a_locked_account(): void
    {
        $this->failTimes(12);
        $boss = User::factory()->superadmin()->create();

        $this->actingAs($boss)->patchJson("/api/users/{$this->user->id}", ['unlock' => true])
            ->assertOk()
            ->assertJsonPath('data.locked_until', null);
        $this->assertSame(0, $this->user->fresh()->failed_logins);

        $this->post('/logout');
        $this->attempt('rahasia123')->assertRedirect('/gudang');
    }

    public function test_the_user_list_shows_who_is_locked(): void
    {
        $this->failTimes(3);
        $this->actingAs(User::factory()->superadmin()->create());

        $row = collect($this->getJson('/api/users')->json('data'))->firstWhere('username', 'gudang');
        $this->assertNotNull($row['locked_until']);
    }
}
