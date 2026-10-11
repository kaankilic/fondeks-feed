<?php

namespace App\Http\Controllers\Api;

use App\Http\Concerns\ApiResponses;
use App\Http\Controllers\Controller;
use App\Models\Guide;
use Illuminate\Http\Request;

/**
 * The blog — the editorial guides behind the site's "Rehber" content, served
 * as a paged list and a single article by slug. Backed by the `guides` table;
 * only rows with a published_at at or before now are public, so a future or
 * null date keeps a draft out of the API.
 */
class BlogController extends Controller
{
    use ApiResponses;

    private const DEFAULT_LIMIT = 20;

    /** Published articles, newest first, with an optional category filter. */
    public function index(Request $request)
    {
        $category = $request->query('category');
        if ($category !== null) {
            $category = trim((string) $category);
            if (mb_strlen($category) > 80) {
                return $this->badRequest('category must contain at most 80 character(s)');
            }
        }

        $limit = $this->intOrNull($request->query('limit', self::DEFAULT_LIMIT), 1, 100);
        if ($limit === false || $limit === null) {
            return $this->badRequest('limit must be between 1 and 100');
        }

        $offset = $this->intOrNull($request->query('offset', 0), 0, PHP_INT_MAX);
        if ($offset === false || $offset === null) {
            return $this->badRequest('offset must be a non-negative integer');
        }

        $query = Guide::query()
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());

        if ($category !== null && $category !== '') {
            $query->where('category', $category);
        }

        $total = (clone $query)->count();

        $items = $query->orderByDesc('published_at')
            ->offset($offset)
            ->limit($limit)
            ->get()
            ->map(fn (Guide $guide) => $this->listItem($guide))
            ->all();

        return $this->cached([
            'items' => $items,
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset,
        ]);
    }

    /** One published article by slug, with its full body. */
    public function show(string $slug)
    {
        $guide = Guide::query()
            ->whereKey($slug)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->first();

        if ($guide === null) {
            return $this->notFound("post {$slug} not found");
        }

        return $this->cached(['post' => $this->detail($guide)]);
    }

    /** List shape: everything but the heavy body. */
    private function listItem(Guide $guide): array
    {
        return [
            'slug' => $guide->slug,
            'title' => $guide->title,
            'summary' => $guide->summary,
            'category' => $guide->category,
            'readingMinutes' => $guide->reading_minutes,
            'publishedAt' => $guide->published_at?->toIso8601String(),
        ];
    }

    /** Detail shape: the list fields plus the article body. */
    private function detail(Guide $guide): array
    {
        return [...$this->listItem($guide), 'body' => $guide->body];
    }

    /** An integer within [min, max]: null when absent, false when invalid. */
    private function intOrNull(mixed $value, int $min, int $max): int|null|false
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (! is_numeric($value) || (int) $value != $value) {
            return false;
        }

        $number = (int) $value;

        return ($number < $min || $number > $max) ? false : $number;
    }
}
