<?php

namespace App\Services\License;

use App\Contracts\HardwareFingerprint;

/** ID komputer: hash stabil dari identitas hardware, dipakai mengikat lisensi ke satu komputer. */
class MachineId
{
    public function __construct(private readonly HardwareFingerprint $source) {}

    public function current(): string
    {
        $raw = trim($this->source->raw());
        // Jangan runtuh ke satu id konstan bila semua identifier kosong (probe gagal / tanpa admin):
        // id konstan membuat satu lisensi jalan di banyak komputer dan mengunci komputer sah secara acak.
        if ($raw === '' || trim(str_replace('|', '', $raw)) === '') {
            throw new \RuntimeException('Identitas hardware tidak terbaca. Jalankan sebagai administrator.');
        }

        return hash('sha256', $raw);
    }
}
