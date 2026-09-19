<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Fund;
use App\Models\Founder;
use App\Models\IngestRun;
use App\Models\News;
use App\Models\User;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function __invoke()
    {
        return Inertia::render('Admin/Dashboard', [
            'stats' => [
                ['label' => 'Fonlar', 'value' => Fund::count()],
                ['label' => 'Kurucular', 'value' => Founder::count()],
                ['label' => 'Kullanıcılar', 'value' => User::count()],
                ['label' => 'Haberler', 'value' => News::count()],
                ['label' => 'Son İşlem', 'value' => IngestRun::latest('started_at')->first()?->started_at?->diffForHumans() ?? '—'],
            ],
            'recentIngests' => IngestRun::orderByDesc('started_at')->limit(10)->get(),
        ]);
    }
}
