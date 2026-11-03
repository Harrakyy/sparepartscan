<?php

namespace App\Http\Middleware;

use App\Support\Edition;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Fitur yang hanya ada di edisi offline (Backup). Di edisi online rutenya dianggap tak ada (404),
 * supaya menu/rute Backup benar-benar hilang. Rute tetap didaftarkan agar perubahan edisi saat
 * runtime (mis. di tes) tetap berlaku.
 */
class EnsureOfflineEdition
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(Edition::isOffline(), 404);

        return $next($request);
    }
}
