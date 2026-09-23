<?php

namespace App\Services\Ingest;

use App\Services\Market\KapClient;
use App\Services\Market\KapExtract;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Portfolio holdings pipeline.
 *
 *   KAP filings ── discover ──▶ kap_portfolio_reports
 *                                      │ document / submit (Batch API)
 *                                      ▼
 *                               fund_holding_snapshots
 *                                      │ diff consecutive periods
 *                                      ▼
 *                              fund_positions
 *
 * Extraction is asynchronous, so a period is worked in two passes: submit
 * discovers filings and sends them; collect reads finished batches and rebuilds
 * the movers. Both are idempotent and resumable — a run that dies mid-period
 * leaves its reports queued against a batch the next collect finds.
 */
class HoldingsJobs
{
    private const TOP_N = 4;
    private const SUBMITTABLE = ['discovered', 'failed'];
    private const DOCUMENTABLE = ['discovered', 'failed', 'extracted', 'no_detail', 'queued'];
    private const REPORTING_WINDOW_DAYS = 75;

    public function __construct(
        private IngestRunService $runs = new IngestRunService(),
        private JobLockService $lock = new JobLockService(),
        private KapClient $kap = new KapClient(),
        private KapExtract $extract = new KapExtract(),
    ) {}

    /* ── Date helpers ──────────────────────────────────────────────────── */

    public static function periodOf(?string $date = null): string
    {
        $ts = $date ? strtotime($date) : time();
        return gmdate('Y-m-01', $ts);
    }

    public static function previousPeriod(string $period): string
    {
        return gmdate('Y-m-01', strtotime("{$period} -1 month"));
    }

    private static function nextPeriod(string $period): string
    {
        return gmdate('Y-m-01', strtotime("{$period} +1 month"));
    }

    private static function addDays(string $day, int $days): string
    {
        return gmdate('Y-m-d', strtotime("{$day} +{$days} days"));
    }

    /** From the day the period closes until the window shuts, never past today. */
    public function reportingWindow(string $period): array
    {
        $opens = self::nextPeriod($period);
        $closes = self::addDays($opens, self::REPORTING_WINDOW_DAYS);
        $today = gmdate('Y-m-d');
        return ['from' => $opens, 'to' => $closes < $today ? $closes : $today];
    }

    private function trackedFundCodes(): \Illuminate\Support\Collection
    {
        return DB::table('funds')->pluck('code')->flip();
    }

    private function markReport(int $disclosureIndex, array $set): void
    {
        DB::table('kap_portfolio_reports')->where('disclosure_index', $disclosureIndex)->update($set);
    }

    private function writeSnapshots(array $rows, string $source): int
    {
        $known = $this->trackedFundCodes();
        $values = [];
        foreach ($rows as $row) {
            if (!$known->has($row['code'])) {
                continue;
            }
            $values[] = [
                'fund_code' => $row['code'],
                'period' => $row['period'],
                'ticker' => $row['ticker'],
                'weight' => $row['weight'],
                'source' => $source,
            ];
        }

        return Upsert::run('fund_holding_snapshots', $values, ['fund_code', 'period', 'ticker'],
            'weight = excluded.weight, source = excluded.source, ingested_at = now()');
    }

    /* ── Discovery ─────────────────────────────────────────────────────── */

    public function discoverPortfolioReports(array $window): array
    {
        return $this->runs->withRun('kap-discovery', $window, function () use ($window) {
            $reports = $this->kap->listPortfolioReports($window['from'], $window['to']);
            if (count($reports) === 0) {
                return ['rowsRead' => 0, 'rowsWritten' => 0];
            }

            $tracked = $this->trackedFundCodes();
            $rows = array_map(fn ($report) => [
                'disclosure_index' => $report['disclosureIndex'],
                'fund_code' => $report['fundCode'],
                'fund_title' => $report['fundTitle'],
                'period' => $report['period'],
                'published_at' => date('Y-m-d H:i:sP', $report['publishedAt']),
                'is_late' => $report['isLate'],
                'status' => $tracked->has($report['fundCode']) ? 'discovered' : 'skipped',
            ], $reports);

            // Only a report nothing has been spent on yet can change lane.
            $written = Upsert::run('kap_portfolio_reports', $rows, ['disclosure_index'], implode(', ', [
                'fund_title = excluded.fund_title',
                'published_at = excluded.published_at',
                'is_late = excluded.is_late',
                "status = case when kap_portfolio_reports.status in ('discovered', 'skipped') "
                    . 'then excluded.status else kap_portfolio_reports.status end',
            ]));

            return ['rowsRead' => count($reports), 'rowsWritten' => $written];
        });
    }

    /* ── Documents ─────────────────────────────────────────────────────── */

    public function recordReportDocuments(?string $period = null, ?int $limit = null): array
    {
        $period ??= self::periodOf();
        $limit ??= config('ingest.kap.document_limit');

        return $this->runs->withRun('kap-documents', ['period' => $period, 'limit' => $limit], function () use ($period, $limit) {
            $pending = DB::table('kap_portfolio_reports')
                ->where('period', $period)
                ->whereNull('document_obj_id')
                ->whereIn('status', self::DOCUMENTABLE)
                ->orderBy('published_at')
                ->limit($limit)
                ->get();

            if ($pending->isEmpty()) {
                return ['rowsRead' => 0, 'rowsWritten' => 0, 'missing' => 0];
            }

            $tracked = $this->trackedFundCodes();
            $wanted = $pending->filter(fn ($row) => $tracked->has($row->fund_code));

            $written = 0;
            $missing = 0;

            foreach ($wanted as $row) {
                try {
                    $document = $this->kap->resolveReportDocument($row->disclosure_index);
                } catch (\Throwable $e) {
                    $missing++;
                    Log::warning("[kap] {$row->disclosure_index}: {$e->getMessage()}");
                    continue;
                }

                if (!$document) {
                    $missing++;
                    continue;
                }

                $this->markReport($row->disclosure_index, [
                    'document_obj_id' => $document['objId'],
                    'document_name' => $document['fileName'],
                    'document_url' => $document['url'],
                ]);
                $written++;
            }

            return ['rowsRead' => $pending->count(), 'rowsWritten' => $written, 'missing' => $missing];
        });
    }

    /**
     * Resolve report documents for one bounded slice of a period's reports past
     * $afterIndex. Cursor-paginated so a chain drains deterministically.
     */
    public function recordDocumentsChunk(string $period, int $afterIndex, int $chunk): array
    {
        return $this->runs->withRun('kap-documents', ['period' => $period, 'after' => $afterIndex, 'chunk' => $chunk], function () use ($period, $afterIndex, $chunk) {
            $pending = DB::table('kap_portfolio_reports')
                ->where('period', $period)
                ->whereNull('document_obj_id')
                ->whereIn('status', self::DOCUMENTABLE)
                ->where('disclosure_index', '>', $afterIndex)
                ->orderBy('disclosure_index')
                ->limit($chunk)
                ->get();

            if ($pending->isEmpty()) {
                return ['rowsRead' => 0, 'rowsWritten' => 0, 'missing' => 0, 'cursor' => $afterIndex, 'more' => false];
            }

            $tracked = $this->trackedFundCodes();
            $written = 0;
            $missing = 0;
            $cursor = $afterIndex;

            foreach ($pending as $row) {
                $cursor = max($cursor, $row->disclosure_index);
                if (!$tracked->has($row->fund_code)) {
                    continue;
                }
                try {
                    $document = $this->kap->resolveReportDocument($row->disclosure_index);
                } catch (\Throwable $e) {
                    $missing++;
                    Log::warning("[kap] {$row->disclosure_index}: {$e->getMessage()}");
                    continue;
                }
                if (!$document) {
                    $missing++;
                    continue;
                }
                $this->markReport($row->disclosure_index, [
                    'document_obj_id' => $document['objId'],
                    'document_name' => $document['fileName'],
                    'document_url' => $document['url'],
                ]);
                $written++;
            }

            return [
                'rowsRead' => $pending->count(),
                'rowsWritten' => $written,
                'missing' => $missing,
                'cursor' => $cursor,
                'more' => $pending->count() === $chunk,
            ];
        });
    }

    /** Disclosure indexes for a period's reports awaiting submission. */
    public function pendingSubmitIndexes(string $period, ?array $statuses = null, ?int $limit = null): array
    {
        $statuses ??= self::SUBMITTABLE;
        $limit ??= config('ingest.kap.submit_limit');

        return DB::table('kap_portfolio_reports')
            ->where('period', $period)
            ->whereIn('status', $statuses)
            ->orderBy('published_at')
            ->limit($limit)
            ->pluck('disclosure_index')
            ->all();
    }

    /**
     * Download the PDFs for one explicit set of reports and submit them as a
     * single extraction batch. One batch per job keeps runtime bounded.
     */
    public function submitOneBatch(string $period, array $disclosureIndexes): array
    {
        if (!KapExtract::isConfigured()) {
            throw new \RuntimeException('ANTHROPIC_API_KEY is not set — portfolio reports cannot be extracted');
        }

        return $this->runs->withRun('kap-extract-submit', ['period' => $period, 'count' => count($disclosureIndexes), 'model' => KapExtract::MODEL], function () use ($period, $disclosureIndexes) {
            $reports = DB::table('kap_portfolio_reports')
                ->whereIn('disclosure_index', $disclosureIndexes)
                ->get();

            if ($reports->isEmpty()) {
                return ['rowsRead' => 0, 'rowsWritten' => 0, 'batches' => []];
            }

            $tracked = $this->trackedFundCodes();
            $requests = [];

            foreach ($reports as $row) {
                if (!$tracked->has($row->fund_code)) {
                    $this->markReport($row->disclosure_index, ['status' => 'skipped', 'note' => 'fund is not in the catalogue']);
                    continue;
                }
                try {
                    $pdf = $this->reportPdf($row);
                } catch (\Throwable $e) {
                    $this->markReport($row->disclosure_index, ['status' => 'failed', 'note' => mb_substr($e->getMessage(), 0, 1000)]);
                    continue;
                }
                if (!$pdf) {
                    $this->markReport($row->disclosure_index, ['status' => 'failed', 'note' => 'filing carries no PDF attachment']);
                    continue;
                }
                $requests[] = $this->extract->buildExtractionRequest($this->toPortfolioReport($row), $pdf);
            }

            if (empty($requests)) {
                return ['rowsRead' => $reports->count(), 'rowsWritten' => 0, 'batches' => []];
            }

            $batch = $this->extract->submitBatch($requests);

            $queued = [];
            foreach ($requests as $request) {
                $index = KapExtract::disclosureIndexFrom($request['custom_id']);
                if ($index !== null) {
                    $queued[] = $index;
                }
            }

            DB::transaction(function () use ($batch, $period, $requests, $queued) {
                DB::table('kap_extraction_batches')->insert([
                    'id' => $batch['id'],
                    'period' => $period,
                    'status' => $batch['status'],
                    'request_count' => count($requests),
                    'submitted_at' => now(),
                ]);
                DB::table('kap_portfolio_reports')
                    ->whereIn('disclosure_index', $queued)
                    ->update(['status' => 'queued', 'batch_id' => $batch['id'], 'note' => null]);
            });

            return ['rowsRead' => $reports->count(), 'rowsWritten' => count($requests), 'batches' => [$batch['id']]];
        });
    }

    /** The report PDF for one row, going through the recorded document. */
    private function reportPdf(object $row): ?string
    {
        if ($row->document_obj_id) {
            return $this->kap->fetchDocumentPdf(['objId' => $row->document_obj_id], $row->disclosure_index);
        }

        $document = $this->kap->resolveReportDocument($row->disclosure_index);
        if (!$document) {
            return null;
        }

        $this->markReport($row->disclosure_index, [
            'document_obj_id' => $document['objId'],
            'document_name' => $document['fileName'],
            'document_url' => $document['url'],
        ]);

        return $this->kap->fetchDocumentPdf($document, $row->disclosure_index);
    }

    private function toPortfolioReport(object $row): array
    {
        return [
            'disclosureIndex' => $row->disclosure_index,
            'fundCode' => $row->fund_code,
            'fundTitle' => $row->fund_title,
            'period' => $row->period,
        ];
    }

    /* ── Extraction ────────────────────────────────────────────────────── */

    public function submitExtractions(?string $period = null, array $options = []): array
    {
        $period ??= self::periodOf();
        $limit = $options['limit'] ?? config('ingest.kap.submit_limit');
        $statuses = $options['statuses'] ?? self::SUBMITTABLE;
        $codes = $options['codes'] ?? null;

        if (!KapExtract::isConfigured()) {
            throw new \RuntimeException('ANTHROPIC_API_KEY is not set — portfolio reports cannot be extracted');
        }

        return $this->runs->withRun('kap-extract-submit', [
            'period' => $period, 'limit' => $limit, 'statuses' => $statuses,
            'codes' => $codes, 'model' => KapExtract::MODEL,
        ], function () use ($period, $limit, $statuses, $codes) {
            $query = DB::table('kap_portfolio_reports')
                ->where('period', $period)
                ->whereIn('status', $statuses);
            if (!empty($codes)) {
                $query->whereIn('fund_code', $codes);
            }
            $pending = $query->orderBy('published_at')->limit($limit)->get();

            if ($pending->isEmpty()) {
                return ['rowsRead' => 0, 'rowsWritten' => 0, 'batches' => []];
            }

            $tracked = $this->trackedFundCodes();
            $batches = [];
            $submitted = 0;

            foreach ($pending->chunk(KapExtract::requestsPerBatch()) as $slice) {
                $requests = [];

                foreach ($slice as $row) {
                    if (!$tracked->has($row->fund_code)) {
                        $this->markReport($row->disclosure_index, [
                            'status' => 'skipped',
                            'note' => 'fund is not in the catalogue',
                        ]);
                        continue;
                    }

                    try {
                        $pdf = $this->reportPdf($row);
                    } catch (\Throwable $e) {
                        $this->markReport($row->disclosure_index, [
                            'status' => 'failed',
                            'note' => mb_substr($e->getMessage(), 0, 1000),
                        ]);
                        continue;
                    }

                    if (!$pdf) {
                        $this->markReport($row->disclosure_index, [
                            'status' => 'failed',
                            'note' => 'filing carries no PDF attachment',
                        ]);
                        continue;
                    }

                    $requests[] = $this->extract->buildExtractionRequest($this->toPortfolioReport($row), $pdf);
                }

                if (empty($requests)) {
                    continue;
                }

                $batch = $this->extract->submitBatch($requests);
                $batches[] = $batch['id'];

                $queued = [];
                foreach ($requests as $request) {
                    $index = KapExtract::disclosureIndexFrom($request['custom_id']);
                    if ($index !== null) {
                        $queued[] = $index;
                    }
                }

                DB::transaction(function () use ($batch, $period, $requests, $queued) {
                    DB::table('kap_extraction_batches')->insert([
                        'id' => $batch['id'],
                        'period' => $period,
                        'status' => $batch['status'],
                        'request_count' => count($requests),
                        'submitted_at' => now(),
                    ]);

                    DB::table('kap_portfolio_reports')
                        ->whereIn('disclosure_index', $queued)
                        ->update(['status' => 'queued', 'batch_id' => $batch['id'], 'note' => null]);
                });

                $submitted += count($requests);
            }

            return ['rowsRead' => $pending->count(), 'rowsWritten' => $submitted, 'batches' => $batches];
        });
    }

    /** Replaces one fund's equity holdings for a period. */
    private function applyExtractedHoldings(object $report, array $holdings): int
    {
        if (count($holdings) > 0) {
            $symbolRows = array_map(fn ($h) => [
                'ticker' => $h['ticker'],
                'name' => $h['name'],
                'color' => null,
            ], $holdings);
            // A curated name or colour outranks whatever the filing printed.
            foreach (array_chunk($symbolRows, Upsert::CHUNK_SIZE) as $batch) {
                DB::table('symbols')->insertOrIgnore($batch);
            }
        }

        DB::table('fund_holding_snapshots')
            ->where('fund_code', $report->fund_code)
            ->where('period', $report->period)
            ->delete();

        if (count($holdings) === 0) {
            return 0;
        }

        $rows = array_map(fn ($h) => [
            'fund_code' => $report->fund_code,
            'period' => $report->period,
            'ticker' => $h['ticker'],
            'weight' => $h['weight'],
            'source' => 'kap',
        ], $holdings);

        return Upsert::insert('fund_holding_snapshots', $rows);
    }

    /** Reads back every finished extraction batch and writes what it returned. */
    public function collectExtractions(): array
    {
        return $this->runs->withRun('kap-extract-collect', [], function () {
            $open = DB::table('kap_extraction_batches')->whereNull('collected_at')->get();

            $read = 0;
            $written = 0;
            $pending = 0;
            $periods = [];

            foreach ($open as $batch) {
                $status = $this->extract->batchStatus($batch->id);

                if (!$status['ended']) {
                    $pending++;
                    if ($status['status'] !== $batch->status) {
                        DB::table('kap_extraction_batches')->where('id', $batch->id)->update(['status' => $status['status']]);
                    }
                    continue;
                }

                $outcomes = $this->extract->collectBatch($batch->id);
                $read += count($outcomes);

                foreach ($outcomes as $outcome) {
                    if ($outcome['disclosureIndex'] === null) {
                        Log::warning("[kap] {$batch->id}: {$outcome['error']}");
                        continue;
                    }

                    $report = DB::table('kap_portfolio_reports')
                        ->where('disclosure_index', $outcome['disclosureIndex'])
                        ->first();
                    if (!$report) {
                        continue;
                    }

                    if (!$outcome['ok']) {
                        $this->markReport($report->disclosure_index, ['status' => 'failed', 'note' => $outcome['error']]);
                        continue;
                    }

                    $checked = $this->extract->validateExtraction($outcome['extraction'], $this->toPortfolioReport($report));

                    if ($checked['missingTable']) {
                        $this->markReport($report->disclosure_index, [
                            'status' => 'no_detail',
                            'note' => mb_substr(implode('; ', $checked['warnings']), 0, 1000),
                        ]);
                        continue;
                    }

                    if ($checked['rejected']) {
                        $this->markReport($report->disclosure_index, [
                            'status' => 'failed',
                            'note' => mb_substr(implode('; ', $checked['warnings']), 0, 1000),
                        ]);
                        continue;
                    }

                    $written += $this->applyExtractedHoldings($report, $checked['holdings']);
                    $periods[$report->period] = true;

                    $this->markReport($report->disclosure_index, [
                        'status' => 'extracted',
                        'holdings_count' => count($checked['holdings']),
                        'note' => $checked['warnings'] ? mb_substr(implode('; ', $checked['warnings']), 0, 1000) : null,
                        'extracted_at' => now(),
                    ]);
                }

                DB::table('kap_extraction_batches')->where('id', $batch->id)
                    ->update(['status' => $status['status'], 'collected_at' => now()]);
            }

            return ['rowsRead' => $read, 'rowsWritten' => $written, 'pendingBatches' => $pending, 'periods' => array_keys($periods)];
        });
    }

    /* ── Movers ────────────────────────────────────────────────────────── */

    /** Rebuilds fund_positions by diffing two snapshots. */
    public function computeFundPositions(?string $period = null): array
    {
        $period ??= self::periodOf();
        $previous = self::previousPeriod($period);

        return $this->runs->withRun('fund-positions', ['period' => $period, 'previous' => $previous], function () use ($period, $previous) {
            $current = DB::table('fund_holding_snapshots')
                ->select('fund_code', 'ticker', 'weight')
                ->where('period', $period)->get();

            $before = DB::table('fund_holding_snapshots')
                ->select('fund_code', 'ticker', 'weight')
                ->where('period', $previous)->get();

            $baseline = [];
            foreach ($before as $row) {
                $baseline["{$row->fund_code}:{$row->ticker}"] = $row->weight;
            }

            $held = [];
            foreach ($current as $row) {
                $held[$row->fund_code] = true;
            }
            $comparable = [];
            foreach ($before as $row) {
                if (isset($held[$row->fund_code])) {
                    $comparable[$row->fund_code] = true;
                }
            }

            $known = DB::table('symbols')->pluck('ticker')->flip();

            $byFund = [];

            foreach ($current as $row) {
                if (!$known->has($row->ticker) || !isset($comparable[$row->fund_code])) {
                    continue;
                }
                $previousWeight = $baseline["{$row->fund_code}:{$row->ticker}"] ?? 0;
                $change = round($row->weight - $previousWeight, 2);
                if ($change === 0.0) {
                    continue;
                }
                $byFund[$row->fund_code][] = ['ticker' => $row->ticker, 'weight' => $row->weight, 'change' => $change];
            }

            $currentKeys = [];
            foreach ($current as $row) {
                $currentKeys["{$row->fund_code}:{$row->ticker}"] = true;
            }

            // A position held last period and absent now was sold outright.
            foreach ($before as $row) {
                if (!$known->has($row->ticker) || !isset($comparable[$row->fund_code])) {
                    continue;
                }
                if (isset($currentKeys["{$row->fund_code}:{$row->ticker}"])) {
                    continue;
                }
                $byFund[$row->fund_code][] = ['ticker' => $row->ticker, 'weight' => 0, 'change' => -$row->weight];
            }

            $rows = [];
            foreach ($byFund as $fundCode => $movers) {
                $increased = array_filter($movers, fn ($m) => $m['change'] > 0);
                usort($increased, fn ($a, $b) => $b['change'] <=> $a['change']);
                $increased = array_slice($increased, 0, self::TOP_N);

                $decreased = array_filter($movers, fn ($m) => $m['change'] < 0);
                usort($decreased, fn ($a, $b) => $a['change'] <=> $b['change']);
                $decreased = array_slice($decreased, 0, self::TOP_N);

                foreach ($increased as $rank => $mover) {
                    $rows[] = ['fund_code' => $fundCode, 'ticker' => $mover['ticker'], 'period' => $period,
                        'direction' => 'increased', 'weight' => $mover['weight'], 'change_points' => $mover['change'], 'rank' => $rank];
                }
                foreach ($decreased as $rank => $mover) {
                    $rows[] = ['fund_code' => $fundCode, 'ticker' => $mover['ticker'], 'period' => $period,
                        'direction' => 'decreased', 'weight' => $mover['weight'], 'change_points' => $mover['change'], 'rank' => $rank];
                }
            }

            DB::table('fund_positions')->where('period', $period)->delete();
            $written = Upsert::insert('fund_positions', $rows);

            return ['rowsRead' => $current->count(), 'rowsWritten' => $written];
        });
    }

    /* ── Entry points ──────────────────────────────────────────────────── */

    /** The scheduler's submit pass. */
    public function syncPositions(?string $period = null): array
    {
        $period ??= self::periodOf();

        $outcome = $this->lock->withJobLock('sync-positions', function () use ($period) {
            if (!KapClient::isKapEnabled()) {
                // Offline: rebuild movers from whatever snapshots exist.
                $positions = $this->computeFundPositions($period);
                return ['mode' => 'offline', 'provider' => KapClient::holdingsProviderName(), 'positions' => $positions['run']];
            }

            $window = $this->reportingWindow($period);
            $discovery = $this->discoverPortfolioReports($window);
            $documents = $this->recordReportDocuments($period);
            $submission = $this->submitExtractions($period);

            return [
                'mode' => 'queued', 'provider' => 'kap', 'window' => $window,
                'discovery' => $discovery['run'], 'documents' => $documents['run'],
                'submission' => $submission['run'], 'batches' => $submission['batches'],
            ];
        });

        return $outcome['ran'] ? $outcome['result'] : ['mode' => 'locked'];
    }

    /** The scheduler's collect pass. */
    public function collectPositions(): array
    {
        $outcome = $this->lock->withJobLock('collect-positions', function () {
            $collected = $this->collectExtractions();

            $rebuilt = [];
            foreach ($collected['periods'] as $period) {
                $positions = $this->computeFundPositions($period);
                $rebuilt[] = array_merge(['period' => $period], $positions['run']);
            }

            return [
                'locked' => false,
                'collection' => $collected['run'],
                'pendingBatches' => $collected['pendingBatches'],
                'positions' => $rebuilt,
            ];
        });

        return $outcome['ran'] ? $outcome['result'] : ['locked' => true];
    }

    /**
     * Extract one KAP disclosure's Portföy Dağılım Raporu on demand, synchronously:
     * download its PDF, read it with Haiku, write the holdings snapshot and rebuild
     * that period's movers. Powers the per-row "Çıkar" button in the Bildirimler
     * admin panel. Throws on any failure so the caller can surface the reason.
     */
    public function extractDisclosureNow(int $disclosureIndex): array
    {
        $disclosure = DB::table('fund_disclosures')->where('disclosure_index', $disclosureIndex)->first();
        if (!$disclosure) {
            throw new \RuntimeException('bildirim bulunamadı');
        }
        if (trim((string) $disclosure->subject) !== KapClient::PORTFOLIO_REPORT_SUBJECT) {
            throw new \RuntimeException('bildirim bir Portföy Dağılım Raporu değil');
        }
        if (!$this->trackedFundCodes()->has($disclosure->fund_code)) {
            throw new \RuntimeException("fon {$disclosure->fund_code} takip edilmiyor");
        }
        if (!KapExtract::isConfigured()) {
            throw new \RuntimeException('ANTHROPIC_API_KEY tanımlı değil');
        }

        return $this->runs->withRun('kap-extract-ondemand', [
            'disclosureIndex' => $disclosureIndex,
            'fundCode' => $disclosure->fund_code,
            'model' => KapExtract::MODEL,
        ], function () use ($disclosure, $disclosureIndex) {
            $document = $this->kap->resolveReportDocument($disclosureIndex);
            if (!$document) {
                throw new \RuntimeException('bildirimde PDF eki bulunamadı');
            }

            $pdf = $this->kap->fetchDocumentPdf($document, $disclosureIndex);

            $report = [
                'disclosureIndex' => $disclosureIndex,
                'fundCode' => $disclosure->fund_code,
                'fundTitle' => $disclosure->fund_title ?: $disclosure->fund_code,
                'period' => '',
            ];

            // Haiku first; escalate to a larger-context model when the PDF is
            // too big for it or the transcription fails reconciliation.
            $outcome = $this->extract->extractValidated($report, $pdf);
            if (!$outcome['ok']) {
                throw new \RuntimeException($outcome['error']);
            }

            $checked = $outcome['checked'];
            if ($checked['missingTable']) {
                throw new \RuntimeException('raporda hisse tablosu (bölüm III) yok');
            }
            if ($checked['rejected']) {
                throw new \RuntimeException('doğrulama başarısız: ' . implode('; ', $checked['warnings']));
            }

            // The report states its own period; fall back to the month before
            // publication, which is when a monthly report is normally filed.
            $period = KapExtract::periodFromLabel($outcome['extraction']['periodLabel'] ?? null)
                ?? self::previousPeriod(self::periodOf((string) $disclosure->published_at));

            $written = $this->applyExtractedHoldings(
                (object) ['fund_code' => $disclosure->fund_code, 'period' => $period],
                $checked['holdings'],
            );

            // Rebuild the movers for the period so the diff against the prior
            // month reflects the new snapshot immediately.
            $positions = $this->computeFundPositions($period);

            return [
                'rowsRead' => count($checked['holdings']),
                'rowsWritten' => $written,
                'period' => $period,
                'holdings' => count($checked['holdings']),
                'movers' => $positions['rowsWritten'] ?? 0,
                'warnings' => $checked['warnings'],
                'model' => $outcome['model'],
                'escalated' => $outcome['escalated'],
            ];
        });
    }

    /** Reads a period's reports again from the documents already recorded. */
    public function reextractPositions(array $options = []): ?array
    {
        $period = $options['period'] ?? self::periodOf();

        if (!KapClient::isKapEnabled()) {
            throw new \RuntimeException('re-extraction reads KAP filings — set HOLDINGS_PROVIDER=kap');
        }

        $outcome = $this->lock->withJobLock('sync-positions', function () use ($period, $options) {
            $documents = $this->recordReportDocuments($period);
            $submission = $this->submitExtractions($period, [
                'limit' => $options['limit'] ?? null,
                'statuses' => ['discovered', 'failed', 'extracted', 'no_detail'],
                'codes' => isset($options['codes'])
                    ? array_map(fn ($c) => strtoupper(trim($c)), $options['codes'])
                    : null,
            ]);

            return [
                'period' => $period,
                'documents' => $documents['run'],
                'submission' => $submission['run'],
                'batches' => $submission['batches'],
            ];
        });

        return $outcome['ran'] ? $outcome['result'] : null;
    }

    public function periodProgress(?string $period = null): array
    {
        $period ??= self::periodOf();
        return DB::table('kap_portfolio_reports')
            ->where('period', $period)
            ->groupBy('status')
            ->selectRaw('status, count(*)::int as count')
            ->pluck('count', 'status')
            ->toArray();
    }
}
