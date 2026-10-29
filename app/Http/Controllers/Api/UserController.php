<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Menu Pengguna (khusus superadmin). Tidak ada hapus: akun dinonaktifkan supaya riwayat tetap utuh. */
class UserController extends Controller
{
    private const MIN_PASSWORD = 8;

    private const MESSAGES = [
        'name.required' => 'Nama wajib diisi',
        'name.max' => 'Nama maksimal 100 karakter',
        'email.required' => 'Email wajib diisi',
        'email.email' => 'Format email tidak valid',
        'email.unique' => 'Email sudah terdaftar',
        'role.*' => 'Peran tidak dikenal',
        'password.required' => 'Password wajib diisi',
        'password.min' => 'Password minimal 8 karakter',
        'is_active.*' => 'Status aktif tidak valid',
    ];

    public function index(): JsonResponse
    {
        return ApiResponse::ok(User::orderBy('name')->get()->map(fn (User $u) => $this->row($u)));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'role' => ['required', Rule::in(User::ROLES)],
            'password' => ['required', 'string', 'min:'.self::MIN_PASSWORD],
        ], self::MESSAGES);

        return ApiResponse::ok($this->row(User::create($data + ['is_active' => true])), 201);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $data = $request->validate([
            'role' => ['sometimes', Rule::in(User::ROLES)],
            'is_active' => ['sometimes', 'boolean'],
            'password' => ['sometimes', 'string', 'min:'.self::MIN_PASSWORD],
        ], self::MESSAGES);

        // Superadmin selalu tersisa: tidak bisa menurunkan atau menonaktifkan dirinya sendiri.
        $demotesSelf = $user->is(Auth::user())
            && ((isset($data['role']) && $data['role'] !== 'superadmin') || (isset($data['is_active']) && ! $data['is_active']));
        if ($demotesSelf) {
            throw ValidationException::withMessages(['role' => 'Tidak bisa mengubah peran atau menonaktifkan akun sendiri']);
        }

        // Password baru: semua sesi lama ikut berakhir (AuthenticateSession + remember token baru).
        if (isset($data['password'])) {
            $data['remember_token'] = Str::random(60);
        }
        $user->forceFill($data)->save();

        // Superadmin yang mengganti password sendiri tetap masuk di sesi ini.
        if (isset($data['password']) && $user->is(Auth::user())) {
            Auth::setUser($user);
            $request->session()->put('password_hash_'.Auth::getDefaultDriver(), $user->getAuthPassword());
        }

        return ApiResponse::ok($this->row($user->fresh()));
    }

    private function row(User $user): array
    {
        return $user->only(['id', 'name', 'email', 'role', 'is_active', 'last_login_at']);
    }
}
