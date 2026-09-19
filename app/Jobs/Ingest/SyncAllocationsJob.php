<?php

namespace App\Jobs\Ingest;

use App\Services\Ingest\FundJobs;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** Imports the portfolio breakdown behind "Varlık Dağılımı". */
class SyncAllocationsJob implements ShouldQueue, ShouldBeUnique
{
    use Queueable;

    public int $tries = 2;
    public int $timeout = 900;
    public int $backoff = 60;

    public function __construct(
        public ?int $days = null,
    ) {}

    public function uniqueId(): string
    {
        return 'sync-allocations';
    }

    public function handle(FundJobs $jobs): void
    {
        if ($this->days !== null) {
            $jobs->syncAllocations(FundJobs::isoDaysAgo($this->days), FundJobs::today());
            return;
        }
        $jobs->syncAllocations();
    }
}
