<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FundDailyStat;
use Illuminate\Http\Request;
use Inertia\Inertia;

class FundDailyStatController extends Controller
{
    public function index(Request $request)
    {
        $query = FundDailyStat::query();

        if ($search = $request->input('search')) {
            $query->where('fund_code', 'ilike', "%{$search}%");
        }

        return Inertia::render('Admin/FundDailyStats', [
            'stats' => $query->orderByDesc('date')->paginate(25)->withQueryString(),
            'filters' => $request->only('search'),
        ]);
    }
}
