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
                        Log::warning("[indices] skipping {$row->name}: TCMB_EVDS_API_KEY is not set");
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
}
