<?php

namespace App\Http\Middleware;

use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Akun yang dinonaktifkan superadmin langsung keluar di request berikutnya, termasuk laptop
 * gudang yang memakai "Ingat saya". Ditolak di sini juga, karena middleware `auth` berprioritas
 * lebih tinggi dan sudah lewat sebelum middleware ini berjalan.
 */
class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check() && ! Auth::user()->is_active) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            if ($request->is('api/*')) {
                return ApiResponse::fail('Sesi berakhir, silakan masuk lagi', 401);
            }

            return redirect('/login')->withErrors(['email' => 'Akun ini dinonaktifkan, hubungi superadmin']);
        }

        return $next($request);
    }
}
