<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\AccountFormRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Edisi offline: akun superadmin pertama dibuat lewat halaman web setelah lisensi aktif. Hanya selama
 * belum ada akun sama sekali; sesudahnya halaman ini tertutup (404) untuk selamanya.
 */
class FirstAccountController extends Controller
{
    public function show(): View
    {
        abort_if(User::exists(), 404);

        return view('auth.first-account');
    }

    public function store(AccountFormRequest $request): RedirectResponse
    {
        $data = $request->validated();

        // Dua tab dikirim hampir bersamaan: SQLite menulis berurutan, jadi yang kedua melihat akun pertama.
        $user = DB::transaction(function () use ($data) {
            abort_if(User::exists(), 404);
            $user = User::create([
                'name' => $data['name'],
                'username' => $data['username'],
                'password' => $data['password'],
                'role' => 'superadmin',
                'is_active' => true,
            ]);
            abort_if(User::count() > 1, 404);

            return $user;
        });

        Auth::login($user);
        $request->session()->regenerate();
        $user->update(['last_login_at' => now()]);

        return redirect('/gudang');
    }
}
