<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\KapPortfolioReport;
use Illuminate\Http\Request;
use Inertia\Inertia;

class KapPortfolioReportController extends Controller
{
    public function index(Request $request)
    {
        $query = KapPortfolioReport::query();

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('fund_code', 'ilike', "%{$search}%")
                  ->orWhere('fund_title', 'ilike', "%{$search}%");
            });
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        return Inertia::render('Admin/KapPortfolioReports', [
            'reports' => $query->orderByDesc('published_at')->paginate(25)->withQueryString(),
            'filters' => $request->only('search', 'status'),
        ]);
    }
}
