<?php

namespace App\Services\Fondeks;

use App\Support\Fondeks\Constants;
use App\Support\Fondeks\Format;
use App\Support\Fondeks\Num;
use App\Support\Fondeks\Palette;
use App\Support\Fondeks\Slug;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * The API's data access layer — a faithful PHP port of the Next.js client's
 * `src/lib/fondeks/queries.ts`. Prices, returns and fund size are derived from
 * the daily and monthly series with the exact same SQL, so a fund's figures are
 * identical whether they are read here or by the client.
 */
class FundQueries
{
    private const TRADING_DAYS_PER_YEAR = 252;

    /**
     * Read results are cached under this prefix, with a generation number folded
     * into every key: bumping the generation retires the whole set at once, so
     * no cache tags are needed (the `file`/`database` stores don't support them).
     * The generation is the latest successful ingest run's finish time — read
     * from the shared database, not the cache — so a worker that ingests new
     * data and the web process that serves it always agree on the current
     * generation, even when they run in separate containers with their own local
     * caches. That is what makes a fresh price show up right after ingest instead
     * of lingering behind a stale cache until the TTL.
     */
    private const CACHE_PREFIX = 'fondeks:funds';

    /**
     * A safety-net TTL. Invalidation is driven by the generation above, so this
     * only bounds how long a superseded entry lingers before it is evicted.
     */
    private const CACHE_TTL_SECONDS = 3600;

    /** Per-instance memo of the cache generation, so one request reads it once. */
    private ?string $generation = null;

    /** A one-day price move this large is a restatement, not a return. */
    private const SERIES_BREAK_RATIO = 1;

    /** How many peers the head-to-head table compares against. */
    private const COMPARE_PEERS = 2;

    /** How many allocation-similar peers the detail bundle lists. */
    private const SIMILAR_PEERS = 6;

    /** How many movers each of the two position panels lists. */
    private const MOVERS_PER_PANEL = 5;

    /** Smallest move worth a row — under this it renders as "0,0 puan". */
    private const MIN_MOVE_POINTS = 0.05;

    /**
     * One row per fund with its latest price, the reference prices the returns
     * are measured against, and the most recent monthly size and investor count.
     * An anchor older than the fund's most recent restatement is dropped.
     */
    private function snapshotBase(): string
    {
        $ratio = self::SERIES_BREAK_RATIO;

        return <<<SQL
            select
                f.code, f.name, f.founder, fo.initials, fo.color, f.category, f.isin,
                f.management_fee, f.withholding_tax, f.risk,
                f.buy_value_days, f.sell_value_days, f.on_tefas, f.inception_date,
                lp.price,
                to_char(lp.date, 'YYYY-MM-DD') as price_date,
                case when brk.at is null or brk.at <= prev.date then prev.price end as prev_price,
                case when brk.at is null or brk.at <= w1.date   then w1.price   end as w1_price,
                case when brk.at is null or brk.at <= m1.date   then m1.price   end as m1_price,
                case when brk.at is null or brk.at <= m3.date   then m3.price   end as m3_price,
                case when brk.at is null or brk.at <= y1.date   then y1.price   end as y1_price,
                lp.total_value,
                lp.investor_count
            from funds f
            join founders fo on fo.name = f.founder
            join lateral (
                select p.price, p.date, p.total_value, p.investor_count
                from fund_daily_stats p
                where p.fund_code = f.code order by p.date desc limit 1
            ) lp on true
            left join lateral (
                select p.price, p.date from fund_daily_stats p
                where p.fund_code = f.code and p.date < lp.date
                order by p.date desc limit 1
            ) prev on true
            left join lateral (
                select p.price, p.date from fund_daily_stats p
                where p.fund_code = f.code and p.date <= lp.date - interval '7 days'
                order by p.date desc limit 1
            ) w1 on true
            left join lateral (
                select p.price, p.date from fund_daily_stats p
                where p.fund_code = f.code and p.date <= lp.date - interval '1 month'
                order by p.date desc limit 1
            ) m1 on true
            left join lateral (
                select p.price, p.date from fund_daily_stats p
                where p.fund_code = f.code and p.date <= lp.date - interval '3 months'
                order by p.date desc limit 1
            ) m3 on true
            left join lateral (
                select p.price, p.date from fund_daily_stats p
                where p.fund_code = f.code and p.date <= lp.date - interval '1 year'
                order by p.date desc limit 1
            ) y1 on true
            left join lateral (
                select max(step.date) as at from (
                    select p.date, p.price,
                           lag(p.price) over (order by p.date) as before
                    from fund_daily_stats p
                    where p.fund_code = f.code and p.date > lp.date - interval '1 year'
                ) step
                where step.before > 0
                  and abs(step.price / step.before - 1) > {$ratio}
            ) brk on true
            SQL;
    }

    /** All funds of one TEFAS universe, best one-year return first. */
    public function getFunds(string $fundType = Constants::PRODUCT_FUND_TYPE): array
    {
        return $this->remember("list:{$fundType}", function () use ($fundType) {
            $rows = DB::select($this->snapshotBase().' where f.fund_type = ?', [$fundType]);

            $funds = array_map(fn ($row) => $this->toFund($row), $rows);

            usort($funds, fn ($a, $b) => $b['y1'] <=> $a['y1']);

            return $funds;
        });
    }

    /** One fund by code. */
    public function getFund(string $code): ?array
    {
        return $this->remember('fund:'.strtoupper($code), function () use ($code) {
            $rows = DB::select($this->snapshotBase().' where upper(f.code) = ?', [strtoupper($code)]);

            return isset($rows[0]) ? $this->toFund($rows[0]) : null;
        });
    }

    /** Maps a snapshot row onto the client's `Fund` shape, in the same key order. */
    private function toFund(object $row): array
    {
        $price = (float) $row->price;

        return [
            'code' => $row->code,
            'slug' => Slug::fundSlug($row->code, $row->name),
            'name' => $row->name,
            'founder' => $row->founder,
            'founderInitials' => $row->initials ?? Palette::FALLBACK_INITIALS,
            'founderColor' => $row->color ?? Palette::FALLBACK_BACKGROUND,
            'category' => $row->category,
            'isin' => $row->isin,
            'managementFee' => Num::json($row->management_fee),
            'withholdingTax' => $row->withholding_tax === null ? null : Num::json($row->withholding_tax),
            'buyValueDays' => $row->buy_value_days === null ? null : (int) $row->buy_value_days,
            'sellValueDays' => $row->sell_value_days === null ? null : (int) $row->sell_value_days,
            'onTefas' => $this->toBool($row->on_tefas),
            'price' => Num::json($price),
            'priceDate' => $row->price_date,
            'daily' => Num::changePct($price, $row->prev_price),
            'w1' => Num::changePct($price, $row->w1_price),
            'm1' => Num::changePct($price, $row->m1_price),
            'm3' => Num::changePct($price, $row->m3_price),
            'y1' => Num::changePct($price, $row->y1_price),
            'inceptionDate' => $row->inception_date,
            'aum' => Num::json($row->total_value ?? 0),
            'investors' => $row->investor_count === null ? null : (int) $row->investor_count,
            'risk' => $row->risk === null ? null : (int) $row->risk,
        ];
    }

    /** Daily price series, oldest first, limited to the last `days` sessions. */
    public function getFundPrices(string $code, int $days = 260): array
    {
        return $this->remember("prices:{$code}:{$days}", function () use ($code, $days) {
            $rows = DB::select(
                "select to_char(date, 'YYYY-MM-DD') as date, price
                 from fund_daily_stats where fund_code = ?
                 order by date desc limit ?",
                [$code, $days],
            );

            $rows = array_reverse($rows);

            return array_map(fn ($row) => [
                'date' => $row->date,
                'price' => Num::json($row->price),
            ], $rows);
        });
    }

    /**
     * Month-end size and investor count, with net flow derived as the part of
     * the change in size the fund's own return does not explain.
     */
    public function getFundMonthly(string $code, int $months = 24): array
    {
        return $this->remember("monthly:{$code}:{$months}", fn () => $this->computeFundMonthly($code, $months));
    }

    private function computeFundMonthly(string $code, int $months): array
    {
        $rows = DB::select(
            "select to_char(date_trunc('month', date), 'YYYY-MM-DD') as month,
                    (array_agg(total_value order by date desc))[1]    as total_value,
                    (array_agg(investor_count order by date desc))[1] as investor_count,
                    (array_agg(price order by date desc))[1]          as price
             from fund_daily_stats
             where fund_code = ?
             group by date_trunc('month', date)
             order by date_trunc('month', date) desc
             limit ?",
            [$code, $months],
        );

        $rows = array_reverse($rows);
        $out = [];

        foreach ($rows as $index => $row) {
            $value = (float) ($row->total_value ?? 0);
            $previous = $rows[$index - 1] ?? null;
            $previousValue = (float) ($previous->total_value ?? 0);
            $priceReturn = $previous ? ((float) $row->price / (float) $previous->price) - 1 : 0;

            $out[] = [
                'month' => $row->month,
                'totalValue' => Num::json($row->total_value ?? 0),
                'investorCount' => $row->investor_count === null ? null : (int) $row->investor_count,
                'netFlow' => $previous
                    ? Num::json(round($value - $previousValue * (1 + $priceReturn), 2))
                    : 0,
            ];
        }

        return $out;
    }

    /** Annualised volatility of daily returns over the last year, in percent. */
    private function getVolatilities(array $codes): array
    {
        if ($codes === []) {
            return [];
        }

        $placeholders = implode(', ', array_fill(0, count($codes), '?'));

        $rows = DB::select(
            'select fund_code,
                    stddev_samp(r) * sqrt('.self::TRADING_DAYS_PER_YEAR.') * 100 as vol
             from (
                select fund_code,
                       price / lag(price) over (partition by fund_code order by date) - 1 as r
                from fund_daily_stats
                where fund_code in ('.$placeholders.")
                  and date >= current_date - interval '1 year'
             ) daily
             where r is not null
             group by fund_code",
            $codes,
        );

        $out = [];
        foreach ($rows as $row) {
            $out[$row->fund_code] = $row->vol === null ? null : Num::json(round((float) $row->vol, 1));
        }

        return $out;
    }

    /** Security-level movements between the two latest monthly portfolio reports. */
    private function getSecurityMoves(string $code): array
    {
        return DB::select(
            'select
                coalesce(s.name, p.ticker) as label,
                s.color                    as color,
                p.weight                   as weight,
                p.change_points            as change
             from fund_positions p
             left join symbols s on s.ticker = p.ticker
             where p.fund_code = ?
               and p.period = (
                    select max(period) from fund_positions where fund_code = ?
               )
               and abs(p.change_points) >= '.self::MIN_MOVE_POINTS.'
             order by p.change_points desc',
            [$code, $code],
        );
    }

    /** Asset-class movements — the fallback panel when securities are unavailable. */
    private function getAllocationMoves(string $code): array
    {
        return DB::select(
            "with latest as (
                select max(date) as at from fund_allocations where fund_code = ?
            ),
            baseline as (
                select max(date) as at from fund_allocations
                where fund_code = ?
                  and date <= (select at from latest) - interval '1 month'
            ),
            current_slices as (
                select label, pct from fund_allocations
                where fund_code = ? and date = (select at from latest)
            ),
            earlier_slices as (
                select label, pct from fund_allocations
                where fund_code = ? and date = (select at from baseline)
            )
            select
                coalesce(c.label, e.label)              as label,
                null::text                              as color,
                coalesce(c.pct, 0)                      as weight,
                coalesce(c.pct, 0) - coalesce(e.pct, 0) as change
            from current_slices c
            full outer join earlier_slices e on e.label = c.label
            where (select at from baseline) is not null
              and abs(coalesce(c.pct, 0) - coalesce(e.pct, 0)) >= ".self::MIN_MOVE_POINTS.'
            order by change desc',
            [$code, $code, $code, $code],
        );
    }

    /**
     * The detail bundle the client's fund page consumes: volatility, the newest
     * allocation, similar funds, the two mover panels and the compare table.
     * `$fund` is the already-loaded snapshot for the requested code.
     */
    public function getFundDetail(string $code, array $fund): array
    {
        return $this->remember('detail:'.$fund['code'], fn () => $this->computeFundDetail($fund));
    }

    private function computeFundDetail(array $fund): array
    {
        $securityMoves = $this->getSecurityMoves($fund['code']);
        $allocationMoves = $this->getAllocationMoves($fund['code']);

        $allocation = array_map(fn ($row) => [
            'label' => $row->label,
            'pct' => Num::json($row->pct),
        ], DB::select(
            'select label, pct from fund_allocations
             where fund_code = ? and date = (
                select max(date) from fund_allocations where fund_code = ?
             )
             order by position asc',
            [$fund['code'], $fund['code']],
        ));

        $everyFund = $this->getFunds();
        $returnsByCode = [];
        foreach ($everyFund as $row) {
            $returnsByCode[$row['code']] = $row;
        }

        // Similar funds by asset allocation (Varlık Dağılımı): rank peers by how
        // much of their latest allocation overlaps this fund's — the sum, over
        // asset classes, of the smaller of the two weights. That is 100 for an
        // identical split and 0 for two funds with nothing in common. Only funds
        // in the product list are eligible, so peers match what the site shows.
        $allocationsByFund = $this->latestAllocationsByFund();
        $self = $allocationsByFund[$fund['code']] ?? [];

        $overlaps = [];
        foreach ($self === [] ? [] : $allocationsByFund as $peerCode => $peerAllocation) {
            if ($peerCode === $fund['code'] || ! isset($returnsByCode[$peerCode])) {
                continue;
            }

            $overlap = 0.0;
            foreach ($self as $label => $pct) {
                if (isset($peerAllocation[$label])) {
                    $overlap += min($pct, $peerAllocation[$label]);
                }
            }

            if ($overlap > 0) {
                $overlaps[$peerCode] = $overlap;
            }
        }

        arsort($overlaps);
        $overlaps = array_slice($overlaps, 0, self::SIMILAR_PEERS, true);

        $similar = [];
        foreach ($overlaps as $peerCode => $overlap) {
            $peer = $returnsByCode[$peerCode];
            $similar[] = [
                'code' => $peer['code'],
                'slug' => $peer['slug'],
                'label' => $peer['name'],
                'initials' => $peer['founderInitials'],
                'color' => $peer['founderColor'],
                'similarity' => Num::json(round($overlap)),
                'y1' => $peer['y1'] ?? 0,
                'risk' => $peer['risk'],
            ];
        }

        $compareCodes = array_merge(
            [$fund['code']],
            array_map(fn ($peer) => $peer['code'], array_slice($similar, 0, self::COMPARE_PEERS)),
        );

        $volatilities = $this->getVolatilities($compareCodes);

        // Every comparison row reads a real column or a series-derived figure.
        $compared = array_map(
            fn ($peerCode) => $peerCode === $fund['code'] ? $fund : ($returnsByCode[$peerCode] ?? null),
            $compareCodes,
        );

        $rows = [
            [
                'label' => '1 Yıl Getiri',
                'values' => array_map(
                    fn ($row) => $row ? Format::percent((float) $row['y1'], 1) : '—',
                    $compared,
                ),
            ],
            [
                'label' => 'Risk Değeri',
                'values' => array_map(function ($row) {
                    if (! $row) {
                        return '—';
                    }

                    return $row['risk'] === null ? Constants::UNKNOWN : "{$row['risk']} / 7";
                }, $compared),
            ],
            [
                'label' => 'Yıllık Yönetim Ücreti',
                'values' => array_map(
                    fn ($row) => $row ? Format::percentPrefixed((float) $row['managementFee'], 2) : '—',
                    $compared,
                ),
            ],
            [
                'label' => 'Stopaj Oranı',
                'values' => array_map(function ($row) {
                    if (! $row) {
                        return '—';
                    }

                    return $row['withholdingTax'] === null
                        ? Constants::UNKNOWN
                        : Format::percentPrefixed((float) $row['withholdingTax'], 0);
                }, $compared),
            ],
            [
                'label' => 'Volatilite (1Y)',
                'values' => array_map(function ($peerCode) use ($volatilities) {
                    $vol = $volatilities[$peerCode] ?? null;

                    return $vol === null ? '—' : Format::percentPrefixed((float) $vol, 1);
                }, $compareCodes),
            ],
            [
                'label' => 'Yatırımcı Sayısı',
                'values' => array_map(function ($row) {
                    if (! $row) {
                        return '—';
                    }

                    return $row['investors'] === null
                        ? Constants::UNKNOWN
                        : Format::count((int) $row['investors']);
                }, $compared),
            ],
        ];

        // Securities when both monthly reports have been read, asset classes
        // otherwise. Never both: they measure different things over different
        // windows, and a mixed panel would read as one list.
        $moves = count($securityMoves) > 0 ? $securityMoves : $allocationMoves;

        $gained = array_values(array_filter($moves, fn ($row) => (float) $row->change > 0));
        $shed = array_reverse(array_values(array_filter($moves, fn ($row) => (float) $row->change < 0)));

        $toHolding = fn ($row, $index) => [
            'label' => $row->label,
            'color' => $row->color ?? Palette::allocationColor($index),
            'weight' => Num::json($row->weight),
            'change' => Num::json($row->change),
        ];

        return [
            'volatility' => $volatilities[$fund['code']] ?? null,
            'allocation' => $allocation,
            'similar' => $similar,
            'increased' => $this->mapIndexed(array_slice($gained, 0, self::MOVERS_PER_PANEL), $toHolding),
            'decreased' => $this->mapIndexed(array_slice($shed, 0, self::MOVERS_PER_PANEL), $toHolding),
            'compare' => ['codes' => $compareCodes, 'rows' => $rows],
        ];
    }

    /** Fund search over code, name and issuer — powers the nav's quick search. */
    public function searchFunds(string $query, int $limit = 6): array
    {
        $needle = trim($query);
        if (mb_strlen($needle) < 2) {
            return [];
        }

        $folded = mb_strtolower($needle, 'UTF-8');

        $matches = array_filter($this->getFunds(), function ($fund) use ($folded) {
            $haystack = mb_strtolower("{$fund['code']} {$fund['name']} {$fund['founder']}", 'UTF-8');

            return str_contains($haystack, $folded);
        });

        return array_slice(array_values($matches), 0, $limit);
    }

    // ── Discovery widgets ───────────────────────────────────────────────────

    /** Best one-year performers. */
    public function getTopGainers(int $limit = 5): array
    {
        return array_slice($this->getFunds(), 0, $limit);
    }

    /** "En Az Kazandıran Fonlar" — the thinnest gains, closest to zero first. */
    public function getSmallestGainers(int $limit = 5): array
    {
        $funds = array_values(array_filter($this->getFunds(), fn ($fund) => $fund['y1'] > 0));
        usort($funds, fn ($a, $b) => $a['y1'] <=> $b['y1']);

        return array_slice($funds, 0, $limit);
    }

    /** Newest funds by kuruluş tarihi. */
    public function getNewestFunds(int $limit = 5): array
    {
        $funds = array_values(array_filter($this->getFunds(), fn ($fund) => $fund['inceptionDate']));
        usort($funds, fn ($a, $b) => $b['inceptionDate'] <=> $a['inceptionDate']);

        return array_slice($funds, 0, $limit);
    }

    /** Funds whose yatırımcı sayısı grew most over the last month. */
    public function getInvestorGrowth(int $limit = 5): array
    {
        $rows = DB::select(
            "with latest as (
                select distinct on (d.fund_code) d.fund_code, d.date, d.investor_count
                from fund_daily_stats d
                join funds f on f.code = d.fund_code
                where d.investor_count is not null
                  and f.fund_type = ?
                order by d.fund_code, d.date desc
            )
            select l.fund_code, l.investor_count,
                   (l.investor_count::numeric / nullif(p.investor_count, 0) - 1) * 100 as growth
            from latest l
            join lateral (
                select investor_count from fund_daily_stats d
                where d.fund_code = l.fund_code
                  and d.investor_count is not null
                  and d.date <= l.date - interval '1 month'
                order by d.date desc
                limit 1
            ) p on true
            order by growth desc nulls last
            limit ?",
            [Constants::PRODUCT_FUND_TYPE, $limit],
        );

        $byCode = [];
        foreach ($this->getFunds() as $fund) {
            $byCode[$fund['code']] = $fund;
        }

        $out = [];
        foreach ($rows as $row) {
            $fund = $byCode[$row->fund_code] ?? null;
            if (! $fund) {
                continue;
            }

            $out[] = [
                'fund' => $fund,
                'growth' => Num::json($row->growth ?? 0),
                'investors' => (int) $row->investor_count,
            ];
        }

        return $out;
    }

    /**
     * Liveness and data-freshness probe payload: the catalogue counts, the
     * latest session and the last daily-stats ingest run.
     */
    public function healthCheck(): array
    {
        $staleAfterDays = 4;
        $checks = ['provider' => config('ingest.market_provider')];
        $healthy = true;

        try {
            $row = DB::selectOne(
                'select
                    (select count(*) from funds where is_active)   as funds,
                    (select count(*) from fund_daily_stats)        as rows,
                    (select max(date)::text from fund_daily_stats) as latest',
            );

            $latest = $row->latest ?? null;
            $ageDays = $latest
                ? (int) floor((time() - strtotime($latest)) / 86400)
                : null;

            $checks['database'] = 'ok';
            $checks['funds'] = (int) ($row->funds ?? 0);
            $checks['dailyRows'] = (int) ($row->rows ?? 0);
            $checks['latestDate'] = $latest;
            $checks['ageDays'] = $ageDays;

            if (! $latest || ($ageDays !== null && $ageDays > $staleAfterDays)) {
                $healthy = false;
                $checks['stale'] = true;
            }
        } catch (\Throwable $error) {
            $healthy = false;
            $checks['database'] = 'unreachable';
            $checks['error'] = $error->getMessage();

            return ['healthy' => $healthy, 'checks' => $checks];
        }

        try {
            $lastRun = DB::selectOne(
                'select status, started_at, finished_at, rows_written, error
                 from ingest_runs where job = ? order by started_at desc limit 1',
                ['daily-stats'],
            );

            $checks['lastRun'] = $lastRun ? [
                'status' => $lastRun->status,
                'startedAt' => $this->toIso($lastRun->started_at),
                'finishedAt' => $this->toIso($lastRun->finished_at),
                'rowsWritten' => $lastRun->rows_written === null ? null : (int) $lastRun->rows_written,
                'error' => $lastRun->error,
            ] : null;

            if ($lastRun && $lastRun->status === 'failed') {
                $healthy = false;
            }
        } catch (\Throwable) {
            // The database check above already covers this.
        }

        return ['healthy' => $healthy, 'checks' => $checks];
    }

    /**
     * Memoise a read across requests. The key carries the current generation,
     * so a {@see flush()} makes every prior entry unreachable at once. A `null`
     * result is not stored, so a not-yet-known fund keeps missing cheaply.
     */
    /**
     * Every fund's most recent asset allocation as [fundCode => [label => pct]].
     * Cached with the rest of the read layer; used to rank similar funds by how
     * closely their Varlık Dağılımı matches.
     */
    private function latestAllocationsByFund(): array
    {
        return $this->remember('allocations:latest', function () {
            $rows = DB::select(
                'select fa.fund_code, fa.label, fa.pct
                   from fund_allocations fa
                   inner join (
                       select fund_code, max(date) as date
                         from fund_allocations
                        group by fund_code
                   ) latest on latest.fund_code = fa.fund_code and latest.date = fa.date',
            );

            $byFund = [];
            foreach ($rows as $row) {
                $byFund[$row->fund_code][$row->label] = (float) $row->pct;
            }

            return $byFund;
        });
    }

    private function remember(string $key, callable $callback): mixed
    {
        $version = $this->cacheGeneration();

        return Cache::remember(
            self::CACHE_PREFIX.":v{$version}:{$key}",
            self::CACHE_TTL_SECONDS,
            $callback,
        );
    }

    /**
     * The current cache generation: the finish time of the latest successful
     * ingest run, as a compact digits-only token. It advances the moment any
     * ingest commits, and lives in the shared database, so every process
     * invalidates in lockstep without a shared cache. A per-instance memo keeps
     * a single request from re-querying it.
     */
    private function cacheGeneration(): string
    {
        if ($this->generation !== null) {
            return $this->generation;
        }

        try {
            $latest = DB::scalar("select max(finished_at) from ingest_runs where status = 'success'");
        } catch (\Throwable) {
            // Never let a probe failure take down a read — fall back to a fixed
            // generation so reads still work (just without cross-run busting).
            $latest = null;
        }

        $token = $latest === null ? '0' : preg_replace('/\D/', '', (string) $latest);

        return $this->generation = ($token === '' ? '0' : $token);
    }

    /** `array_map` with the item's index, the way the client's `.map((row, i))` reads. */
    private function mapIndexed(array $items, callable $callback): array
    {
        return array_map($callback, $items, array_keys(array_values($items)));
    }

    /** Postgres booleans reach PDO as 't'/'f', bool or null depending on driver. */
    private function toBool(mixed $value): ?bool
    {
        if ($value === null) {
            return null;
        }
        if (is_bool($value)) {
            return $value;
        }

        return in_array($value, ['t', 'true', '1', 1, true], true);
    }

    private function toIso(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return Carbon::parse($value)->toIso8601String();
    }
}
