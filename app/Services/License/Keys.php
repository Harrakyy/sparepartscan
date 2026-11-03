<?php

namespace App\Services\License;

/**
 * Pasangan kunci Ed25519 untuk lisensi. Kunci rahasia (secret.key) hanya ada di laptop Ashira
 * dan dipakai `license:buat` untuk menandatangani lisensi; kunci publik (public.key) ikut aplikasi
 * dan dipakai memverifikasi lisensi di komputer pelanggan. Secret.key TIDAK PERNAH ikut repo/installer.
 */
class Keys
{
    public function __construct(private readonly ?string $dir = null) {}

    /** Buat sepasang kunci baru; menolak bila kunci sudah ada agar kunci lama tak tertimpa. */
    public function generate(): void
    {
        if ($this->exists()) {
            throw new \RuntimeException('Kunci sudah ada. Hapus dulu bila benar-benar ingin membuat yang baru.');
        }

        $pair = sodium_crypto_sign_keypair();
        file_put_contents($this->secretPath(), base64_encode(sodium_crypto_sign_secretkey($pair)));
        file_put_contents($this->publicPath(), base64_encode(sodium_crypto_sign_publickey($pair)));
        @chmod($this->secretPath(), 0600);
    }

    public function exists(): bool
    {
        return is_file($this->secretPath()) || is_file($this->publicPath());
    }

    /** Tanda tangan detached Ed25519 (base64) atas pesan, memakai kunci rahasia. */
    public function sign(string $msg): string
    {
        $secret = base64_decode(trim((string) file_get_contents($this->secretPath())));

        return base64_encode(sodium_crypto_sign_detached($msg, $secret));
    }

    /** Verifikasi tanda tangan (base64) atas pesan, memakai kunci publik. */
    public function verify(string $msg, string $sig): bool
    {
        if (! is_file($this->publicPath())) {
            return false;
        }
        $pub = base64_decode(trim((string) file_get_contents($this->publicPath())));
        $raw = base64_decode($sig, true);
        if ($raw === false) {
            return false;
        }

        try {
            return sodium_crypto_sign_verify_detached($raw, $msg, $pub);
        } catch (\SodiumException) {
            return false;
        }
    }

    private function dir(): string
    {
        $dir = $this->dir ?? storage_path('app/keys');
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        return $dir;
    }

    private function secretPath(): string
    {
        return $this->dir().DIRECTORY_SEPARATOR.'secret.key';
    }

    private function publicPath(): string
    {
        return $this->dir().DIRECTORY_SEPARATOR.'public.key';
    }
}
