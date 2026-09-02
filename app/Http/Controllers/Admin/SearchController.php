<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Book;
use App\Models\Course;
use App\Models\Post;
use App\Models\Quiz;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * A single admin-wide search box across the entities an admin daily needs to jump to —
 * each section only runs (and only appears) when the acting admin actually holds the
 * permission that area's own list already requires, so this can never surface something
 * a narrower-permission admin couldn't otherwise see.
 */
class SearchController extends Controller
{
    private const LIMIT = 8;

    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));
        $user = $request->user();
        $results = [];

        if ($q !== '') {
            if ($user->hasPermission('students.view')) {
                $results['students'] = User::query()->students()
                    ->where(fn ($w) => $w->where('name', 'like', "%{$q}%")->orWhere('roll', 'like', "%{$q}%"))
                    ->orderBy('name')->limit(self::LIMIT)->get();
            }
            if ($user->hasPermission('quizzes.view')) {
                $results['quizzes'] = Quiz::query()->where('title', 'like', "%{$q}%")
                    ->orderByDesc('id')->limit(self::LIMIT)->get();
            }
            if ($user->hasPermission('courses.manage')) {
                $results['courses'] = Course::query()->where('title', 'like', "%{$q}%")
                    ->orderBy('sort_order')->limit(self::LIMIT)->get();
            }
            if ($user->hasPermission('books.manage')) {
                $results['books'] = Book::query()->where('title', 'like', "%{$q}%")
                    ->orderBy('title')->limit(self::LIMIT)->get();
            }
            if ($user->hasPermission('posts.manage')) {
                $results['posts'] = Post::query()->where('title', 'like', "%{$q}%")
                    ->orderByDesc('id')->limit(self::LIMIT)->get();
            }
        }

        return view('admin.search', ['q' => $q, 'results' => $results]);
    }
}
