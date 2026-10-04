<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Proxies a BIST ticker's logo from Forinvest's CDN so the frontend can show it
 * from our own origin (no third-party CORS, and the upstream URL stays hidden).
 * The response is cached, and only an image is ever passed through.
 */
class SymbolController extends Controller
{
    private const UPSTREAM = 'https://web-api.forinvestcdn.com/definitions/icon';

    private const TTL_DAYS = 7;

    // A ticker with no logo upstream is remembered too, for a shorter window, so
    // a missing icon doesn't re-hit the CDN on every single request.
    private const MISS_TTL_HOURS = 6;

    // Sentinel stored for a cached miss, so it is distinguishable from an
    // uncached ticker (Cache::get's null) without a second cache round-trip.
    private const MISS = false;

    public function icon(Request $request, string $code)
    {
        // Only a plain BIST ticker is accepted, and it travels to the fixed
        // upstream host as a single query parameter — nothing the caller sends
        // can redirect the request elsewhere (SSRF) or inject a command.
        $code = strtoupper(trim($code));
        abort_unless(preg_match('/^[A-Z0-9]{1,12}$/', $code), 404);

        $cacheKey = "symbol-icon:{$code}";
        $icon = Cache::get($cacheKey);

        if ($icon === self::MISS) {
            abort(404);
        }

        if ($icon === null) {
            $icon = $this->resolve($code, $cacheKey);
        }

        // The upstream logos are SVG, which can embed scripts. Served via <img>
        // they never execute, but the CSP + sandbox neutralises any script even
        // if the URL is opened directly, and nosniff stops MIME confusion.
        return response($icon['body'])
            ->header('Content-Type', $icon['type'])
            ->header('Cache-Control', 'public, max-age='.(self::TTL_DAYS * 86400))
            ->header('X-Content-Type-Options', 'nosniff')
            ->header('Content-Security-Policy', "default-src 'none'; style-src 'unsafe-inline'; sandbox");
    }

    /**
     * Fetch the logo from the CDN and cache it. A short-lived lock collapses a
     * burst of concurrent misses for the same ticker into a single upstream
     * fetch rather than one per request; if the lock can't be taken in time we
     * fetch anyway rather than fail the request.
     *
     * @return array{body: string, type: string}
     */
    private function resolve(string $code, string $cacheKey): array
    {
        $lock = Cache::lock("{$cacheKey}:lock", 15);

        try {
            $lock->block(10);
        } catch (LockTimeoutException) {
            return $this->fetch($code, $cacheKey);
        }

        try {
            // Another request may have populated the cache while we waited for
            // the lock — honour it instead of hitting the CDN again.
            $icon = Cache::get($cacheKey);

            if ($icon === self::MISS) {
                abort(404);
            }

            return $icon ?? $this->fetch($code, $cacheKey);
        } finally {
            $lock->release();
        }
    }

    /**
     * @return array{body: string, type: string}
     */
    private function fetch(string $code, string $cacheKey): array
    {
        try {
            $response = Http::timeout(10)->get(self::UPSTREAM, ['code' => $code]);
        } catch (\Throwable) {
            $response = null;
        }

        $type = $response ? (string) $response->header('Content-Type') : '';

        // Forward only a real image; never let the proxy relay HTML/JS from our
        // origin. A failure is negative-cached briefly so repeated requests for
        // a logo the CDN doesn't have stop hammering it.
        if (! $response || ! $response->successful() || $response->body() === '' || ! str_starts_with($type, 'image/')) {
            Cache::put($cacheKey, self::MISS, now()->addHours(self::MISS_TTL_HOURS));
            abort(404);
        }

        $icon = ['body' => $response->body(), 'type' => explode(';', $type)[0]];
        Cache::put($cacheKey, $icon, now()->addDays(self::TTL_DAYS));

        return $icon;
    }
}
