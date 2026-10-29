<?php

use App\Http\Middleware\EnsureUserIsActive;
use App\Support\ApiResponse;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        // API memakai grup "web" supaya CSRF dan sesi login berlaku untuk halaman di aplikasi ini.
        then: function () {
            Route::middleware(['web', 'auth'])->prefix('api')->group(base_path('routes/api.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectGuestsTo('/login');
        $middleware->redirectUsersTo('/gudang');
        $middleware->web(append: [EnsureUserIsActive::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $isApi = fn (Request $request) => $request->is('api/*');

        $exceptions->render(function (ValidationException $e, Request $request) use ($isApi) {
            if ($isApi($request)) {
                return ApiResponse::fail(collect($e->errors())->flatten()->first() ?? 'Data tidak valid', 422);
            }
        });

        // Belum login / sesi habis: halaman scan mengarahkan ke /login (lihat api.js).
        $exceptions->render(function (AuthenticationException $e, Request $request) use ($isApi) {
            if ($isApi($request)) {
                return ApiResponse::fail('Sesi berakhir, silakan masuk lagi', 401);
            }
        });

        // Sesi Laravel habis (default 2 jam) saat halaman scan dibiarkan terbuka.
        // Laravel sudah mengubah TokenMismatchException menjadi HttpException 419 sebelum sampai di sini,
        // dan AuthorizationException (Gate superadmin) menjadi HttpException 403.
        $exceptions->render(function (HttpException $e, Request $request) use ($isApi) {
            if ($isApi($request) && $e->getStatusCode() === 419) {
                return ApiResponse::fail('Sesi halaman kedaluwarsa. Muat ulang halaman (F5) lalu coba lagi.', 419);
            }
            if ($isApi($request) && $e->getStatusCode() === 403) {
                return ApiResponse::fail('Hanya superadmin yang boleh melakukan ini', 403);
            }
        });

        $exceptions->render(function (NotFoundHttpException $e, Request $request) use ($isApi) {
            if ($isApi($request)) {
                return ApiResponse::fail('Data tidak ditemukan', 404);
            }
        });
    })->create();
