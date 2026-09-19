<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CategoryPerformance;
use Inertia\Inertia;

class CategoryPerformanceController extends Controller
{
    public function index()
    {
        return Inertia::render('Admin/CategoryPerformance', [
            'categories' => CategoryPerformance::orderByDesc('y1')->get(),
        ]);
    }
}
