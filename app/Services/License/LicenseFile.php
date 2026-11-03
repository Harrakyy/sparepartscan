<?php

namespace App\Services\License;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Date;

/**
 * File lisensi `license.lic`: payload JSON (machine_id, untuk, tanggal) + signature Ed25519.
 * Dibuat di laptop Ashira (`license:buat`), diperiksa offline di komputer pelanggan tiap kali jalan.
 */
class LicenseFile
{
    private const FLAGS = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;

    public function __construct(private readonly Keys $keys, private readonly ?string $path = null)
    {
    }

    public function path(): string
    {
        return $this->path ?? storage_path('app/license.lic');
    }

    /** Terbitkan lisensi: bangun payload, tandatangani, tulis ke file, kembalikan isinya. */
    public function issue(array $data): string
    {
        $payload = [
            'machine_id' => (string) ($data['machine_id'] ?? ''),
            'untuk' => $data['untuk'] ?? null,
            'issued_at' => Date::now()->toIso8601String(),
            'expires_at' => (string) ($data['expires_at'] ?? ''),
        ];

        $content = json_encode([
            'payload' => $payload,
            'signature' => $this->keys->sign($this->canonical($payload)),
        ], self::FLAGS | JSON_PRETTY_PRINT);

        file_put_contents($this->path(), $content);

        return $content;
    }

    /** @return array{payload: array, signature: string} */
    public function parse(string $content): array
    {
        $data = json_decode($content, true);
        if (! is_array($data) || ! isset($data['payload'], $data['signature'])) {
            throw new \RuntimeException('File lisensi tidak valid.');
        }

        return ['payload' => $data['payload'], 'signature' => (string) $data['signature']];
    }

    /**
     * Periksa lisensi: tanda tangan dulu, lalu komputer, lalu masa berlaku.
     *
     * @return array{ok: bool, reason?: string}
     */
    public function valid(array $license, string $machineId, Carbon $now): array
    {
        $payload = $license['payload'] ?? [];
        $signature = (string) ($license['signature'] ?? '');

        if (! is_array($payload) || ! $this->keys->verify($this->canonical($payload), $signature)) {
            return ['ok' => false, 'reason' => 'signature'];
        }
        if (! hash_equals((string) ($payload['machine_id'] ?? ''), $machineId)) {
            return ['ok' => false, 'reason' => 'machine'];
        }
        $expires = (string) ($payload['expires_at'] ?? '');
        if ($expires !== '') {
            try {
                $expiredAt = Carbon::parse($expires)->endOfDay();
            } catch (\Throwable) {
                return ['ok' => false, 'reason' => 'expired']; // Tanggal tak terbaca: anggap tak berlaku, jangan 500.
            }
            if ($now->gt($expiredAt)) {
                return ['ok' => false, 'reason' => 'expired'];
            }
        }

        return ['ok' => true];
    }

    private function canonical(array $payload): string
    {
        return json_encode($payload, self::FLAGS);
    }
}
