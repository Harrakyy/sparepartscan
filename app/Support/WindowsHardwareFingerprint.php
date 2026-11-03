<?php

namespace App\Support;

use App\Contracts\HardwareFingerprint;

/** Identitas hardware Windows: UUID produk + serial motherboard + serial disk pertama (PowerShell CIM). */
class WindowsHardwareFingerprint implements HardwareFingerprint
{
    public function raw(): string
    {
        return implode('|', [
            $this->ps('(Get-CimInstance Win32_ComputerSystemProduct).UUID'),
            $this->ps('(Get-CimInstance Win32_BaseBoard).SerialNumber'),
            $this->ps('(Get-CimInstance Win32_PhysicalMedia | Select-Object -First 1 -ExpandProperty SerialNumber)'),
        ]);
    }

    private function ps(string $expr): string
    {
        $out = shell_exec('powershell -NoProfile -NonInteractive -Command '.escapeshellarg($expr));

        return is_string($out) ? trim($out) : '';
    }
}
