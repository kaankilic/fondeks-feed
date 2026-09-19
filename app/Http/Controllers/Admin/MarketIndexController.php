<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MarketIndex;
use Illuminate\Http\Request;
use Inertia\Inertia;

class MarketIndexController extends Controller
{
    public function index(Request $request)
    {
        $query = MarketIndex::query();

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                  ->orWhere('symbol', 'ilike', "%{$search}%");
            });
        }

        return Inertia::render('Admin/MarketIndices', [
            'indices' => $query->orderBy('position')->paginate(25)->withQueryString(),
            'filters' => $request->only('search'),
        ]);
    }
}
