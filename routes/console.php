<?php

use App\Jobs\Ingest\CollectPositionsJob;
use App\Jobs\Ingest\SyncAllocationsJob;
use App\Jobs\Ingest\SyncDailyStatsJob;
use App\Jobs\Ingest\SyncDisclosuresJob;
use App\Jobs\Ingest\SyncFundCatalogJob;
use App\Jobs\Ingest\SyncFundInceptionsJob;
use App\Jobs\Ingest\SyncFundProfilesJob;
use App\Jobs\Ingest\SyncMarketIndicesJob;
use App\Jobs\Ingest\SyncPositionsJob;
use Illuminate\Support\Facades\Schedule;

/*
 * Ingest schedule. Times are Europe/Istanbul, matching the sources:
 * TEFAS publishes after the ~18:15 close, TCMB's bulletin lands after 15:30,
 * and KAP portfolio-report filings land through the first weeks of a month.
 *
 * Every job is idempotent (natural-key upserts) and the KAP passes hold a
 * Postgres advisory lock, so overlap between a schedule and a manual run is
 * safe. onOneServer keeps a multi-worker deployment from double-dispatching.
 */

$tz = 'Europe/Istanbul';

// Daily prices, size and investor counts — after the market closes.
Schedule::job(new SyncDailyStatsJob(days: 3))
    ->dailyAt('19:20')->timezone($tz)->onOneServer()->withoutOverlapping();

// Portfolio breakdowns behind "Varlık Dağılımı".
Schedule::job(new SyncAllocationsJob())
    ->dailyAt('19:45')->timezone($tz)->onOneServer()->withoutOverlapping();

// Index quotes — after TCMB's afternoon bulletin.
Schedule::job(new SyncMarketIndicesJob(days: 5))
    ->dailyAt('16:10')->timezone($tz)->onOneServer()->withoutOverlapping();

// Catalogue: new funds, renames, retired funds. Weekly is plenty.
Schedule::job(new SyncFundCatalogJob())
    ->weeklyOn(1, '06:30')->timezone($tz)->onOneServer()->withoutOverlapping();

// Fund launch dates from KAP — converges then idles, so cheap to run daily.
Schedule::job(new SyncFundInceptionsJob())
    ->dailyAt('07:00')->timezone($tz)->onOneServer()->withoutOverlapping();

// Fund künye (ISIN, risk, valör) from TEFAS — converges then idles.
Schedule::job(new SyncFundProfilesJob())
    ->dailyAt('07:20')->timezone($tz)->onOneServer()->withoutOverlapping();

// Portfolio disclosures archive.
Schedule::job(new SyncDisclosuresJob())
    ->dailyAt('08:15')->timezone($tz)->onOneServer()->withoutOverlapping();

// Holdings submit pass: discover the closed month's filings and queue them.
Schedule::job(new SyncPositionsJob())
    ->dailyAt('08:45')->timezone($tz)->onOneServer()->withoutOverlapping();

// Holdings collect pass: apply finished batches and rebuild the movers.
// Batches finish within the hour, so check often through the working day.
Schedule::job(new CollectPositionsJob())
    ->hourlyAt(20)->timezone($tz)->onOneServer()->withoutOverlapping();
