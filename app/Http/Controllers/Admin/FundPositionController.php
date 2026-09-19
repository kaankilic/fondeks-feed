<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FundPosition;
use Illuminate\Http\Request;
use Inertia\Inertia;

class FundPositionController extends Controller
{
    public function index(Request $request)
    {
        $query = FundPosition::query();

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('fund_code', 'ilike', "%{$search}%")
                  ->orWhere('ticker', 'ilike', "%{$search}%");
            });
        }

        return Inertia::render('Admin/FundPositions', [
            'positions' => $query->orderByDesc('period')->paginate(25)->withQueryString(),
            'filters' => $request->only('search'),
        ]);
    }
}
