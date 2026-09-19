<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Symbol;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SymbolController extends Controller
{
    public function index(Request $request)
    {
        $query = Symbol::query();

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('ticker', 'ilike', "%{$search}%")
                  ->orWhere('name', 'ilike', "%{$search}%");
            });
        }

        return Inertia::render('Admin/Symbols', [
            'symbols' => $query->orderBy('ticker')->paginate(25)->withQueryString(),
            'filters' => $request->only('search'),
        ]);
    }
}
