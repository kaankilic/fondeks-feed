<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
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

    public function icon(Request $request, string $code)
    {
        // Only a plain BIST ticker is accepted, and it travels to the fixed
        // upstream host as a single query parameter — nothing the caller sends
        // can redirect the request elsewhere (SSRF) or inject a command.
        $code = strtoupper(trim($code));
        abort_unless(preg_match('/^[A-Z0-9]{1,12}$/', $code), 404);

        // Cache only successful fetches, so a transient upstream failure is
        // retried on the next request rather than pinned for the whole TTL.
        $cacheKey = "symbol-icon:{$code}";
        $icon = Cache::get($cacheKey);

        if ($icon === null) {
            try {
                $response = Http::timeout(10)->get(self::UPSTREAM, ['code' => $code]);
            } catch (\Throwable) {
                abort(404);
            }

            $type = (string) $response->header('Content-Type');

            // Forward only a real image; never let the proxy relay HTML/JS from
            // our origin.
            if (! $response->successful() || $response->body() === '' || ! str_starts_with($type, 'image/')) {
                abort(404);
            }

            $icon = ['body' => $response->body(), 'type' => explode(';', $type)[0]];
            Cache::put($cacheKey, $icon, now()->addDays(self::TTL_DAYS));
        }

        // The upstream logos are SVG, which can embed scripts. Served via <img>
        // they never execute, but the CSP + sandbox neutralises any script even
        // if the URL is opened directly, and nosniff stops MIME confusion.
        return response($icon['body'])
            ->header('Content-Type', $icon['type'])
            ->header('Cache-Control', 'public, max-age=' . (self::TTL_DAYS * 86400))
            ->header('X-Content-Type-Options', 'nosniff')
            ->header('Content-Security-Policy', "default-src 'none'; style-src 'unsafe-inline'; sandbox");
    }
}
