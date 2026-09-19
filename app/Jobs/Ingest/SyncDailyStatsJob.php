<?php

namespace App\Jobs\Ingest;

use App\Services\Ingest\FundJobs;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Scheduled daily import. Re-reads the last few days by default so a late or
 * corrected publish is picked up — writes are upserts, so overlap is safe.
 */
class SyncDailyStatsJob implements ShouldQueue, ShouldBeUnique
{
    use Queueable;

    public int $tries = 2;
    public int $timeout = 900;
    public int $backoff = 60;

    public function __construct(
        public int $days = 3,
        public ?string $from = null,
        public ?string $to = null,
    ) {}

    public function uniqueId(): string
    {
        return 'sync-daily-stats';
    }

    public function handle(FundJobs $jobs): void
    {
        if ($this->from) {
            $jobs->syncDailyStats($this->from, $this->to ?? FundJobs::today());
            return;
        }
        $jobs->syncRecentDays($this->days);
    }
}
