<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\AccountFormRequest;
use App\Models\Setting;
use App\Models\User;
use App\Support\Edition;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Edisi offline: petugas gudang mendaftar sendiri dari halaman login (jaringan kabel tertutup), tanpa
 * menunggu superadmin. Superadmin bisa mematikannya (menu Pengguna) dan menonaktifkan akun.
 */
class RegisterController extends Controller
{
    public static function isOpen(): bool
    {
        // Tanpa superadmin, akun daftar akan menutup halaman akun pertama selamanya: tunggu superadmin dulu.
        return Edition::isOffline() && Setting::get('allow_registration', '1') === '1'
            && User::where('role', 'superadmin')->exists();
    }

    public function show(): View
    {
        abort_unless(self::isOpen(), 404);

        return view('auth.register');
    }

    public function store(AccountFormRequest $request): RedirectResponse
    {
        abort_unless(self::isOpen(), 404);
        $data = $request->validated();

        $user = User::create([
            'name' => $data['name'],
            'username' => $data['username'],
            'password' => $data['password'],
            'role' => 'admin',
            'is_active' => true,
        ]);

        Auth::login($user);
        $request->session()->regenerate();
        $user->update(['last_login_at' => now()]);

        return redirect('/gudang');
    }
}
