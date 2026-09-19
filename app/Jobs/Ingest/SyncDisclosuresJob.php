<?php

namespace App\Jobs\Ingest;

use App\Services\Ingest\DisclosuresJobs;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Fund disclosure archive. Discovers the window's KAP filings for funds we
 * track, then resolves the PDF link for anything still missing one. Both passes
 * are idempotent and bounded, so overlap and re-runs are safe.
 */
class SyncDisclosuresJob implements ShouldQueue, ShouldBeUnique
{
    use Queueable;

    public int $tries = 2;
    public int $timeout = 1200;
    public int $backoff = 60;

    public function __construct(
        public ?int $days = null,
        public ?int $limit = null,
    ) {}

    public function uniqueId(): string
    {
        return 'sync-disclosures';
    }

    public function handle(DisclosuresJobs $jobs): void
    {
        $jobs->syncDisclosures($this->days, $this->limit);
    }
}
