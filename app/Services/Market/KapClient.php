<?php

namespace App\Services\Market;

use Illuminate\Support\Facades\Log;

/**
 * Fund portfolio disclosures from KAP (Kamuyu Aydınlatma Platformu).
 *
 * Every call goes through the site's own /{lang}/api proxy. The disclosure
 * query takes yyyy-MM-dd dates and caps a response at 2000 rows with no paging,
 * so discovery walks one day at a time.
 */
class KapClient
{
    public const PORTFOLIO_REPORT_SUBJECT = 'Portföy Dağılım Raporu';
    private const RESPONSE_ROW_CAP = 2000;
    private const LANG = 'tr';

    private string $base;
    private HttpClient $http;

    public function __construct()
    {
        $this->base = rtrim((string) config('ingest.kap.base_url'), '/');
        $this->http = new HttpClient(
            config('ingest.kap.rate_limit'),
            config('ingest.kap.rate_window_ms'),
            failFast429: true,
        );
    }

    private function timeoutMs(): int
    {
        return config('ingest.kap.timeout_ms');
    }

    private function headers(?string $referer = null): array
    {
        return [
            'referer' => $referer ?? "{$this->base}/" . self::LANG,
            'accept-language' => 'tr-TR,tr;q=0.9',
            'user-agent' => config('ingest.kap.user_agent'),
        ];
    }

    private function daysBetween(string $from, string $to): array
    {
        $days = [];
        $cursor = strtotime("{$from}T00:00:00Z");
        $end = strtotime("{$to}T00:00:00Z");
        while ($cursor <= $end) {
            $days[] = gmdate('Y-m-d', $cursor);
            $cursor = strtotime('+1 day', $cursor);
        }
        return $days;
    }

    /** "03.08.2026 19:10:39" — KAP publishes local Istanbul time, UTC+3. */
    private function parsePublishDate(?string $value): ?int
    {
        if (!$value) {
            return null;
        }
        if (!preg_match('/^(\d{2})\.(\d{2})\.(\d{4})\s+(\d{2}):(\d{2}):(\d{2})$/', trim($value), $m)) {
            return null;
        }
        $at = strtotime("{$m[3]}-{$m[2]}-{$m[1]}T{$m[4]}:{$m[5]}:{$m[6]}+03:00");
        return $at === false ? null : $at;
    }

    private function periodFrom(array $row): ?string
    {
        $year = $row['year'] ?? null;
        $period = $row['period'] ?? null;
        if (!$year || !$period || $period < 1 || $period > 12) {
            return null;
        }
        return "{$year}-" . str_pad((string) $period, 2, '0', STR_PAD_LEFT) . '-01';
    }

    /** One day of fund disclosures. */
    private function fetchDay(string $day): array
    {
        $rows = $this->http->requestJson("{$this->base}/" . self::LANG . '/api/disclosure/funds/byCriteria', [
            'method' => 'POST',
            'body' => [
                'fromDate' => $day, 'toDate' => $day,
                'fundTypeList' => [], 'mkkMemberOidList' => [], 'fundOidList' => [],
                'passiveFundOidList' => [], 'disclosureClass' => '', 'isLate' => '',
                'subjectList' => [], 'discIndex' => [], 'fromSrc' => false, 'srcCategory' => '',
            ],
            'timeoutMs' => $this->timeoutMs(),
            'headers' => $this->headers("{$this->base}/" . self::LANG . '/bildirim-sorgu'),
        ]);

        if (!is_array($rows)) {
            return [];
        }

        if (count($rows) >= self::RESPONSE_ROW_CAP) {
            Log::warning("[kap] {$day} returned " . count($rows) . ' rows — at the response cap, likely truncated');
        }

        return $rows;
    }

    /** @return array<int, array> portfolio reports published between two dates */
    public function listPortfolioReports(string $from, string $to): array
    {
        $reports = [];

        foreach ($this->daysBetween($from, $to) as $day) {
            foreach ($this->fetchDay($day) as $row) {
                if (($row['subject'] ?? null) !== self::PORTFOLIO_REPORT_SUBJECT) {
                    continue;
                }
                $period = $this->periodFrom($row);
                $publishedAt = $this->parsePublishDate($row['publishDate'] ?? null);
                $code = isset($row['fundCode']) ? strtoupper(trim((string) $row['fundCode'])) : null;
                $index = $row['disclosureIndex'] ?? null;

                if (!$index || !$period || !$publishedAt || !$code) {
                    continue;
                }
                if (empty($row['attachmentCount'])) {
                    continue;
                }

                $existing = $reports[$index] ?? null;
                if ($existing && $existing['publishedAt'] >= $publishedAt) {
                    continue;
                }

                $reports[$index] = [
                    'disclosureIndex' => $index,
                    'fundCode' => $code,
                    'fundTitle' => isset($row['kapTitle']) ? trim((string) $row['kapTitle']) : $code,
                    'period' => $period,
                    'publishedAt' => $publishedAt,
                    'isLate' => ($row['isLate'] ?? null) === true,
                    'attachmentCount' => $row['attachmentCount'],
                ];
            }
        }

        $values = array_values($reports);
        usort($values, fn ($a, $b) => $a['publishedAt'] <=> $b['publishedAt']);
        return $values;
    }

    /** @return array<int, array> every fund disclosure of every subject */
    public function listDisclosures(string $from, string $to): array
    {
        $disclosures = [];

        foreach ($this->daysBetween($from, $to) as $day) {
            foreach ($this->fetchDay($day) as $row) {
                $publishedAt = $this->parsePublishDate($row['publishDate'] ?? null);
                $code = isset($row['fundCode']) ? strtoupper(trim((string) $row['fundCode'])) : null;
                $subject = isset($row['subject']) ? trim((string) $row['subject']) : null;
                $index = $row['disclosureIndex'] ?? null;

                if (!$index || !$publishedAt || !$code || !$subject) {
                    continue;
                }

                $existing = $disclosures[$index] ?? null;
                if ($existing && $existing['publishedAt'] >= $publishedAt) {
                    continue;
                }

                $disclosures[$index] = [
                    'disclosureIndex' => $index,
                    'fundCode' => $code,
                    'fundTitle' => isset($row['kapTitle']) ? trim((string) $row['kapTitle']) : $code,
                    'subject' => $subject,
                    'publishedAt' => $publishedAt,
                    'isLate' => ($row['isLate'] ?? null) === true,
                    'attachmentCount' => $row['attachmentCount'] ?? 0,
                ];
            }
        }

        $values = array_values($disclosures);
        usort($values, fn ($a, $b) => $a['publishedAt'] <=> $b['publishedAt']);
        return $values;
    }

    public function disclosurePageUrl(int $disclosureIndex): string
    {
        return "{$this->base}/" . self::LANG . "/Bildirim/{$disclosureIndex}";
    }

    public function attachmentUrl(string $objId): string
    {
        return "{$this->base}/" . self::LANG . "/api/file/download/{$objId}";
    }

    /** @return array<int, array> attachments on one disclosure */
    public function fetchAttachments(int $disclosureIndex): array
    {
        $payload = $this->http->requestJson(
            "{$this->base}/" . self::LANG . "/api/notification/attachment-detail/{$disclosureIndex}",
            [
                'timeoutMs' => $this->timeoutMs(),
                'headers' => $this->headers("{$this->base}/" . self::LANG . "/Bildirim/{$disclosureIndex}"),
            ],
        );

        $entries = array_is_list($payload ?? []) ? $payload : [$payload];
        $attachments = [];

        foreach ($entries as $entry) {
            $disclosure = $entry['disclosure'] ?? null;
            $found = $disclosure['disclosureBasic']['attachments']
                ?? $disclosure['attachments']
                ?? $entry['attachments']
                ?? [];
            foreach ($found as $a) {
                $attachments[] = $a;
            }
        }

        return $attachments;
    }

    /** Locates a disclosure's report PDF without downloading it. */
    public function resolveReportDocument(int $disclosureIndex): ?array
    {
        $attachments = $this->fetchAttachments($disclosureIndex);

        $pdf = null;
        foreach ($attachments as $a) {
            $ext = strtolower((string) ($a['fileExtension'] ?? ''));
            $name = strtolower((string) ($a['fileName'] ?? ''));
            if ($ext === 'pdf' || str_ends_with($name, '.pdf')) {
                $pdf = $a;
                break;
            }
        }

        if (!$pdf || empty($pdf['objId'])) {
            return null;
        }

        return [
            'objId' => $pdf['objId'],
            'fileName' => trim((string) ($pdf['fileName'] ?? '')) ?: "{$disclosureIndex}.pdf",
            'url' => $this->attachmentUrl($pdf['objId']),
        ];
    }

    /** Downloads one attachment, unwrapped and ready to read. */
    public function fetchAttachmentBytes(string $objId, ?int $disclosureIndex = null): string
    {
        $url = $this->attachmentUrl($objId);
        $referer = $disclosureIndex === null
            ? "{$this->base}/" . self::LANG
            : "{$this->base}/" . self::LANG . "/Bildirim/{$disclosureIndex}";

        $bytes = $this->http->requestBytes($url, [
            'timeoutMs' => $this->timeoutMs(),
            'accept' => 'application/pdf,*/*',
            'headers' => $this->headers($referer),
        ]);

        return $this->unwrapSerialisedBytes($bytes);
    }

    public function fetchDocumentPdf(array $document, ?int $disclosureIndex = null): string
    {
        return $this->fetchAttachmentBytes($document['objId'], $disclosureIndex);
    }

    /**
     * Unwraps KAP's Java-serialised byte[] envelope: ac ed 00 05, a type header,
     * a big-endian int32 length, then the bytes — while claiming application/pdf.
     */
    public function unwrapSerialisedBytes(string $payload): string
    {
        $magic = [0xac, 0xed, 0x00, 0x05];
        $wrapped = true;
        for ($i = 0; $i < 4; $i++) {
            if (ord($payload[$i] ?? "\0") !== $magic[$i]) {
                $wrapped = false;
                break;
            }
        }
        if (!$wrapped) {
            return $payload;
        }

        $start = strpos($payload, '%PDF');
        if ($start === false || $start < 4) {
            return $payload;
        }

        $length = unpack('N', substr($payload, $start - 4, 4))[1];
        // int32 is signed in Java; guard against a bogus length.
        if ($length <= 0 || $start + $length > strlen($payload)) {
            return substr($payload, $start);
        }

        return substr($payload, $start, $length);
    }

    public static function isKapEnabled(): bool
    {
        return (config('ingest.holdings_provider') ?: 'fixture') === 'kap';
    }

    public static function holdingsProviderName(): string
    {
        return config('ingest.holdings_provider') ?: 'fixture';
    }
}
