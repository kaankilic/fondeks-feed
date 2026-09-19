<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Fund;
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
}
