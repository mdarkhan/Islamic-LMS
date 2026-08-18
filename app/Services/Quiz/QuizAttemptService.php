<?php

namespace App\Services\Quiz;

use App\Models\PointTransaction;
use App\Models\Quiz;
use App\Models\QuizAnswer;
use App\Models\QuizAnswerOption;
use App\Models\QuizAttempt;
use App\Models\QuizOption;
use App\Models\QuizQuestion;
use App\Models\User;
use App\Services\Points\PointService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Owns the lifecycle of an attempt: eligibility, the atomic point debit, autosave,
 * and submission.
 *
 * The server clock is authoritative throughout. Nothing here trusts a
 * client-supplied user id, score, expiry or elapsed time.
 *
 * Terminal states (submitted / expired / voided) are one-way — see finalise().
 */
class QuizAttemptService
{
    public function __construct(
        private readonly PointService $points,
        private readonly QuizScoringService $scoring,
    ) {}

    /**
     * Start an official attempt, or return the one already in progress.
     *
     * Resuming NEVER debits a second point: an in-progress attempt is returned
     * untouched before any point logic runs.
     *
     * @throws AttemptNotAllowedException
     * @throws \App\Services\Points\InsufficientPointsException
     */
    public function startOfficial(Quiz $quiz, User $user, ?CarbonImmutable $now = null): QuizAttempt
    {
        $now ??= CarbonImmutable::now();

        if (! $user->isActive()) {
            throw new AttemptNotAllowedException('আপনার অ্যাকাউন্ট সক্রিয় নয়।');
        }

        if (! $quiz->isOpenAt($now)) {
            throw new AttemptNotAllowedException('এই কুইজটি এখন চালু নেই।');
        }

        return DB::transaction(function () use ($quiz, $user, $now) {
            // Lock the user row first: this serialises concurrent start requests for
            // the same student, so the balance check below cannot be raced.
            $lockedUser = User::query()->whereKey($user->getKey())->lockForUpdate()->firstOrFail();

            $existing = QuizAttempt::query()
                ->where('quiz_id', $quiz->getKey())
                ->where('user_id', $lockedUser->getKey())
                ->where('kind', QuizAttempt::KIND_OFFICIAL)
                ->orderByDesc('attempt_no')
                ->first();

            if ($existing !== null) {
                // Resume: already paid for, so return it without touching points.
                if ($existing->isInProgress()) {
                    if ($existing->hasExpiredAt($now)) {
                        return $this->finalise($existing, $now, QuizAttempt::STATUS_EXPIRED);
                    }

                    return $existing;
                }

                if ($existing->attempt_no >= $quiz->max_official_attempts) {
                    throw new AttemptNotAllowedException('আপনি ইতিমধ্যে এই কুইজে অংশ নিয়েছেন।');
                }
            }

            $attemptNo = ($existing->attempt_no ?? 0) + 1;

            // Append-only ledger: the attempt is created FIRST so the debit can carry
            // it as an immutable reference at insert time. No ledger row is ever
            // updated afterward (SECURITY.md / DATABASE_SCHEMA.md §2).
            $attempt = QuizAttempt::query()->create([
                'quiz_id' => $quiz->getKey(),
                'user_id' => $lockedUser->getKey(),
                'kind' => QuizAttempt::KIND_OFFICIAL,
                'attempt_no' => $attemptNo,
                'status' => QuizAttempt::STATUS_IN_PROGRESS,
                'started_at' => $now,
                'expires_at' => $this->deadlineFor($quiz, $now),
                'counts_toward_cumulative' => true,
                'point_transaction_id' => null,
                'total_marks_snapshot' => $quiz->total_marks,
            ]);

            if ($quiz->point_cost > 0) {
                // If this throws InsufficientPointsException, the whole transaction —
                // including the attempt just created — rolls back. The student is
                // never charged for an attempt that does not exist, and never gets an
                // attempt they did not pay for.
                $transaction = $this->points->debit(
                    user: $lockedUser,
                    amount: $quiz->point_cost,
                    type: PointTransaction::TYPE_DEDUCTION,
                    reason: "Quiz: {$quiz->slug}",
                    reference: $attempt,
                );

                // Updating the ATTEMPT (not the ledger) to link its debit is allowed —
                // quiz_attempts is mutable; point_transactions is not.
                $attempt->forceFill(['point_transaction_id' => $transaction->getKey()])->save();
            }

            return $attempt;
        });
    }

    /**
     * Start a practice attempt: free, unranked, and NOT retained as history.
     *
     * Practice keeps no history (product decision) — any earlier practice attempt for
     * this quiz+user is deleted first (its answers cascade), so at most one practice
     * attempt exists at a time, purely for the current session and its immediate review.
     * Nothing here touches the point ledger.
     *
     * Timer: untimed by default. When the admin enabled `practice_timer_enabled` and the
     * quiz has a duration, the attempt gets a countdown from `duration_seconds` — the
     * official `ends_at` is never reused.
     */
    public function startPractice(Quiz $quiz, User $user, ?CarbonImmutable $now = null): QuizAttempt
    {
        $now ??= CarbonImmutable::now();

        if (! $quiz->practice_enabled) {
            throw new AttemptNotAllowedException('এই কুইজের জন্য অনুশীলন চালু নেই।');
        }

        return DB::transaction(function () use ($quiz, $user, $now) {
            // No practice history: drop any prior practice attempt (answers cascade).
            QuizAttempt::query()
                ->where('quiz_id', $quiz->getKey())
                ->where('user_id', $user->getKey())
                ->where('kind', QuizAttempt::KIND_PRACTICE)
                ->get()
                ->each(fn (QuizAttempt $old) => $old->delete());

            return QuizAttempt::query()->create([
                'quiz_id' => $quiz->getKey(),
                'user_id' => $user->getKey(),
                'kind' => QuizAttempt::KIND_PRACTICE,
                'attempt_no' => 1,
                'status' => QuizAttempt::STATUS_IN_PROGRESS,
                'started_at' => $now,
                'expires_at' => $quiz->practiceTimerActive() ? $now->addSeconds((int) $quiz->duration_seconds) : null,
                'counts_toward_cumulative' => false,   // never affects leaderboards
                'point_transaction_id' => null,        // never costs a point
                'total_marks_snapshot' => $quiz->total_marks,
            ]);
        });
    }

    /**
     * Persist the selection for one question. Idempotent: repeated or retried saves
     * upsert the same row rather than accumulating duplicates.
     *
     * Fails closed. A submitted option that does not belong to the question, or more
     * than one option for a single-choice question, is rejected — never silently
     * dropped or truncated. An empty selection clears the answer (unanswered).
     *
     * @param  array<int, int>  $optionIds
     * @return QuizAnswer|null  null when the selection was cleared
     *
     * @throws AttemptNotAllowedException
     * @throws InvalidAnswerSelectionException
     */
    public function saveAnswer(
        QuizAttempt $attempt,
        int $questionId,
        array $optionIds,
        ?CarbonImmutable $now = null,
    ): ?QuizAnswer {
        $now ??= CarbonImmutable::now();

        if (! $attempt->isInProgress()) {
            throw new AttemptNotAllowedException('এই অ্যাটেম্পটটি আর চালু নেই।');
        }

        // Re-checked server-side on every write, whatever the browser timer showed.
        if ($attempt->hasExpiredAt($now)) {
            $this->finalise($attempt, $now, QuizAttempt::STATUS_EXPIRED);
            throw new AttemptNotAllowedException('এই অ্যাটেম্পটের সময় শেষ হয়ে গেছে।');
        }

        $question = QuizQuestion::query()
            ->where('quiz_id', $attempt->quiz_id)
            ->whereKey($questionId)
            ->firstOrFail();

        // Normalise duplicates, then validate every id belongs to this question.
        $requested = array_values(array_unique(array_map(intval(...), $optionIds)));

        $validOptionIds = QuizOption::query()
            ->where('question_id', $question->getKey())
            ->pluck('id')
            ->map(intval(...))
            ->all();

        $foreign = array_diff($requested, $validOptionIds);
        if ($foreign !== []) {
            // Reject rather than silently discard: a payload referencing options that
            // are not part of this question is malformed and must not be half-applied.
            throw new InvalidAnswerSelectionException(
                'নির্বাচিত অপশনটি এই প্রশ্নের অন্তর্ভুক্ত নয়।'
            );
        }

        if ($question->type === QuizQuestion::TYPE_SINGLE && count($requested) > 1) {
            throw new InvalidAnswerSelectionException(
                'এই প্রশ্নের জন্য একটির বেশি উত্তর নির্বাচন করা যাবে না।'
            );
        }

        return DB::transaction(function () use ($attempt, $question, $requested, $now) {
            // Empty selection clears the answer, so "unanswered" is a truly absent row.
            if ($requested === []) {
                QuizAnswer::query()
                    ->where('attempt_id', $attempt->getKey())
                    ->where('question_id', $question->getKey())
                    ->delete();   // cascades to quiz_answer_options

                return null;
            }

            $answer = QuizAnswer::query()->updateOrCreate(
                ['attempt_id' => $attempt->getKey(), 'question_id' => $question->getKey()],
                ['answered_at' => $now],
            );

            // Replace the selection wholesale — simpler and race-free versus diffing.
            QuizAnswerOption::query()->where('answer_id', $answer->getKey())->delete();

            foreach ($requested as $optionId) {
                QuizAnswerOption::query()->create([
                    'answer_id' => $answer->getKey(),
                    'option_id' => $optionId,
                ]);
            }

            return $answer->refresh();
        });
    }

    /**
     * Submit an attempt.
     *
     * Idempotent and terminal-safe: a terminal attempt (already submitted, expired
     * or voided) is returned unchanged. A submit that arrives after the authoritative
     * deadline is recorded as EXPIRED, not submitted — see finalise().
     */
    public function submit(QuizAttempt $attempt, ?CarbonImmutable $now = null): QuizAttempt
    {
        $now ??= CarbonImmutable::now();

        if ($attempt->isTerminal()) {
            return $attempt;
        }

        return $this->finalise($attempt, $now, QuizAttempt::STATUS_SUBMITTED);
    }

    /**
     * Finalise every official attempt whose authoritative deadline has passed but
     * which is still in_progress (the student closed the tab, lost connectivity, or
     * never submitted). Each is closed through submit(), so finalise() records it as
     * EXPIRED with the deadline as the effective end — never as an on-time submission.
     *
     * Safety net only: saveAnswer(), startOfficial() (on resume) and the exam screen
     * all finalise opportunistically too, so an attempt is never left dangling. Runs
     * from the scheduler (attempts:finalize-expired). Idempotent — an already-terminal
     * attempt is skipped by finalise().
     *
     * @return int  number of attempts finalised
     */
    public function finalizeExpired(?CarbonImmutable $now = null): int
    {
        $now ??= CarbonImmutable::now();

        $finalised = 0;

        QuizAttempt::query()
            ->where('kind', QuizAttempt::KIND_OFFICIAL)
            ->where('status', QuizAttempt::STATUS_IN_PROGRESS)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', $now)
            ->orderBy('id')
            ->chunkById(200, function ($attempts) use ($now, &$finalised): void {
                foreach ($attempts as $attempt) {
                    // submit() past the deadline records EXPIRED (see finalise()).
                    $this->submit($attempt, $now);
                    $finalised++;
                }
            });

        return $finalised;
    }

    /**
     * Close out an attempt and score it. The single place an attempt becomes terminal.
     *
     * Guarantees:
     *  - a terminal attempt is returned untouched (no rescore, no timestamp rewrite);
     *  - if the authoritative deadline has passed, the attempt is finalised as EXPIRED
     *    regardless of the requested status, so a late submit() can never masquerade
     *    as an on-time submission;
     *  - submitted_at and time_taken_seconds are measured against the authoritative
     *    end (the deadline for an expiry, else now), never inflated by a late request.
     */
    private function finalise(QuizAttempt $attempt, CarbonImmutable $now, string $requestedStatus): QuizAttempt
    {
        return DB::transaction(function () use ($attempt, $now, $requestedStatus) {
            $locked = QuizAttempt::query()->whereKey($attempt->getKey())->lockForUpdate()->firstOrFail();

            // Terminal is one-way. Whoever got here first owns the outcome.
            if ($locked->isTerminal()) {
                return $locked;
            }

            // The server decides the real terminal status. Past the deadline, the
            // outcome is EXPIRED with the deadline as the effective end — a submit
            // request cannot change the authoritative expiry or inflate elapsed time.
            $expired = $locked->hasExpiredAt($now);
            $status = $expired ? QuizAttempt::STATUS_EXPIRED : $requestedStatus;
            $effectiveEnd = $expired && $locked->expires_at
                ? CarbonImmutable::instance($locked->expires_at)
                : $now;

            $locked->forceFill([
                'status' => $status,
                'submitted_at' => $effectiveEnd,
                'time_taken_seconds' => max(0, $effectiveEnd->diffInSeconds($locked->started_at, absolute: true)),
                'submission_seq' => $this->assignSubmissionSeq($locked),
            ])->save();

            $this->scoring->scoreAttempt($locked);

            return $locked->refresh();
        });
    }

    /**
     * Assign a per-quiz submission serial, concurrency-safe.
     *
     * Only official attempts get a serial; practice attempts return null. The quiz
     * row is locked so two students finalising the same quiz concurrently cannot read
     * the same MAX and collide. A unique index on (quiz_id, submission_seq) is the
     * database backstop. Ranking never uses this value, so it is informational only.
     *
     * Must be called inside the finalise() transaction.
     */
    private function assignSubmissionSeq(QuizAttempt $attempt): ?int
    {
        if (! $attempt->isOfficial()) {
            return null;
        }

        // Serialise all official finalisations for this quiz on the quiz row.
        Quiz::query()->whereKey($attempt->quiz_id)->lockForUpdate()->first();

        return (int) QuizAttempt::query()
            ->where('quiz_id', $attempt->quiz_id)
            ->where('kind', QuizAttempt::KIND_OFFICIAL)
            ->max('submission_seq') + 1;
    }

    /**
     * Deadline is the earlier of (start + duration) and the quiz end time, so a
     * late starter can never run past the exam window.
     */
    private function deadlineFor(Quiz $quiz, CarbonImmutable $now): ?CarbonImmutable
    {
        $byDuration = $quiz->duration_seconds
            ? $now->addSeconds($quiz->duration_seconds)
            : null;

        $byWindow = $quiz->ends_at ? CarbonImmutable::instance($quiz->ends_at) : null;

        return match (true) {
            $byDuration && $byWindow => $byDuration->min($byWindow),
            default => $byDuration ?? $byWindow,
        };
    }
}
