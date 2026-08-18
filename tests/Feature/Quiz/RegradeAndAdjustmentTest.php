<?php

namespace Tests\Feature\Quiz;

use App\Models\PointTransaction;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\QuizQuestion;
use App\Models\RegradeRun;
use App\Models\User;
use App\Services\Points\PointService;
use App\Services\Quiz\AdjustmentOutOfBoundsException;
use App\Services\Quiz\QuizAttemptService;
use App\Services\Quiz\QuizRegradeService;
use App\Services\Quiz\ScoreAdjustmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\QuizBuilder;
use Tests\TestCase;

class RegradeAndAdjustmentTest extends TestCase
{
    use RefreshDatabase;

    private QuizRegradeService $regrade;
    private ScoreAdjustmentService $adjust;
    private QuizAttemptService $attempts;

    protected function setUp(): void
    {
        parent::setUp();
        $this->regrade = app(QuizRegradeService::class);
        $this->adjust = app(ScoreAdjustmentService::class);
        $this->attempts = app(QuizAttemptService::class);
    }

    /** Student sits the quiz, picking $pickIndex on the (single) question. */
    private function sit(Quiz $quiz, QuizQuestion $question, int $pickIndex): QuizAttempt
    {
        $student = $this->makeStudent();
        app(PointService::class)->credit($student, 5, PointTransaction::TYPE_ADJUSTMENT, 'seed');
        $attempt = $this->attempts->startOfficial($quiz->fresh(), $student->fresh());
        $this->attempts->saveAnswer($attempt, (int) $question->id, [$question->options[$pickIndex]->id]);

        return $this->attempts->submit($attempt)->fresh();
    }

    public function test_correcting_the_key_raises_a_previously_wrong_score(): void
    {
        $quiz = Quiz::factory()->create();
        $q = QuizBuilder::for($quiz)->question(['A', 'B'], correctPositions: [1], marks: 1);   // correct = A
        $attempt = $this->sit($quiz, $q, pickIndex: 1);   // student picked B → wrong → 0
        $this->assertSame(0, $attempt->final_score);

        // Correct answer was actually B.
        $this->regrade->apply($q->fresh(), [$q->options[1]->id], 1, QuizQuestion::TYPE_SINGLE, null, $this->makeAdmin(), 'key fix');

        $this->assertSame(1, $attempt->fresh()->final_score);
    }

    public function test_correcting_the_key_can_lower_a_previously_right_score(): void
    {
        $quiz = Quiz::factory()->create();
        $q = QuizBuilder::for($quiz)->question(['A', 'B'], correctPositions: [1], marks: 1);   // correct = A
        $attempt = $this->sit($quiz, $q, pickIndex: 0);   // student picked A → right → 1
        $this->assertSame(1, $attempt->final_score);

        $this->regrade->apply($q->fresh(), [$q->options[1]->id], 1, QuizQuestion::TYPE_SINGLE, null, $this->makeAdmin(), 'key fix');

        $this->assertSame(0, $attempt->fresh()->final_score);
    }

    public function test_a_manual_adjustment_survives_a_regrade(): void
    {
        $quiz = Quiz::factory()->create();
        $q = QuizBuilder::for($quiz)->question(['A', 'B'], correctPositions: [1], marks: 5);   // correct = A
        $attempt = $this->sit($quiz, $q, pickIndex: 1);   // wrong → calculated 0
        $this->assertSame(0, $attempt->calculated_score);

        // Admin grants +2 goodwill.
        $this->adjust->adjust($attempt->fresh(), 2, 'goodwill', $this->makeAdmin());
        $this->assertSame(2, $attempt->fresh()->final_score);

        // Key corrected to B → student now scores 5 calculated; +2 must survive.
        $this->regrade->apply($q->fresh(), [$q->options[1]->id], 5, QuizQuestion::TYPE_SINGLE, null, $this->makeAdmin(), 'key fix');

        $fresh = $attempt->fresh();
        $this->assertSame(5, $fresh->calculated_score);
        $this->assertSame(2, $fresh->manual_adjustment);
        $this->assertSame(7, $fresh->final_score);
    }

    public function test_multiple_choice_keeps_exact_set_matching_after_regrade(): void
    {
        $quiz = Quiz::factory()->create();
        $q = QuizBuilder::for($quiz)->question(['A', 'B', 'C'], correctPositions: [1, 2], marks: 4);   // correct = {A,B}
        // Student picks A + C (partially right) → exact-set rule → 0.
        $student = $this->makeStudent();
        app(PointService::class)->credit($student, 5, PointTransaction::TYPE_ADJUSTMENT, 'seed');
        $attempt = $this->attempts->startOfficial($quiz->fresh(), $student->fresh());
        $this->attempts->saveAnswer($attempt, (int) $q->id, [$q->options[0]->id, $q->options[2]->id]);
        $this->attempts->submit($attempt);
        $this->assertSame(0, $attempt->fresh()->final_score);

        // Regrade the correct set to exactly {A,C} — now the student matches exactly.
        $this->regrade->apply($q->fresh(), [$q->options[0]->id, $q->options[2]->id], 4, QuizQuestion::TYPE_MULTIPLE, null, $this->makeAdmin(), 'fix');

        $this->assertSame(4, $attempt->fresh()->final_score);
    }

    public function test_a_marks_change_recomputes_score_and_quiz_total(): void
    {
        $quiz = Quiz::factory()->create();
        $q = QuizBuilder::for($quiz)->question(['A', 'B'], correctPositions: [1], marks: 1);
        $attempt = $this->sit($quiz, $q, pickIndex: 0);   // correct → 1
        $this->assertSame(1, $attempt->final_score);

        $this->regrade->apply($q->fresh(), [$q->options[0]->id], 5, QuizQuestion::TYPE_SINGLE, null, $this->makeAdmin(), 'bump marks');

        $fresh = $attempt->fresh();
        $this->assertSame(5, $fresh->final_score);
        $this->assertSame(5, $fresh->total_marks_snapshot);
        $this->assertSame(5, $quiz->fresh()->total_marks);
    }

    public function test_regrade_preserves_terminal_metadata(): void
    {
        $quiz = Quiz::factory()->create();
        $q = QuizBuilder::for($quiz)->question(['A', 'B'], correctPositions: [1], marks: 1);
        $attempt = $this->sit($quiz, $q, pickIndex: 1);
        $before = $attempt->only(['started_at', 'submitted_at', 'expires_at', 'time_taken_seconds', 'submission_seq', 'status']);

        $this->regrade->apply($q->fresh(), [$q->options[1]->id], 1, QuizQuestion::TYPE_SINGLE, null, $this->makeAdmin(), 'fix');

        $after = $attempt->fresh()->only(['started_at', 'submitted_at', 'expires_at', 'time_taken_seconds', 'submission_seq', 'status']);
        $this->assertEquals($before, $after);
    }

    public function test_regrade_records_a_run_with_before_after_entries(): void
    {
        $quiz = Quiz::factory()->create();
        $q = QuizBuilder::for($quiz)->question(['A', 'B'], correctPositions: [1], marks: 1);
        $attempt = $this->sit($quiz, $q, pickIndex: 1);

        $run = $this->regrade->apply($q->fresh(), [$q->options[1]->id], 1, QuizQuestion::TYPE_SINGLE, null, $this->makeAdmin(), 'fix');

        $this->assertSame(RegradeRun::STATUS_COMPLETED, $run->status);
        $this->assertSame(1, $run->attempts_affected);
        $this->assertSame(1, $run->entries()->count());
        $entry = $run->entries()->first();
        $this->assertSame(0, $entry->old_final);
        $this->assertSame(1, $entry->new_final);
        $this->assertTrue($attempt->fresh()->wasRegraded());
    }

    public function test_preview_reports_impact_without_persisting(): void
    {
        $quiz = Quiz::factory()->create();
        $q = QuizBuilder::for($quiz)->question(['A', 'B'], correctPositions: [1], marks: 1);
        $attempt = $this->sit($quiz, $q, pickIndex: 1);   // currently wrong

        $preview = $this->regrade->preview($q->fresh(), [$q->options[1]->id], 1);

        $this->assertSame(1, $preview['affected_total']);
        $this->assertSame(1, $preview['changed_count']);
        $this->assertSame(0, $preview['unchanged_count']);
        // Nothing changed on disk.
        $this->assertSame(0, $attempt->fresh()->final_score);
        $this->assertSame(0, RegradeRun::query()->count());
    }

    public function test_manual_adjustment_is_audited_and_rejects_out_of_bounds(): void
    {
        $quiz = Quiz::factory()->create();
        $q = QuizBuilder::for($quiz)->question(['A', 'B'], correctPositions: [1], marks: 5);
        $attempt = $this->sit($quiz, $q, pickIndex: 0);   // correct → calculated 5, total 5

        // +1 would push final to 6 > total 5 → refused.
        $this->expectException(AdjustmentOutOfBoundsException::class);
        $this->adjust->adjust($attempt->fresh(), 1, 'too much', $this->makeAdmin());
    }

    public function test_manual_adjustment_writes_an_audit_row(): void
    {
        $quiz = Quiz::factory()->create();
        $q = QuizBuilder::for($quiz)->question(['A', 'B'], correctPositions: [1], marks: 5);
        $attempt = $this->sit($quiz, $q, pickIndex: 1);   // wrong → calculated 0

        $this->adjust->adjust($attempt->fresh(), 3, 'partial credit', $this->makeAdmin());

        $this->assertSame(3, $attempt->fresh()->final_score);
        $this->assertDatabaseHas('score_adjustments', ['attempt_id' => $attempt->id, 'new_manual' => 3, 'new_final' => 3, 'reason' => 'partial credit']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'result.adjusted', 'auditable_id' => $attempt->id]);
    }
}
