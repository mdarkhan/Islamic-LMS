<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\QuizAttempt;
use App\Services\Quiz\LeaderboardService;
use App\Support\AttemptReviewPresenter;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The student's own official result history and detailed answer sheets.
 *
 * Release is enforced HERE, server-side, on every path — a score, percentage, rank or
 * answer sheet is exposed only once the quiz's results are released (brief §5, §54).
 * Nothing relies on a hidden button. A student can only ever see their own attempts.
 */
class ResultController extends Controller
{
    public function __construct(private readonly LeaderboardService $leaderboards) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $now = CarbonImmutable::now();

        $attempts = QuizAttempt::query()
            ->where('user_id', $user->getKey())
            ->where('kind', QuizAttempt::KIND_OFFICIAL)
            ->whereIn('status', QuizAttempt::RANKABLE_STATUSES)
            ->with('quiz.course')
            ->orderByDesc('submitted_at')
            ->get();

        $groups = ['released' => [], 'expired' => [], 'pending' => [], 'legacy' => []];

        foreach ($attempts as $attempt) {
            $quiz = $attempt->quiz;
            $released = $quiz->resultsReleasedAt($now);

            // Exactly one bucket per attempt, by precedence.
            $group = match (true) {
                $attempt->is_legacy_import => 'legacy',
                $released => 'released',
                $attempt->status === QuizAttempt::STATUS_EXPIRED => 'expired',
                default => 'pending',
            };

            $groups[$group][] = [
                'attempt' => $attempt,
                'quiz' => $quiz,
                'released' => $released,
                'percentage' => $released
                    ? $this->leaderboards->percentage((int) $attempt->final_score, (int) $attempt->total_marks_snapshot)
                    : null,
                // Rank is only meaningful — and only shown — once results are released.
                'rank' => $released && ! $attempt->is_legacy_import
                    ? $this->leaderboards->studentQuizRank($quiz, $user)
                    : null,
                'reviewable' => $released && $attempt->answer_details_available,
            ];
        }

        return view('student.results.index', [
            'groups' => $groups,
            'overall' => $this->leaderboards->studentOverall($user, $now),
        ]);
    }

    /**
     * The detailed answer sheet for one of the student's own official attempts.
     * Released-and-owned is enforced server-side; a legacy attempt with no stored
     * answers shows a clear message rather than any fabricated selection.
     */
    public function show(Request $request, QuizAttempt $attempt): View
    {
        $user = $request->user();

        abort_unless((int) $attempt->user_id === (int) $user->id, 403);
        abort_unless($attempt->isOfficial() && $attempt->isTerminal(), 404);

        $now = CarbonImmutable::now();
        // The core security gate: no answer sheet, correctness, key or explanation
        // before the quiz's results are released.
        abort_unless($attempt->quiz->resultsReleasedAt($now), 403);

        $legacy = $attempt->is_legacy_import || ! $attempt->answer_details_available;

        return view('student.results.show', [
            'attempt' => $attempt,
            'quiz' => $attempt->quiz,
            'legacy' => $legacy,
            'rows' => $legacy ? [] : AttemptReviewPresenter::questions($attempt),
            'regraded' => ! $legacy && $attempt->wasRegraded(),
            'percentage' => $this->leaderboards->percentage((int) $attempt->final_score, (int) $attempt->total_marks_snapshot),
            'rank' => $legacy ? null : $this->leaderboards->studentQuizRank($attempt->quiz, $user),
        ]);
    }
}
