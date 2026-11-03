<?php

namespace App\Contracts;

/** Sumber identitas hardware komputer. Di produksi: WMIC/PowerShell; di tes: fake. */
interface HardwareFingerprint
{
    /** Gabungan identifier hardware (UUID motherboard, serial board/disk). */
    public function raw(): string;
}
