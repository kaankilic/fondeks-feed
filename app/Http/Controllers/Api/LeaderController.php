<?php

namespace App\Http\Controllers\Api;

use App\Http\Concerns\ApiResponses;
use App\Http\Controllers\Controller;
use App\Services\Fondeks\FundQueries;
use Illuminate\Http\Request;

/**
 * The discovery lists the product is built around — the API mirror of the
 * client's `/api/leaders` route.
 *
 * returns   — Getiri Liderleri
 * gainers   — En Çok Kazandıran Fonlar
 * losers    — En Az Kazandıran Fonlar
 * investors — Yatırımcısı En Çok Artan Fonlar
 * new       — Son Çıkan Fonlar
 */
class LeaderController extends Controller
{
    use ApiResponses;

    private const TYPES = ['returns', 'gainers', 'losers', 'investors', 'new'];

    public function __construct(private readonly FundQueries $funds) {}

    public function index(Request $request)
    {
        $type = $request->query('type', 'returns');
        if (! in_array($type, self::TYPES, true)) {
            return $this->badRequest('invalid type');
        }

        $limit = $request->query('limit', 5);
        if (! is_numeric($limit) || (int) $limit != $limit || (int) $limit < 1 || (int) $limit > 50) {
            return $this->badRequest('limit must be between 1 and 50');
        }
        $limit = (int) $limit;

        $items = match ($type) {
            'returns' => array_slice($this->funds->getFunds(), 0, $limit),
            'gainers' => $this->funds->getTopGainers($limit),
            'losers' => $this->funds->getSmallestGainers($limit),
            'new' => $this->funds->getNewestFunds($limit),
            'investors' => array_map(fn ($row) => [
                'fund' => $row['fund'],
                'growthPct' => $row['growth'],
                'investors' => $row['investors'],
            ], $this->funds->getInvestorGrowth($limit)),
        };

        return $this->cached(['type' => $type, 'items' => $items]);
    }
}
