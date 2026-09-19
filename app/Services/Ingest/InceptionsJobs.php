<?php

namespace App\Services\Ingest;

use App\Services\Market\KapFunds;
use Illuminate\Support\Facades\Cache;
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

    /** The KAP fund directory, cached briefly so chunk jobs share one fetch. */
    private function directory(): array
    {
        return Cache::remember('kap:fund-directory', now()->addMinutes(30), fn () => $this->kap->fetchFundDirectory());
    }

    /**
     * Read launch dates for one bounded slice of funds past $afterCode. Cursor
     * by code so a chain drains deterministically; the directory is fetched once
     * and shared across the chain via cache.
     */
    public function inceptionChunk(string $afterCode, int $chunk): array
    {
        return $this->runs->withRun('fund-inceptions', ['after' => $afterCode, 'chunk' => $chunk], function () use ($afterCode, $chunk) {
            $pending = DB::table('funds')
                ->whereNull('inception_date')
                ->where('is_active', true)
                ->where('code', '>', $afterCode)
                ->orderBy('code')
                ->limit($chunk)
                ->pluck('code');

            if ($pending->isEmpty()) {
                return ['rowsRead' => 0, 'rowsWritten' => 0, 'missing' => 0, 'cursor' => $afterCode, 'more' => false];
            }

            $directory = $this->directory();
            $written = 0;
            $missing = 0;
            $cursor = $afterCode;

            foreach ($pending as $code) {
                $cursor = $code > $cursor ? $code : $cursor;
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
                DB::table('funds')->where('code', $code)->update(['inception_date' => $inceptionDate, 'updated_at' => now()]);
                $written++;
            }

            return [
                'rowsRead' => $pending->count(),
                'rowsWritten' => $written,
                'missing' => $missing,
                'cursor' => $cursor,
                'more' => $pending->count() === $chunk,
            ];
        });
    }
}
