<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\AccountFormRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

    public function store(Request $request): RedirectResponse
    {
        // Klik dua kali / tab kedua: dicek SEBELUM validasi (username yang sama akan ditolak "sudah dipakai"
        // dan dikembalikan ke halaman yang sudah tertutup), langsung ke halaman masuk dengan pesan jelas.
        if (User::exists()) {
            return $this->alreadyCreated();
        }
        $data = app(AccountFormRequest::class)->validated();

        // Dua permintaan hampir bersamaan: transaksi memegang kunci tulis sejak awal, jadi yang kedua
        // melihat akun pertama dan dibatalkan.
        $user = DB::transaction(function () use ($data) {
            if (User::exists()) {
                return null;
            }

            return User::create([
                'name' => $data['name'],
                'username' => $data['username'],
                'password' => $data['password'],
                'role' => 'superadmin',
                'is_active' => true,
            ]);
        });
        if ($user === null) {
            return $this->alreadyCreated();
        }

        Auth::login($user);
        $request->session()->regenerate();
        $user->update(['last_login_at' => now()]);

        return redirect('/gudang');
    }

    private function alreadyCreated(): RedirectResponse
    {
        return redirect('/login')->with('status', 'Akun superadmin sudah dibuat, silakan masuk.');
    }
}
