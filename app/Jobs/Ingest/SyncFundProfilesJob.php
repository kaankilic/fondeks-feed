<?php

namespace App\Jobs\Ingest;

use App\Services\Ingest\FundJobs;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Fills each fund's künye (ISIN, risk value, alış/satış valör) from TEFAS's
 * fonProfilBilgiGetir, one small slice per job, re-dispatching from the cursor
 * until every fund is enriched. Converges then idles, since künye is static.
 */
class SyncFundProfilesJob implements ShouldQueue, ShouldBeUniqueUntilProcessing
{
    use Queueable;

    public int $tries = 1;
    public int $timeout = 600;

    // TEFAS throttles ~8 requests/min; a small slice keeps each job short.
    private const CHUNK = 15;

    public function __construct(
        public string $after = '',
    ) {}

    public function uniqueId(): string
    {
        return 'sync-fund-profiles:' . $this->after;
    }

    public function handle(FundJobs $jobs): void
    {
        $result = $jobs->enrichProfilesChunk($this->after, self::CHUNK);

        if ($result['more']) {
            self::dispatch($result['cursor']);
        }
    }
}
