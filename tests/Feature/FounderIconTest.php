<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FounderIconTest extends TestCase
{
    public function test_a_fetched_logo_is_served_and_cached_so_the_cdn_is_hit_once(): void
    {
        Http::fake([
            'storage.fintables.com/*' => Http::response('PNGDATA', 200, ['Content-Type' => 'image/png']),
        ]);

        // A slug with no locally stored logo falls through to the CDN proxy.
        $this->get('/api/founders/zzz_fetch_portfoy/icon')
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png')
            ->assertSee('PNGDATA', false);

        // A second request is served from the cache without touching the CDN.
        $this->get('/api/founders/zzz_fetch_portfoy/icon')->assertOk();

        Http::assertSentCount(1);
    }

    public function test_a_locally_stored_logo_is_served_from_disk_without_touching_the_cdn(): void
    {
        Http::fake();

        $path = public_path('founder-logos/test_fixture_portfoy.png');
        file_put_contents($path, base64_decode(
            // 1×1 transparent PNG.
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=='
        ));

        try {
            $this->get('/api/founders/test_fixture_portfoy/icon')
                ->assertOk()
                ->assertHeader('Content-Type', 'image/png');
        } finally {
            @unlink($path);
        }

        // The stored file shadows the proxy entirely — the CDN is never reached.
        Http::assertNothingSent();
    }

    public function test_a_missing_logo_is_negative_cached_so_the_cdn_is_not_rehit(): void
    {
        Http::fake([
            'storage.fintables.com/*' => Http::response('', 404),
        ]);

        $this->get('/api/founders/zzz_miss_portfoy/icon')->assertNotFound();
        $this->get('/api/founders/zzz_miss_portfoy/icon')->assertNotFound();

        // The first miss is remembered, so the second 404 never reaches the CDN.
        Http::assertSentCount(1);
        $this->assertFalse(Cache::get('founder-icon:zzz_miss_portfoy'));
    }

    public function test_an_invalid_slug_never_reaches_the_cdn(): void
    {
        Http::fake();

        $this->get('/api/founders/Not-A-Slug/icon')->assertNotFound();

        Http::assertNothingSent();
    }
}
