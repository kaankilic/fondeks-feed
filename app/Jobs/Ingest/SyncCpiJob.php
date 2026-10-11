<?php

namespace App\Jobs\Ingest;

use App\Services\Ingest\IndicesJobs;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * TÜFE (CPI) index from EVDS, the deflator behind real fund returns. The series
 * is monthly, so a deep window is only ~one row per month: re-reading a long
 * span each run is cheap and keeps history reaching back past old funds'
 * inception dates. A late monthly release is picked up by the next run.
 */
class SyncCpiJob implements ShouldQueue, ShouldBeUnique
{
    use Queueable;

    public int $tries = 2;
    public int $timeout = 120;
    public int $backoff = 60;

    public function __construct(
        public int $months = 120,
    ) {}

    public function uniqueId(): string
    {
        return 'sync-cpi';
    }

    public function handle(IndicesJobs $jobs): void
    {
        $from = CarbonImmutable::now('Europe/Istanbul')
            ->subMonths($this->months)
            ->startOfMonth()
            ->toDateString();
        $to = CarbonImmutable::now('Europe/Istanbul')->toDateString();

        $jobs->syncCpi($from, $to);
    }
}
