<?php

namespace App\Jobs\Ingest;

use App\Services\Ingest\InceptionsJobs;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Backfills fund launch dates from KAP. Runs through the catalogue a slice at a
 * time and then idles, since a launch date is read once and never changes.
 */
class SyncFundInceptionsJob implements ShouldQueue, ShouldBeUnique
{
    use Queueable;

    public int $tries = 2;
    public int $timeout = 1200;
    public int $backoff = 60;

    public function __construct(
        public ?int $limit = null,
    ) {}

    public function uniqueId(): string
    {
        return 'sync-fund-inceptions';
    }

    public function handle(InceptionsJobs $jobs): void
    {
        $jobs->syncFundInceptions($this->limit);
    }
}
