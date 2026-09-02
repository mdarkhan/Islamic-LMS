<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CourseRequest;
use App\Models\Course;
use App\Services\Audit\AuditLogger;
use App\Services\Rewards\RewardService;
use App\Support\Slug;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class CourseController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(): View
    {
        $courses = Course::query()
            ->withCount(['lessons', 'completions'])
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
            'slug' => $this->uniqueSlug($data['slug'] ?? '' ?: $data['title']),
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
        $before = $course->only(['slug', 'title', 'description', 'is_published', 'sort_order']);

        // Slug is editable; an empty value regenerates it from the title. Either way it is
        // normalised and made unique (ignoring this course, so an unchanged slug stays put).
        $slugSource = $request->filled('slug') ? $request->input('slug') : $request->validated()['title'];

        $course->fill([
            'slug' => $this->uniqueSlug($slugSource, $course->getKey()),
            'title' => $request->validated()['title'],
            'description' => $request->validated()['description'] ?? null,
            'is_published' => $request->boolean('is_published'),
            // sort_order is managed by the up/down arrows, never edited here.
            'topper_rewards' => $this->cleanTopperRewards($request->input('topper_rewards')),
        ])->save();

        $this->audit->log('course.updated', $course,
            before: $before,
            after: $course->only(['slug', 'title', 'description', 'is_published', 'sort_order']),
        );

        return redirect()->route('admin.courses.index')->with('success', 'কোর্স হালনাগাদ করা হয়েছে।');
    }

    public function destroy(Course $course): RedirectResponse
    {
        // RESTRICT on lessons AND quizzes: a course with content is never silently deleted
        // (deleting one with a linked quiz would otherwise fail on the foreign key).
        if ($course->lessons()->exists() || $course->quizzes()->exists()) {
            return back()->with('error', 'এই কোর্সে ক্লাস বা কুইজ রয়েছে, তাই এটি মুছে ফেলা যাবে না। আগে সেগুলো সরান বা অন্য কোর্সে নিন।');
        }

        $this->audit->log('course.deleted', $course, before: $course->only(['slug', 'title']));
        $course->delete();
        $this->resequence();   // close the gap so the order stays 1..N

        return redirect()->route('admin.courses.index')->with('success', 'কোর্স মুছে ফেলা হয়েছে।');
    }

    /**
     * Move a course one step up or down by swapping its sort_order with its neighbour.
     * Ordering is 1-based and unique, so the neighbour is always well defined.
     */
    public function move(Course $course, string $direction): RedirectResponse
    {
        abort_unless(in_array($direction, ['up', 'down'], true), 404);

        $neighbour = Course::query()
            ->where('sort_order', $direction === 'up' ? '<' : '>', $course->sort_order)
            ->orderBy('sort_order', $direction === 'up' ? 'desc' : 'asc')
            ->first();

        if ($neighbour !== null) {
            DB::transaction(function () use ($course, $neighbour) {
                $original = $course->sort_order;
                $course->forceFill(['sort_order' => $neighbour->sort_order])->save();
                $neighbour->forceFill(['sort_order' => $original])->save();
            });
        }

        return back();
    }

    /** Renumber every course to a clean 1..N sequence in its current order. */
    private function resequence(): void
    {
        $position = 1;

        foreach (Course::query()->orderBy('sort_order')->orderBy('id')->get() as $course) {
            if ($course->sort_order !== $position) {
                $course->forceFill(['sort_order' => $position])->save();
            }
            $position++;
        }
    }

    /**
     * Grant the course's configured position → points rewards to the current toppers.
     * Idempotent — a student who already holds this course's reward is never re-awarded —
     * so the admin can safely press it again after more results come in.
     */
    public function awardToppers(Course $course, RewardService $rewards): RedirectResponse
    {
        if ($course->topperRewardRows() === []) {
            return back()->with('error', 'আগে টপারদের জন্য অবস্থান ও পয়েন্ট সেট করুন।');
        }

        $granted = $rewards->awardCourseToppers($course, request()->user());
        $this->audit->log('course.toppers_awarded', $course, after: ['granted' => $granted]);

        return back()->with('success', $granted > 0
            ? "{$granted} জন টপারকে বোনাস পয়েন্ট দেওয়া হয়েছে।"
            : 'নতুন করে কাউকে পুরস্কার দেওয়ার নেই (হয়তো ইতিমধ্যে দেওয়া হয়েছে অথবা ফলাফল এখনো প্রকাশ হয়নি)।');
    }

    /**
     * Keep only rows with a valid position and points, so junk from a half-filled form
     * never reaches the JSON column.
     *
     * @param  mixed  $rows
     * @return array<int, array{position:int, points:int}>
     */
    private function cleanTopperRewards($rows): array
    {
        return collect(is_array($rows) ? $rows : [])
            ->map(fn ($r) => ['position' => (int) ($r['position'] ?? 0), 'points' => (int) ($r['points'] ?? 0)])
            ->filter(fn ($r) => $r['position'] >= 1 && $r['points'] >= 1)
            ->unique('position')
            ->sortBy('position')
            ->values()
            ->all();
    }

    public function togglePublish(Course $course): RedirectResponse
    {
        $course->forceFill(['is_published' => ! $course->is_published])->save();

        $this->audit->log('course.updated', $course, after: ['is_published' => $course->is_published]);

        return back()->with('success', $course->is_published ? 'কোর্স প্রকাশ করা হয়েছে।' : 'কোর্স আড়াল করা হয়েছে।');
    }

    private function uniqueSlug(string $source, ?int $ignoreId = null): string
    {
        return Slug::unique(
            $source,
            fn (string $slug) => Course::query()
                ->where('slug', $slug)
                ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
                ->exists(),
            'course',
        );
    }
}
