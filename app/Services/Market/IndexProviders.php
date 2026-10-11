<?php

namespace App\Services\Market;

/**
 * Index sources. TCMB's daily bulletin is the reference for FX (free, no key).
 * BIST indices, gram altın and gösterge faiz come from EVDS (needs a key) or
 * Yahoo. Each returns rows of ['name' => .., 'date' => .., 'value' => ..].
 */
class IndexProviders
{
    private const TCMB_SYMBOLS = ['USD' => 'USD', 'EUR' => 'EUR'];

    public function __construct(private HttpClient $http = new HttpClient()) {}

    /** @return array<int, string> weekdays between from..to inclusive */
    public static function eachDay(string $from, string $to): array
    {
        $days = [];
        $cursor = strtotime("{$from}T00:00:00Z");
        $end = strtotime("{$to}T00:00:00Z");
        while ($cursor <= $end) {
            $weekday = (int) gmdate('w', $cursor);
            if ($weekday !== 0 && $weekday !== 6) {
                $days[] = gmdate('Y-m-d', $cursor);
            }
            $cursor = strtotime('+1 day', $cursor);
        }
        return $days;
    }

    /* ── TCMB (FX) ─────────────────────────────────────────────────────── */

    private function tcmbBase(): string
    {
        return trim((string) config('ingest.tcmb.base_url')) ?: 'https://www.tcmb.gov.tr/kurlar';
    }

    private function isoToTcmbPath(string $date): string
    {
        [$year, $month, $day] = explode('-', $date);
        return "{$year}{$month}/{$day}{$month}{$year}";
    }

    private function readRate(string $xml, string $code): ?float
    {
        if (!preg_match('#<Currency[^>]*Kod="' . $code . '"[^>]*>([\s\S]*?)</Currency>#', $xml, $block)) {
            return null;
        }
        if (!preg_match('#<ForexSelling>([\d.]+)</ForexSelling>#', $block[1], $selling)) {
            return null;
        }
        preg_match('#<Unit>(\d+)</Unit>#', $block[1], $unit);

        $rate = (float) $selling[1];
        $per = isset($unit[1]) ? (int) $unit[1] : 1;
        return (is_finite($rate) && $per > 0) ? $rate / $per : null;
    }

    /** @return array<string, float> source symbol => value */
    public function tcmbRates(string $date): array
    {
        $url = "{$this->tcmbBase()}/{$this->isoToTcmbPath($date)}.xml";

        try {
            $xml = $this->http->requestText($url, ['accept' => 'application/xml', 'maxRetries' => 1]);
        } catch (UpstreamError $e) {
            // Holidays and weekends simply have no bulletin (404).
            if ($e->status === 404) {
                return [];
            }
            throw $e;
        }

        $rates = [];
        foreach (self::TCMB_SYMBOLS as $symbol) {
            $rate = $this->readRate($xml, $symbol);
            if ($rate !== null) {
                $rates[$symbol] = $rate;
            }
        }
        return $rates;
    }

    /** @return array<int, array{name:string,date:string,value:float}> */
    public function tcmbQuotes(string $from, string $to): array
    {
        $quotes = [];
        foreach (self::eachDay($from, $to) as $date) {
            foreach ($this->tcmbRates($date) as $symbol => $value) {
                $quotes[] = ['name' => $symbol, 'date' => $date, 'value' => $value];
            }
        }
        return $quotes;
    }

    /* ── EVDS ──────────────────────────────────────────────────────────── */

    public function evdsConfigured(): bool
    {
        return (bool) trim((string) config('ingest.tcmb.evds_key'));
    }

    /** @return array<int, array{name:string,date:string,value:float}> */
    public function evdsSeries(string $rawSymbol, string $from, string $to): array
    {
        $key = trim((string) config('ingest.tcmb.evds_key'));
        if ($key === '') {
            throw new \RuntimeException('TCMB_EVDS_API_TOKEN is not set');
        }

        [$series, $divisorStr] = array_pad(explode('/', $rawSymbol), 2, null);
        $divisor = $divisorStr ? (float) $divisorStr : 1;

        $toEvds = function (string $iso) {
            [$year, $month, $day] = explode('-', $iso);
            return "{$day}-{$month}-{$year}";
        };

        $base = trim((string) config('ingest.tcmb.evds_base_url'));
        $url = "{$base}/series=" . rawurlencode($series)
            . "&startDate={$toEvds($from)}&endDate={$toEvds($to)}&type=json";

        $payload = $this->http->requestJson($url, ['headers' => ['key' => $key], 'timeoutMs' => 20000]);

        $column = preg_replace('/[.-]/', '_', $series);
        $quotes = [];

        foreach (($payload['items'] ?? []) as $item) {
            $raw = $item[$column] ?? $item[$series] ?? null;
            $date = $this->evdsDate($item['Tarih'] ?? $item['TARIH'] ?? null);
            if (!$raw || $date === null) {
                continue;
            }

            $value = (float) str_replace(',', '.', (string) $raw) / $divisor;
            if (!is_finite($value)) {
                continue;
            }

            $quotes[] = ['name' => $rawSymbol, 'date' => $date, 'value' => $value];
        }

        return $quotes;
    }

    /**
     * Normalise an EVDS `Tarih` to an ISO `YYYY-MM-DD`. EVDS formats it by the
     * series' frequency: a daily series gives "DD-MM-YYYY", while a monthly one
     * (like the TÜFE/CPI level) gives "YYYY-M" — which a day-first parser would
     * otherwise drop, taking every reading with it. A monthly reading is dated
     * to the first of its month. Null when the shape is unrecognised.
     */
    private function evdsDate(?string $raw): ?string
    {
        $raw = trim((string) $raw);

        // Monthly: "YYYY-M" or "YYYY-MM".
        if (preg_match('/^(\d{4})-(\d{1,2})$/', $raw, $m)) {
            return sprintf('%04d-%02d-01', (int) $m[1], (int) $m[2]);
        }

        // Daily: "DD-MM-YYYY".
        if (preg_match('/^(\d{1,2})-(\d{1,2})-(\d{4})$/', $raw, $m)) {
            return sprintf('%04d-%02d-%02d', (int) $m[3], (int) $m[2], (int) $m[1]);
        }

        // Already ISO: "YYYY-MM-DD".
        if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $raw, $m)) {
            return sprintf('%04d-%02d-%02d', (int) $m[1], (int) $m[2], (int) $m[3]);
        }

        return null;
    }

    /* ── Yahoo ─────────────────────────────────────────────────────────── */

    /** @return array<int, array{name:string,date:string,value:float}> */
    public function yahooSeries(string $symbol, string $from, string $to): array
    {
        $period1 = strtotime("{$from}T00:00:00Z");
        $period2 = strtotime("{$to}T23:59:59Z");

        $url = 'https://query1.finance.yahoo.com/v8/finance/chart/' . rawurlencode($symbol)
            . "?period1={$period1}&period2={$period2}&interval=1d";

        $payload = $this->http->requestJson($url, [
            'timeoutMs' => 15000,
            'headers' => ['user-agent' => 'Mozilla/5.0'],
        ]);

        $result = $payload['chart']['result'][0] ?? null;
        if (!$result || empty($result['timestamp'])) {
            return [];
        }

        $closes = $result['indicators']['quote'][0]['close'] ?? [];
        $quotes = [];

        foreach ($result['timestamp'] as $i => $ts) {
            $close = $closes[$i] ?? null;
            if ($close === null || !is_finite((float) $close)) {
                continue;
            }
            $quotes[] = [
                'name' => $symbol,
                'date' => gmdate('Y-m-d', $ts),
                'value' => round((float) $close, 4),
            ];
        }

        return $quotes;
    }

    /* ── Fixture ───────────────────────────────────────────────────────── */

    /** Deterministic stand-in series so local development shows every card. */
    public function fixtureQuotes(string $name, int $seed, string $from, string $to): array
    {
        $days = self::eachDay($from, $to);
        $state = $seed * 9301;
        $value = $seed * 137.5;
        $quotes = [];

        foreach ($days as $date) {
            $state = ($state * 9301 + 49297) % 233280;
            $drift = $state / 233280 - 0.48;
            $value = max(1, $value * (1 + $drift * 0.012));
            $quotes[] = ['name' => $name, 'date' => $date, 'value' => round($value, 4)];
        }

        return $quotes;
    }
}
