<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Fund;
use App\Models\FundAllocation;
use App\Models\FundDailyStat;
use App\Models\FundDisclosure;
use App\Models\FundHoldingSnapshot;
use App\Models\FundPosition;
use App\Models\FundSimilarity;
use App\Models\KapPortfolioReport;
use Illuminate\Http\Request;
use Inertia\Inertia;

class FundController extends Controller
{
    public function index(Request $request)
    {
        $query = Fund::query();

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('code', 'ilike', "%{$search}%")
                  ->orWhere('name', 'ilike', "%{$search}%")
                  ->orWhere('founder', 'ilike', "%{$search}%");
            });
        }

        if ($category = $request->input('category')) {
            $query->where('category', $category);
        }

        return Inertia::render('Admin/Funds', [
            'funds' => $query->orderBy('code')->paginate(25)->withQueryString(),
            'filters' => $request->only('search', 'category'),
        ]);
    }

    public function show(string $code)
    {
        $code = strtoupper($code);
        $fund = Fund::with('founderRelation')->findOrFail($code);

        // Daily stats — bounded to the last 90 trading rows (table is large).
        $daily = FundDailyStat::where('fund_code', $code)
            ->orderByDesc('date')
            ->limit(90)
            ->get()
            ->reverse()
            ->values();

        $latest = $daily->last();
        $first = $daily->first();

        // Allocations — the most recent published day's breakdown.
        $allocationDate = FundAllocation::where('fund_code', $code)->max('date');
        $allocations = $allocationDate
            ? FundAllocation::where('fund_code', $code)->where('date', $allocationDate)
                ->orderBy('position')->get()
            : collect();

        // Position movers — the latest reporting period.
        $positionPeriod = FundPosition::where('fund_code', $code)->max('period');
        $positions = $positionPeriod
            ? FundPosition::where('fund_code', $code)->where('period', $positionPeriod)
                ->orderBy('direction')->orderBy('rank')->get()
            : collect();

        // Holdings — the latest snapshot period, heaviest first.
        $holdingPeriod = FundHoldingSnapshot::where('fund_code', $code)->max('period');
        $holdings = $holdingPeriod
            ? FundHoldingSnapshot::where('fund_code', $code)->where('period', $holdingPeriod)
                ->orderByDesc('weight')->limit(50)->get()
            : collect();

        $similarities = FundSimilarity::where('fund_code', $code)
            ->orderByDesc('similarity')->get();

        $disclosures = FundDisclosure::where('fund_code', $code)
            ->orderByDesc('published_at')->limit(30)->get();

        $reports = KapPortfolioReport::where('fund_code', $code)
            ->orderByDesc('published_at')->limit(30)->get();

        return Inertia::render('Admin/FundDetail', [
            'fund' => $fund,
            'founder' => $fund->founderRelation,
            'summary' => [
                'latestDate' => $latest?->date,
                'latestPrice' => $latest?->price,
                'totalValue' => $latest?->total_value,
                'investorCount' => $latest?->investor_count,
                'shareCount' => $latest?->share_count,
                'changePct' => ($first && $latest && $first->price)
                    ? round((($latest->price - $first->price) / $first->price) * 100, 2)
                    : null,
                'windowDays' => $daily->count(),
                'allocationDate' => $allocationDate,
                'positionPeriod' => $positionPeriod,
                'holdingPeriod' => $holdingPeriod,
            ],
            'dailySeries' => $daily->map(fn ($r) => [
                'date' => $r->date,
                'price' => $r->price,
                'totalValue' => $r->total_value,
                'investorCount' => $r->investor_count,
            ])->values(),
            'allocations' => $allocations,
            'positions' => $positions,
            'holdings' => $holdings,
            'similarities' => $similarities,
            'disclosures' => $disclosures,
            'reports' => $reports,
            'counts' => [
                'daily' => FundDailyStat::where('fund_code', $code)->count(),
                'allocations' => FundAllocation::where('fund_code', $code)->count(),
                'positions' => FundPosition::where('fund_code', $code)->count(),
                'holdings' => FundHoldingSnapshot::where('fund_code', $code)->count(),
                'similarities' => $similarities->count(),
                'disclosures' => FundDisclosure::where('fund_code', $code)->count(),
                'reports' => KapPortfolioReport::where('fund_code', $code)->count(),
            ],
        ]);
    }
}
