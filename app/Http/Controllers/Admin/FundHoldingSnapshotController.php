<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FundHoldingSnapshot;
use Illuminate\Http\Request;
use Inertia\Inertia;

class FundHoldingSnapshotController extends Controller
{
    public function index(Request $request)
    {
        $query = FundHoldingSnapshot::query();

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('fund_code', 'ilike', "%{$search}%")
                  ->orWhere('ticker', 'ilike', "%{$search}%");
            });
        }

        return Inertia::render('Admin/FundHoldingSnapshots', [
            'snapshots' => $query->orderByDesc('period')->paginate(25)->withQueryString(),
            'filters' => $request->only('search'),
        ]);
    }
}
