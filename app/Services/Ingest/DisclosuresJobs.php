<?php

namespace App\Services\Ingest;

use App\Services\Market\KapClient;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Fund disclosure archive. Two idempotent passes: discovery walks the window
 * and upserts every filing of every tracked fund; recording resolves the direct
 * PDF link for filings that carry an attachment, bounded by a limit so a daily
 * run finishes in budget.
 */
class DisclosuresJobs
{
    public function __construct(
        private IngestRunService $runs = new IngestRunService(),
        private KapClient $kap = new KapClient(),
    ) {}

    private function trackedFundCodes(): \Illuminate\Support\Collection
    {
        return DB::table('funds')->pluck('code')->flip();
    }

    public function discoverDisclosures(string $from, string $to): array
    {
        return $this->runs->withRun('kap-disclosures', ['from' => $from, 'to' => $to], function () use ($from, $to) {
            $disclosures = $this->kap->listDisclosures($from, $to);
            if (count($disclosures) === 0) {
                return ['rowsRead' => 0, 'rowsWritten' => 0];
            }

            $tracked = $this->trackedFundCodes();
            $rows = [];
            foreach ($disclosures as $row) {
                if (!$tracked->has($row['fundCode'])) {
                    continue;
                }
                $rows[] = [
                    'disclosure_index' => $row['disclosureIndex'],
                    'fund_code' => $row['fundCode'],
                    'fund_title' => $row['fundTitle'],
                    'subject' => $row['subject'],
                    'published_at' => date('Y-m-d H:i:sP', $row['publishedAt']),
                    'is_late' => $row['isLate'],
                    'disclosure_url' => $this->kap->disclosurePageUrl($row['disclosureIndex']),
                    'attachment_count' => $row['attachmentCount'],
                ];
            }

            $written = Upsert::run('fund_disclosures', $rows, ['disclosure_index'], implode(', ', [
                'fund_title = excluded.fund_title',
                'subject = excluded.subject',
                'published_at = excluded.published_at',
                'is_late = excluded.is_late',
                'disclosure_url = excluded.disclosure_url',
                'attachment_count = excluded.attachment_count',
            ]));

            return ['rowsRead' => count($disclosures), 'rowsWritten' => $written];
        });
    }

    public function recordDisclosureLinks(?int $limit = null): array
    {
        $limit ??= config('ingest.kap.disclosure_limit');

        return $this->runs->withRun('kap-disclosure-links', ['limit' => $limit], function () use ($limit) {
            $pending = DB::table('fund_disclosures')
                ->whereNull('pdf_url')
                ->where('attachment_count', '>', 0)
                ->orderBy('published_at')
                ->limit($limit)
                ->get();

            if ($pending->isEmpty()) {
                return ['rowsRead' => 0, 'rowsWritten' => 0, 'missing' => 0];
            }

            $written = 0;
            $missing = 0;

            foreach ($pending as $row) {
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

                DB::table('fund_disclosures')
                    ->where('disclosure_index', $row->disclosure_index)
                    ->update(['pdf_url' => $document['url'], 'pdf_name' => $document['fileName']]);
                $written++;
            }

            return ['rowsRead' => $pending->count(), 'rowsWritten' => $written, 'missing' => $missing];
        });
    }

    public function syncDisclosures(?int $days = null, ?int $limit = null): array
    {
        $days ??= config('ingest.kap.disclosure_days');
        $discovery = $this->discoverDisclosures(now()->subDays($days)->format('Y-m-d'), now()->format('Y-m-d'));
        $links = $this->recordDisclosureLinks($limit);

        return ['discovery' => $discovery, 'links' => $links];
    }

    /**
     * Resolve PDF links for one bounded slice of disclosures past $afterIndex,
     * ordered by disclosure_index so a chain drains deterministically without
     * re-selecting rows a previous chunk already tried. Returns a cursor and
     * whether more rows may remain.
     */
    public function resolveLinksChunk(int $afterIndex, int $chunk): array
    {
        return $this->runs->withRun('kap-disclosure-links', ['after' => $afterIndex, 'chunk' => $chunk], function () use ($afterIndex, $chunk) {
            $pending = DB::table('fund_disclosures')
                ->whereNull('pdf_url')
                ->where('attachment_count', '>', 0)
                ->where('disclosure_index', '>', $afterIndex)
                ->orderBy('disclosure_index')
                ->limit($chunk)
                ->get();

            if ($pending->isEmpty()) {
                return ['rowsRead' => 0, 'rowsWritten' => 0, 'missing' => 0, 'cursor' => $afterIndex, 'more' => false];
            }

            $written = 0;
            $missing = 0;
            $cursor = $afterIndex;

            foreach ($pending as $row) {
                $cursor = max($cursor, $row->disclosure_index);
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
                DB::table('fund_disclosures')
                    ->where('disclosure_index', $row->disclosure_index)
                    ->update(['pdf_url' => $document['url'], 'pdf_name' => $document['fileName']]);
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
}
