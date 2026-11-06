<?php

use App\Services\License\Keys;
use App\Services\License\LicenseFile;

/**
 * Form pembuat lisensi di laptop Ashira: ID komputer + keterangan → file .lic bertanda tangan, berlaku
 * selamanya, dicatat di riwayat. Tidak ikut installer (tools/ export-ignore).
 */
final class LicenseForm
{
    /**
     * @param  array<string, mixed>  $post
     * @return array{filename: string, content: string}
     */
    public static function handle(array $post, Keys $keys, string $historyFile): array
    {
        $id = strtolower(trim((string) ($post['id'] ?? '')));
        $untuk = trim((string) ($post['untuk'] ?? ''));

        if (! preg_match('/^[0-9a-f]{64}$/', $id)) {
            throw new InvalidArgumentException('ID komputer harus 64 karakter 0-9/a-f (salin persis dari halaman Aktivasi).');
        }
        if ($untuk === '') {
            throw new InvalidArgumentException('Isi keterangan lisensi (mis. "PT Mitra — komputer master").');
        }
        if (mb_strlen($untuk) > 100) {
            throw new InvalidArgumentException('Keterangan maksimal 100 karakter.');
        }
        if (! $keys->hasSecret()) {
            throw new InvalidArgumentException('Kunci rahasia tidak ada di laptop ini (storage/app/keys/secret.key).');
        }

        $content = (new LicenseFile($keys))->build(['machine_id' => $id, 'untuk' => $untuk]);

        $history = fopen($historyFile, 'a');
        fputcsv($history, [date('c'), $untuk, $id]);
        fclose($history);

        return ['filename' => 'lisensi '.self::safeName($untuk).'.lic', 'content' => $content];
    }

    /** Nama file aman untuk Windows: karakter selain huruf/angka/spasi/._()- diganti "-". */
    private static function safeName(string $text): string
    {
        $name = preg_replace('/-{2,}/', '-', preg_replace('/[^A-Za-z0-9 ._()-]/', '-', $text));

        return trim(preg_replace('/\s+/', ' ', $name), ' .-') ?: 'komputer';
    }

    /**
     * Baris riwayat terbaru dulu.
     *
     * @return list<array{0: string, 1: string, 2: string}>
     */
    public static function history(string $historyFile, int $limit = 10): array
    {
        if (! is_file($historyFile)) {
            return [];
        }
        $rows = array_map('str_getcsv', file($historyFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES));

        return array_slice(array_reverse($rows), 0, $limit);
    }
}
