<?php

namespace App\Services;

use App\Contracts\RemovableDisks;
use Illuminate\Support\Facades\DB;

/**
 * Backup database SQLite (edisi offline). Memakai `VACUUM INTO` sehingga app tetap bisa dipakai
 * saat backup berjalan, lalu memeriksa keutuhan hasilnya. Backup yang isinya sama persis dengan
 * backup terakhir tidak disimpan ulang; tiap jenis punya batas jumlah versi.
 */
class BackupService
{
    private const JENIS = ['perjam', 'harian', 'manual', 'sebelum-pulihkan'];

    /** Folder backup di flashdisk, dan berapa hari file di sana dipertahankan. */
    private const FOLDER_DISK = 'VerifikasiFaktur-Backup';

    private const HARI_SIMPAN_DISK = 7;

    public function __construct(private readonly RemovableDisks $disks) {}

    /**
     * Daftar flashdisk yang bisa dipilih untuk backup.
     *
     * @return array<int, array{drive: string, label: string, free: int}>
     */
    public function removableDisks(): array
    {
        return $this->disks->list();
    }

    /**
     * Salin backup terbaru + 7 backup harian terbaru ke flashdisk, lalu hapus file di flashdisk
     * yang lebih tua dari 7 hari. Mengembalikan daftar nama file yang disalin.
     *
     * @return array<int, string>
     */
    public function copyToDisk(string $drive): array
    {
        $tujuan = rtrim($drive, '/\\').DIRECTORY_SEPARATOR.self::FOLDER_DISK;
        if (! is_dir($tujuan)) {
            mkdir($tujuan, 0775, true);
        }

        $sumber = $this->files('harian');                 // harian terbaru dulu
        $sumber = array_slice($sumber, 0, self::HARI_SIMPAN_DISK);
        if ($terbaru = $this->latest()) {                  // + backup terbaru apa pun jenisnya
            array_unshift($sumber, $terbaru);
        }
        $sumber = array_values(array_unique($sumber));

        $disalin = [];
        foreach ($sumber as $path) {
            $nama = basename($path);
            copy($path, $tujuan.DIRECTORY_SEPARATOR.$nama);
            $disalin[] = $nama;
        }

        $this->rotateDisk($tujuan);

        return array_values(array_unique($disalin));
    }

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

    /** Cari path backup dari namanya (di semua folder jenis), atau null. */
    public function find(string $nama): ?string
    {
        $nama = basename($nama);
        foreach (self::JENIS as $jenis) {
            $path = $this->dir($jenis).DIRECTORY_SEPARATOR.$nama;
            if (is_file($path)) {
                return $path;
            }
        }

        return null;
    }

    /**
     * Pulihkan database dari sebuah backup. Membuat backup "sebelum-pulihkan" dulu, lalu menyalin
     * isi backup ke database aktif (ATTACH + salin tabel) sehingga jalan untuk file maupun :memory:.
     */
    public function restore(string $nama): void
    {
        $path = $this->find($nama);
        if ($path === null) {
            throw new \RuntimeException('Backup tidak ditemukan');
        }

        $this->create('sebelum-pulihkan');

        $pdo = DB::getPdo();
        $pdo->exec('PRAGMA foreign_keys=OFF');
        $pdo->exec('ATTACH '.$pdo->quote($path).' AS pulih');
        try {
            $tables = $pdo->query("SELECT name FROM pulih.sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'")
                ->fetchAll(\PDO::FETCH_COLUMN);
            $pdo->beginTransaction();
            foreach ($tables as $t) {
                $pdo->exec('DELETE FROM main."'.$t.'"');
                $pdo->exec('INSERT INTO main."'.$t.'" SELECT * FROM pulih."'.$t.'"');
            }
            $pdo->commit();
        } finally {
            $pdo->exec('DETACH pulih');
            $pdo->exec('PRAGMA foreign_keys=ON');
        }
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

    /** Backup paling baru dari semua jenis, atau null bila belum ada. */
    private function latest(): ?string
    {
        $terbaru = null;
        foreach (self::JENIS as $jenis) {
            if ($path = $this->files($jenis)[0] ?? null) {
                if ($terbaru === null || filemtime($path) > filemtime($terbaru)) {
                    $terbaru = $path;
                }
            }
        }

        return $terbaru;
    }

    /** Hapus file backup di flashdisk yang lebih tua dari batas simpan. */
    private function rotateDisk(string $tujuan): void
    {
        $batas = now()->subDays(self::HARI_SIMPAN_DISK)->timestamp;
        foreach (glob($tujuan.'/faktur-*.sqlite') ?: [] as $path) {
            if (filemtime($path) < $batas) {
                @unlink($path);
            }
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
