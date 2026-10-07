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

        // Each founder carries the proxied logo URL; the page shows the logo
        // when one exists upstream and falls back to the initials chip when the
        // image 404s. The admin is served same-origin, so the icon lives under
        // the /api mount.
        $founders->getCollection()->transform(function (Founder $founder) {
            $data = $founder->only(['name', 'initials', 'color']);
            $data['logo'] = url('/api/founders/'.$founder->logoSlug().'/icon');

            return $data;
        });

        return Inertia::render('Admin/Founders', [
            'founders' => $founders,
            'filters' => $request->only('search'),
        ]);
    }
}
