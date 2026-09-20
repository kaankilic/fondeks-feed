<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Guide;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class GuideController extends Controller
{
    public function index(Request $request)
    {
        $query = Guide::query();

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'ilike', "%{$search}%")
                  ->orWhere('category', 'ilike', "%{$search}%")
                  ->orWhere('slug', 'ilike', "%{$search}%");
            });
        }

        return Inertia::render('Admin/Guides', [
            'guides' => $query->orderByDesc('published_at')->paginate(25)->withQueryString(),
            'filters' => $request->only('search'),
        ]);
    }

    public function create()
    {
        return Inertia::render('Admin/GuideForm', [
            'guide' => null,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'slug' => ['required', 'string', 'max:80', 'regex:/^[a-z0-9-]+$/', Rule::unique('guides', 'slug')],
            'title' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', 'max:255'],
            'summary' => ['required', 'string'],
            'body' => ['required', 'string'],
        ]);

        Guide::create([
            'slug' => $data['slug'],
            'title' => $data['title'],
            'category' => $data['category'],
            'summary' => $data['summary'],
            'body' => $data['body'],
            'reading_minutes' => $this->readingMinutes($data['body']),
            'published_at' => now(),
        ]);

        return redirect()->route('admin.guides')->with('flash', [
            'type' => 'success',
            'message' => 'Rehber oluşturuldu.',
        ]);
    }

    public function edit(Guide $guide)
    {
        return Inertia::render('Admin/GuideForm', [
            'guide' => $guide,
        ]);
    }

    public function update(Request $request, Guide $guide)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', 'max:255'],
            'summary' => ['required', 'string'],
            'body' => ['required', 'string'],
        ]);

        $guide->update([
            'title' => $data['title'],
            'category' => $data['category'],
            'summary' => $data['summary'],
            'body' => $data['body'],
            'reading_minutes' => $this->readingMinutes($data['body']),
        ]);

        return redirect()->route('admin.guides')->with('flash', [
            'type' => 'success',
            'message' => 'Rehber güncellendi.',
        ]);
    }

    public function destroy(Guide $guide)
    {
        $guide->delete();

        return redirect()->route('admin.guides')->with('flash', [
            'type' => 'success',
            'message' => 'Rehber silindi.',
        ]);
    }

    /** Estimate reading time from the rich-text content (~200 words/min). */
    private function readingMinutes(string $html): int
    {
        $text = trim(preg_replace('/\s+/', ' ', strip_tags($html)));
        $words = $text === '' ? 0 : count(explode(' ', $text));
        return max(1, (int) ceil($words / 200));
    }
}
