<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\Ingest\CollectPositionsJob;
use App\Jobs\Ingest\SyncAllocationsJob;
use App\Jobs\Ingest\SyncDailyStatsJob;
use App\Jobs\Ingest\SyncDisclosuresJob;
use App\Jobs\Ingest\SyncFundCatalogJob;
use App\Jobs\Ingest\SyncFundInceptionsJob;
use App\Jobs\Ingest\SyncFundProfilesJob;
use App\Jobs\Ingest\SyncMarketIndicesJob;
use App\Jobs\Ingest\SyncPositionsJob;
use App\Models\IngestRun;
use Illuminate\Http\Request;
use Inertia\Inertia;

class IngestController extends Controller
{
    /**
     * The jobs exposed in the panel, each with the params its form accepts.
     * `job` maps to the queued job's runtime name in ingest_runs, for status.
     */
    private const JOBS = [
        'catalog' => [
            'label' => 'Fon Kataloğu', 'run' => 'fund-catalog',
            'description' => 'Yeni fonlar, isim değişiklikleri, kapanan fonlar (TEFAS).',
            'params' => [],
        ],
        'daily' => [
            'label' => 'Günlük İstatistikler', 'run' => 'daily-stats',
            'description' => 'Fiyat, büyüklük ve yatırımcı sayıları.',
            'params' => ['days'],
        ],
        'allocations' => [
            'label' => 'Varlık Dağılımları', 'run' => 'fund-allocations',
            'description' => 'Portföy dağılım kırılımları.',
            'params' => ['days'],
        ],
        'indices' => [
            'label' => 'Piyasa Endeksleri', 'run' => 'market-indices',
            'description' => 'Endeks ve döviz kotasyonları (TCMB / EVDS / Yahoo).',
            'params' => ['days'],
        ],
        'inceptions' => [
            'label' => 'Fon Kuruluş Tarihleri', 'run' => 'fund-inceptions',
            'description' => 'KAP kayıtlarından halka arz tarihleri (parça parça).',
            'params' => [],
        ],
        'profiles' => [
            'label' => 'Fon Künye (ISIN/Risk/Valör)', 'run' => 'fund-profiles',
            'description' => 'TEFAS profil bilgisinden ISIN, risk ve valör (parça parça).',
            'params' => [],
        ],
        'disclosures' => [
            'label' => 'KAP Bildirimleri', 'run' => 'kap-disclosures',
            'description' => 'Bildirimleri keşfet; PDF linkleri parça parça çözülür.',
            'params' => ['days'],
        ],
        'positions' => [
            'label' => 'Pozisyonlar (Gönder)', 'run' => 'kap-extract-submit',
            'description' => 'Portföy raporlarını bul ve çıkarım için sıraya al.',
            'params' => ['period'],
        ],
        'collect' => [
            'label' => 'Pozisyonlar (Topla)', 'run' => 'kap-extract-collect',
            'description' => 'Biten çıkarım batch’lerini uygula ve hareketleri yeniden hesapla.',
            'params' => [],
        ],
    ];

    public function index()
    {
        $jobs = [];
        foreach (self::JOBS as $key => $meta) {
            $last = IngestRun::where('job', $meta['run'])->orderByDesc('started_at')->first();
            $jobs[] = [
                'key' => $key,
                'label' => $meta['label'],
                'description' => $meta['description'],
                'params' => $meta['params'],
                'last' => $last ? [
                    'status' => $last->status,
                    'rows_written' => $last->rows_written,
                    'started_at' => $last->started_at,
                    'error' => $last->error,
                ] : null,
            ];
        }

        return Inertia::render('Admin/Ingest', [
            'jobs' => $jobs,
            'recentRuns' => IngestRun::orderByDesc('started_at')->limit(15)->get(),
        ]);
    }

    public function run(Request $request)
    {
        $validated = $request->validate([
            'job' => 'required|string|in:' . implode(',', array_keys(self::JOBS)),
            'days' => 'nullable|integer|min:1|max:1000',
            'limit' => 'nullable|integer|min:1|max:5000',
            'period' => 'nullable|date_format:Y-m-d',
        ]);

        $key = $validated['job'];
        $days = $validated['days'] ?? null;
        $limit = $validated['limit'] ?? null;
        $period = $validated['period'] ?? null;

        match ($key) {
            'catalog' => SyncFundCatalogJob::dispatch(),
            'daily' => SyncDailyStatsJob::dispatch(days: $days ?? 3),
            'allocations' => SyncAllocationsJob::dispatch(days: $days),
            'indices' => SyncMarketIndicesJob::dispatch(days: $days ?? 5),
            'inceptions' => SyncFundInceptionsJob::dispatch(),
            'profiles' => SyncFundProfilesJob::dispatch(),
            'disclosures' => SyncDisclosuresJob::dispatch(days: $days, limit: $limit),
            'positions' => SyncPositionsJob::dispatch(period: $period),
            'collect' => CollectPositionsJob::dispatch(),
        };

        $label = self::JOBS[$key]['label'];

        return back()->with('flash', [
            'type' => 'success',
            'message' => "{$label} kuyruğa alındı. İlerlemeyi aşağıdaki tablodan izleyebilirsiniz.",
        ]);
    }
}
