<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\IngestRun;
use Illuminate\Http\Request;
use Inertia\Inertia;

class IngestRunController extends Controller
{
    public function index(Request $request)
    {
        $query = IngestRun::query();

        if ($search = $request->input('search')) {
            $query->where('job', 'ilike', "%{$search}%");
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        return Inertia::render('Admin/IngestRuns', [
            'runs' => $query->orderByDesc('started_at')->paginate(25)->withQueryString(),
            'filters' => $request->only('search', 'status'),
        ]);
    }
}
