<?php

namespace App\Http\Controllers\Api;

use App\Http\Concerns\ApiResponses;
use App\Http\Controllers\Controller;
use App\Services\Fondeks\FundQueries;

/**
 * Liveness and data-freshness probe — the API mirror of the client's
 * `/api/health` route. Returns 503 when the database is unreachable, the last
 * import failed, or prices have gone stale.
 */
class HealthController extends Controller
{
    use ApiResponses;

    public function __construct(private readonly FundQueries $funds) {}

    public function show()
    {
        ['healthy' => $healthy, 'checks' => $checks] = $this->funds->healthCheck();

        return $this->noStore(
            ['status' => $healthy ? 'ok' : 'degraded', 'checks' => $checks],
            $healthy ? 200 : 503,
        );
    }
}
