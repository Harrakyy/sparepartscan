<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Penguncian per akun saat password salah: setiap 3 kali salah akun dikunci makin lama
 * (5, 10, 15 menit), lalu sampai besok 00.00 WIB. Hanya akun yang ada yang dihitung; email
 * tak terdaftar tidak bisa mengunci siapa pun. Superadmin bisa membuka kunci di menu Pengguna.
 */
class LoginLockout
{
    private const FAILURES_PER_STEP = 3;

    /** Lama kunci per tahap (menit); setelah tahap terakhir dikunci sampai besok. */
    private const STEP_MINUTES = [5, 10, 15];

    private const FORGET_AFTER_HOURS = 24;

    private const TIMEZONE = 'Asia/Jakarta';

    /** Pesan bila akun sedang dikunci, atau null bila boleh mencoba. */
    public function lockedMessage(User $user): ?string
    {
        if ($user->locked_until === null || ! $user->locked_until->isFuture()) {
            return null;
        }
        $prefix = 'Akun ini dikunci karena terlalu banyak salah password. Coba lagi ';

        return $prefix.($this->isDayLock($user) ? 'besok.' : 'dalam '.(int) ceil(now()->diffInSeconds($user->locked_until) / 60).' menit.');
    }

    /** Catat satu password salah; mengembalikan pesan untuk ditampilkan. */
    public function recordFailure(User $user): string
    {
        // Mulai dari nol bila kesalahan terakhir sudah lama, atau kunci "sampai besok" sudah lewat.
        $stale = $user->last_failed_login_at?->lt(now()->subHours(self::FORGET_AFTER_HOURS)) ?? true;
        $dayLockOver = $user->locked_until !== null && ! $user->locked_until->isFuture() && $this->isDayLock($user);
        $failures = ($stale || $dayLockOver ? 0 : $user->failed_logins) + 1;

        $lockedUntil = null;
        if ($failures % self::FAILURES_PER_STEP === 0) {
            $step = intdiv($failures, self::FAILURES_PER_STEP) - 1;
            $lockedUntil = isset(self::STEP_MINUTES[$step])
                ? now()->addMinutes(self::STEP_MINUTES[$step])
                : Carbon::now(self::TIMEZONE)->addDay()->startOfDay()->utc();
        }

        $user->forceFill(['failed_logins' => $failures, 'last_failed_login_at' => now(), 'locked_until' => $lockedUntil])->save();

        if ($lockedUntil === null) {
            return 'Email atau password salah';
        }

        return "Password salah {$failures} kali. Akun dikunci "
            .($this->isDayLock($user) ? 'sampai besok (00.00 WIB).' : self::STEP_MINUTES[intdiv($failures, self::FAILURES_PER_STEP) - 1].' menit.');
    }

    public function reset(User $user): void
    {
        $user->forceFill(['failed_logins' => 0, 'last_failed_login_at' => null, 'locked_until' => null])->save();
    }

    private function isDayLock(User $user): bool
    {
        return $user->failed_logins >= self::FAILURES_PER_STEP * (count(self::STEP_MINUTES) + 1);
    }
}
