<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SymbolIconTest extends TestCase
{
    public function test_a_fetched_logo_is_served_and_cached_so_the_cdn_is_hit_once(): void
    {
        Http::fake([
            'web-api.forinvestcdn.com/*' => Http::response('<svg/>', 200, ['Content-Type' => 'image/svg+xml']),
        ]);

        $this->get('/api/symbols/THYAO/icon')
            ->assertOk()
            ->assertHeader('Content-Type', 'image/svg+xml')
            ->assertSee('<svg/>', false);

        // A second request is served from the cache without touching the CDN.
        $this->get('/api/symbols/THYAO/icon')->assertOk();

        Http::assertSentCount(1);
    }

    public function test_a_missing_logo_is_negative_cached_so_the_cdn_is_not_rehit(): void
    {
        Http::fake([
            'web-api.forinvestcdn.com/*' => Http::response('', 404),
        ]);

        $this->get('/api/symbols/NOPE/icon')->assertNotFound();
        $this->get('/api/symbols/NOPE/icon')->assertNotFound();

        // The first miss is remembered, so the second 404 never reaches the CDN.
        Http::assertSentCount(1);
        $this->assertFalse(Cache::get('symbol-icon:NOPE'));
    }

    public function test_an_invalid_ticker_never_reaches_the_cdn(): void
    {
        Http::fake();

        $this->get('/api/symbols/not-a-ticker/icon')->assertNotFound();

        Http::assertNothingSent();
    }
}
