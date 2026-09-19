<?php

namespace App\Jobs\Ingest;

use App\Services\Ingest\DisclosuresJobs;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Fund disclosure archive coordinator. Runs the bounded discovery walk, then
 * hands PDF-link resolution to a self-chaining chunk job so no single job makes
 * hundreds of sequential KAP lookups.
 */
class SyncDisclosuresJob implements ShouldQueue, ShouldBeUnique
{
    use Queueable;

    public int $tries = 2;
    public int $timeout = 600;
    public int $backoff = 60;

    public function __construct(
        public ?int $days = null,
        public ?int $limit = null,
    ) {
        $this->onQueue('kap');
    }

    public function uniqueId(): string
    {
        return 'sync-disclosures';
    }

    public function handle(DisclosuresJobs $jobs): void
    {
        $days = $this->days ?? config('ingest.kap.disclosure_days');
        $jobs->discoverDisclosures(
            now()->subDays($days)->format('Y-m-d'),
            now()->format('Y-m-d'),
        );

        ResolveDisclosureLinksJob::dispatch(0);
    }
}
