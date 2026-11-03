<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\LoginLockout;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function show(): View
    {
        return view('auth.login');
    }

    public function login(Request $request, LoginLockout $lockout): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string'],
        ], [
            'email.required' => 'Email wajib diisi',
            'email.email' => 'Format email tidak valid',
            'password.required' => 'Password wajib diisi',
        ]);

        $user = User::where('email', $credentials['email'])->first();
        if (! $user) {
            throw ValidationException::withMessages(['email' => 'Email atau password salah']);
        }
        // Akun yang sedang dikunci ditolak sebelum password dicek, termasuk password yang benar.
        if ($message = $lockout->lockedMessage($user)) {
            throw ValidationException::withMessages(['email' => $message]);
        }
        if (! Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages(['email' => $lockout->recordFailure($user)]);
        }
        if (! $user->is_active) {
            throw ValidationException::withMessages(['email' => 'Akun ini dinonaktifkan, hubungi superadmin']);
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
