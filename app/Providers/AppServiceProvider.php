<?php

namespace App\Providers;

use App\Models\Invoice;
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
        // Angka awal badge "perlu di-scan" di menu Gudang; selanjutnya diperbarui oleh nav-badge.js.
        View::composer('layouts.app', function ($view) {
            $view->with('openInvoiceCount', Invoice::whereIn('status', Invoice::OPEN_STATUSES)->count());
        });
    }
}
