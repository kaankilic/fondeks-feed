<?php

namespace App\Http\Controllers\Api;

use App\Http\Concerns\ApiResponses;
use App\Http\Controllers\Controller;
use App\Services\Fondeks\FundQueries;
use App\Support\Fondeks\Constants;
use App\Support\Fondeks\Slug;
use Illuminate\Http\Request;

/**
 * Fund list, quick-search and single-fund detail — the API mirror of the
 * client's `/api/funds`, `/api/funds/search` and `/api/funds/[code]` routes.
 */
class FundController extends Controller
{
    use ApiResponses;

    private const SORT_FIELDS = ['y1', 'm3', 'm1', 'daily', 'price', 'aum', 'investors'];

    public function __construct(private readonly FundQueries $funds) {}

    /** Fund list with filtering, sorting and paging. */
    public function index(Request $request)
    {
        $q = $request->query('q');
        if ($q !== null) {
            $q = trim((string) $q);
            if (mb_strlen($q) > 80) {
                return $this->badRequest('q must contain at most 80 character(s)');
            }
        }

        $category = $request->query('category');
        if ($category !== null && ! in_array($category, Constants::FUND_CATEGORIES, true)) {
            return $this->badRequest('invalid category');
        }

        $minRisk = $this->intOrNull($request->query('minRisk'), Constants::RISK_MIN, Constants::RISK_MAX);
        $maxRisk = $this->intOrNull($request->query('maxRisk'), Constants::RISK_MIN, Constants::RISK_MAX);
        if ($minRisk === false || $maxRisk === false) {
            return $this->badRequest('risk must be between 1 and 7');
        }

        $minReturn = $this->floatOrNull($request->query('minReturn'), -100, 1000);
        if ($minReturn === false) {
            return $this->badRequest('minReturn must be between -100 and 1000');
        }

        $sort = $request->query('sort', 'y1');
        if (! in_array($sort, self::SORT_FIELDS, true)) {
            return $this->badRequest('invalid sort');
        }

        $dir = $request->query('dir', 'desc');
        if (! in_array($dir, ['asc', 'desc'], true)) {
            return $this->badRequest('invalid dir');
        }

        $limit = $this->intOrNull($request->query('limit', 25), 1, 100);
        if ($limit === false || $limit === null) {
            return $this->badRequest('limit must be between 1 and 100');
        }

        $offset = $this->intOrNull($request->query('offset', 0), 0, PHP_INT_MAX);
        if ($offset === false || $offset === null) {
            return $this->badRequest('offset must be a non-negative integer');
        }

        $needle = $q === null || $q === '' ? null : mb_strtolower($q, 'UTF-8');

        $filtered = array_filter($this->funds->getFunds(), function ($fund) use (
            $category, $minRisk, $maxRisk, $minReturn, $needle
        ) {
            if ($category && $fund['category'] !== $category) {
                return false;
            }

            if ($minRisk !== null || $maxRisk !== null) {
                if ($fund['risk'] === null) {
                    return false;
                }
                if ($minRisk !== null && $fund['risk'] < $minRisk) {
                    return false;
                }
                if ($maxRisk !== null && $fund['risk'] > $maxRisk) {
                    return false;
                }
            }

            if ($minReturn !== null && $fund['y1'] < $minReturn) {
                return false;
            }

            if ($needle !== null) {
                $haystack = mb_strtolower("{$fund['code']} {$fund['name']} {$fund['founder']}", 'UTF-8');
                if (! str_contains($haystack, $needle)) {
                    return false;
                }
            }

            return true;
        });

        $sorted = array_values($filtered);
        usort($sorted, function ($a, $b) use ($sort, $dir) {
            $left = $a[$sort];
            $right = $b[$sort];

            // An unknown figure has no place in the order, so it goes to the end.
            if ($left === null || $right === null) {
                return $left === $right ? 0 : ($left === null ? 1 : -1);
            }

            return $dir === 'asc' ? $left <=> $right : $right <=> $left;
        });

        return $this->cached([
            'items' => array_slice($sorted, $offset, $limit),
            'total' => count($sorted),
            'limit' => $limit,
            'offset' => $offset,
        ]);
    }

    /** Quick-search endpoint for the top navigation. */
    public function search(Request $request)
    {
        $query = (string) $request->query('q', '');
        $funds = $this->funds->searchFunds($query);

        return $this->plain([
            'results' => array_map(fn ($fund) => [
                'code' => $fund['code'],
                'slug' => $fund['slug'],
                'name' => $fund['name'],
                'founder' => $fund['founder'],
                'initials' => $fund['founderInitials'],
                'color' => $fund['founderColor'],
                'category' => $fund['category'],
                'y1' => $fund['y1'],
            ], $funds),
        ]);
    }

    /** One fund. Accepts a code or a full slug, with optional includes. */
    public function show(Request $request, string $code)
    {
        $resolved = Slug::codeFromSlug($code);

        $fund = $this->funds->getFund($resolved);
        if ($fund === null) {
            return $this->notFound("fund {$resolved} not found");
        }

        $include = collect(explode(',', (string) $request->query('include', '')))
            ->map(fn ($part) => trim($part))
            ->filter()
            ->all();

        $payload = ['fund' => $fund];

        if (in_array('prices', $include, true)) {
            $payload['prices'] = $this->funds->getFundPrices($fund['code']);
        }

        if (in_array('monthly', $include, true)) {
            $payload['monthly'] = $this->funds->getFundMonthly($fund['code']);
        }

        if (in_array('detail', $include, true)) {
            $payload = array_merge($payload, $this->funds->getFundDetail($resolved, $fund));
        }

        return $this->cached($payload);
    }

    /** An integer within [min, max]: null when absent, false when invalid. */
    private function intOrNull(mixed $value, int $min, int $max): int|null|false
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (! is_numeric($value) || (int) $value != $value) {
            return false;
        }

        $number = (int) $value;

        return ($number < $min || $number > $max) ? false : $number;
    }

    /** A float within [min, max]: null when absent, false when invalid. */
    private function floatOrNull(mixed $value, float $min, float $max): float|null|false
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (! is_numeric($value)) {
            return false;
        }

        $number = (float) $value;

        return ($number < $min || $number > $max) ? false : $number;
    }
}
