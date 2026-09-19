<?php

namespace App\Console\Commands;

use App\Services\Ingest\DisclosuresJobs;
use App\Services\Ingest\FundJobs;
use App\Services\Ingest\HoldingsJobs;
use App\Services\Ingest\IndicesJobs;
use App\Services\Ingest\InceptionsJobs;
use App\Models\IngestRun;
use Illuminate\Console\Command;

/**
 * Ingestion CLI, mirroring the Next.js `yarn ingest` tool.
 *
 *   php artisan ingest catalog
 *   php artisan ingest daily --days=3
 *   php artisan ingest range --from=2026-01-01 --to=2026-03-31
 *   php artisan ingest allocations --days=45
 *   php artisan ingest indices --days=60
 *   php artisan ingest positions --period=2026-07-01
 *   php artisan ingest collect
 *   php artisan ingest documents --period=2026-07-01 --limit=500
 *   php artisan ingest disclosures --days=7 --limit=500
 *   php artisan ingest reextract --period=2026-07-01 --codes=AFT,BHE
 *   php artisan ingest inceptions --limit=200
 *   php artisan ingest backfill --days=400
 *   php artisan ingest reports --period=2026-07-01
 *   php artisan ingest status
 */
class IngestCommand extends Command
{
    protected $signature = 'ingest {action} {--days=} {--from=} {--to=} {--period=} {--codes=} {--limit=}';

    protected $description = 'Run a market-data ingestion job';

    public function handle(
        FundJobs $funds,
        IndicesJobs $indices,
        HoldingsJobs $holdings,
        DisclosuresJobs $disclosures,
        InceptionsJobs $inceptions,
    ): int {
        $action = $this->argument('action');
        $this->line('provider: ' . config('ingest.market_provider'));

        try {
            match ($action) {
                'catalog' => $this->report('catalog', $funds->syncFundCatalog()),
                'daily' => $this->report('daily', $funds->syncRecentDays((int) ($this->option('days') ?? 3))),
                'range' => $this->range($funds),
                'allocations' => $this->allocations($funds),
                'indices' => $this->report('indices', $indices->syncMarketIndices(
                    FundJobs::isoDaysAgo((int) ($this->option('days') ?? 60)), FundJobs::today())),
                'positions' => $this->report('positions', $holdings->syncPositions($this->option('period'))),
                'collect' => $this->report('collect', $holdings->collectPositions()),
                'documents' => $this->report('documents', $holdings->recordReportDocuments(
                    $this->option('period'), $this->option('limit') ? (int) $this->option('limit') : null)),
                'disclosures' => $this->report('disclosures', $disclosures->syncDisclosures(
                    $this->option('days') ? (int) $this->option('days') : null,
                    $this->option('limit') ? (int) $this->option('limit') : null)),
                'reextract' => $this->report('reextract', $holdings->reextractPositions([
                    'period' => $this->option('period'),
                    'codes' => $this->option('codes') ? explode(',', $this->option('codes')) : null,
                    'limit' => $this->option('limit') ? (int) $this->option('limit') : null,
                ]) ?? ['skipped' => true]),
                'inceptions' => $this->report('inceptions', $inceptions->syncFundInceptions(
                    $this->option('limit') ? (int) $this->option('limit') : null)),
                'backfill' => $this->backfill($funds, $indices, $holdings),
                'reports' => $this->reports($holdings),
                'status' => $this->status(),
                default => $this->usage(),
            };
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function report(string $label, array $result): void
    {
        $run = $result['run'] ?? null;
        if ($run) {
            $this->info("{$label}: read {$run['rowsRead']}, wrote {$run['rowsWritten']} in {$run['durationMs']}ms");
        } else {
            $this->info("{$label}: " . json_encode($result));
        }
    }

    private function range(FundJobs $funds): void
    {
        $from = $this->option('from');
        if (!$from) {
            throw new \RuntimeException('range needs --from=yyyy-mm-dd');
        }
        $to = $this->option('to') ?? FundJobs::today();
        $this->report("range {$from}..{$to}", $funds->syncDailyStats($from, $to));
    }

    private function allocations(FundJobs $funds): void
    {
        $days = $this->option('days');
        $result = $days === null
            ? $funds->syncAllocations()
            : $funds->syncAllocations(FundJobs::isoDaysAgo((int) $days), FundJobs::today());
        $this->report('allocations', $result);
    }

    private function backfill(FundJobs $funds, IndicesJobs $indices, HoldingsJobs $holdings): void
    {
        $days = (int) ($this->option('days') ?? 400);
        $funds->syncFundCatalog();
        $funds->backfillDailyStats($days);
        $indices->syncMarketIndices(FundJobs::isoDaysAgo(min($days, 120)), FundJobs::today());
        $funds->syncAllocations(FundJobs::isoDaysAgo(FundJobs::ALLOCATION_BACKFILL_DAYS), FundJobs::today());
        $holdings->syncPositions();
        $this->info('backfill: complete');
    }

    private function reports(HoldingsJobs $holdings): void
    {
        $period = $this->option('period') ?? HoldingsJobs::periodOf();
        $progress = $holdings->periodProgress($period);
        $counts = [];
        foreach ($progress as $status => $count) {
            $counts[] = "{$status}={$count}";
        }
        $this->info("reports {$period}: " . ($counts ? implode(' ', $counts) : 'none discovered yet'));
    }

    private function status(): void
    {
        foreach (IngestRun::orderByDesc('started_at')->limit(10)->get() as $run) {
            $this->line(implode(' ', array_filter([
                $run->started_at?->toIso8601String(),
                str_pad($run->job, 14),
                str_pad($run->status, 8),
                "read={$run->rows_read}",
                "wrote={$run->rows_written}",
                $run->error ? "error={$run->error}" : '',
            ])));
        }
    }

    private function usage(): void
    {
        $this->warn('usage: php artisan ingest <catalog|daily|range|allocations|indices|positions|collect|documents|disclosures|reextract|inceptions|backfill|reports|status> [--days=] [--from=] [--to=] [--period=] [--codes=] [--limit=]');
    }
}
