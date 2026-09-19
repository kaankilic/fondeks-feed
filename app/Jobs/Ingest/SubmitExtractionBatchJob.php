<?php

namespace App\Jobs\Ingest;

use App\Services\Ingest\HoldingsJobs;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Downloads the PDFs for one explicit set of reports and submits them as a
 * single extraction batch. One batch per job bounds runtime.
 */
class SubmitExtractionBatchJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;
    public int $timeout = 600;

    /**
     * @param int[] $disclosureIndexes
     */
    public function __construct(
        public string $period,
        public array $disclosureIndexes,
    ) {
        $this->onQueue('kap');
    }

    public function handle(HoldingsJobs $jobs): void
    {
        if (empty($this->disclosureIndexes)) {
            return;
        }
        $jobs->submitOneBatch($this->period, $this->disclosureIndexes);
    }
}
