<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FundDisclosure;
use Illuminate\Http\Request;
use Inertia\Inertia;

class FundDisclosureController extends Controller
{
    public function index(Request $request)
    {
        $query = FundDisclosure::query();

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('fund_code', 'ilike', "%{$search}%")
                  ->orWhere('fund_title', 'ilike', "%{$search}%")
                  ->orWhere('subject', 'ilike', "%{$search}%");
            });
        }

        return Inertia::render('Admin/FundDisclosures', [
            'disclosures' => $query->orderByDesc('published_at')->paginate(25)->withQueryString(),
            'filters' => $request->only('search'),
        ]);
    }
}
