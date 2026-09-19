<?php

namespace App\Jobs\Ingest;

use App\Services\Ingest\HoldingsJobs;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Portfolio disclosures, collect pass. Applies whatever extraction batches have
 * finished and rebuilds artırılan / azaltılan pozisyonlar for the periods they
 * touched. Safe to call at any time: with nothing outstanding it does nothing.
 */
class CollectPositionsJob implements ShouldQueue, ShouldBeUnique
{
    use Queueable;

    public int $tries = 1;
    public int $timeout = 1800;

    public function uniqueId(): string
    {
        return 'collect-positions';
    }

    public function handle(HoldingsJobs $jobs): void
    {
        $jobs->collectPositions();
    }
}
