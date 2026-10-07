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

        $this->get('/api/founders/pusula_portfoy/icon')
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png')
            ->assertSee('PNGDATA', false);

        // A second request is served from the cache without touching the CDN.
        $this->get('/api/founders/pusula_portfoy/icon')->assertOk();

        Http::assertSentCount(1);
    }

    public function test_a_missing_logo_is_negative_cached_so_the_cdn_is_not_rehit(): void
    {
        Http::fake([
            'storage.fintables.com/*' => Http::response('', 404),
        ]);

        $this->get('/api/founders/ak_portfoy/icon')->assertNotFound();
        $this->get('/api/founders/ak_portfoy/icon')->assertNotFound();

        // The first miss is remembered, so the second 404 never reaches the CDN.
        Http::assertSentCount(1);
        $this->assertFalse(Cache::get('founder-icon:ak_portfoy'));
    }

    public function test_an_invalid_slug_never_reaches_the_cdn(): void
    {
        Http::fake();

        $this->get('/api/founders/Not-A-Slug/icon')->assertNotFound();

        Http::assertNothingSent();
    }
}
