<?php

namespace App\Services\Market;

use Illuminate\Support\Facades\Http;

/**
 * Reading holdings out of a Portföy Dağılım Raporu PDF via the Anthropic Batch
 * API. Extraction is a monthly job over ~one report per fund; batching halves
 * the bill. The numbers are checked against the equity group total the report
 * itself prints before anything reaches the database.
 */
class KapExtract
{
    public const MODEL = 'claude-haiku-4-5-20251001';
    private const MAX_TOKENS = 8000;
    private const EQUITY_TOTAL_TOLERANCE = 1.0;
    private const TICKER_PATTERN = '/^[A-Z0-9]{3,10}$/';
    private const API_BASE = 'https://api.anthropic.com/v1';

    private const SYSTEM_PROMPT = <<<'PROMPT'
You are a financial analyst transcribing the equity holdings of a Turkish investment fund from its monthly KAP portfolio report ("Portföy Dağılım Raporu"). Your transcription is stored as the fund's holdings for the period and later diffed against the next month to compute which positions the fund increased and decreased, so a wrong ticker or weight corrupts those figures. Accuracy matters more than completeness: a dropped row is recoverable, a wrong one is not.

The report follows a fixed SPK template. Section III, "FON PORTFÖY DEĞERİ TABLOSU", lists the fund's individual positions grouped by instrument type: HİSSE SENETLERİ (equities), then groups such as T.REPO, TPP, kira sertifikaları, mevduat and so on. Each group ends in a GRUP TOPLAMI line.

Transcribe only the HİSSE SENETLERİ group. Ignore every other group. Ignore the aggregate percentages in sections I and II — including the "Portföy Dağılım Özeti" summary — those are month averages by asset class, not individual positions, and must never be read as holdings.

Each equity row carries several percentage columns. Take the one under TOPLAM (FPD göre) — the position's share of fund portfolio value. Do not take GRUP (b), which is the share within the equity group and sums to 100, and do not take TOPLAM (FTD göre), which divides by total fund value instead. As a check: the FPD percentages of the equity rows sum to the equity GRUP TOPLAMI, while the GRUP (b) ones sum to 100.

The ticker in the MENKUL KIYMET column is a BIST ticker: 3 to 10 uppercase letters and digits. Copy it exactly as printed. Never invent or correct a ticker, never map an issuer name to a ticker you assume, and never carry a ticker across from an adjacent row. If a row's ticker is missing or unreadable, leave the whole row out — a row saved under the wrong ticker is attributed to the wrong company.

Numbers are Turkish-formatted: "." groups thousands and "," is the decimal separator, so 20,53 is 20.53 and 1.234,56 is 1234.56.

Not every filing includes section III. Some — short ones, often exchange-traded funds — go straight from section II to section V and never list a position, even when section I reports a large equity percentage. That is not the same as a fund holding no equities: set hasPortfolioTable to false and return no holdings, and the report will be set aside rather than read as an empty portfolio.

Report what the document shows. Never infer a ticker, an ISIN or a weight that is not printed, and if a row is unreadable leave it out rather than guessing. An empty holdings list is a valid answer for a fund that holds no equities.
PROMPT;

    public static function requestsPerBatch(): int
    {
        return config('ingest.kap.extract_batch_size', 200);
    }

    public static function isConfigured(): bool
    {
        return (bool) (trim((string) config('ingest.anthropic.api_key'))
            || trim((string) config('ingest.anthropic.auth_token')));
    }

    public static function customIdFor(array $report): string
    {
        return "pdr-{$report['disclosureIndex']}";
    }

    public static function disclosureIndexFrom(string $customId): ?int
    {
        return preg_match('/^pdr-(\d+)$/', $customId, $m) ? (int) $m[1] : null;
    }

    private function outputSchema(): array
    {
        return [
            'type' => 'json_schema',
            'name' => 'extraction',
            'schema' => [
                'type' => 'object',
                'properties' => [
                    'fundCode' => ['type' => ['string', 'null'], 'description' => 'Fund code printed in the report header, e.g. "BHE".'],
                    'hasPortfolioTable' => ['type' => 'boolean', 'description' => 'True only if section III, "FON PORTFÖY DEĞERİ TABLOSU", is actually present.'],
                    'periodLabel' => ['type' => ['string', 'null'], 'description' => 'Reporting month as printed, e.g. "Temmuz-2026".'],
                    'equityGroupWeight' => ['type' => ['number', 'null'], 'description' => 'GRUP TOPLAMI for the HİSSE SENETLERİ group on the TOPLAM (FPD göre) basis. Null if none.'],
                    'holdings' => [
                        'type' => 'array',
                        'description' => 'One entry per equity row. Empty when the fund holds no equities.',
                        'items' => [
                            'type' => 'object',
                            'properties' => [
                                'ticker' => ['type' => 'string', 'description' => 'BIST ticker exactly as printed in MENKUL KIYMET, letters and digits only.'],
                                'name' => ['type' => 'string', 'description' => 'Issuer name as printed. Use the ticker when the row prints no name.'],
                                'isin' => ['type' => ['string', 'null'], 'description' => 'ISIN from the ISIN KODU column. Null if absent.'],
                                'weight' => ['type' => 'number', 'description' => "The row's share of fund portfolio value from TOPLAM (FPD göre): 20,53 becomes 20.53."],
                            ],
                            'required' => ['ticker', 'name', 'isin', 'weight'],
                            'additionalProperties' => false,
                        ],
                    ],
                ],
                'required' => ['fundCode', 'hasPortfolioTable', 'periodLabel', 'equityGroupWeight', 'holdings'],
                'additionalProperties' => false,
            ],
        ];
    }

    /** One report, as a batch request carrying the PDF inline. */
    public function buildExtractionRequest(array $report, string $pdf): array
    {
        return [
            'custom_id' => self::customIdFor($report),
            'params' => [
                'model' => self::MODEL,
                'max_tokens' => self::MAX_TOKENS,
                'system' => self::SYSTEM_PROMPT,
                'output_config' => ['format' => $this->outputSchema()],
                'messages' => [[
                    'role' => 'user',
                    'content' => [
                        [
                            'type' => 'document',
                            'source' => [
                                'type' => 'base64',
                                'media_type' => 'application/pdf',
                                'data' => base64_encode($pdf),
                            ],
                        ],
                        [
                            'type' => 'text',
                            'text' => 'This is the ' . substr($report['period'], 0, 7)
                                . " Portföy Dağılım Raporu for fund {$report['fundCode']} ({$report['fundTitle']}). Transcribe its equity holdings.",
                        ],
                    ],
                ]],
            ],
        ];
    }

    private function client()
    {
        $key = trim((string) config('ingest.anthropic.api_key'));
        $token = trim((string) config('ingest.anthropic.auth_token'));

        $headers = [
            'anthropic-version' => '2023-06-01',
            'anthropic-beta' => 'structured-outputs-2025-11-13',
        ];
        if ($key) {
            $headers['x-api-key'] = $key;
        } elseif ($token) {
            $headers['authorization'] = "Bearer {$token}";
        }

        return Http::withHeaders($headers)
            ->timeout(120)
            ->retry(config('ingest.anthropic.max_retries', 3), 1000);
    }

    public function submitBatch(array $requests): array
    {
        $response = $this->client()->post(self::API_BASE . '/messages/batches', ['requests' => $requests]);
        $response->throw();
        $batch = $response->json();
        return ['id' => $batch['id'], 'status' => $batch['processing_status']];
    }

    public function batchStatus(string $batchId): array
    {
        $response = $this->client()->get(self::API_BASE . "/messages/batches/{$batchId}");
        $response->throw();
        $batch = $response->json();
        return [
            'id' => $batch['id'],
            'status' => $batch['processing_status'],
            'ended' => $batch['processing_status'] === 'ended',
            'counts' => $batch['request_counts'] ?? null,
        ];
    }

    /** Every result in a finished batch (results endpoint streams JSONL). */
    public function collectBatch(string $batchId): array
    {
        $meta = $this->client()->get(self::API_BASE . "/messages/batches/{$batchId}");
        $meta->throw();
        $resultsUrl = $meta->json()['results_url'] ?? null;
        if (!$resultsUrl) {
            return [];
        }

        $response = $this->client()->get($resultsUrl);
        $response->throw();

        $outcomes = [];
        foreach (preg_split('/\r?\n/', trim($response->body())) as $line) {
            if ($line === '') {
                continue;
            }
            $entry = json_decode($line, true);
            if (!$entry) {
                continue;
            }
            $outcomes[] = $this->readResult($entry['custom_id'], $entry['result']);
        }

        return $outcomes;
    }

    /** Parses one batch result, keeping a bad row from failing the whole batch. */
    private function readResult(string $customId, array $result): array
    {
        $disclosureIndex = self::disclosureIndexFrom($customId);
        if ($disclosureIndex === null) {
            return ['disclosureIndex' => null, 'ok' => false, 'error' => "unknown custom_id {$customId}"];
        }

        $type = $result['type'] ?? '';

        if ($type === 'errored') {
            $err = $result['error'] ?? [];
            return ['disclosureIndex' => $disclosureIndex, 'ok' => false,
                'error' => mb_substr(($err['type'] ?? 'error') . ': ' . json_encode($err['error'] ?? []), 0, 500)];
        }

        if ($type !== 'succeeded') {
            return ['disclosureIndex' => $disclosureIndex, 'ok' => false, 'error' => $type];
        }

        $message = $result['message'] ?? [];
        $stop = $message['stop_reason'] ?? null;

        if ($stop === 'max_tokens') {
            return ['disclosureIndex' => $disclosureIndex, 'ok' => false, 'error' => 'response hit max_tokens'];
        }
        if ($stop === 'refusal') {
            return ['disclosureIndex' => $disclosureIndex, 'ok' => false, 'error' => 'model declined the request'];
        }

        $text = '';
        foreach (($message['content'] ?? []) as $block) {
            if (($block['type'] ?? '') === 'text') {
                $text .= $block['text'];
            }
        }

        if (trim($text) === '') {
            return ['disclosureIndex' => $disclosureIndex, 'ok' => false, 'error' => 'empty response'];
        }

        $payload = json_decode($text, true);
        if ($payload === null) {
            return ['disclosureIndex' => $disclosureIndex, 'ok' => false, 'error' => 'unparseable JSON: ' . mb_substr($text, 0, 200)];
        }

        return ['disclosureIndex' => $disclosureIndex, 'ok' => true, 'extraction' => $payload];
    }

    public function cancelBatch(string $batchId): array
    {
        $response = $this->client()->post(self::API_BASE . "/messages/batches/{$batchId}/cancel");
        $response->throw();
        return $response->json();
    }

    /**
     * Checks an extraction against what the report asserts about itself.
     * Returns ['holdings'=>[], 'warnings'=>[], 'rejected'=>bool, 'missingTable'=>bool].
     */
    public function validateExtraction(array $extraction, array $report): array
    {
        $warnings = [];
        $seen = [];
        $holdings = [];

        $fundCode = $extraction['fundCode'] ?? null;
        if ($fundCode && strtoupper(trim($fundCode)) !== $report['fundCode']) {
            $warnings[] = "document is headed {$fundCode}, KAP files it under {$report['fundCode']}";
        }

        if (empty($extraction['hasPortfolioTable'])) {
            $warnings[] = 'filing carries no section III holdings table';
            return ['holdings' => [], 'rejected' => false, 'missingTable' => true, 'warnings' => $warnings];
        }

        foreach (($extraction['holdings'] ?? []) as $holding) {
            $ticker = preg_replace('/\.$/', '', strtoupper(trim($holding['ticker'] ?? '')));

            if (!preg_match(self::TICKER_PATTERN, $ticker)) {
                $warnings[] = 'dropped unusable ticker ' . json_encode($holding['ticker'] ?? null);
                continue;
            }

            $weight = $holding['weight'] ?? null;
            if (!is_numeric($weight) || $weight < 0 || $weight > 100) {
                $warnings[] = "dropped {$ticker}: weight {$weight} out of range";
                continue;
            }

            if (isset($seen[$ticker])) {
                $warnings[] = "dropped duplicate row for {$ticker}";
                continue;
            }

            $seen[$ticker] = true;
            $holdings[] = [
                'ticker' => $ticker,
                'name' => trim($holding['name'] ?? '') ?: $ticker,
                'isin' => $holding['isin'] ?? null,
                'weight' => (float) $weight,
            ];
        }

        $total = array_sum(array_column($holdings, 'weight'));

        if ($total > 100 + self::EQUITY_TOTAL_TOLERANCE) {
            $warnings[] = 'equity weights sum to ' . number_format($total, 2) . '%';
            return ['holdings' => [], 'rejected' => true, 'missingTable' => false, 'warnings' => $warnings];
        }

        $stated = $extraction['equityGroupWeight'] ?? null;
        if ($stated !== null && abs($total - $stated) > self::EQUITY_TOTAL_TOLERANCE) {
            $warnings[] = 'rows sum to ' . number_format($total, 2) . "% but the report's equity GRUP TOPLAMI is " . number_format($stated, 2) . '%';
            return ['holdings' => [], 'rejected' => true, 'missingTable' => false, 'warnings' => $warnings];
        }

        return ['holdings' => $holdings, 'warnings' => $warnings, 'rejected' => false, 'missingTable' => false];
    }
}
