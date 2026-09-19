<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FundAllocation;
use Illuminate\Http\Request;
use Inertia\Inertia;

class FundAllocationController extends Controller
{
    public function index(Request $request)
    {
        $query = FundAllocation::query();

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('fund_code', 'ilike', "%{$search}%")
                  ->orWhere('label', 'ilike', "%{$search}%");
            });
        }

        return Inertia::render('Admin/FundAllocations', [
            'allocations' => $query->orderByDesc('date')->paginate(25)->withQueryString(),
            'filters' => $request->only('search'),
        ]);
    }
}
