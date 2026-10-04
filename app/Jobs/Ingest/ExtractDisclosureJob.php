<?php

namespace App\Jobs\Ingest;

use App\Services\Ingest\HoldingsJobs;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Extract one Portföy Dağılım Raporu on demand — the Bildirimler "Çıkar" button
 * dispatches this instead of running the extraction inside the web request.
 * Runs on the single-process KAP worker so it stays paced behind other KAP
 * calls (KAP IP-blocks fast callers) and never races the position rebuild.
 *
 * The handler downloads the PDF, reads it (Haiku, escalating to Sonnet when
 * needed), writes the holdings snapshot, and rebuilds that period's artırılan /
 * azaltılan fund_positions so the increased and decreased stocks reflect the
 * new snapshot. Outcome and any failure reason are recorded on the
 * kap-extract-ondemand ingest run.
 */
class ExtractDisclosureJob implements ShouldQueue, ShouldBeUnique
{
    use Queueable;

    public int $tries = 1;
    public int $timeout = 1800;
    public int $uniqueFor = 1800;

    public function __construct(public int $disclosureIndex)
    {
        $this->onQueue('kap');
    }

    public function uniqueId(): string
    {
        return "extract-disclosure:{$this->disclosureIndex}";
    }

    public function handle(HoldingsJobs $jobs): void
    {
        $jobs->extractDisclosureNow($this->disclosureIndex);
    }
}
