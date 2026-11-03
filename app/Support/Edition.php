<?php

namespace App\Support;

/** Edisi aplikasi (lihat config/app.php). Offline = LAN/WiFi di komputer master MGI. */
class Edition
{
    public static function isOffline(): bool
    {
        return config('app.edition') === 'offline';
    }
}
