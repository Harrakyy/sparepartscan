<?php

namespace App\Providers;

use Illuminate\Database\Events\ConnectionEstablished;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

/**
 * Setelan SQLite untuk edisi offline (dua titik scan menulis bersamaan):
 * - WAL: pembaca tidak memblokir penulis (hanya untuk database file; :memory: tak mendukung WAL).
 * - busy_timeout: penulis menunggu, bukan langsung "database is locked".
 * - synchronous NORMAL: cukup aman dengan WAL, lebih cepat.
 * - foreign_keys: konsistensi relasi (cascade hapus faktur, dll.).
 */
class SqliteTuningProvider extends ServiceProvider
{
    public function boot(): void
    {
        Event::listen(ConnectionEstablished::class, function (ConnectionEstablished $event) {
            $connection = $event->connection;
            if ($connection->getDriverName() !== 'sqlite') {
                return;
            }
            if ($connection->getDatabaseName() !== ':memory:') {
                $connection->statement('PRAGMA journal_mode=WAL');
            }
            $connection->statement('PRAGMA busy_timeout=5000');
            $connection->statement('PRAGMA synchronous=NORMAL');
            $connection->statement('PRAGMA foreign_keys=ON');
        });
    }
}
