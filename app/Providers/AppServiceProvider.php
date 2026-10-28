<?php

namespace App\Providers;

use App\Models\Invoice;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Dibuka lewat tunnel/proxy HTTPS: percayai X-Forwarded-* supaya URL CSS/JS memakai https dan
        // alamat publik, bukan http://localhost (yang diblokir browser). Diatur lewat TRUSTED_PROXIES.
        $proxies = config('app.trusted_proxies');
        if ($proxies) {
            TrustProxies::at($proxies === '*' ? '*' : array_map('trim', explode(',', $proxies)));
        }

        // Angka awal badge "perlu di-scan" di menu Gudang; selanjutnya diperbarui oleh nav-badge.js.
        View::composer('layouts.app', function ($view) {
            $view->with('openInvoiceCount', Invoice::whereIn('status', Invoice::OPEN_STATUSES)->count());
        });
    }
}
