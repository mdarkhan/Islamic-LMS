<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\Course;
use App\Models\Post;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Simple site-wide search across the three public content types (courses, books,
 * articles). No search infrastructure — a capped `LIKE` scan is more than enough for
 * this site's content volume. Never touches lessons (behind login) or anything
 * unpublished/draft.
 */
class SearchController extends Controller
{
    private const LIMIT = 5;

    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));

        if ($q === '') {
            return view('public.search', ['q' => $q, 'courses' => collect(), 'books' => collect(), 'posts' => collect()]);
        }

        return view('public.search', [
            'q' => $q,
            'courses' => Course::query()
                ->where('is_published', true)
                ->where('title', 'like', "%{$q}%")
                ->limit(self::LIMIT)
                ->get(),
            'books' => Book::query()
                ->published()
                ->where(fn ($w) => $w->where('title', 'like', "%{$q}%")->orWhere('author', 'like', "%{$q}%"))
                ->limit(self::LIMIT)
                ->get(),
            'posts' => Post::query()
                ->public()
                ->with('category:id,name,slug')
                ->where(fn ($w) => $w->where('title', 'like', "%{$q}%")->orWhere('excerpt', 'like', "%{$q}%"))
                ->limit(self::LIMIT)
                ->get(),
        ]);
    }
}
