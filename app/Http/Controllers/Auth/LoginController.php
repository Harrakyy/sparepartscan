<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\LoginLockout;
use App\Support\Edition;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function show(): View|RedirectResponse
    {
        // Edisi offline yang baru dipasang belum punya akun: buat superadmin pertama dulu.
        if (Edition::isOffline() && ! User::exists()) {
            return redirect('/akun-pertama');
        }

        return view('auth.login');
    }

    public function login(Request $request, LoginLockout $lockout): RedirectResponse
    {
        $credentials = $request->validate([
            'username' => ['required', 'string', 'max:60'],
            'password' => ['required', 'string'],
        ], [
            'username.required' => 'Username wajib diisi',
            'password.required' => 'Password wajib diisi',
        ]);

        $user = User::where('username', User::normalizeUsername($credentials['username']))->first();
        if (! $user) {
            throw ValidationException::withMessages(['username' => 'Username atau password salah']);
        }
        // Akun yang sedang dikunci ditolak sebelum password dicek, termasuk password yang benar.
        if ($message = $lockout->lockedMessage($user)) {
            throw ValidationException::withMessages(['username' => $message]);
        }
        if (! Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages(['username' => $lockout->recordFailure($user)]);
        }
        if (! $user->is_active) {
            throw ValidationException::withMessages(['username' => 'Akun ini dinonaktifkan, hubungi superadmin']);
        }

        $lockout->reset($user);
        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();
        $user->update(['last_login_at' => now()]);

        return redirect()->intended('/gudang');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login');
    }
}
