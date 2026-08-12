<?php

namespace Tests\Feature;

use App\Models\PointTransaction;
use App\Models\Quiz;
use App\Models\QuizAnswerOption;
use App\Models\QuizAttempt;
use App\Models\User;
use App\Services\Points\InsufficientPointsException;
use App\Services\Quiz\AttemptNotAllowedException;
use App\Services\Quiz\InvalidAnswerSelectionException;
use App\Services\Quiz\QuizAttemptService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\QuizBuilder;
use Tests\TestCase;

class QuizAttemptTest extends TestCase
{
    use RefreshDatabase;

    private QuizAttemptService $attempts;

    protected function setUp(): void
    {
        parent::setUp();
        $this->attempts = app(QuizAttemptService::class);
    }

    private function quizWithOneQuestion(array $state = []): array
    {
        $quiz = Quiz::factory()->create($state);
        $question = QuizBuilder::for($quiz)->question(['ক', 'খ'], correctPositions: [1]);

        return [$quiz->fresh(), $question];
    }

    public function test_starting_an_official_attempt_debits_exactly_one_point(): void
    {
        [$quiz] = $this->quizWithOneQuestion();
        $user = User::factory()->withPoints(4)->create();

        $attempt = $this->attempts->startOfficial($quiz, $user);

        $this->assertSame(3, $user->fresh()->points_balance);
        $this->assertNotNull($attempt->point_transaction_id);
        $this->assertSame(QuizAttempt::STATUS_IN_PROGRESS, $attempt->status);

        // The ledger row points back at the attempt that caused it.
        $tx = PointTransaction::find($attempt->point_transaction_id);
        $this->assertSame(QuizAttempt::class, $tx->reference_type);
        $this->assertSame($attempt->id, (int) $tx->reference_id);
    }

    public function test_resuming_an_attempt_does_not_debit_a_second_point(): void
    {
        [$quiz] = $this->quizWithOneQuestion();
        $user = User::factory()->withPoints(4)->create();

        $first = $this->attempts->startOfficial($quiz, $user);
        $second = $this->attempts->startOfficial($quiz, $user);
        $third = $this->attempts->startOfficial($quiz, $user);

        $this->assertSame($first->id, $second->id);
        $this->assertSame($first->id, $third->id);
        $this->assertSame(3, $user->fresh()->points_balance);
        $this->assertSame(1, PointTransaction::query()->where('user_id', $user->id)->where('amount', '<', 0)->count());
    }

    public function test_insufficient_points_blocks_the_start_and_creates_no_attempt(): void
    {
        [$quiz] = $this->quizWithOneQuestion();
        $user = User::factory()->create();   // zero balance

        $this->expectException(InsufficientPointsException::class);

        try {
            $this->attempts->startOfficial($quiz, $user);
        } finally {
            $this->assertSame(0, QuizAttempt::query()->count());
            $this->assertSame(0, $user->fresh()->points_balance);
        }
    }

    public function test_a_second_official_attempt_is_refused_once_submitted(): void
    {
        [$quiz] = $this->quizWithOneQuestion();
        $user = User::factory()->withPoints(5)->create();

        $attempt = $this->attempts->startOfficial($quiz, $user);
        $this->attempts->submit($attempt);

        $this->expectException(AttemptNotAllowedException::class);
        $this->attempts->startOfficial($quiz, $user);
    }

    public function test_quiz_that_has_not_started_cannot_be_attempted(): void
    {
        [$quiz] = $this->quizWithOneQuestion();
        $quiz->forceFill(['starts_at' => now()->addDay(), 'ends_at' => now()->addDays(2)])->save();

        $this->expectException(AttemptNotAllowedException::class);
        $this->attempts->startOfficial($quiz->fresh(), User::factory()->withPoints(5)->create());
    }

    public function test_ended_quiz_cannot_be_attempted(): void
    {
        [$quiz] = $this->quizWithOneQuestion();
        $quiz->forceFill(['starts_at' => now()->subDays(2), 'ends_at' => now()->subDay()])->save();

        $this->expectException(AttemptNotAllowedException::class);
        $this->attempts->startOfficial($quiz->fresh(), User::factory()->withPoints(5)->create());
    }

    public function test_suspended_student_cannot_start_and_is_not_charged(): void
    {
        [$quiz] = $this->quizWithOneQuestion();
        $user = User::factory()->suspended()->withPoints(5)->create();

        $this->expectException(AttemptNotAllowedException::class);

        try {
            $this->attempts->startOfficial($quiz, $user);
        } finally {
            $this->assertSame(5, $user->fresh()->points_balance);
        }
    }

    public function test_autosave_is_idempotent_and_replaces_the_previous_selection(): void
    {
        [$quiz, $question] = $this->quizWithOneQuestion();
        $user = User::factory()->withPoints(5)->create();
        $attempt = $this->attempts->startOfficial($quiz, $user);

        $a = $question->options[0]->id;
        $b = $question->options[1]->id;

        $this->attempts->saveAnswer($attempt, $question->id, [$a]);
        $this->attempts->saveAnswer($attempt, $question->id, [$a]);   // duplicate retry
        $this->assertSame(1, $attempt->answers()->count());
        $this->assertSame(1, QuizAnswerOption::query()->count());

        $this->attempts->saveAnswer($attempt, $question->id, [$b]);   // changed answer
        $this->assertSame(1, $attempt->answers()->count());
        $this->assertSame([$b], $attempt->answers()->first()->selectedOptionIds());
    }

    public function test_single_choice_rejects_more_than_one_option(): void
    {
        // Fail closed: do not silently truncate to one option.
        [$quiz, $question] = $this->quizWithOneQuestion();
        $user = User::factory()->withPoints(5)->create();
        $attempt = $this->attempts->startOfficial($quiz, $user);

        try {
            $this->attempts->saveAnswer($attempt, $question->id, [
                $question->options[0]->id,
                $question->options[1]->id,
            ]);
            $this->fail('Expected InvalidAnswerSelectionException.');
        } catch (InvalidAnswerSelectionException) {
            // The malformed request must leave no partial answer behind.
            $this->assertSame(0, $attempt->answers()->count());
        }
    }

    public function test_multiple_choice_accepts_a_valid_multi_selection(): void
    {
        $quiz = Quiz::factory()->create();
        $question = QuizBuilder::for($quiz)->question(['ক', 'খ', 'গ'], correctPositions: [1, 2]);
        $user = User::factory()->withPoints(5)->create();
        $attempt = $this->attempts->startOfficial($quiz->fresh(), $user);

        $ids = [$question->options[0]->id, $question->options[1]->id];
        $this->attempts->saveAnswer($attempt, $question->id, $ids);

        $this->assertSame(
            collect($ids)->sort()->values()->all(),
            $attempt->answers()->first()->selectedOptionIds()
        );
    }

    public function test_foreign_option_is_rejected_not_discarded(): void
    {
        [$quiz, $question] = $this->quizWithOneQuestion();
        $otherQuiz = Quiz::factory()->create();
        $foreign = QuizBuilder::for($otherQuiz)->question(['ক', 'খ'], correctPositions: [1]);

        $user = User::factory()->withPoints(5)->create();
        $attempt = $this->attempts->startOfficial($quiz, $user);

        try {
            $this->attempts->saveAnswer($attempt, $question->id, [$foreign->options[0]->id]);
            $this->fail('Expected InvalidAnswerSelectionException.');
        } catch (InvalidAnswerSelectionException) {
            $this->assertSame(0, $attempt->answers()->count());
        }
    }

    public function test_a_foreign_option_mixed_with_a_valid_one_rejects_the_whole_request(): void
    {
        [$quiz, $question] = $this->quizWithOneQuestion();
        $otherQuiz = Quiz::factory()->create();
        $foreign = QuizBuilder::for($otherQuiz)->question(['ক', 'খ'], correctPositions: [1]);
        $user = User::factory()->withPoints(5)->create();
        $attempt = $this->attempts->startOfficial($quiz, $user);

        $this->expectException(InvalidAnswerSelectionException::class);
        $this->attempts->saveAnswer($attempt, $question->id, [
            $question->options[0]->id,   // valid
            $foreign->options[0]->id,    // foreign — poisons the whole request
        ]);
    }

    public function test_duplicate_option_ids_are_normalised(): void
    {
        $quiz = Quiz::factory()->create();
        $question = QuizBuilder::for($quiz)->question(['ক', 'খ', 'গ'], correctPositions: [1, 2]);
        $user = User::factory()->withPoints(5)->create();
        $attempt = $this->attempts->startOfficial($quiz->fresh(), $user);

        $a = $question->options[0]->id;
        $this->attempts->saveAnswer($attempt, $question->id, [$a, $a, $a]);

        $this->assertSame([$a], $attempt->answers()->first()->selectedOptionIds());
    }

    public function test_empty_selection_clears_the_answer(): void
    {
        [$quiz, $question] = $this->quizWithOneQuestion();
        $user = User::factory()->withPoints(5)->create();
        $attempt = $this->attempts->startOfficial($quiz, $user);

        $this->attempts->saveAnswer($attempt, $question->id, [$question->options[0]->id]);
        $this->assertSame(1, $attempt->answers()->count());

        $cleared = $this->attempts->saveAnswer($attempt, $question->id, []);

        $this->assertNull($cleared);
        $this->assertSame(0, $attempt->answers()->count(), 'clearing leaves no answer row');
        $this->assertSame(0, QuizAnswerOption::query()->count());
    }

    public function test_answers_cannot_be_saved_after_the_deadline(): void
    {
        [$quiz, $question] = $this->quizWithOneQuestion();
        $user = User::factory()->withPoints(5)->create();
        $attempt = $this->attempts->startOfficial($quiz, $user);

        // Server clock decides, regardless of what the browser timer displayed.
        $afterDeadline = CarbonImmutable::instance($attempt->expires_at)->addSecond();

        $this->expectException(AttemptNotAllowedException::class);

        try {
            $this->attempts->saveAnswer($attempt, $question->id, [$question->options[0]->id], $afterDeadline);
        } finally {
            $this->assertSame(QuizAttempt::STATUS_EXPIRED, $attempt->fresh()->status);
        }
    }

    public function test_submitting_twice_does_not_rescore_or_recharge(): void
    {
        [$quiz, $question] = $this->quizWithOneQuestion();
        $user = User::factory()->withPoints(5)->create();

        $attempt = $this->attempts->startOfficial($quiz, $user);
        $this->attempts->saveAnswer($attempt, $question->id, [$question->options[0]->id]);

        $first = $this->attempts->submit($attempt);
        $balanceAfterFirst = $user->fresh()->points_balance;
        $second = $this->attempts->submit($first);

        $this->assertSame($first->id, $second->id);
        $this->assertSame($first->submitted_at->toDateTimeString(), $second->submitted_at->toDateTimeString());
        $this->assertSame(1, $second->final_score);
        $this->assertSame($balanceAfterFirst, $user->fresh()->points_balance);
        $this->assertSame(1, QuizAttempt::query()->count());
    }

    public function test_attempt_deadline_never_runs_past_the_quiz_end(): void
    {
        // Duration is 1 hour but only 10 minutes of the window remain.
        [$quiz] = $this->quizWithOneQuestion();
        $quiz->forceFill([
            'duration_seconds' => 3600,
            'ends_at' => now()->addMinutes(10),
        ])->save();

        $user = User::factory()->withPoints(5)->create();
        $attempt = $this->attempts->startOfficial($quiz->fresh(), $user);

        $this->assertTrue(
            $attempt->expires_at->lessThanOrEqualTo($quiz->fresh()->ends_at),
            'Attempt deadline must be clamped to the exam window.'
        );
    }

    public function test_submission_serial_increments_per_quiz(): void
    {
        [$quiz] = $this->quizWithOneQuestion();

        $seqs = collect(range(1, 3))->map(function () use ($quiz) {
            $user = User::factory()->withPoints(5)->create();

            return $this->attempts->submit($this->attempts->startOfficial($quiz, $user))->submission_seq;
        });

        $this->assertSame([1, 2, 3], $seqs->all());
    }

    public function test_practice_attempt_is_free_and_unranked(): void
    {
        [$quiz] = $this->quizWithOneQuestion(['practice_enabled' => true]);
        $user = User::factory()->withPoints(3)->create();

        $attempt = $this->attempts->startPractice($quiz, $user);

        $this->assertSame(3, $user->fresh()->points_balance, 'practice must not cost a point');
        $this->assertNull($attempt->point_transaction_id);
        $this->assertFalse($attempt->counts_toward_cumulative);
        $this->assertSame(QuizAttempt::KIND_PRACTICE, $attempt->kind);
    }

    public function test_practice_allows_repeated_attempts(): void
    {
        [$quiz] = $this->quizWithOneQuestion(['practice_enabled' => true]);
        $user = User::factory()->create();

        $a = $this->attempts->startPractice($quiz, $user);
        $b = $this->attempts->startPractice($quiz, $user);

        $this->assertNotSame($a->id, $b->id);
        $this->assertSame(2, $b->attempt_no);
    }

    public function test_practice_is_refused_when_not_enabled(): void
    {
        [$quiz] = $this->quizWithOneQuestion();

        $this->expectException(AttemptNotAllowedException::class);
        $this->attempts->startPractice($quiz, User::factory()->create());
    }

    public function test_practice_attempt_carries_no_submission_serial(): void
    {
        [$quiz, $question] = $this->quizWithOneQuestion(['practice_enabled' => true]);
        $user = User::factory()->create();

        $attempt = $this->attempts->startPractice($quiz, $user);
        $this->attempts->saveAnswer($attempt, $question->id, [$question->options[0]->id]);
        $submitted = $this->attempts->submit($attempt);

        $this->assertSame(QuizAttempt::STATUS_SUBMITTED, $submitted->status);
        $this->assertNull($submitted->submission_seq, 'practice must not consume an official serial');
    }

    // ── Terminal-state integrity ────────────────────────────────────────────────

    public function test_an_expired_attempt_is_not_reopened_by_a_late_submit(): void
    {
        [$quiz, $question] = $this->quizWithOneQuestion();
        $user = User::factory()->withPoints(5)->create();
        $attempt = $this->attempts->startOfficial($quiz, $user);
        $this->attempts->saveAnswer($attempt, $question->id, [$question->options[0]->id]);

        // Trigger expiry authoritatively at the deadline.
        $deadline = CarbonImmutable::instance($attempt->expires_at);
        try {
            $this->attempts->saveAnswer($attempt, $question->id, [$question->options[0]->id], $deadline->addSecond());
        } catch (AttemptNotAllowedException) {
            // expected — the save is rejected and the attempt is finalised as expired
        }

        $expired = $attempt->fresh();
        $this->assertSame(QuizAttempt::STATUS_EXPIRED, $expired->status);
        $snapshot = [
            'status' => $expired->status,
            'submitted_at' => $expired->submitted_at->toDateTimeString(),
            'time_taken' => $expired->time_taken_seconds,
            'score' => $expired->final_score,
            'seq' => $expired->submission_seq,
        ];

        // A submit request arrives well after expiry.
        $result = $this->attempts->submit($expired, $deadline->addMinutes(30));

        $after = $result->fresh();
        $this->assertSame($snapshot['status'], $after->status, 'expired must stay expired');
        $this->assertSame($snapshot['submitted_at'], $after->submitted_at->toDateTimeString(), 'authoritative timestamp unchanged');
        $this->assertSame($snapshot['time_taken'], $after->time_taken_seconds, 'elapsed time not inflated');
        $this->assertSame($snapshot['score'], $after->final_score, 'score fixed at expiry retained');
        $this->assertSame($snapshot['seq'], $after->submission_seq);
    }

    public function test_a_submit_after_the_deadline_is_recorded_as_expired_not_submitted(): void
    {
        // The attempt is never touched between start and a late submit, so it is
        // still in_progress in the DB when submit() arrives past the deadline.
        [$quiz, $question] = $this->quizWithOneQuestion();
        $user = User::factory()->withPoints(5)->create();
        $attempt = $this->attempts->startOfficial($quiz, $user);
        $this->attempts->saveAnswer($attempt, $question->id, [$question->options[0]->id]);

        $deadline = CarbonImmutable::instance($attempt->expires_at);
        $result = $this->attempts->submit($attempt, $deadline->addMinutes(5));

        $this->assertSame(QuizAttempt::STATUS_EXPIRED, $result->status, 'a late submit cannot masquerade as on-time');
        // submitted_at is pinned to the authoritative deadline, not the late request.
        $this->assertSame($deadline->toDateTimeString(), $result->submitted_at->toDateTimeString());
        $this->assertSame(1, $result->final_score, 'answers saved before the deadline are still scored');
    }

    public function test_a_voided_attempt_is_never_finalised(): void
    {
        [$quiz, $question] = $this->quizWithOneQuestion();
        $user = User::factory()->withPoints(5)->create();
        $attempt = $this->attempts->startOfficial($quiz, $user);
        $this->attempts->saveAnswer($attempt, $question->id, [$question->options[0]->id]);

        // An admin voids the attempt (e.g. for misconduct).
        $attempt->forceFill(['status' => QuizAttempt::STATUS_VOIDED])->save();

        $result = $this->attempts->submit($attempt->fresh());

        $this->assertSame(QuizAttempt::STATUS_VOIDED, $result->status);
        $this->assertNull($result->submitted_at, 'voided attempts are never scored or timestamped');
    }

    // ── Append-only ledger ──────────────────────────────────────────────────────

    public function test_starting_an_attempt_attaches_the_debit_without_mutating_the_ledger(): void
    {
        [$quiz] = $this->quizWithOneQuestion();
        $user = User::factory()->withPoints(4)->create();

        $attempt = $this->attempts->startOfficial($quiz, $user);

        // Exactly one debit row, and its reference was set at insert time (the attempt
        // exists before the debit is written), so no ledger row is ever updated.
        $debits = PointTransaction::query()->where('user_id', $user->id)->where('amount', '<', 0)->get();
        $this->assertCount(1, $debits);
        $this->assertSame(QuizAttempt::class, $debits[0]->reference_type);
        $this->assertSame($attempt->id, (int) $debits[0]->reference_id);
        $this->assertSame($debits[0]->id, $attempt->point_transaction_id);

        // Invariant preserved.
        $this->assertSame(3, $user->fresh()->points_balance);
        $this->assertSame(
            (int) PointTransaction::query()->where('user_id', $user->id)->sum('amount'),
            $user->fresh()->points_balance
        );
    }
}
