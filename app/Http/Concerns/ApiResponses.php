<?php

namespace App\Http\Concerns;

use Illuminate\Http\JsonResponse;

/**
 * Response helpers that reproduce the Next.js client's `src/lib/api/response.ts`
 * and `src/lib/api/auth.ts` byte-for-byte: success returns the raw data object
 * (no envelope), errors return `{ "error": "<message>" }` with the matching
 * status code and `cache-control: no-store`.
 */
trait ApiResponses
{
    /** Public market data is identical for everyone, so it is cacheable at the edge. */
    private const PUBLIC_CACHE = 'public, s-maxage=60, stale-while-revalidate=300';

    /** A cacheable success — the client's `json(...)`. */
    protected function cached(mixed $data, int $status = 200, array $headers = []): JsonResponse
    {
        return response()->json($data, $status, array_merge(
            ['Cache-Control' => self::PUBLIC_CACHE],
            $headers,
        ));
    }

    /** A success with no explicit cache header — the client's `NextResponse.json(...)`. */
    protected function plain(mixed $data, int $status = 200, array $headers = []): JsonResponse
    {
        return response()->json($data, $status, $headers);
    }

    /** A success that must never be cached — used by /health. */
    protected function noStore(mixed $data, int $status = 200): JsonResponse
    {
        return response()->json($data, $status, ['Cache-Control' => 'no-store']);
    }

    protected function badRequest(string $message): JsonResponse
    {
        return response()->json(['error' => $message], 400, ['Cache-Control' => 'no-store']);
    }

    protected function notFound(string $message = 'not found'): JsonResponse
    {
        return response()->json(['error' => $message], 404, ['Cache-Control' => 'no-store']);
    }

    protected function unauthorizedResponse(string $message = 'unauthorized'): JsonResponse
    {
        return response()->json(['error' => $message], 401, ['Cache-Control' => 'no-store']);
    }
}
