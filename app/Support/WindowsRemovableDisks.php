<?php

namespace App\Support;

use App\Contracts\RemovableDisks;

/** Daftar flashdisk di Windows lewat PowerShell `Get-Volume` (DriveType=Removable). */
class WindowsRemovableDisks implements RemovableDisks
{
    public function list(): array
    {
        $ps = 'Get-Volume | Where-Object { $_.DriveType -eq "Removable" -and $_.DriveLetter } '
            .'| ForEach-Object { [pscustomobject]@{ drive = "$($_.DriveLetter):"; '
            .'label = $_.FileSystemLabel; free = [int64]$_.SizeRemaining } } | ConvertTo-Json -Compress';

        $out = shell_exec('powershell -NoProfile -NonInteractive -Command '.escapeshellarg($ps));
        if (! is_string($out) || trim($out) === '') {
            return [];
        }

        $data = json_decode(trim($out), true);
        if ($data === null) {
            return [];
        }
        // ConvertTo-Json mengembalikan objek tunggal (bukan array) bila hanya satu disk.
        if (array_is_list($data) === false) {
            $data = [$data];
        }

        return array_map(fn ($d) => [
            'drive' => (string) ($d['drive'] ?? ''),
            'label' => (string) ($d['label'] ?? ''),
            'free' => (int) ($d['free'] ?? 0),
        ], $data);
    }
}
