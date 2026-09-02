<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Notice;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\User;
use App\Services\Progress\LessonProgressService;
use App\Services\Quiz\LeaderboardService;
use Carbon\CarbonImmutable;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(
        private readonly LeaderboardService $leaderboards,
        private readonly LessonProgressService $progress,
    ) {}

    public function index(): View
    {
        $user = auth()->user();
        $now = CarbonImmutable::now();

        // Overall figures come from the SAME service that powers the leaderboard, so the
        // dashboard can never disagree with it (brief §24). null until a released,
        // counted attempt exists — never fabricated.
        $overall = $this->leaderboards->studentOverall($user, $now);
        $rankMovement = $this->rankMovement($user, $overall);

        // Exams completed = distinct official exams the student has a terminal attempt in.
        $completed = QuizAttempt::query()
            ->where('user_id', $user->getKey())
            ->where('kind', QuizAttempt::KIND_OFFICIAL)
            ->whereIn('status', QuizAttempt::RANKABLE_STATUSES)
            ->distinct()
            ->count('quiz_id');

        $courses = Course::query()
            ->where('is_published', true)
            ->withCount(['lessons' => fn ($q) => $q->where('is_published', true)])
            ->orderBy('sort_order')
            ->get();

        $continueLesson = $this->progress->recentlyViewedLesson($user);
        $nextLesson = $this->progress->nextUnviewedLesson($user, $continueLesson?->course)
            ?? $this->progress->nextUnviewedLesson($user);

        return view('student.dashboard', [
            'user' => $user,
            'completed' => $completed,
            'overall' => $overall,
            'rankMovement' => $rankMovement,
            'notices' => Notice::query()
                ->activeAt()
                ->forAudience(Notice::AUDIENCE_STUDENTS)
                ->orderByDesc('priority')
                ->limit(3)
                ->get(),
            'recent' => QuizAttempt::query()
                ->where('user_id', $user->getKey())
                ->where('kind', QuizAttempt::KIND_OFFICIAL)
                ->whereIn('status', QuizAttempt::RANKABLE_STATUSES)
                ->with('quiz')
                ->orderByDesc('submitted_at')
                ->limit(5)
                ->get(),
            'recentPoints' => $user->pointTransactions()->latest('id')->limit(5)->get(),
            'courses' => $courses,
            'courseProgress' => $courses->mapWithKeys(fn (Course $course) => [
                $course->id => [
                    ...$this->progress->courseProgress($user, $course),
                    'completed' => $this->progress->hasCompletedCourse($user, $course),
                ],
            ]),
            'continueLesson' => $continueLesson,
            'nextLesson' => $nextLesson,
            'upcomingExam' => $this->upcomingOfficialExam($user, $now),
            'resumableAttempts' => $this->resumableAttempts($user, $now),
            'unseenResults' => $this->unseenResults($user, $now),
            'practiceSuggestions' => $this->practiceSuggestions($user, $now),
        ]);
    }

    /**
     * Compares the overall rank the student last saw on this dashboard against today's,
     * then updates the stored value — purely a display concern (brief: ranking itself
     * lives only in LeaderboardService, never re-derived here).
     *
     * @return 'up'|'down'|'same'|null
     */
    private function rankMovement(User $user, ?array $overall): ?string
    {
        $previous = $user->last_seen_overall_rank;
        $current = $overall['rank'] ?? null;

        $movement = match (true) {
            $current === null || $previous === null => null,
            $current < $previous => 'up',     // a lower rank number is better
            $current > $previous => 'down',
            default => 'same',
        };

        if ($current !== null && $current !== $previous) {
            $user->forceFill(['last_seen_overall_rank' => $current])->save();
        }

        return $movement;
    }

    /**
     * The single most relevant official exam to highlight: an already-open one the
     * student hasn't touched yet, else the soonest upcoming one. Excludes any quiz the
     * student already has an attempt for (in progress is covered by the resume card;
     * completed by results) — the same simplification ExamController's own listing uses.
     * Only title/course/schedule are ever touched — never marks, questions or the answer key.
     */
    private function upcomingOfficialExam(User $user, CarbonImmutable $now): ?array
    {
        $attemptedQuizIds = QuizAttempt::query()
            ->where('user_id', $user->getKey())
            ->where('kind', QuizAttempt::KIND_OFFICIAL)
            ->pluck('quiz_id');

        $quiz = Quiz::query()
            ->where('status', '!=', Quiz::STATUS_DRAFT)
            ->whereNotIn('id', $attemptedQuizIds)
            ->with('course:id,title')
            ->withCount(['questions as active_questions_count' => fn ($q) => $q->where('is_active', true)])
            ->get()
            ->filter(fn (Quiz $quiz) => in_array($quiz->officialState($now), [Quiz::STATE_OPEN, Quiz::STATE_UPCOMING], true))
            ->sort(function (Quiz $a, Quiz $b) use ($now) {
                $aOpen = $a->officialState($now) === Quiz::STATE_OPEN;
                $bOpen = $b->officialState($now) === Quiz::STATE_OPEN;

                return ($bOpen <=> $aOpen) ?: ($a->starts_at?->getTimestamp() ?? 0) <=> ($b->starts_at?->getTimestamp() ?? 0);
            })
            ->first();

        if (! $quiz) {
            return null;
        }

        $state = $quiz->officialState($now);

        return [
            'quiz' => $quiz,
            'state' => $state,
            'startable' => $state === Quiz::STATE_OPEN && $quiz->isOpenAt($now) && $quiz->active_questions_count > 0,
        ];
    }

    /**
     * Official and practice attempts the student left mid-way — a still-valid resume
     * target, never one already past its deadline (that would 409 and finalise as expired).
     *
     * @return \Illuminate\Support\Collection<int, QuizAttempt>
     */
    private function resumableAttempts(User $user, CarbonImmutable $now): \Illuminate\Support\Collection
    {
        return QuizAttempt::query()
            ->where('user_id', $user->getKey())
            ->where('status', QuizAttempt::STATUS_IN_PROGRESS)
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', $now))
            ->with('quiz')
            ->orderByDesc('started_at')
            ->limit(3)
            ->get();
    }

    /**
     * Released official results the student has not yet looked at (results_seen_at is
     * set the moment they open /results, so this naturally clears itself).
     *
     * @return \Illuminate\Support\Collection<int, QuizAttempt>
     */
    private function unseenResults(User $user, CarbonImmutable $now): \Illuminate\Support\Collection
    {
        return QuizAttempt::query()
            ->where('user_id', $user->getKey())
            ->where('kind', QuizAttempt::KIND_OFFICIAL)
            ->whereIn('status', QuizAttempt::RANKABLE_STATUSES)
            ->whereNull('results_seen_at')
            ->with('quiz')
            ->orderByDesc('submitted_at')
            ->get()
            ->filter(fn (QuizAttempt $attempt) => $attempt->quiz->resultsReleasedAt($now))
            ->take(3)
            ->values();
    }

    /**
     * Quizzes the student has already sat officially, where Practice Mode has since
     * opened (brief §10 — never before results are released) and they haven't tried yet.
     *
     * @return \Illuminate\Support\Collection<int, Quiz>
     */
    private function practiceSuggestions(User $user, CarbonImmutable $now): \Illuminate\Support\Collection
    {
        $attemptedQuizIds = QuizAttempt::query()
            ->where('user_id', $user->getKey())
            ->where('kind', QuizAttempt::KIND_OFFICIAL)
            ->whereIn('status', QuizAttempt::RANKABLE_STATUSES)
            ->pluck('quiz_id')
            ->unique();

        if ($attemptedQuizIds->isEmpty()) {
            return collect();
        }

        $practicedQuizIds = QuizAttempt::query()
            ->where('user_id', $user->getKey())
            ->where('kind', QuizAttempt::KIND_PRACTICE)
            ->pluck('quiz_id')
            ->unique();

        return Quiz::query()
            ->whereIn('id', $attemptedQuizIds)
            ->whereNotIn('id', $practicedQuizIds)
            ->with('course:id,title')
            ->get()
            ->filter(fn (Quiz $quiz) => $quiz->practiceAvailableAt($now))
            ->take(3)
            ->values();
    }
}
