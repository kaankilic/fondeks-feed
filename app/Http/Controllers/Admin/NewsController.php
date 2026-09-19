<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\News;
use Illuminate\Http\Request;
use Inertia\Inertia;

class NewsController extends Controller
{
    public function index(Request $request)
    {
        $query = News::query();

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'ilike', "%{$search}%")
                  ->orWhere('symbol', 'ilike', "%{$search}%")
                  ->orWhere('publisher', 'ilike', "%{$search}%");
            });
        }

        if ($source = $request->input('source')) {
            $query->where('source', $source);
        }

        return Inertia::render('Admin/News', [
            'news' => $query->orderByDesc('published_at')->paginate(25)->withQueryString(),
            'filters' => $request->only('search', 'source'),
        ]);
    }
}
