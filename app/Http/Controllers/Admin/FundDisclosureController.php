<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FundDisclosure;
use App\Services\Ingest\HoldingsJobs;
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

    /** Extract one Portföy Dağılım Raporu disclosure's holdings with Haiku, on demand. */
    public function extract(int $index, HoldingsJobs $holdings)
    {
        $disclosure = FundDisclosure::where('disclosure_index', $index)->firstOrFail();

        try {
            $result = $holdings->extractDisclosureNow($disclosure->disclosure_index);
        } catch (\Throwable $e) {
            return back()->with('flash', [
                'type' => 'error',
                'message' => "{$disclosure->fund_code}: çıkarım başarısız — {$e->getMessage()}",
            ]);
        }

        return back()->with('flash', [
            'type' => 'success',
            'message' => "{$disclosure->fund_code} ({$result['period']}): {$result['holdings']} hisse kaydedildi, {$result['movers']} hareket güncellendi.",
        ]);
    }
}
