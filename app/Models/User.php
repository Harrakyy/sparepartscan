<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const ROLES = ['superadmin', 'admin'];

    /** Username: 3–30 karakter huruf kecil, angka, titik, minus, garis bawah (mis. budi, ana.k_1). */
    public const USERNAME_RULE = 'regex:/^[a-z0-9._-]{3,30}$/';

    public const USERNAME_MESSAGE = 'Username 3–30 karakter: huruf kecil, angka, titik, minus, garis bawah';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'username',
        'password',
        'role',
        'is_active',
        'last_login_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
            'failed_logins' => 'integer',
            'last_failed_login_at' => 'datetime',
            'locked_until' => 'datetime',
        ];
    }

    /** Username dibandingkan tanpa beda huruf besar/kecil dan tanpa spasi di tepi. */
    public static function normalizeUsername(?string $username): string
    {
        return strtolower(trim((string) $username));
    }

    /** Superadmin: hapus faktur, edit jumlah keluar, dan kelola pengguna. */
    public function isSuperadmin(): bool
    {
        return $this->role === 'superadmin';
    }
}
