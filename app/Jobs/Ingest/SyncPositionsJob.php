<?php

namespace App\Jobs\Ingest;

use App\Services\Ingest\HoldingsJobs;
use App\Services\Market\KapClient;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Holdings submit pass coordinator. Runs the bounded discovery walk for the
 * closed month, then hands document resolution to a self-chaining chunk job,
 * which in turn fans out extraction submission — so the heavy per-report work
 * never lands in one long job.
 *
 * Offline (fixture) it finishes in one call, since nothing is asynchronous.
 */
class SyncPositionsJob implements ShouldQueue, ShouldBeUnique
{
    use Queueable;

    public int $tries = 1;
    public int $timeout = 600;

    public function __construct(
        public ?string $period = null,
    ) {}

    public function uniqueId(): string
    {
        return 'sync-positions';
    }

    public function handle(HoldingsJobs $jobs): void
    {
        $period = $this->period ?? HoldingsJobs::previousPeriod(HoldingsJobs::periodOf());

        if (!KapClient::isKapEnabled()) {
            $jobs->syncPositions($period);
            return;
        }

        $window = $jobs->reportingWindow($period);
        $jobs->discoverPortfolioReports($window);

        RecordReportDocumentsJob::dispatch($period, 0);
    }
}
