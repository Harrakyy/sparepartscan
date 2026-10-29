<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

/** Akun superadmin pertama; password diketik saat dijalankan, tidak pernah ditulis di kode. */
class CreateSuperadmin extends Command
{
    protected $signature = 'app:superadmin {email} {--name=Superadmin}';

    protected $description = 'Buat akun superadmin (dipakai sekali saat aplikasi pertama dipasang)';

    private const MIN_PASSWORD = 8;

    public function handle(): int
    {
        $password = (string) $this->secret('Password (min. '.self::MIN_PASSWORD.' karakter)');
        $confirm = (string) $this->secret('Ulangi password');

        $validator = Validator::make(
            ['email' => $this->argument('email'), 'name' => $this->option('name'), 'password' => $password],
            ['email' => ['required', 'email', 'max:255', 'unique:users,email'], 'name' => ['required', 'max:100'], 'password' => ['required', 'min:'.self::MIN_PASSWORD]],
            ['email.unique' => 'Email sudah terdaftar', 'email.email' => 'Format email tidak valid', 'password.min' => 'Password minimal '.self::MIN_PASSWORD.' karakter'],
        );
        if ($validator->fails()) {
            $this->error($validator->errors()->first());

            return self::FAILURE;
        }
        if ($password !== $confirm) {
            $this->error('Password tidak sama');

            return self::FAILURE;
        }

        User::create([
            'name' => $this->option('name'),
            'email' => $this->argument('email'),
            'password' => $password,
            'role' => 'superadmin',
            'is_active' => true,
        ]);
        $this->info('Superadmin '.$this->argument('email').' dibuat.');

        return self::SUCCESS;
    }
}
