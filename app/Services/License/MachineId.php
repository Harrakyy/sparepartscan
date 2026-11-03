<?php

namespace App\Services\License;

use App\Contracts\HardwareFingerprint;

/** ID komputer: hash stabil dari identitas hardware, dipakai mengikat lisensi ke satu komputer. */
class MachineId
{
    public function __construct(private readonly HardwareFingerprint $source) {}

    public function current(): string
    {
        return hash('sha256', trim($this->source->raw()));
    }
}
