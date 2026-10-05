<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\Course;
use App\Models\Post;
use App\Services\Import\BengaliText;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Simple site-wide search across the three public content types (courses, books,
 * articles). No search infrastructure — a capped `LIKE` scan is more than enough for
 * this site's content volume. Never touches lessons (behind login) or anything
 * unpublished/draft.
 *
 * The term is matched in every stored spelling of Bengali য়/ড়/ঢ় (see
 * BengaliText::searchVariants) and LIKE wildcards in it are escaped, so a search for
 * "%" or "_" is a search for that character, not "everything".
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

        $patterns = array_map(
            fn (string $variant) => '%'.addcslashes($variant, '\\%_').'%',
            BengaliText::searchVariants($q),
        );

        return view('public.search', [
            'q' => $q,
            'courses' => Course::query()
                ->where('is_published', true)
                ->where(fn ($w) => $this->matching($w, ['title'], $patterns))
                ->limit(self::LIMIT)
                ->get(),
            'books' => Book::query()
                ->published()
                ->where(fn ($w) => $this->matching($w, ['title', 'author'], $patterns))
                ->limit(self::LIMIT)
                ->get(),
            'posts' => Post::query()
                ->public()
                ->with('category:id,name,slug')
                ->where(fn ($w) => $this->matching($w, ['title', 'excerpt'], $patterns))
                ->limit(self::LIMIT)
                ->get(),
        ]);
    }

    /**
     * Any of the columns LIKE any of the patterns.
     *
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $query
     * @param  array<int, string>  $columns
     * @param  array<int, string>  $patterns
     */
    private function matching(Builder $query, array $columns, array $patterns): void
    {
        foreach ($columns as $column) {
            foreach ($patterns as $pattern) {
                $query->orWhere($column, 'like', $pattern);
            }
        }
    }
}
