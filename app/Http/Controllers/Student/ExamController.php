<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use Carbon\CarbonImmutable;
use Illuminate\View\View;

/**
 * Student Exams listing (Phase 6 foundation). Informational only — it never starts,
 * debits or creates an attempt; QuizAttemptService remains authoritative for that
 * (the live Start/Resume flow is Phase 7). Correct answers are never loaded or shown.
 */
class ExamController extends Controller
{
    public function index(): View
    {
        $user = auth()->user();
        $now = CarbonImmutable::now();

        // Draft quizzes are never visible. Options (and their answer key) are never loaded.
        $quizzes = Quiz::query()
            ->where('status', '!=', Quiz::STATUS_DRAFT)
            ->with('course')
            ->withCount(['questions as active_questions_count' => fn ($q) => $q->where('is_active', true)])
            ->orderBy('starts_at')
            ->get();

        $attempts = QuizAttempt::query()
            ->where('user_id', $user->getKey())
            ->where('kind', QuizAttempt::KIND_OFFICIAL)
            ->get()
            ->groupBy('quiz_id')
            ->map(fn ($group) => $group->sortByDesc('attempt_no')->first());

        $groups = ['open' => [], 'upcoming' => [], 'completed' => [], 'previous' => []];

        foreach ($quizzes as $quiz) {
            $card = $this->card($quiz, $attempts->get($quiz->id), $user, $now);
            $groups[$card['group']][] = $card;
        }

        return view('student.exams.index', [
            'user' => $user,
            'groups' => $groups,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function card(Quiz $quiz, ?QuizAttempt $attempt, $user, CarbonImmutable $now): array
    {
        $state = $quiz->officialState($now);
        $completed = $attempt !== null && $attempt->isSubmitted();
        $inProgress = $attempt !== null && $attempt->isInProgress();
        $resultsReleased = $quiz->resultsReleasedAt($now);

        // Bucketing: completed exams first, then live/resume, then upcoming, else past.
        $group = match (true) {
            $completed => 'completed',
            $inProgress && $quiz->isOpenAt($now) => 'open',
            $state === Quiz::STATE_OPEN => 'open',
            $state === Quiz::STATE_UPCOMING => 'upcoming',
            default => 'previous',   // closed or archived
        };

        // The quiz can actually be started now (open window, questions authored) —
        // presentation only; QuizAttemptService re-checks everything authoritatively.
        $startable = $state === Quiz::STATE_OPEN
            && $quiz->isOpenAt($now)
            && $quiz->active_questions_count > 0;

        return [
            'group' => $group,
            'quiz' => $quiz,
            'state' => $state,
            'completed' => $completed,
            'resume' => $inProgress && $quiz->isOpenAt($now),
            'startable' => $startable && ! $inProgress,
            'has_questions' => $quiz->active_questions_count > 0,
            'attempt_id' => $attempt?->id,
            'enough_points' => $user->points_balance >= $quiz->point_cost,
            'practice_available' => $quiz->practiceAvailable(),
            // A student sees their own score only once results are released.
            'score' => ($completed && $resultsReleased) ? $attempt->final_score : null,
            'results_pending' => $completed && ! $resultsReleased,
        ];
    }
}
