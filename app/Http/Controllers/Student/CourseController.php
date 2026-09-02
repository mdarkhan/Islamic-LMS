<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Lesson;
use App\Services\Progress\LessonProgressService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CourseController extends Controller
{
    public function __construct(private readonly LessonProgressService $progress) {}

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

    public function show(Request $request, Lesson $lesson): View
    {
        abort_unless($lesson->is_published && $lesson->course->is_published, 404);

        $lesson->load('resources', 'course');

        $this->progress->recordView($request->user(), $lesson);

        return view('student.courses.show', [
            'lesson' => $lesson,
            'previousLesson' => $this->adjacentLesson($lesson, 'previous'),
            'nextLesson' => $this->adjacentLesson($lesson, 'next'),
        ]);
    }

    /**
     * The published lesson immediately before/after $lesson within the same course, by
     * (sort_order, id) — the same ordering the course listing and admin builder use, with
     * id as a deterministic tiebreak for lessons sharing a sort_order.
     */
    private function adjacentLesson(Lesson $lesson, string $direction): ?Lesson
    {
        $forward = $direction === 'next';

        return Lesson::query()
            ->where('course_id', $lesson->course_id)
            ->where('is_published', true)
            ->where(function ($q) use ($lesson, $forward) {
                $q->where('sort_order', $forward ? '>' : '<', $lesson->sort_order)
                    ->orWhere(function ($q2) use ($lesson, $forward) {
                        $q2->where('sort_order', $lesson->sort_order)
                            ->where('id', $forward ? '>' : '<', $lesson->id);
                    });
            })
            ->orderBy('sort_order', $forward ? 'asc' : 'desc')
            ->orderBy('id', $forward ? 'asc' : 'desc')
            ->first();
    }
}
