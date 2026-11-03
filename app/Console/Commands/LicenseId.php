<?php

namespace App\Console\Commands;

use App\Services\License\MachineId;
use Illuminate\Console\Command;

class LicenseId extends Command
{
    protected $signature = 'license:id';

    protected $description = 'Tampilkan ID komputer ini (untuk dibuatkan lisensi)';

    public function handle(MachineId $machine): int
    {
        $this->line($machine->current());

        return self::SUCCESS;
    }
}
