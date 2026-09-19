<?php

namespace App\Jobs\Ingest;

use App\Services\Ingest\InceptionsJobs;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Backfills fund launch dates from KAP, one small slice per job. Reads up to
 * CHUNK records, then re-dispatches itself from the cursor until the catalogue
 * is drained — so no single job makes hundreds of sequential page reads.
 */
class SyncFundInceptionsJob implements ShouldQueue, ShouldBeUniqueUntilProcessing
{
    use Queueable;

    public int $tries = 1;
    public int $timeout = 300;

    private const CHUNK = 25;

    public function __construct(
        public string $after = '',
    ) {}

    public function uniqueId(): string
    {
        return 'sync-fund-inceptions:' . $this->after;
    }

    public function handle(InceptionsJobs $jobs): void
    {
        $result = $jobs->inceptionChunk($this->after, self::CHUNK);

        if ($result['more']) {
            self::dispatch($result['cursor']);
        }
    }
}
