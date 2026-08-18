<?php

namespace Tests\Feature\Console;

use App\Models\PointTransaction;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Services\Points\PointService;
use App\Services\Quiz\QuizAttemptService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\QuizBuilder;
use Tests\TestCase;

/**
 * The `attempts:finalize-expired` sweep is the safety net for a student who closes the
 * tab before submitting: their attempt must still become terminal (and be scored) so it
 * can never stay in-progress forever. Run every minute by the scheduler.
 */
class FinalizeExpiredAttemptsTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_finalises_only_the_attempts_past_their_deadline(): void
    {
        $expiredQuiz = Quiz::factory()->create();
        $q = QuizBuilder::for($expiredQuiz)->question(['ক', 'খ'], correctPositions: [1]);
        // A quiz with a much longer window, so its attempt is still live when the first expires.
        $liveQuiz = Quiz::factory()->create([
            'duration_seconds' => 7200,
            'ends_at' => now()->addHours(3),
        ]);
        QuizBuilder::for($liveQuiz)->question(['ক', 'খ'], correctPositions: [1]);

        $expiredAttempt = $this->startAttempt($expiredQuiz->fresh());
        $liveAttempt = $this->startAttempt($liveQuiz->fresh());

        // Jump past the first quiz's deadline but not far — the second is still live.
        $this->travelTo(CarbonImmutable::instance($expiredAttempt->expires_at)->addSecond());

        $this->artisan('attempts:finalize-expired')
            ->expectsOutputToContain('1')
            ->assertSuccessful();

        $this->assertSame(QuizAttempt::STATUS_EXPIRED, $expiredAttempt->fresh()->status);
        $this->assertSame(QuizAttempt::STATUS_IN_PROGRESS, $liveAttempt->fresh()->status);
        $this->travelBack();
    }

    public function test_it_scores_the_expired_attempt_from_its_saved_answers(): void
    {
        $quiz = Quiz::factory()->create();
        $q = QuizBuilder::for($quiz)->question(['ক', 'খ'], correctPositions: [1], marks: 3);
        $attempt = $this->startAttempt($quiz->fresh());

        app(QuizAttemptService::class)->saveAnswer($attempt, (int) $q->id, [$q->options[0]->id]);

        $this->travelTo(CarbonImmutable::instance($attempt->expires_at)->addSecond());
        $this->artisan('attempts:finalize-expired')->assertSuccessful();

        $fresh = $attempt->fresh();
        $this->assertSame(QuizAttempt::STATUS_EXPIRED, $fresh->status);
        $this->assertSame(3, $fresh->final_score);
        $this->travelBack();
    }

    public function test_it_is_a_no_op_when_nothing_has_expired(): void
    {
        $quiz = Quiz::factory()->create();
        QuizBuilder::for($quiz)->question(['ক', 'খ'], correctPositions: [1]);
        $this->startAttempt($quiz->fresh());

        $this->artisan('attempts:finalize-expired')->assertSuccessful();

        $this->assertSame(0, QuizAttempt::query()->whereIn('status', [
            QuizAttempt::STATUS_EXPIRED,
            QuizAttempt::STATUS_SUBMITTED,
        ])->count());
    }

    private function startAttempt(Quiz $quiz): QuizAttempt
    {
        $user = $this->makeStudent();
        app(PointService::class)->credit($user, 5, PointTransaction::TYPE_ADJUSTMENT, 'seed');

        return app(QuizAttemptService::class)->startOfficial($quiz, $user->fresh());
    }
}
