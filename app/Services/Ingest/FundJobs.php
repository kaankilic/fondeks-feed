<?php

namespace App\Services\Ingest;

use App\Services\Market\TefasProvider;
use Illuminate\Support\Facades\DB;

/**
 * Catalogue, daily stats and allocation jobs. Every write is an idempotent
 * upsert keyed on the natural key, so re-running a range repairs it instead of
 * duplicating rows — which is what makes retries and overlapping schedules safe.
 */
class FundJobs
{
    /** Days of breakdown history a routine allocations run refreshes. */
    private const ALLOCATION_WINDOW_DAYS = 10;
    public const ALLOCATION_BACKFILL_DAYS = 45;

    private const FOUNDER_COLORS = [
        '#E30613', '#1F5FA6', '#12386B', '#0A7A3D', '#0B4DA2',
        '#7A2E8E', '#C4122F', '#8A1538', '#B01030', '#0A6E8A',
    ];

    public function __construct(private IngestRunService $runs = new IngestRunService()) {}

    public static function isoDaysAgo(int $days): string
    {
        return now()->subDays($days)->format('Y-m-d');
    }

    public static function today(): string
    {
        return now()->format('Y-m-d');
    }

    private function initialsFor(string $name): string
    {
        $parts = array_values(array_filter(preg_split('/\s+/', $name)));
        $letters = '';
        foreach (array_slice($parts, 0, 2) as $part) {
            $letters .= mb_substr($part, 0, 1, 'UTF-8');
        }
        $letters = $letters ?: 'F';
        return mb_substr(mb_strtoupper($letters, 'UTF-8'), 0, 4, 'UTF-8');
    }

    private function colorFor(string $name): string
    {
        $hash = 0;
        $len = mb_strlen($name, 'UTF-8');
        for ($i = 0; $i < $len; $i++) {
            $char = mb_substr($name, $i, 1, 'UTF-8');
            $hash = ($hash * 31 + mb_ord($char, 'UTF-8')) & 0xFFFFFFFF;
        }
        return self::FOUNDER_COLORS[$hash % count(self::FOUNDER_COLORS)];
    }

    /** Imports the fund catalogue: issuers first, then the funds themselves. */
    public function syncFundCatalog(): array
    {
        $provider = new TefasProvider();

        return $this->runs->withRun('fund-catalog', ['provider' => $provider->name], function () use ($provider) {
            $entries = $provider->listFunds();
            if (count($entries) === 0) {
                throw new \RuntimeException("{$provider->name} returned an empty fund catalogue");
            }

            $issuers = [];
            foreach ($entries as $entry) {
                if (isset($issuers[$entry['founder']])) {
                    continue;
                }
                $issuers[$entry['founder']] = [
                    'initials' => $entry['founderInitials'] ?? $this->initialsFor($entry['founder']),
                    'color' => $entry['founderColor'] ?? $this->colorFor($entry['founder']),
                ];
            }

            $founderRows = [];
            foreach ($issuers as $name => $brand) {
                $founderRows[] = ['name' => $name, 'initials' => $brand['initials'], 'color' => $brand['color']];
            }
            Upsert::run('founders', $founderRows, ['name'], 'initials = excluded.initials, color = excluded.color');

            $now = now();
            $rows = array_map(fn ($entry) => [
                'code' => $entry['code'],
                'name' => $entry['name'],
                'founder' => $entry['founder'],
                'category' => $entry['category'],
                'isin' => $entry['isin'] ?? null,
                'inception_date' => $entry['inceptionDate'] ?? null,
                'management_fee' => $entry['managementFee'] ?? 0,
                'withholding_tax' => $entry['withholdingTax'] ?? null,
                'risk' => $entry['risk'] ?? null,
                'buy_value_days' => $entry['buyValueDays'] ?? null,
                'sell_value_days' => $entry['sellValueDays'] ?? null,
                'fund_type' => $entry['fundType'] ?? 'YAT',
                'on_tefas' => $entry['onTefas'] ?? null,
                'tefas_type_code' => $entry['typeCode'] ?? null,
                'is_active' => true,
                'source' => $provider->name,
                'updated_at' => $now,
            ], $entries);

            $written = Upsert::run('funds', $rows, ['code'], implode(', ', [
                'name = excluded.name',
                'founder = excluded.founder',
                'category = excluded.category',
                'isin = coalesce(excluded.isin, funds.isin)',
                'inception_date = coalesce(excluded.inception_date, funds.inception_date)',
                'management_fee = excluded.management_fee',
                'withholding_tax = coalesce(excluded.withholding_tax, funds.withholding_tax)',
                'risk = coalesce(excluded.risk, funds.risk)',
                'buy_value_days = coalesce(excluded.buy_value_days, funds.buy_value_days)',
                'sell_value_days = coalesce(excluded.sell_value_days, funds.sell_value_days)',
                'fund_type = excluded.fund_type',
                'on_tefas = coalesce(excluded.on_tefas, funds.on_tefas)',
                'tefas_type_code = excluded.tefas_type_code',
                'is_active = true',
                'source = excluded.source',
                'updated_at = now()',
            ]));

            // Anything the source stopped listing is retired, not removed.
            $codes = array_column($entries, 'code');
            DB::table('funds')
                ->where('source', $provider->name)
                ->whereNotIn('code', $codes)
                ->update(['is_active' => false, 'updated_at' => now()]);

            return ['rowsRead' => count($entries), 'rowsWritten' => $written];
        });
    }

    /** Imports daily price, size and investor counts for a date range. */
    public function syncDailyStats(string $from, string $to): array
    {
        $provider = new TefasProvider();

        return $this->runs->withRun('daily-stats', ['provider' => $provider->name, 'from' => $from, 'to' => $to], function () use ($provider, $from, $to) {
            $stats = $provider->fetchDailyStats(['from' => $from, 'to' => $to]);

            $known = DB::table('funds')->pluck('code')->flip();

            $rows = [];
            foreach ($stats as $stat) {
                if (!$known->has($stat['code'])) {
                    continue;
                }
                $rows[] = [
                    'fund_code' => $stat['code'],
                    'date' => $stat['date'],
                    'price' => $stat['price'],
                    'total_value' => $stat['totalValue'] ?? null,
                    'investor_count' => $stat['investorCount'] ?? null,
                    'share_count' => $stat['shareCount'] ?? null,
                ];
            }

            $written = Upsert::run('fund_daily_stats', $rows, ['fund_code', 'date'], implode(', ', [
                'price = excluded.price',
                'total_value = coalesce(excluded.total_value, fund_daily_stats.total_value)',
                'investor_count = coalesce(excluded.investor_count, fund_daily_stats.investor_count)',
                'share_count = coalesce(excluded.share_count, fund_daily_stats.share_count)',
                'ingested_at = now()',
            ]));

            return ['rowsRead' => count($stats), 'rowsWritten' => $written, 'skipped' => count($stats) - count($rows)];
        });
    }

    public function syncRecentDays(int $days = 3): array
    {
        return $this->syncDailyStats(self::isoDaysAgo($days), self::today());
    }

    public function backfillDailyStats(int $days = 400): array
    {
        return $this->syncDailyStats(self::isoDaysAgo($days), self::today());
    }

    /**
     * Fills each fund's künye (ISIN, risk, valör days) from TEFAS one bounded
     * slice at a time. Only funds still missing the data are read, so this
     * converges then idles — like the inceptions backfill. Cursor by code.
     */
    public function enrichProfilesChunk(string $afterCode, int $chunk): array
    {
        $provider = new TefasProvider();

        return $this->runs->withRun('fund-profiles', ['after' => $afterCode, 'chunk' => $chunk], function () use ($provider, $afterCode, $chunk) {
            $pending = DB::table('funds')
                ->where('is_active', true)
                ->where('code', '>', $afterCode)
                ->where(function ($q) {
                    $q->whereNull('isin')->orWhereNull('risk');
                })
                ->orderBy('code')
                ->limit($chunk)
                ->pluck('code');

            if ($pending->isEmpty()) {
                return ['rowsRead' => 0, 'rowsWritten' => 0, 'missing' => 0, 'cursor' => $afterCode, 'more' => false];
            }

            $written = 0;
            $missing = 0;
            $cursor = $afterCode;

            foreach ($pending as $code) {
                $cursor = $code > $cursor ? $code : $cursor;

                try {
                    $profile = $provider->fetchFundProfile($code);
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::warning("[profiles] {$code}: {$e->getMessage()}");
                    continue;
                }

                if (!$profile) {
                    $missing++;
                    continue;
                }

                // Only overwrite with values TEFAS actually returned, so a null
                // valör (common for pension funds) never wipes existing data.
                $update = ['updated_at' => now()];
                foreach (['isin' => 'isin', 'risk' => 'risk', 'buy_value_days' => 'buyValueDays', 'sell_value_days' => 'sellValueDays'] as $col => $key) {
                    if ($profile[$key] !== null) {
                        $update[$col] = $profile[$key];
                    }
                }

                if (count($update) > 1) {
                    DB::table('funds')->where('code', $code)->update($update);
                    $written++;
                } else {
                    $missing++;
                }
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

    /** Imports the portfolio breakdown behind "Varlık Dağılımı". */
    public function syncAllocations(?string $from = null, ?string $to = null): array
    {
        $provider = new TefasProvider();
        $window = [
            'from' => $from ?? self::isoDaysAgo(self::ALLOCATION_WINDOW_DAYS),
            'to' => $to ?? self::today(),
        ];

        return $this->runs->withRun('fund-allocations', array_merge(['provider' => $provider->name], $window), function () use ($provider, $window) {
            $slices = $provider->fetchAllocations($window);

            $known = DB::table('funds')->pluck('code')->flip();

            // Grouped by fund and day, keyed by label.
            $byDay = [];
            foreach ($slices as $slice) {
                if (!$known->has($slice['code'])) {
                    continue;
                }
                $key = "{$slice['code']}\0{$slice['date']}";
                $byDay[$key][$slice['label']] = $slice['pct'];
            }

            $rows = [];
            foreach ($byDay as $key => $labels) {
                [$fundCode, $date] = explode("\0", $key);
                arsort($labels); // heaviest slice first
                $position = 0;
                foreach ($labels as $label => $pct) {
                    $rows[] = [
                        'fund_code' => $fundCode,
                        'date' => $date,
                        'label' => $label,
                        'pct' => $pct,
                        'position' => $position++,
                    ];
                }
            }

            // One transaction, or a failure between delete and inserts leaves the
            // window empty.
            $written = DB::transaction(function () use ($rows, $window) {
                DB::table('fund_allocations')
                    ->whereBetween('date', [$window['from'], $window['to']])
                    ->delete();
                return Upsert::insert('fund_allocations', $rows);
            });

            $days = [];
            foreach (array_keys($byDay) as $key) {
                $days[explode("\0", $key)[1]] = true;
            }

            return [
                'rowsRead' => count($slices),
                'rowsWritten' => $written,
                'days' => count($days),
                'skipped' => count($slices) - count($rows),
            ];
        });
    }
}
