<?php

namespace App\Http\Middleware;

use App\Services\License\LicenseFile;
use App\Services\License\MachineId;
use App\Support\Edition;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Edisi offline: blokir seluruh aplikasi bila lisensi komputer tidak sah, arahkan ke /aktivasi.
 * Berjalan sebelum `auth` (aktivasi tak perlu login) dan dilewati di edisi online (tak pernah cek).
 * Hasil pemeriksaan di-cache 5 menit supaya tak membaca+verifikasi file tiap permintaan.
 */
class EnsureLicensed
{
    public function __construct(
        private readonly LicenseFile $license,
        private readonly MachineId $machine,
    ) {}

    public function handle(Request $request, Closure $next): mixed
    {
        if (! Edition::isOffline() || ! config('license.enforce') || $request->routeIs('aktivasi', 'aktivasi.store')) {
            return $next($request);
        }

        if ($this->licensed()) {
            return $next($request);
        }

        return redirect('/aktivasi');
    }

    private function licensed(): bool
    {
        return Cache::remember('license.ok', now()->addMinutes(5), function () {
            $path = $this->license->path();
            if (! is_file($path)) {
                return false;
            }
            try {
                $parsed = $this->license->parse((string) file_get_contents($path));

                return $this->license->valid($parsed, $this->machine->current(), now())['ok'];
            } catch (\Throwable) {
                // File lisensi rusak atau identitas hardware tak terbaca: perlakukan sebagai tak berlisensi.
                return false;
            }
        });
    }
}
