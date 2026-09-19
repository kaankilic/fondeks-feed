<?php

namespace App\Jobs\Ingest;

use App\Services\Ingest\HoldingsJobs;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Portfolio disclosures, submit pass. Against KAP this discovers the period's
 * "Portföy Dağılım Raporu" filings and queues them for extraction; the movers
 * are rebuilt later by CollectPositionsJob once the batches end.
 *
 * The default period is the month that just closed — on the 3rd, filings for
 * the previous month are landing, and the current month has no report at all.
 */
class SyncPositionsJob implements ShouldQueue, ShouldBeUnique
{
    use Queueable;

    public int $tries = 1;
    public int $timeout = 1800;

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
        $jobs->syncPositions($period);
    }
}
