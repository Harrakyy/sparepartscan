<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\LoginLockout;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * Akun superadmin (darurat / tanpa halaman web), atau --reset bila password superadmin lupa / akunnya
 * terkunci (ikon "Reset superadmin" di komputer master). Password diketik saat dijalankan, tidak pernah
 * ditulis di kode.
 */
class CreateSuperadmin extends Command
{
    protected $signature = 'app:superadmin {username? : Username superadmin (ditanyakan bila kosong)}
        {--name=Superadmin}
        {--reset : Ganti password superadmin yang sudah ada, aktifkan, dan buka kuncinya}';

    protected $description = 'Buat akun superadmin, atau reset password superadmin (--reset)';

    private const MIN_PASSWORD = 8;

    /** Di komputer master, folder data hanya bisa dibuka administrator; SQLite lalu melaporkan "tidak ada". */
    private const NO_ACCESS = '/unable to open database|Database file at path .* does not exist|permission denied|access is denied/i';

    public function handle(LoginLockout $lockout): int
    {
        $username = User::normalizeUsername($this->argument('username') ?? $this->ask('Username superadmin'));

        try {
            return $this->option('reset') ? $this->reset($username, $lockout) : $this->create($username);
        } catch (\Throwable $e) {
            if (! preg_match(self::NO_ACCESS, $e->getMessage())) {
                throw $e;
            }
            $this->error('Database tidak bisa dibuka. Jalankan sebagai administrator (klik kanan Command Prompt → Run as administrator), lalu ulangi.');

            return self::FAILURE;
        }
    }

    private function create(string $username): int
    {
        $password = $this->askPassword();
        $validator = Validator::make(
            ['username' => $username, 'name' => $this->option('name'), 'password' => $password],
            ['username' => ['required', User::USERNAME_RULE, 'unique:users,username'], 'name' => ['required', 'max:100'], 'password' => ['required', 'min:'.self::MIN_PASSWORD]],
            ['username.unique' => 'Username sudah dipakai', 'username.regex' => User::USERNAME_MESSAGE, 'password.min' => 'Password minimal '.self::MIN_PASSWORD.' karakter'],
        );
        if ($password === null || $validator->fails()) {
            $this->error($password === null ? 'Password tidak sama' : $validator->errors()->first());

            return self::FAILURE;
        }

        User::create([
            'name' => $this->option('name'),
            'username' => $username,
            'password' => $password,
            'role' => 'superadmin',
            'is_active' => true,
        ]);
        $this->info('Superadmin '.$username.' dibuat.');

        return self::SUCCESS;
    }

    private function reset(string $username, LoginLockout $lockout): int
    {
        $user = User::where('username', $username)->first();
        if ($user === null || ! $user->isSuperadmin()) {
            $this->error('Tidak ada superadmin dengan username '.$username);

            return self::FAILURE;
        }

        $password = $this->askPassword();
        if ($password === null || strlen($password) < self::MIN_PASSWORD) {
            $this->error($password === null ? 'Password tidak sama' : 'Password minimal '.self::MIN_PASSWORD.' karakter');

            return self::FAILURE;
        }

        // Sama seperti ganti password di menu Pengguna: sesi lama berakhir (remember token baru).
        $user->forceFill(['password' => $password, 'is_active' => true, 'remember_token' => Str::random(60)])->save();
        $lockout->reset($user);
        $this->info('Password superadmin '.$username.' diganti; akun aktif dan tidak terkunci.');

        return self::SUCCESS;
    }

    /** Password yang diketik dua kali, atau null bila tidak sama. */
    private function askPassword(): ?string
    {
        $password = (string) $this->secret('Password (min. '.self::MIN_PASSWORD.' karakter)');
        $confirm = (string) $this->secret('Ulangi password');

        return $password === $confirm ? $password : null;
    }
}
