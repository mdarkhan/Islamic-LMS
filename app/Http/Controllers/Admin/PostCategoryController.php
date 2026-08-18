<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PostCategoryRequest;
use App\Models\PostCategory;
use App\Services\Audit\AuditLogger;
use App\Support\Slug;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class PostCategoryController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(): View
    {
        return view('admin.categories.index', [
            'categories' => PostCategory::query()
                ->withCount('posts')
                ->orderBy('sort_order')
                ->get(),
        ]);
    }

    public function store(PostCategoryRequest $request): RedirectResponse
    {
        $category = PostCategory::query()->create([
            'name' => $request->input('name'),
            'slug' => Slug::unique($request->input('name'), fn (string $s) => PostCategory::query()->where('slug', $s)->exists(), 'category'),
            'is_active' => $request->boolean('is_active'),
            'sort_order' => (int) PostCategory::query()->max('sort_order') + 1,
        ]);
        $this->audit->log('category.created', $category, after: ['name' => $category->name]);

        return back()->with('success', __('posts.category_saved'));
    }

    public function update(PostCategoryRequest $request, PostCategory $category): RedirectResponse
    {
        $category->update([
            'name' => $request->input('name'),
            'is_active' => $request->boolean('is_active'),
        ]);
        $this->audit->log('category.updated', $category, after: ['name' => $category->name, 'is_active' => $category->is_active]);

        return back()->with('success', __('posts.category_saved'));
    }

    public function destroy(PostCategory $category): RedirectResponse
    {
        // Never orphan content: a category with posts is archived (deactivated), not
        // deleted. Deletion is only for an empty category.
        if ($category->posts()->exists()) {
            return back()->with('error', __('posts.category_delete_blocked'));
        }

        $this->audit->log('category.deleted', $category, before: ['name' => $category->name]);
        $category->delete();

        return back()->with('success', __('posts.category_deleted'));
    }
}
