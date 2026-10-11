<?php

namespace App\Services\Ingest;

use App\Services\Market\IndexProviders;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Fills index_quotes for every active index, using whichever source the row
 * declares. Sources that are not configured (EVDS without a key) are skipped
 * with a warning rather than failing the run.
 */
class IndicesJobs
{
    public function __construct(
        private IngestRunService $runs = new IngestRunService(),
        private IndexProviders $providers = new IndexProviders(),
    ) {}

    public function syncMarketIndices(string $from, string $to): array
    {
        return $this->runs->withRun('market-indices', ['from' => $from, 'to' => $to], function () use ($from, $to) {
            $indices = DB::table('market_indices')->where('is_active', true)->get();

            $offline = (config('ingest.market_provider') ?: 'fixture') === 'fixture';
            $quotes = [];
            $read = 0;

            // TCMB publishes one bulletin per day covering every currency.
            $tcmbRows = $indices->filter(fn ($row) => !$offline && $row->source === 'tcmb' && $row->source_symbol);

            if ($tcmbRows->isNotEmpty()) {
                $series = $this->providers->tcmbQuotes($from, $to);
                $read += count($series);
                foreach ($series as $quote) {
                    foreach ($tcmbRows as $row) {
                        if ($row->source_symbol === $quote['name']) {
                            $quotes[] = ['index_name' => $row->name, 'date' => $quote['date'], 'value' => $quote['value']];
                        }
                    }
                }
            }

            foreach ($indices as $row) {
                $source = $offline ? 'fixture' : $row->source;

                if ($source === 'tcmb') {
                    continue;
                }

                if ($source === 'evds') {
                    if (!$this->providers->evdsConfigured()) {
                        Log::warning("[indices] skipping {$row->name}: TCMB_EVDS_API_TOKEN is not set");
                        continue;
                    }
                    if (!$row->source_symbol) {
                        continue;
                    }
                    try {
                        $series = $this->providers->evdsSeries($row->source_symbol, $from, $to);
                        $read += count($series);
                        foreach ($series as $quote) {
                            $quotes[] = ['index_name' => $row->name, 'date' => $quote['date'], 'value' => $quote['value']];
                        }
                    } catch (\Throwable $e) {
                        Log::warning("[indices] {$row->name} failed: {$e->getMessage()}");
                    }
                    continue;
                }

                if ($source === 'yahoo') {
                    if (!$row->source_symbol) {
                        continue;
                    }
                    try {
                        $series = $this->providers->yahooSeries($row->source_symbol, $from, $to);
                        $read += count($series);
                        foreach ($series as $quote) {
                            $quotes[] = ['index_name' => $row->name, 'date' => $quote['date'], 'value' => $quote['value']];
                        }
                    } catch (\Throwable $e) {
                        Log::warning("[indices] {$row->name} failed: {$e->getMessage()}");
                    }
                    continue;
                }

                $series = $this->providers->fixtureQuotes($row->name, $row->position + 3, $from, $to);
                $read += count($series);
                foreach ($series as $quote) {
                    $quotes[] = ['index_name' => $row->name, 'date' => $quote['date'], 'value' => $quote['value']];
                }
            }

            $written = Upsert::run('index_quotes', $quotes, ['index_name', 'date'],
                'value = excluded.value, ingested_at = now()');

            return ['rowsRead' => $read, 'rowsWritten' => $written];
        });
    }

    /**
     * Fills the `cpi` table from EVDS's TÜFE series — the monthly index levels
     * real fund returns are measured against. A wide window is cheap (one row
     * per month) and keeps history deep enough to deflate a fund's return since
     * its inception. Skipped with a warning when EVDS has no key, so a run on an
     * unconfigured environment is a no-op rather than a failure.
     */
    public function syncCpi(string $from, string $to): array
    {
        return $this->runs->withRun('cpi', ['from' => $from, 'to' => $to], function () use ($from, $to) {
            if ((config('ingest.market_provider') ?: 'fixture') === 'fixture') {
                return ['rowsRead' => 0, 'rowsWritten' => 0];
            }

            if (!$this->providers->evdsConfigured()) {
                Log::warning('[cpi] skipping: TCMB_EVDS_API_TOKEN is not set');

                return ['rowsRead' => 0, 'rowsWritten' => 0];
            }

            $series = trim((string) config('ingest.tcmb.cpi_series'));
            if ($series === '') {
                Log::warning('[cpi] skipping: CPI_SERIES is not set');

                return ['rowsRead' => 0, 'rowsWritten' => 0];
            }

            $quotes = $this->providers->evdsSeries($series, $from, $to);

            $rows = array_map(fn ($quote) => [
                'date' => $quote['date'],
                'value' => $quote['value'],
            ], $quotes);

            $written = Upsert::run('cpi', $rows, ['date'],
                'value = excluded.value, ingested_at = now()');

            return ['rowsRead' => count($quotes), 'rowsWritten' => $written];
        });
    }
}
