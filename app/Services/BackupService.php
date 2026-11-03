<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Backup database SQLite (edisi offline). Memakai `VACUUM INTO` sehingga app tetap bisa dipakai
 * saat backup berjalan, lalu memeriksa keutuhan hasilnya. Backup yang isinya sama persis dengan
 * backup terakhir tidak disimpan ulang; tiap jenis punya batas jumlah versi.
 */
class BackupService
{
    private const JENIS = ['perjam', 'harian', 'manual'];

    /** Buat satu backup; mengembalikan path file, atau null bila isi database tidak berubah. */
    public function create(string $jenis): ?string
    {
        if (! in_array($jenis, self::JENIS, true)) {
            throw new \InvalidArgumentException("Jenis backup tidak dikenal: {$jenis}");
        }

        $dir = $this->dir($jenis);
        $temp = $dir.'/.tmp-'.uniqid().'.sqlite';
        DB::statement('VACUUM INTO '.DB::getPdo()->quote($temp));

        if ((new \PDO('sqlite:'.$temp))->query('PRAGMA integrity_check')->fetchColumn() !== 'ok') {
            @unlink($temp);
            throw new \RuntimeException('Backup gagal: hasil tidak utuh');
        }

        // Tidak menyimpan versi baru bila identik dengan backup terakhir (mis. malam/libur).
        $last = $this->files($jenis)[0] ?? null;
        if ($last && hash_file('sha256', $last) === hash_file('sha256', $temp)) {
            @unlink($temp);

            return null;
        }

        $path = $dir.'/faktur-'.now()->format('YmdHi').'.sqlite';
        rename($temp, $path);
        $this->rotate($jenis);

        return $path;
    }

    /**
     * Semua backup, terbaru dulu.
     *
     * @return array<int, array{nama: string, jenis: string, waktu: string, ukuran: int, jumlah_faktur: int}>
     */
    public function list(): array
    {
        $rows = [];
        foreach (self::JENIS as $jenis) {
            foreach ($this->files($jenis) as $path) {
                $rows[] = [
                    'nama' => basename($path),
                    'jenis' => $jenis,
                    'waktu' => date('c', filemtime($path)),
                    'ukuran' => filesize($path),
                    'jumlah_faktur' => $this->invoiceCount($path),
                ];
            }
        }
        usort($rows, fn ($a, $b) => strcmp($b['waktu'], $a['waktu']));

        return $rows;
    }

    public function dir(string $jenis): string
    {
        $dir = rtrim(config('backup.dir'), '/\\').DIRECTORY_SEPARATOR.$jenis;
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        return $dir;
    }

    /** @return array<int, string> file backup satu jenis, terbaru dulu (urut nama = urut waktu). */
    private function files(string $jenis): array
    {
        $files = glob($this->dir($jenis).'/faktur-*.sqlite') ?: [];
        rsort($files);

        return $files;
    }

    private function rotate(string $jenis): void
    {
        $keep = config("backup.keep.{$jenis}", 30);
        foreach (array_slice($this->files($jenis), $keep) as $old) {
            @unlink($old);
        }
    }

    private function invoiceCount(string $path): int
    {
        try {
            return (int) (new \PDO('sqlite:'.$path))->query('SELECT COUNT(*) FROM invoices')->fetchColumn();
        } catch (\Throwable) {
            return 0;
        }
    }
}
