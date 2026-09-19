<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\KapExtractionBatch;
use Illuminate\Http\Request;
use Inertia\Inertia;

class KapExtractionBatchController extends Controller
{
    public function index(Request $request)
    {
        $query = KapExtractionBatch::query();

        if ($search = $request->input('search')) {
            $query->where('id', 'ilike', "%{$search}%");
        }

        return Inertia::render('Admin/KapExtractionBatches', [
            'batches' => $query->orderByDesc('submitted_at')->paginate(25)->withQueryString(),
            'filters' => $request->only('search'),
        ]);
    }
}
