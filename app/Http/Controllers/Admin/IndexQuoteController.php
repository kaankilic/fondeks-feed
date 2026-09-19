<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\IndexQuote;
use Illuminate\Http\Request;
use Inertia\Inertia;

class IndexQuoteController extends Controller
{
    public function index(Request $request)
    {
        $query = IndexQuote::query();

        if ($search = $request->input('search')) {
            $query->where('index_name', 'ilike', "%{$search}%");
        }

        return Inertia::render('Admin/IndexQuotes', [
            'quotes' => $query->orderByDesc('date')->paginate(25)->withQueryString(),
            'filters' => $request->only('search'),
        ]);
    }
}
