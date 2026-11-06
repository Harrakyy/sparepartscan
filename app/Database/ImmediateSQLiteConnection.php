<?php

namespace App\Database;

use Illuminate\Database\SQLiteConnection;
use PDOException;

/**
 * Transaksi SQLite yang memegang kunci tulis sejak BEGIN (setara BEGIN IMMEDIATE), juga di PHP < 8.4.
 *
 * Laravel hanya memakai `transaction_mode` di PHP >= 8.4; paket kita PHP 8.3, jadi transaksi DEFERRED:
 * baca dulu, minta kunci tulis belakangan — bila titik scan lain sudah menulis di antaranya, SQLite
 * langsung "database is locked" tanpa menunggu busy_timeout. Di sini, tepat setelah BEGIN, dijalankan
 * tulisan kosong (UPDATE … WHERE 0) yang mengambil kunci tulis sebelum ada bacaan; saat itu SQLite
 * masih menunggu lewat busy_timeout, sehingga permintaan kedua mengantre, bukan gagal.
 */
class ImmediateSQLiteConnection extends SQLiteConnection
{
    protected function executeBeginTransactionStatement()
    {
        if (version_compare(PHP_VERSION, '8.4.0', '>=') || strtoupper((string) $this->getConfig('transaction_mode')) !== 'IMMEDIATE') {
            parent::executeBeginTransactionStatement();

            return;
        }

        $pdo = $this->getPdo();
        $pdo->beginTransaction();
        try {
            $pdo->exec('UPDATE "migrations" SET "batch" = "batch" WHERE 0');
        } catch (PDOException $e) {
            // Tabel migrations belum ada (database baru, sebelum migrasi pertama): tetap DEFERRED.
            if (! str_contains($e->getMessage(), 'no such table')) {
                $pdo->rollBack();

                throw $e;
            }
        }
    }
}
