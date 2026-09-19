<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FundSimilarity;
use Illuminate\Http\Request;
use Inertia\Inertia;

class FundSimilarityController extends Controller
{
    public function index(Request $request)
    {
        $query = FundSimilarity::query();

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('fund_code', 'ilike', "%{$search}%")
                  ->orWhere('peer_code', 'ilike', "%{$search}%");
            });
        }

        return Inertia::render('Admin/FundSimilarities', [
            'similarities' => $query->orderByDesc('similarity')->paginate(25)->withQueryString(),
            'filters' => $request->only('search'),
        ]);
    }
}
