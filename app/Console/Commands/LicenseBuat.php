<?php

namespace App\Console\Commands;

use App\Services\License\Keys;
use App\Services\License\LicenseFile;
use Illuminate\Console\Command;

class LicenseBuat extends Command
{
    protected $signature = 'license:buat {machine_id : ID komputer pelanggan (dari `license:id`)}
        {--untuk= : Nama pelanggan/komputer (catatan)}
        {--sampai= : Tanggal kedaluwarsa, mis. 2027-12-31}';

    protected $description = 'Buat & tandatangani lisensi untuk satu komputer (hanya di laptop Ashira)';

    public function handle(Keys $keys, LicenseFile $license): int
    {
        if (! $keys->hasSecret()) {
            $this->error('Kunci rahasia tidak ada di komputer ini. Jalankan di laptop Ashira yang memegang secret.key.');

            return self::FAILURE;
        }

        $license->issue([
            'machine_id' => $this->argument('machine_id'),
            'untuk' => $this->option('untuk'),
            'expires_at' => $this->option('sampai'),
        ]);

        $this->info('Lisensi dibuat: '.$license->path());
        $this->line('Salin file itu ke komputer pelanggan sebagai storage/app/license.lic.');

        return self::SUCCESS;
    }
}
