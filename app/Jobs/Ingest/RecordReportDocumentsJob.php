<?php

namespace App\Jobs\Ingest;

use App\Services\Ingest\HoldingsJobs;
use App\Services\Market\KapExtract;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Resolves report document links for a period one small slice at a time,
 * re-dispatching from the cursor until drained. Once documents are recorded it
 * kicks off the extraction-submit coordinator.
 */
class RecordReportDocumentsJob implements ShouldQueue, ShouldBeUniqueUntilProcessing
{
    use Queueable;

    public int $tries = 1;
    public int $timeout = 300;

    private const CHUNK = 40;

    public function __construct(
        public string $period,
        public int $after = 0,
    ) {}

    public function uniqueId(): string
    {
        return "record-report-documents:{$this->period}:{$this->after}";
    }

    public function handle(HoldingsJobs $jobs): void
    {
        $result = $jobs->recordDocumentsChunk($this->period, $this->after, self::CHUNK);

        if ($result['more']) {
            self::dispatch($this->period, $result['cursor']);
            return;
        }

        // Documents drained — submit for extraction if configured.
        if (KapExtract::isConfigured()) {
            SubmitExtractionsJob::dispatch($this->period);
        }
    }
}
