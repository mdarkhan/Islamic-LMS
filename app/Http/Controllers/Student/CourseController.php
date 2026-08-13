<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Lesson;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CourseController extends Controller
{
    public function index(Request $request): View
    {
        $courses = Course::query()->where('is_published', true)->orderBy('sort_order')->get();

        $search = trim((string) $request->query('q', ''));
        $courseFilter = $request->query('course');

        $lessons = Lesson::query()
            ->where('is_published', true)
            ->whereHas('course', fn ($q) => $q->where('is_published', true))
            ->when($courseFilter, fn ($q) => $q->whereHas('course', fn ($c) => $c->where('slug', $courseFilter)))
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('title', 'like', "%{$search}%")
                ->orWhere('description', 'like', "%{$search}%")))
            ->with('course')
            ->orderBy('course_id')
            ->orderBy('sort_order')
            ->paginate(18)
            ->withQueryString();

        return view('student.courses.index', compact('courses', 'lessons', 'search', 'courseFilter'));
    }

    public function show(Lesson $lesson): View
    {
        abort_unless($lesson->is_published && $lesson->course->is_published, 404);

        $lesson->load('resources', 'course');

        return view('student.courses.show', ['lesson' => $lesson]);
    }
}
