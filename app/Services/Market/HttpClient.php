<?php

namespace App\Services\Market;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Small resilient HTTP layer for upstream market data: bounded timeouts,
 * retries with exponential backoff and jitter, and a simple sliding-window
 * rate limit so a backfill cannot hammer the source.
 *
 * PHP requests run synchronously, so the TS ConcurrencyLimiter/Promise.all
 * become plain sequential loops in the callers; only the rate limit needs
 * modelling here.
 */
class HttpClient
{
    /** @var array<int, float> epoch-ms of recent request starts */
    private array $starts = [];
    private float $blockedUntil = 0;

    public function __construct(
        private readonly int $rateLimit = 0,
        private readonly int $rateWindowMs = 60000,
        /** Throw immediately on HTTP 429 instead of cooling down and retrying. */
        private readonly bool $failFast429 = false,
    ) {}

    private function nowMs(): float
    {
        return microtime(true) * 1000;
    }

    /** Sliding-window admission: at most $rateLimit starts in any window. */
    private function admit(): void
    {
        if ($this->rateLimit <= 0) {
            return;
        }

        while (true) {
            $now = $this->nowMs();

            if ($now < $this->blockedUntil) {
                usleep((int) (($this->blockedUntil - $now) * 1000));
                continue;
            }

            $cutoff = $now - $this->rateWindowMs;
            $this->starts = array_values(array_filter($this->starts, fn ($s) => $s > $cutoff));

            if (count($this->starts) < $this->rateLimit) {
                $this->starts[] = $now;
                return;
            }

            usleep((int) (($this->starts[0] - $cutoff) * 1000));
        }
    }

    /** Holds every caller back until $ms from now (used after a 429). */
    public function pause(int $ms): void
    {
        $this->blockedUntil = max($this->blockedUntil, $this->nowMs() + $ms);
    }

    /**
     * @param array{method?:string,body?:mixed,headers?:array,timeoutMs?:int,maxRetries?:int,accept?:string} $options
     */
    public function requestJson(string $url, array $options = []): mixed
    {
        return $this->request($url, $options, fn ($response) => $response->json());
    }

    public function requestText(string $url, array $options = []): string
    {
        $options['accept'] ??= 'text/html,*/*';
        return $this->request($url, $options, fn ($response) => $response->body());
    }

    public function requestBytes(string $url, array $options = []): string
    {
        $options['accept'] ??= 'application/pdf,*/*';
        return $this->request($url, $options, fn ($response) => $response->body());
    }

    private function request(string $url, array $options, callable $read): mixed
    {
        $method = $options['method'] ?? (isset($options['body']) ? 'POST' : 'GET');
        $timeoutMs = $options['timeoutMs'] ?? 20000;
        $maxRetries = $options['maxRetries'] ?? 3;
        $retryBaseMs = $options['retryBaseMs'] ?? 500;
        $cooldownMs = $options['cooldownMs'] ?? 60000;
        $headers = $options['headers'] ?? [];
        $accept = $options['accept'] ?? 'application/json';
        $body = $options['body'] ?? null;

        $lastError = null;
        $cooldown = null;

        for ($attempt = 1; $attempt <= $maxRetries + 1; $attempt++) {
            $this->admit();

            try {
                $request = Http::withHeaders(array_merge(['accept' => $accept], $headers))
                    ->timeout((int) ceil($timeoutMs / 1000))
                    ->withOptions(['stream' => false]);

                if ($body !== null) {
                    $response = $request
                        ->withBody(json_encode($body), 'application/json; charset=UTF-8')
                        ->send($method, $url);
                } else {
                    $response = $request->send($method, $url);
                }

                if ($response->failed()) {
                    $status = $response->status();

                    // A 429 from an IP-blocking source (KAP) will not clear by
                    // retrying — throw at once so the job fails and its chain
                    // stops, rather than hammering a banned endpoint.
                    if ($status === 429 && $this->failFast429) {
                        throw new UpstreamError(
                            "{$method} {$url} failed with 429 (rate limited / blocked)",
                            429,
                            null,
                        );
                    }

                    if ($status === 429) {
                        $cooldown = $this->retryAfterMs($response) ?? $cooldownMs;
                        $this->pause($cooldown);
                    }
                    throw new UpstreamError(
                        "{$method} {$url} failed with {$status}",
                        $status,
                        mb_substr($response->body(), 0, 500),
                    );
                }

                return $read($response);
            } catch (\Throwable $error) {
                $lastError = $error;
                $canRetry = $attempt <= $maxRetries && $this->isRetryable($error);
                if (!$canRetry) {
                    break;
                }

                $wait = $cooldown === null
                    ? $this->backoffMs($attempt, $retryBaseMs)
                    : $cooldown + $this->backoffMs($attempt, $retryBaseMs);
                usleep($wait * 1000);
                $cooldown = null;
            }
        }

        if ($lastError instanceof UpstreamError) {
            throw $lastError;
        }
        throw new UpstreamError("{$method} {$url} failed: " . ($lastError?->getMessage() ?? 'unknown'));
    }

    private function isRetryable(\Throwable $error): bool
    {
        if ($error instanceof UpstreamError) {
            if ($error->status === null) {
                return true;
            }
            if ($error->status === 429) {
                return !$this->failFast429;
            }
            return $error->status >= 500;
        }
        return true;
    }

    private function backoffMs(int $attempt, int $base): int
    {
        $exponential = $base * (2 ** ($attempt - 1));
        return (int) round(mt_rand() / mt_getrandmax() * min($exponential, 30000));
    }

    private function retryAfterMs($response): ?int
    {
        $header = $response->header('retry-after');
        if (!$header) {
            return null;
        }
        if (is_numeric($header)) {
            return max(0, (int) $header * 1000);
        }
        $at = strtotime($header);
        return $at === false ? null : max(0, ($at * 1000) - (int) $this->nowMs());
    }
}
