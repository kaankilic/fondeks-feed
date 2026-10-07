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

        $founders = $query->orderBy('name')->paginate(25)->withQueryString();

        // Each founder carries a logo URL: the locally stored, squared file when
        // we have one, otherwise the live Fintables proxy (same-origin, under the
        // /api mount). The page falls back to the initials chip when the image
        // 404s. A founder with no slug has no logo at all.
        $founders->getCollection()->transform(function (Founder $founder) {
            $data = $founder->only(['name', 'initials', 'color']);

            if ($logo = $founder->logo) {
                $data['logo'] = asset($logo);
            } else {
                $slug = $founder->logoSlug();
                $data['logo'] = $slug === '' ? null : url('/api/founders/'.$slug.'/icon');
            }

            return $data;
        });

        return Inertia::render('Admin/Founders', [
            'founders' => $founders,
            'filters' => $request->only('search'),
        ]);
    }
}
