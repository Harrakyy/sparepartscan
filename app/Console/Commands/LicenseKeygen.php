<?php

namespace App\Console\Commands;

use App\Services\License\Keys;
use Illuminate\Console\Command;

class LicenseKeygen extends Command
{
    protected $signature = 'license:keygen';

    protected $description = 'Buat sepasang kunci lisensi Ed25519 (sekali saja, di laptop Ashira)';

    public function handle(Keys $keys): int
    {
        try {
            $keys->generate();
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info('Kunci dibuat di storage/app/keys.');
        $this->warn('RAHASIAKAN secret.key: jangan pernah commit atau sebar. Hanya public.key yang ikut aplikasi.');

        return self::SUCCESS;
    }
}
