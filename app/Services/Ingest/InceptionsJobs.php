<?php

namespace App\Services\Ingest;

use App\Services\Market\KapFunds;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Fills in each fund's launch date from KAP. Only funds still missing a date
 * are read, so this converges: the first runs work through the catalogue a slice
 * at a time and every run after that makes one directory request and stops.
 */
class InceptionsJobs
{
    public function __construct(
        private IngestRunService $runs = new IngestRunService(),
        private KapFunds $kap = new KapFunds(),
    ) {}

    public function syncFundInceptions(?int $limit = null): array
    {
        $limit ??= config('ingest.kap.inception_limit');

        return $this->runs->withRun('fund-inceptions', ['limit' => $limit], function () use ($limit) {
            $pending = DB::table('funds')
                ->whereNull('inception_date')
                ->where('is_active', true)
                ->orderBy('code')
                ->limit($limit)
                ->pluck('code');

            if ($pending->isEmpty()) {
                return ['rowsRead' => 0, 'rowsWritten' => 0, 'missing' => 0];
            }

            $directory = $this->kap->fetchFundDirectory();

            $written = 0;
            $missing = 0;

            foreach ($pending as $code) {
                $oid = $directory[$code] ?? null;
                if (!$oid) {
                    $missing++;
                    continue;
                }

                try {
                    $inceptionDate = $this->kap->fetchFundInception($oid);
                } catch (\Throwable $e) {
                    Log::warning("[kap] {$code}: could not read its record — {$e->getMessage()}");
                    continue;
                }

                if (!$inceptionDate) {
                    $missing++;
                    continue;
                }

                DB::table('funds')
                    ->where('code', $code)
                    ->update(['inception_date' => $inceptionDate, 'updated_at' => now()]);
                $written++;
            }

            return ['rowsRead' => $pending->count(), 'rowsWritten' => $written, 'missing' => $missing];
        });
    }
}
