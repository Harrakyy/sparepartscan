<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\LoginLockout;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * Akun superadmin pertama (dipakai installer), atau --reset darurat bila password superadmin lupa /
 * akunnya terkunci (ikon "Reset superadmin" di komputer master). Password diketik saat dijalankan,
 * tidak pernah ditulis di kode.
 */
class CreateSuperadmin extends Command
{
    protected $signature = 'app:superadmin {email? : Email superadmin (ditanyakan bila kosong)}
        {--name=Superadmin}
        {--reset : Ganti password superadmin yang sudah ada, aktifkan, dan buka kuncinya}';

    protected $description = 'Buat akun superadmin, atau reset password superadmin (--reset)';

    private const MIN_PASSWORD = 8;

    public function handle(LoginLockout $lockout): int
    {
        $email = (string) ($this->argument('email') ?? $this->ask('Email superadmin'));

        return $this->option('reset') ? $this->reset($email, $lockout) : $this->create($email);
    }

    private function create(string $email): int
    {
        $password = $this->askPassword();
        $validator = Validator::make(
            ['email' => $email, 'name' => $this->option('name'), 'password' => $password],
            ['email' => ['required', 'email', 'max:255', 'unique:users,email'], 'name' => ['required', 'max:100'], 'password' => ['required', 'min:'.self::MIN_PASSWORD]],
            ['email.unique' => 'Email sudah terdaftar', 'email.email' => 'Format email tidak valid', 'password.min' => 'Password minimal '.self::MIN_PASSWORD.' karakter'],
        );
        if ($password === null || $validator->fails()) {
            $this->error($password === null ? 'Password tidak sama' : $validator->errors()->first());

            return self::FAILURE;
        }

        User::create([
            'name' => $this->option('name'),
            'email' => $email,
            'password' => $password,
            'role' => 'superadmin',
            'is_active' => true,
        ]);
        $this->info('Superadmin '.$email.' dibuat.');

        return self::SUCCESS;
    }

    private function reset(string $email, LoginLockout $lockout): int
    {
        $user = User::where('email', $email)->first();
        if ($user === null || ! $user->isSuperadmin()) {
            $this->error('Tidak ada superadmin dengan email '.$email);

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
        $this->info('Password superadmin '.$email.' diganti; akun aktif dan tidak terkunci.');

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
