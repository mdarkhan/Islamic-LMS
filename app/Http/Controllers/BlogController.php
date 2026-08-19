<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Models\PostCategory;
use App\Models\Tag;
use App\Services\Calendar\CalendarService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Public articles / fatwa / Q&A. Only published, publish-time-reached posts are ever
 * visible here — the single `Post::scopePublic()` definition governs every path, so a
 * draft, archived or future-scheduled post cannot leak (brief §9, §53).
 */
class BlogController extends Controller
{
    public function index(Request $request): View
    {
        $q = trim($request->string('q')->toString());

        $category = ($slug = $request->string('category')->toString()) !== ''
            ? PostCategory::query()->active()->where('slug', $slug)->first()
            : null;

        $tag = ($tagSlug = $request->string('tag')->toString()) !== ''
            ? Tag::query()->where('slug', $tagSlug)->first()
            : null;

        $posts = Post::query()
            ->public()
            ->with(['category', 'author:id,name'])
            ->when($category, fn ($query) => $query->where('post_category_id', $category->id))
            ->when($tag, fn ($query) => $query->whereHas('tags', fn ($t) => $t->whereKey($tag->getKey())))
            ->when($q !== '', fn ($query) => $query->where(fn ($w) => $w
                ->where('title', 'like', "%{$q}%")
                ->orWhere('excerpt', 'like', "%{$q}%")
                ->orWhere('body', 'like', "%{$q}%")))
            ->latest('published_at')
            ->paginate(9)
            ->withQueryString();

        $categories = PostCategory::query()
            ->active()
            ->orderBy('sort_order')
            ->withCount(['posts as public_posts_count' => fn ($query) => $query->public()])
            ->get();

        return view('public.posts.index', [
            'posts' => $posts,
            'categories' => $categories,
            'activeCategory' => $category,
            'activeTag' => $tag,
            'q' => $q,
        ]);
    }

    public function show(Post $post, CalendarService $calendar): View
    {
        // Route binding resolves any slug; the gate keeps drafts/archived/scheduled out.
        abort_unless($post->isPublic(), 404);

        $post->load(['category', 'author:id,name', 'tags']);
        $this->recordView($post);

        $related = Post::query()
            ->public()
            ->where('post_category_id', $post->post_category_id)
            ->whereKeyNot($post->getKey())
            ->latest('published_at')
            ->limit(3)
            ->get(['id', 'slug', 'title', 'published_at', 'post_category_id']);

        return view('public.posts.show', [
            'post' => $post,
            'related' => $related,
            'hijri' => $post->published_at ? $calendar->hijri($post->published_at->toImmutable()) : null,
        ]);
    }

    /**
     * Count a view at most once per session per article, so a refresh does not inflate
     * the number. Never counts an admin previewing a draft (that path is a separate route).
     */
    private function recordView(Post $post): void
    {
        $seen = session()->get('viewed_posts', []);
        if (in_array($post->getKey(), $seen, true)) {
            return;
        }

        $post->increment('views_count');
        session()->put('viewed_posts', [...$seen, $post->getKey()]);
    }
}
