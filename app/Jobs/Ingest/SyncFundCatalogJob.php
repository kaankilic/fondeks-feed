<?php

namespace App\Jobs\Ingest;

use App\Services\Ingest\FundJobs;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** Weekly catalogue refresh: new funds, renames, retired funds. */
class SyncFundCatalogJob implements ShouldQueue, ShouldBeUnique
{
    use Queueable;

    public int $tries = 2;
    public int $timeout = 600;
    public int $backoff = 60;

    public function uniqueId(): string
    {
        return 'sync-fund-catalog';
    }

    public function handle(FundJobs $jobs): void
    {
        $jobs->syncFundCatalog();
    }
}
