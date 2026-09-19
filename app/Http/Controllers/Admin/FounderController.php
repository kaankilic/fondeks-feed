<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Founder;
use Illuminate\Http\Request;
use Inertia\Inertia;

class FounderController extends Controller
{
    public function index(Request $request)
    {
        $query = Founder::query();

        if ($search = $request->input('search')) {
            $query->where('name', 'ilike', "%{$search}%");
        }

        return Inertia::render('Admin/Founders', [
            'founders' => $query->orderBy('name')->paginate(25)->withQueryString(),
            'filters' => $request->only('search'),
        ]);
    }
}
