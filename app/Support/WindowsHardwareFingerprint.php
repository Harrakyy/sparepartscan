<?php

namespace App\Support;

use App\Contracts\HardwareFingerprint;

/**
 * Identitas hardware Windows: UUID produk + serial motherboard (PowerShell CIM).
 * Sengaja TIDAK memakai serial disk: aplikasi ini mencolok flashdisk ke komputer master untuk backup,
 * dan enumerasi disk ("first media") bisa berubah sehingga mengunci aplikasi tanpa alasan. UUID produk
 * dan serial board tetap stabil saat disk/flashdisk/RAM diganti; ganti motherboard = komputer baru (lisensi ulang).
 */
class WindowsHardwareFingerprint implements HardwareFingerprint
{
    public function raw(): string
    {
        return implode('|', [
            $this->ps('(Get-CimInstance Win32_ComputerSystemProduct).UUID'),
            $this->ps('(Get-CimInstance Win32_BaseBoard).SerialNumber'),
        ]);
    }

    private function ps(string $expr): string
    {
        $out = shell_exec('powershell -NoProfile -NonInteractive -Command '.escapeshellarg($expr));

        return is_string($out) ? trim($out) : '';
    }
}
