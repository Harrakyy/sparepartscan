<?php

use App\Support\ApiResponse;
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
        // API memakai grup "web" supaya CSRF tetap aktif untuk halaman di aplikasi ini.
        then: function () {
            Route::middleware('web')->prefix('api')->group(base_path('routes/api.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $isApi = fn (Request $request) => $request->is('api/*');

        $exceptions->render(function (ValidationException $e, Request $request) use ($isApi) {
            if ($isApi($request)) {
                return ApiResponse::fail(collect($e->errors())->flatten()->first() ?? 'Data tidak valid', 422);
            }
        });

        // Sesi Laravel habis (default 2 jam) saat halaman scan dibiarkan terbuka.
        // Laravel sudah mengubah TokenMismatchException menjadi HttpException 419 sebelum sampai di sini.
        $exceptions->render(function (HttpException $e, Request $request) use ($isApi) {
            if ($isApi($request) && $e->getStatusCode() === 419) {
                return ApiResponse::fail('Sesi halaman kedaluwarsa. Muat ulang halaman (F5) lalu coba lagi.', 419);
            }
        });

        $exceptions->render(function (NotFoundHttpException $e, Request $request) use ($isApi) {
            if ($isApi($request)) {
                return ApiResponse::fail('Data tidak ditemukan', 404);
            }
        });
    })->create();
