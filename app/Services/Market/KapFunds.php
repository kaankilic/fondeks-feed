<?php

namespace App\Services\Market;

/**
 * Fund records from KAP — the fund's own page rather than its filings.
 * A fund's launch date is not in TEFAS; KAP states it on each fund's record
 * page. Pages are React Server Components with no API, so this reads the page
 * and matches on KAP's own field keys. A launch date never changes, so a fund
 * is read once and never again.
 */
class KapFunds
{
    private const LANG = 'tr';

    private string $base;
    private HttpClient $http;

    public function __construct()
    {
        $this->base = rtrim((string) config('ingest.kap.base_url'), '/');
        $this->http = new HttpClient();
    }

    private function headers(string $referer): array
    {
        return [
            'referer' => $referer,
            'accept-language' => 'tr-TR,tr;q=0.9',
            'user-agent' => config('ingest.kap.user_agent'),
        ];
    }

    /** Flight data embedded in HTML escapes its quotes; served alone it does not. */
    private function unescapeFlight(string $payload): string
    {
        return str_replace('\\"', '"', $payload);
    }

    private function toIsoDate(string $value): ?string
    {
        if (!preg_match('#^(\d{2})[./](\d{2})[./](\d{4})$#', trim($value), $m)) {
            return null;
        }
        $iso = "{$m[3]}-{$m[2]}-{$m[1]}";
        return strtotime($iso) === false ? null : $iso;
    }

    /** @return array<string, string> fund code => KAP fundOid */
    public function fetchFundDirectory(): array
    {
        $payload = $this->unescapeFlight(
            $this->http->requestText("{$this->base}/" . self::LANG . '/YatirimFonlari/ALL', [
                'timeoutMs' => config('ingest.kap.directory_timeout_ms'),
                'headers' => $this->headers("{$this->base}/" . self::LANG),
            ]),
        );

        $directory = [];
        preg_match_all(
            '/"fundOid"\s*:\s*"([0-9a-fA-F]{16,40})"[\s\S]{0,200}?"fundCode"\s*:\s*"([A-Z0-9]{2,8})"/',
            $payload,
            $matches,
            PREG_SET_ORDER,
        );

        foreach ($matches as $match) {
            [, $oid, $code] = $match;
            if (!isset($directory[$code])) {
                $directory[$code] = $oid;
            }
        }

        return $directory;
    }

    /** The day a fund was first offered, or null when KAP has no date. */
    public function fetchFundInception(string $oid): ?string
    {
        $payload = $this->unescapeFlight(
            $this->http->requestText("{$this->base}/" . self::LANG . "/fon-bilgileri/genel/{$oid}", [
                'timeoutMs' => config('ingest.kap.timeout_ms'),
                'headers' => $this->headers("{$this->base}/" . self::LANG . "/fon-bilgileri/ozet/{$oid}"),
            ]),
        );

        preg_match_all(
            '/td_halkaArzTarihleri[\s\S]{0,300}?"children"\s*:\s*"([^"]{6,12})"/',
            $payload,
            $matches,
        );

        $offerings = array_values(array_filter(array_map(fn ($v) => $this->toIsoDate($v), $matches[1] ?? [])));
        sort($offerings);

        if (!empty($offerings)) {
            return $offerings[0];
        }

        foreach (['kpy81_acc1_kurulus_tarihi', 'kpy81_acc1_fon_kurulus_tarih'] as $key) {
            if (preg_match('/"div","' . $key . '"[\s\S]{0,1200}?"children"\s*:\s*"([^"]{6,12})"/', $payload, $m)) {
                $date = $this->toIsoDate($m[1]);
                if ($date) {
                    return $date;
                }
            }
        }

        return null;
    }
}
