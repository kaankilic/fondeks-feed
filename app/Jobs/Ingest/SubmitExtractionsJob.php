<?php

namespace App\Jobs\Ingest;

use App\Services\Ingest\HoldingsJobs;
use App\Services\Market\KapExtract;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Submit coordinator: finds a period's reports awaiting extraction and fans
 * them out into one SubmitExtractionBatchJob per batch slice, so each job
 * downloads and submits only one batch's worth of PDFs.
 */
class SubmitExtractionsJob implements ShouldQueue, ShouldBeUnique
{
    use Queueable;

    public int $tries = 1;
    public int $timeout = 120;

    public function __construct(
        public ?string $period = null,
    ) {}

    public function uniqueId(): string
    {
        return 'submit-extractions:' . ($this->period ?? 'default');
    }

    public function handle(HoldingsJobs $jobs): void
    {
        if (!KapExtract::isConfigured()) {
            return;
        }

        $period = $this->period ?? HoldingsJobs::previousPeriod(HoldingsJobs::periodOf());
        $indexes = $jobs->pendingSubmitIndexes($period);

        foreach (array_chunk($indexes, KapExtract::requestsPerBatch()) as $slice) {
            SubmitExtractionBatchJob::dispatch($period, $slice);
        }
    }
}
