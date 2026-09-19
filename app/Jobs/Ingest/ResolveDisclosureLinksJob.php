<?php

namespace App\Jobs\Ingest;

use App\Services\Ingest\DisclosuresJobs;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Resolves disclosure PDF links one small slice at a time, re-dispatching from
 * the cursor until drained — keeps each job to a handful of KAP lookups.
 */
class ResolveDisclosureLinksJob implements ShouldQueue, ShouldBeUniqueUntilProcessing
{
    use Queueable;

    public int $tries = 1;
    public int $timeout = 300;

    private const CHUNK = 40;

    public function __construct(
        public int $after = 0,
    ) {
        $this->onQueue('kap');
    }

    public function uniqueId(): string
    {
        return 'resolve-disclosure-links:' . $this->after;
    }

    public function handle(DisclosuresJobs $jobs): void
    {
        $result = $jobs->resolveLinksChunk($this->after, self::CHUNK);

        if ($result['more']) {
            self::dispatch($result['cursor']);
        }
    }
}
