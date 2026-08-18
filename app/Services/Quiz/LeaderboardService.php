<?php

namespace App\Services\Quiz;

use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * The single owner of ranking. Every screen — per-quiz leaderboard, overall
 * leaderboard, a student's own rank on their dashboard/result — reads from here, so
 * the ordering can never disagree between views (brief §18, §25).
 *
 * Ranking is computed from the authoritative stored scores (final_score), never from a
 * materialised/cached rank column, so a manual adjustment or a regrade is reflected the
 * next time any board is rendered — there is nothing stale to invalidate (brief §38–39).
 *
 * Eligibility, everywhere:
 *   - official attempts only (practice is never ranked);
 *   - terminal & scored: submitted or expired (voided and in-progress excluded);
 *   - one row per student: their BEST valid attempt under the ranking order (brief §16).
 *
 * Per-quiz order:   final_score DESC, time_taken ASC, then a deterministic unique
 *                   tiebreak (submitted_at ASC, id ASC) purely for stable display.
 * Overall order:    total_obtained DESC, percentage DESC, then user id ASC.
 * Ties:             competition ranking (1,2,2,4). Two students share a rank only when
 *                   they are equal on every dimension BEFORE the deterministic
 *                   tiebreaker — i.e. equal (score,time) per quiz, or equal
 *                   (obtained,percentage) overall.
 */
class LeaderboardService
{
    /**
     * Ranked rows for one quiz's official leaderboard, one entry per student.
     *
     * @return Collection<int, array{
     *     rank:int, user_id:int, roll:?string, name:string,
     *     obtained:int, total:int, percentage:float,
     *     time_taken_seconds:?int, submission_seq:?int
     * }>
     */
    public function quizLeaderboard(Quiz $quiz): Collection
    {
        $attempts = QuizAttempt::query()
            ->where('quiz_attempts.quiz_id', $quiz->getKey())
            ->where('quiz_attempts.kind', QuizAttempt::KIND_OFFICIAL)
            ->whereIn('quiz_attempts.status', QuizAttempt::RANKABLE_STATUSES)
            ->join('users', 'users.id', '=', 'quiz_attempts.user_id')
            ->get([
                'quiz_attempts.id', 'quiz_attempts.user_id', 'quiz_attempts.final_score',
                'quiz_attempts.total_marks_snapshot', 'quiz_attempts.time_taken_seconds',
                'quiz_attempts.submitted_at', 'quiz_attempts.submission_seq',
                'users.roll', 'users.name',
            ]);

        // One best attempt per student, then order the winners and assign ranks.
        $best = $attempts
            ->groupBy('user_id')
            ->map(fn (Collection $group) => $group->sort($this->perQuizComparator(...))->first());

        $ordered = $best->sort($this->perQuizComparator(...))->values();

        return $this->assignCompetitionRanks(
            $ordered,
            tie: fn ($a, $b): bool => (int) $a->final_score === (int) $b->final_score
                && (int) $a->time_taken_seconds === (int) $b->time_taken_seconds,
            row: fn ($a, int $rank): array => [
                'rank' => $rank,
                'user_id' => (int) $a->user_id,
                'roll' => $a->roll,
                'name' => $a->name,
                'obtained' => (int) $a->final_score,
                'total' => (int) $a->total_marks_snapshot,
                'percentage' => $this->percentage((int) $a->final_score, (int) $a->total_marks_snapshot),
                'time_taken_seconds' => $a->time_taken_seconds === null ? null : (int) $a->time_taken_seconds,
                'submission_seq' => $a->submission_seq === null ? null : (int) $a->submission_seq,
            ],
        );
    }

    /** A single student's rank on one quiz, or null if they have no ranked attempt. */
    public function studentQuizRank(Quiz $quiz, User $user): ?int
    {
        $row = $this->quizLeaderboard($quiz)->firstWhere('user_id', $user->getKey());

        return $row['rank'] ?? null;
    }

    /**
     * The cumulative leaderboard across every quiz that counts toward the overall
     * standings AND whose results are released (an unreleased quiz's scores are never
     * aggregated into a public board). One row per student, best attempt per quiz.
     *
     * @return Collection<int, array{
     *     rank:int, user_id:int, roll:?string, name:string,
     *     exams_counted:int, obtained:int, possible:int, percentage:float
     * }>
     */
    public function overallLeaderboard(?CarbonImmutable $now = null): Collection
    {
        $now ??= CarbonImmutable::now();

        $quizIds = Quiz::query()
            ->where('counts_toward_overall', true)
            ->get(['id', 'ends_at', 'result_release_at', 'results_released_at'])
            ->filter(fn (Quiz $quiz) => $quiz->resultsReleasedAt($now))
            ->pluck('id');

        if ($quizIds->isEmpty()) {
            return collect();
        }

        $attempts = QuizAttempt::query()
            ->whereIn('quiz_attempts.quiz_id', $quizIds)
            ->where('quiz_attempts.kind', QuizAttempt::KIND_OFFICIAL)
            ->whereIn('quiz_attempts.status', QuizAttempt::RANKABLE_STATUSES)
            ->join('users', 'users.id', '=', 'quiz_attempts.user_id')
            ->get([
                'quiz_attempts.id', 'quiz_attempts.user_id', 'quiz_attempts.quiz_id',
                'quiz_attempts.final_score', 'quiz_attempts.total_marks_snapshot',
                'quiz_attempts.time_taken_seconds', 'quiz_attempts.submitted_at',
                'users.roll', 'users.name',
            ]);

        $students = $attempts
            ->groupBy('user_id')
            ->map(function (Collection $group) {
                // Best attempt per quiz for this student, then aggregate across quizzes.
                $bestPerQuiz = $group
                    ->groupBy('quiz_id')
                    ->map(fn (Collection $q) => $q->sort($this->perQuizComparator(...))->first());

                $first = $group->first();

                return (object) [
                    'user_id' => (int) $first->user_id,
                    'roll' => $first->roll,
                    'name' => $first->name,
                    'exams_counted' => $bestPerQuiz->count(),
                    'obtained' => (int) $bestPerQuiz->sum(fn ($a) => (int) $a->final_score),
                    'possible' => (int) $bestPerQuiz->sum(fn ($a) => (int) $a->total_marks_snapshot),
                ];
            })
            ->values();

        $ordered = $students->sort(function ($a, $b): int {
            return [$b->obtained, $this->percentage($b->obtained, $b->possible)]
                <=> [$a->obtained, $this->percentage($a->obtained, $a->possible)]
                ?: $a->user_id <=> $b->user_id;
        })->values();

        return $this->assignCompetitionRanks(
            $ordered,
            tie: fn ($a, $b): bool => $a->obtained === $b->obtained
                && $this->percentage($a->obtained, $a->possible) === $this->percentage($b->obtained, $b->possible),
            row: fn ($a, int $rank): array => [
                'rank' => $rank,
                'user_id' => $a->user_id,
                'roll' => $a->roll,
                'name' => $a->name,
                'exams_counted' => $a->exams_counted,
                'obtained' => $a->obtained,
                'possible' => $a->possible,
                'percentage' => $this->percentage($a->obtained, $a->possible),
            ],
        );
    }

    /**
     * One student's overall standings row (including rank), or null if they have no
     * counted attempt yet. Used by the dashboard and results page so the number there is
     * exactly the leaderboard's, never a parallel calculation (brief §24).
     *
     * @return array{rank:int, user_id:int, roll:?string, name:string, exams_counted:int, obtained:int, possible:int, percentage:float}|null
     */
    public function studentOverall(User $user, ?CarbonImmutable $now = null): ?array
    {
        return $this->overallLeaderboard($now)->firstWhere('user_id', $user->getKey());
    }

    /**
     * Percentage as a 2-dp float, safe when the possible total is zero (brief §6) even
     * though an active quiz should never have a zero total.
     */
    public function percentage(int $obtained, int $possible): float
    {
        return $possible > 0 ? round($obtained / $possible * 100, 2) : 0.0;
    }

    /** Sort comparator for the per-quiz order. Lower sorts first (best attempt first). */
    private function perQuizComparator(object $a, object $b): int
    {
        return $b->final_score <=> $a->final_score               // higher score first
            ?: ($a->time_taken_seconds ?? PHP_INT_MAX) <=> ($b->time_taken_seconds ?? PHP_INT_MAX)  // faster first
            ?: $this->timestamp($a->submitted_at) <=> $this->timestamp($b->submitted_at)            // earlier first
            ?: $a->id <=> $b->id;                                // stable unique final tiebreak
    }

    /**
     * Walk an already-ordered collection and assign competition ranks: the rank is the
     * 1-based position, but a row equal to its predecessor (per $tie) keeps that rank,
     * producing 1,2,2,4.
     *
     * @template T
     * @param  Collection<int, T>  $ordered
     * @param  callable(T, T): bool  $tie
     * @param  callable(T, int): array<string, mixed>  $row
     * @return Collection<int, array<string, mixed>>
     */
    private function assignCompetitionRanks(Collection $ordered, callable $tie, callable $row): Collection
    {
        $rows = collect();
        $rank = 0;
        $index = 0;
        $previous = null;

        foreach ($ordered as $item) {
            $index++;
            if ($previous === null || ! $tie($previous, $item)) {
                $rank = $index;
            }
            $rows->push($row($item, $rank));
            $previous = $item;
        }

        return $rows;
    }

    private function timestamp(mixed $value): int
    {
        if ($value === null) {
            return PHP_INT_MAX;
        }

        return CarbonImmutable::parse($value)->getTimestamp();
    }
}
