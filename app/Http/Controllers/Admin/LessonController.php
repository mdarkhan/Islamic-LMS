<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\LessonRequest;
use App\Models\Course;
use App\Models\Lesson;
use App\Services\Audit\AuditLogger;
use App\Services\Import\BengaliText;
use App\Support\Slug;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class LessonController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(Request $request): View
    {
        $courseFilter = $request->query('course');

        $lessons = Lesson::query()
            ->with('course')
            ->withCount('resources')
            ->when($courseFilter, fn ($q) => $q->whereHas('course', fn ($c) => $c->where('slug', $courseFilter)))
            ->orderBy('course_id')
            ->orderBy('sort_order')
            ->paginate(25)
            ->withQueryString();

        return view('admin.lessons.index', [
            'lessons' => $lessons,
            'courses' => Course::query()->orderBy('sort_order')->get(),
            'courseFilter' => $courseFilter,
        ]);
    }

    public function create(): View
    {
        return view('admin.lessons.create', [
            'courses' => Course::query()->orderBy('sort_order')->get(),
        ]);
    }

    public function store(LessonRequest $request): RedirectResponse
    {
        $lesson = DB::transaction(function () use ($request) {
            $data = $request->validated();

            $lesson = Lesson::query()->create([
                'course_id' => $data['course_id'],
                'slug' => $this->uniqueSlug($data['title']),
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'syllabus' => $data['syllabus'] ?? null,
                // The calendar picker is the only source of truth for the date; the
                // Bengali label shown to students is always derived from it, never
                // typed by hand.
                'held_on' => $data['held_on'] ?? null,
                'date_label' => BengaliText::formatDateLabel($data['held_on'] ?? null),
                'duration_minutes' => $data['duration_minutes'] ?? null,
                'duration_label' => $data['duration_label'] ?? null,
                'media_provider' => $data['media_provider'],
                'media_url' => $data['media_url'] ?? null,
                'media_file_id' => $this->driveFileId($data['media_url'] ?? null),
                'is_published' => $request->boolean('is_published'),
                'sort_order' => (int) ($data['sort_order'] ?? (Lesson::query()->where('course_id', $data['course_id'])->max('sort_order') + 1)),
            ]);

            $this->syncResources($lesson, $request->input('resources', []));

            return $lesson;
        });

        $this->audit->log('lesson.created', $lesson, after: $lesson->only(['slug', 'title', 'is_published']));

        return redirect()->route('admin.lessons.edit', $lesson)->with('success', 'ক্লাস তৈরি করা হয়েছে।');
    }

    public function edit(Lesson $lesson): View
    {
        $lesson->load('resources');

        return view('admin.lessons.edit', [
            'lesson' => $lesson,
            'courses' => Course::query()->orderBy('sort_order')->get(),
        ]);
    }

    public function update(LessonRequest $request, Lesson $lesson): RedirectResponse
    {
        $before = $lesson->only(['course_id', 'title', 'is_published']);

        DB::transaction(function () use ($request, $lesson) {
            $data = $request->validated();

            $lesson->fill([
                'course_id' => $data['course_id'],
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'syllabus' => $data['syllabus'] ?? null,
                'held_on' => $data['held_on'] ?? null,
                'date_label' => BengaliText::formatDateLabel($data['held_on'] ?? null),
                'duration_minutes' => $data['duration_minutes'] ?? null,
                'duration_label' => $data['duration_label'] ?? null,
                'media_provider' => $data['media_provider'],
                'media_url' => $data['media_url'] ?? null,
                'media_file_id' => $this->driveFileId($data['media_url'] ?? null),
                'is_published' => $request->boolean('is_published'),
                'sort_order' => (int) ($data['sort_order'] ?? $lesson->sort_order),
            ])->save();

            $this->syncResources($lesson, $request->input('resources', []));
        });

        $this->audit->log('lesson.updated', $lesson,
            before: $before,
            after: $lesson->only(['course_id', 'title', 'is_published']),
        );

        return redirect()->route('admin.lessons.edit', $lesson)->with('success', 'ক্লাস হালনাগাদ করা হয়েছে।');
    }

    public function destroy(Lesson $lesson): RedirectResponse
    {
        $this->audit->log('lesson.deleted', $lesson, before: $lesson->only(['slug', 'title']));
        $lesson->delete();   // resources cascade

        return redirect()->route('admin.lessons.index')->with('success', 'ক্লাস মুছে ফেলা হয়েছে।');
    }

    public function togglePublish(Lesson $lesson): RedirectResponse
    {
        $lesson->forceFill(['is_published' => ! $lesson->is_published])->save();

        $this->audit->log('lesson.updated', $lesson, after: ['is_published' => $lesson->is_published]);

        return back()->with('success', $lesson->is_published ? 'ক্লাস প্রকাশ করা হয়েছে।' : 'ক্লাস আড়াল করা হয়েছে।');
    }

    /**
     * Replace the lesson's resources from the submitted rows. A row with no label is
     * dropped; an empty or "#" URL is stored as NULL so it renders as plain text
     * rather than a dead link (the legacy placeholder problem).
     */
    private function syncResources(Lesson $lesson, array $rows): void
    {
        $lesson->resources()->delete();

        $order = 0;
        foreach ($rows as $row) {
            $label = trim((string) ($row['label'] ?? ''));
            if ($label === '') {
                continue;
            }

            $url = trim((string) ($row['url'] ?? ''));
            $url = ($url === '' || $url === '#') ? null : $url;

            $lesson->resources()->create([
                'label' => $label,
                'url' => $url,
                'kind' => $row['kind'] ?? 'link',
                'sort_order' => $order++,
            ]);
        }
    }

    private function driveFileId(?string $url): ?string
    {
        if ($url === null) {
            return null;
        }

        return preg_match('#/file/d/([^/]+)/#', $url, $m) ? $m[1] : null;
    }

    private function uniqueSlug(string $title): string
    {
        return Slug::unique(
            $title,
            fn (string $slug) => Lesson::query()->where('slug', $slug)->exists(),
            'lesson',
        );
    }
}
