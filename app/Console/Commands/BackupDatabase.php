<?php

namespace App\Console\Commands;

use App\Services\BackupService;
use Illuminate\Console\Command;

class BackupDatabase extends Command
{
    protected $signature = 'app:backup {jenis=manual : perjam | harian | manual}';

    protected $description = 'Backup database (edisi offline)';

    public function handle(BackupService $backup): int
    {
        $jenis = $this->argument('jenis');
        $path = $backup->create($jenis);
        $this->info($path ? "Backup dibuat: {$path}" : 'Data tidak berubah, backup dilewati.');

        return self::SUCCESS;
    }
}
