<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PostRequest;
use App\Models\Post;
use App\Models\PostCategory;
use App\Models\Tag;
use App\Services\Audit\AuditLogger;
use App\Support\HtmlSanitizer;
use App\Support\Slug;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Admin CMS for articles / fatwa / Q&A. The body is rich text (HTML) from the editor,
 * reduced to a safe allow-list on save and again on display — see App\Support\HtmlSanitizer.
 * Bengali titles get real Unicode slugs via the Phase 6 Slug service, auto-derived from
 * the title unless the admin sets one explicitly.
 */
class PostController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(Request $request): View
    {
        $filters = [
            'status' => $request->string('status')->toString() ?: null,
            'category' => $request->integer('category') ?: null,
            'q' => trim($request->string('q')->toString()),
        ];

        $posts = Post::query()
            ->with(['category:id,name', 'author:id,name'])
            ->when($filters['status'], fn ($query, $s) => $query->where('status', $s))
            ->when($filters['category'], fn ($query, $c) => $query->where('post_category_id', $c))
            ->when($filters['q'] !== '', fn ($query) => $query->where('title', 'like', "%{$filters['q']}%"))
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('admin.posts.index', [
            'posts' => $posts,
            'filters' => $filters,
            'categories' => PostCategory::query()->orderBy('sort_order')->get(['id', 'name']),
        ]);
    }

    public function create(): View
    {
        return view('admin.posts.create', ['categories' => $this->activeCategories()]);
    }

    public function store(PostRequest $request): RedirectResponse
    {
        $data = $request->validated();
        unset($data['new_category'], $data['tags']);
        $data['post_category_id'] = $this->resolveCategoryId($request);
        $data['slug'] = $this->uniqueSlug($request->input('slug') ?: $request->input('title'));
        $data['body'] = HtmlSanitizer::clean($data['body']);
        $data['author_id'] = $request->user()->getKey();
        $data['published_at'] = $this->resolvePublishedAt($data['status'], $request->input('published_at'));

        $post = Post::query()->create($data);
        $post->tags()->sync(Tag::resolveMany(Tag::parseInput($request->input('tags'))));
        $this->audit->log('post.created', $post, after: ['title' => $post->title, 'status' => $post->status]);

        return redirect()->route('admin.posts.edit', $post)->with('success', __('posts.saved'));
    }

    public function edit(Post $post): View
    {
        return view('admin.posts.edit', [
            'post' => $post->load('tags'),
            'categories' => $this->activeCategories(),
        ]);
    }

    public function update(PostRequest $request, Post $post): RedirectResponse
    {
        $data = $request->validated();
        unset($data['new_category'], $data['tags']);
        $data['post_category_id'] = $this->resolveCategoryId($request);
        $data['slug'] = $this->uniqueSlug($request->input('slug') ?: $request->input('title'), $post->getKey());
        $data['body'] = HtmlSanitizer::clean($data['body']);
        $data['published_at'] = $this->resolvePublishedAt($data['status'], $request->input('published_at'), $post);

        $post->update($data);
        $post->tags()->sync(Tag::resolveMany(Tag::parseInput($request->input('tags'))));
        $this->audit->log('post.updated', $post, after: ['title' => $post->title, 'status' => $post->status]);

        return redirect()->route('admin.posts.edit', $post)->with('success', __('posts.saved'));
    }

    /** Render the public detail view for any post (draft included) so admins can preview. */
    public function preview(Post $post): View
    {
        $post->load(['category', 'author:id,name']);

        return view('public.posts.show', ['post' => $post, 'related' => collect(), 'preview' => true]);
    }

    public function publish(Post $post): RedirectResponse
    {
        $post->update([
            'status' => Post::STATUS_PUBLISHED,
            'published_at' => $post->published_at ?? now(),
        ]);
        $this->audit->log('post.published', $post, after: ['published_at' => $post->published_at?->toIso8601String()]);

        return back()->with('success', __('posts.published'));
    }

    public function archive(Post $post): RedirectResponse
    {
        $post->update(['status' => Post::STATUS_ARCHIVED]);
        $this->audit->log('post.archived', $post);

        return back()->with('success', __('posts.archived'));
    }

    public function destroy(Post $post): RedirectResponse
    {
        // Delete only when safe: a live post must be archived first, so a published URL
        // is never yanked out from under a visitor by accident.
        if ($post->status === Post::STATUS_PUBLISHED) {
            return back()->with('error', __('posts.delete_published_blocked'));
        }

        $this->audit->log('post.deleted', $post, before: ['title' => $post->title]);
        $post->delete();

        return redirect()->route('admin.posts.index')->with('success', __('posts.deleted'));
    }

    private function activeCategories()
    {
        return PostCategory::query()->active()->orderBy('sort_order')->get(['id', 'name']);
    }

    /**
     * A category is either picked from the list or typed inline. An inline name reuses a
     * category of the same name (case-insensitive) if one exists, otherwise creates one
     * with a real Bengali slug — so the admin never has to leave the editor to file a
     * post under a new topic.
     */
    private function resolveCategoryId(PostRequest $request): int
    {
        $name = trim((string) $request->input('new_category'));

        if ($name === '') {
            return (int) $request->input('post_category_id');
        }

        $existing = PostCategory::query()->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->first();
        if ($existing !== null) {
            return $existing->getKey();
        }

        $category = PostCategory::query()->create([
            'name' => $name,
            'slug' => Slug::unique($name, fn (string $s) => PostCategory::query()->where('slug', $s)->exists(), 'category'),
            'is_active' => true,
            'sort_order' => (int) PostCategory::query()->max('sort_order') + 1,
        ]);
        $this->audit->log('category.created', $category, after: ['name' => $category->name]);

        return $category->getKey();
    }

    private function uniqueSlug(string $source, ?int $ignoreId = null): string
    {
        return Slug::unique($source, fn (string $slug) => Post::query()
            ->where('slug', $slug)
            ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists(), 'post');
    }

    /**
     * Publishing needs a publish time: keep an explicit one (allowing future scheduling),
     * else stamp now on first publish. Non-published statuses keep whatever was set.
     */
    private function resolvePublishedAt(string $status, ?string $input, ?Post $post = null): ?string
    {
        if ($status !== Post::STATUS_PUBLISHED) {
            return $input ?: $post?->published_at?->toDateTimeString();
        }

        return $input ?: ($post?->published_at?->toDateTimeString() ?? now()->toDateTimeString());
    }
}
