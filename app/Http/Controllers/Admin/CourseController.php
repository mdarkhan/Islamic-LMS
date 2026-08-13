<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CourseRequest;
use App\Models\Course;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class CourseController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(): View
    {
        $courses = Course::query()
            ->withCount('lessons')
            ->orderBy('sort_order')
            ->get();

        return view('admin.courses.index', compact('courses'));
    }

    public function create(): View
    {
        return view('admin.courses.create');
    }

    public function store(CourseRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $course = Course::query()->create([
            'slug' => $this->uniqueSlug($data['title']),
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'is_published' => $request->boolean('is_published'),
            'sort_order' => (int) Course::query()->max('sort_order') + 1,
        ]);

        $this->audit->log('course.created', $course, after: $course->only(['slug', 'title', 'is_published']));

        return redirect()->route('admin.courses.index')->with('success', 'কোর্স তৈরি করা হয়েছে।');
    }

    public function edit(Course $course): View
    {
        return view('admin.courses.edit', compact('course'));
    }

    public function update(CourseRequest $request, Course $course): RedirectResponse
    {
        $before = $course->only(['title', 'description', 'is_published', 'sort_order']);

        $course->fill([
            'title' => $request->validated()['title'],
            'description' => $request->validated()['description'] ?? null,
            'is_published' => $request->boolean('is_published'),
            'sort_order' => (int) $request->input('sort_order', $course->sort_order),
        ])->save();

        $this->audit->log('course.updated', $course,
            before: $before,
            after: $course->only(['title', 'description', 'is_published', 'sort_order']),
        );

        return redirect()->route('admin.courses.index')->with('success', 'কোর্স হালনাগাদ করা হয়েছে।');
    }

    public function destroy(Course $course): RedirectResponse
    {
        // RESTRICT on lessons: a course with content is never silently deleted.
        if ($course->lessons()->exists()) {
            return back()->with('error', 'এই কোর্সে ক্লাস রয়েছে, তাই এটি মুছে ফেলা যাবে না। আগে ক্লাসগুলো সরান।');
        }

        $this->audit->log('course.deleted', $course, before: $course->only(['slug', 'title']));
        $course->delete();

        return redirect()->route('admin.courses.index')->with('success', 'কোর্স মুছে ফেলা হয়েছে।');
    }

    public function togglePublish(Course $course): RedirectResponse
    {
        $course->forceFill(['is_published' => ! $course->is_published])->save();

        $this->audit->log('course.updated', $course, after: ['is_published' => $course->is_published]);

        return back()->with('success', $course->is_published ? 'কোর্স প্রকাশ করা হয়েছে।' : 'কোর্স আড়াল করা হয়েছে।');
    }

    public function reorder(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'order' => ['required', 'array'],
            'order.*' => ['integer', 'exists:courses,id'],
        ]);

        foreach ($data['order'] as $position => $id) {
            Course::query()->whereKey($id)->update(['sort_order' => $position]);
        }

        return back()->with('success', 'ক্রম পরিবর্তন করা হয়েছে।');
    }

    private function uniqueSlug(string $title): string
    {
        $base = Str::slug($title) ?: 'course';
        $slug = $base;
        $i = 1;

        while (Course::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.(++$i);
        }

        return $slug;
    }
}
