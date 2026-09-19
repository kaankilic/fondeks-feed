<?php

namespace App\Jobs\Ingest;

use App\Services\Ingest\FundJobs;
use App\Services\Ingest\IndicesJobs;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Index quotes. Re-reads a short window so a late bulletin — TCMB publishes
 * after 15:30 — is picked up by the next run.
 */
class SyncMarketIndicesJob implements ShouldQueue, ShouldBeUnique
{
    use Queueable;

    public int $tries = 2;
    public int $timeout = 600;
    public int $backoff = 60;

    public function __construct(
        public int $days = 5,
    ) {}

    public function uniqueId(): string
    {
        return 'sync-market-indices';
    }

    public function handle(IndicesJobs $jobs): void
    {
        $jobs->syncMarketIndices(FundJobs::isoDaysAgo($this->days), FundJobs::today());
    }
}
