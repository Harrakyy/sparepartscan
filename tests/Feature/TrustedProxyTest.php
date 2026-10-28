<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Dibuka lewat tunnel HTTPS: URL aset harus ikut alamat publik, bukan http://localhost. */
class TrustedProxyTest extends TestCase
{
    use RefreshDatabase;

    private const TUNNEL_HEADERS = [
        'X-Forwarded-Proto' => 'https',
        'X-Forwarded-Host' => 'contoh-tunnel.trycloudflare.com',
        'X-Forwarded-Port' => '443',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        // TrustProxies menyimpan daftar proxy secara statis; bersihkan antar tes.
        \Illuminate\Http\Middleware\TrustProxies::flushState();
    }

    private function bootWithProxies(?string $proxies): void
    {
        config(['app.trusted_proxies' => $proxies]);
        (new \App\Providers\AppServiceProvider($this->app))->boot();
    }

    public function test_asset_urls_follow_the_tunnel_when_proxies_are_trusted(): void
    {
        $this->bootWithProxies('*');

        $this->withHeaders(self::TUNNEL_HEADERS)->get('/gudang')
            ->assertOk()
            ->assertSee('https://contoh-tunnel.trycloudflare.com/css/app.css', false);
    }

    public function test_forwarded_headers_are_ignored_without_trusted_proxies(): void
    {
        $this->bootWithProxies(null);

        $this->withHeaders(self::TUNNEL_HEADERS)->get('/gudang')
            ->assertOk()
            ->assertDontSee('contoh-tunnel.trycloudflare.com', false);
    }
}
