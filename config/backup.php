<?php

return [
    // Folder backup database (edisi offline). Default di storage; installer bisa menaruh di C:\VerifikasiFaktur\backup.
    'dir' => env('BACKUP_DIR', storage_path('app/backup')),

    // Berapa versi disimpan per jenis.
    'keep' => [
        'perjam' => 48,
        'harian' => 30,
        'manual' => 30,
    ],
];
