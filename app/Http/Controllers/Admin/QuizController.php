<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\QuizRequest;
use App\Models\Course;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Services\Audit\AuditLogger;
use App\Services\Quiz\QuizDuplicator;
use App\Support\Slug;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class QuizController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q', ''));
        $status = $request->query('status');
        $courseFilter = $request->query('course');

        $quizzes = Quiz::query()
            ->with('course', 'lesson')
            ->withCount([
                'questions',
                'attempts as official_attempts_count' => fn ($q) => $q->where('kind', QuizAttempt::KIND_OFFICIAL),
            ])
            ->when($search !== '', fn ($q) => $q->where('title', 'like', "%{$search}%"))
            ->when(in_array($status, [Quiz::STATUS_DRAFT, Quiz::STATUS_SCHEDULED, Quiz::STATUS_PUBLISHED, Quiz::STATUS_ARCHIVED], true),
                fn ($q) => $q->where('status', $status))
            ->when($courseFilter, fn ($q) => $q->whereHas('course', fn ($c) => $c->where('slug', $courseFilter)))
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('admin.quizzes.index', [
            'quizzes' => $quizzes,
            'courses' => Course::query()->orderBy('sort_order')->get(),
            'search' => $search,
            'status' => $status,
            'courseFilter' => $courseFilter,
        ]);
    }

    public function create(): View
    {
        return view('admin.quizzes.create', ['courses' => $this->coursesWithLessons()]);
    }

    private function coursesWithLessons()
    {
        return Course::query()
            ->with(['lessons' => fn ($q) => $q->orderBy('sort_order')->select('id', 'course_id', 'title')])
            ->orderBy('sort_order')
            ->get();
    }

    public function store(QuizRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $quiz = Quiz::query()->create([
            'course_id' => $data['course_id'] ?? null,
            'lesson_id' => $data['lesson_id'] ?? null,
            'slug' => $this->slug($data['slug'] ?? null, $data['title']),
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'status' => $data['status'],
            'point_cost' => $data['point_cost'],
            'duration_seconds' => $request->durationSeconds(),
            'starts_at' => $data['starts_at'] ?? null,
            'ends_at' => $data['ends_at'] ?? null,
            'result_release_at' => $data['result_release_at'] ?? null,
            'practice_enabled' => $request->boolean('practice_enabled'),
            'practice_timer_enabled' => $request->boolean('practice_timer_enabled'),
            'leaderboard_visible' => $request->boolean('leaderboard_visible'),
            'bonus_enabled' => $request->boolean('bonus_enabled'),
            'bonus_threshold_type' => $data['bonus_threshold_type'] ?? Quiz::BONUS_THRESHOLD_FULL,
            'bonus_threshold_marks' => $data['bonus_threshold_marks'] ?? null,
            'bonus_points' => $data['bonus_points'] ?? 0,
            'max_official_attempts' => $data['max_official_attempts'],
            'created_by' => $request->user()->id,
            'published_at' => $data['status'] === Quiz::STATUS_PUBLISHED ? now() : null,
        ]);

        $this->audit->log('quiz.created', $quiz, after: $quiz->only(['slug', 'title', 'status']));

        return redirect()->route('admin.quizzes.edit', $quiz)
            ->with('success', 'কুইজ তৈরি হয়েছে। এবার প্রশ্ন যোগ করুন।');
    }

    public function edit(Quiz $quiz): View
    {
        $quiz->load(['questions.options', 'course', 'lesson']);

        return view('admin.quizzes.edit', [
            'quiz' => $quiz,
            'courses' => $this->coursesWithLessons(),
            'scoringLocked' => $quiz->scoringLocked(),
        ]);
    }

    public function update(QuizRequest $request, Quiz $quiz): RedirectResponse
    {
        $data = $request->validated();
        $before = $quiz->only(['title', 'status', 'point_cost']);

        $quiz->fill([
            'course_id' => $data['course_id'] ?? null,
            'lesson_id' => $data['lesson_id'] ?? null,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'status' => $data['status'],
            'point_cost' => $data['point_cost'],
            'duration_seconds' => $request->durationSeconds(),
            'starts_at' => $data['starts_at'] ?? null,
            'ends_at' => $data['ends_at'] ?? null,
            'result_release_at' => $data['result_release_at'] ?? null,
            'practice_enabled' => $request->boolean('practice_enabled'),
            'practice_timer_enabled' => $request->boolean('practice_timer_enabled'),
            'leaderboard_visible' => $request->boolean('leaderboard_visible'),
            'bonus_enabled' => $request->boolean('bonus_enabled'),
            'bonus_threshold_type' => $data['bonus_threshold_type'] ?? Quiz::BONUS_THRESHOLD_FULL,
            'bonus_threshold_marks' => $data['bonus_threshold_marks'] ?? null,
            'bonus_points' => $data['bonus_points'] ?? 0,
            'max_official_attempts' => $data['max_official_attempts'],
        ]);

        if (! empty($data['slug'])) {
            $quiz->slug = $data['slug'];
        }
        if ($quiz->status === Quiz::STATUS_PUBLISHED && $quiz->published_at === null) {
            $quiz->published_at = now();
        }

        $quiz->save();

        $this->audit->log('quiz.updated', $quiz, before: $before, after: $quiz->only(['title', 'status', 'point_cost']));

        return back()->with('success', 'কুইজ হালনাগাদ করা হয়েছে।');
    }

    /**
     * Quick status transition from the index (publish / schedule / archive).
     */
    public function updateStatus(Request $request, Quiz $quiz): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in([Quiz::STATUS_DRAFT, Quiz::STATUS_SCHEDULED, Quiz::STATUS_PUBLISHED, Quiz::STATUS_ARCHIVED])],
        ]);

        $quiz->status = $data['status'];
        if ($data['status'] === Quiz::STATUS_PUBLISHED && $quiz->published_at === null) {
            $quiz->published_at = now();
        }
        $quiz->save();

        $action = match ($data['status']) {
            Quiz::STATUS_PUBLISHED => 'quiz.published',
            Quiz::STATUS_SCHEDULED => 'quiz.scheduled',
            Quiz::STATUS_ARCHIVED => 'quiz.archived',
            default => 'quiz.updated',
        };
        $this->audit->log($action, $quiz, after: ['status' => $quiz->status]);

        return back()->with('success', 'কুইজের অবস্থা পরিবর্তন করা হয়েছে।');
    }

    public function duplicate(Quiz $quiz, QuizDuplicator $duplicator): RedirectResponse
    {
        $copy = $duplicator->duplicate($quiz);

        $this->audit->log('quiz.duplicated', $copy, after: ['source_id' => $quiz->id, 'title' => $copy->title]);

        return redirect()->route('admin.quizzes.edit', $copy)->with('success', 'কুইজের একটি কপি তৈরি করা হয়েছে।');
    }

    public function preview(Quiz $quiz): View
    {
        $quiz->load(['questions' => fn ($q) => $q->orderBy('sort_order'), 'questions.options', 'course', 'lesson']);

        return view('admin.quizzes.preview', ['quiz' => $quiz]);
    }

    public function destroy(Quiz $quiz): RedirectResponse
    {
        // A quiz with official attempts is exam history and is never casually deleted.
        if ($quiz->hasOfficialAttempts()) {
            return back()->with('error', 'এই কুইজে অফিসিয়াল পরীক্ষা জমা পড়েছে, তাই এটি মুছে ফেলা যাবে না।');
        }

        $this->audit->log('quiz.deleted', $quiz, before: $quiz->only(['slug', 'title']));
        $quiz->delete();   // questions/options cascade

        return redirect()->route('admin.quizzes.index')->with('success', 'কুইজ মুছে ফেলা হয়েছে।');
    }

    private function slug(?string $provided, string $title): string
    {
        if ($provided !== null && trim($provided) !== '') {
            return $provided;
        }

        return Slug::unique($title, fn (string $slug) => Quiz::query()->where('slug', $slug)->exists(), 'quiz');
    }
}
