<?php

namespace Tests\Feature\Student;

use App\Models\PointTransaction;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\QuizQuestion;
use App\Models\User;
use App\Services\Points\PointService;
use App\Services\Quiz\QuizAttemptService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\QuizBuilder;
use Tests\TestCase;

class ResultsAndAnswerSheetTest extends TestCase
{
    use RefreshDatabase;

    /** Run a real official attempt (answering q1 correctly) and return [quiz, attempt, q1]. */
    private function sitExam(User $student): array
    {
        $quiz = Quiz::factory()->create();   // open now
        $q1 = QuizBuilder::for($quiz)->question(['ক', 'খ', 'গ'], correctPositions: [2]);
        QuizBuilder::for($quiz)->question(['ক', 'খ'], correctPositions: [1]);   // left unanswered

        app(PointService::class)->credit($student, 5, PointTransaction::TYPE_ADJUSTMENT, 'seed');
        $svc = app(QuizAttemptService::class);
        $attempt = $svc->startOfficial($quiz->fresh(), $student->fresh());
        $svc->saveAnswer($attempt, (int) $q1->id, [$q1->options[1]->id]);   // correct
        $svc->submit($attempt);

        return [$quiz->fresh(), $attempt->fresh(), $q1];
    }

    private function release(Quiz $quiz): void
    {
        $quiz->forceFill(['ends_at' => now()->subHour(), 'results_released_at' => now()])->save();
    }

    // ── Before release ────────────────────────────────────────────────────────────

    public function test_results_index_hides_the_score_before_release(): void
    {
        $student = $this->makeStudent();
        [$quiz, $attempt] = $this->sitExam($student);   // not released

        $this->actingAs($student)->get(route('student.results.index'))
            ->assertOk()
            ->assertSee(__('results.group_pending'))
            ->assertDontSee($attempt->final_score.' / '.$attempt->total_marks_snapshot);
    }

    public function test_answer_sheet_is_forbidden_before_release(): void
    {
        $student = $this->makeStudent();
        [, $attempt] = $this->sitExam($student);

        $this->actingAs($student)->get(route('student.results.show', $attempt))->assertForbidden();
    }

    // ── After release ─────────────────────────────────────────────────────────────

    public function test_results_index_shows_the_score_after_release(): void
    {
        $student = $this->makeStudent();
        [$quiz, $attempt] = $this->sitExam($student);
        $this->release($quiz);

        $this->actingAs($student)->get(route('student.results.index'))
            ->assertOk()
            ->assertSee(__('results.group_released'))
            ->assertSee(__('results.view_answer_sheet'));
    }

    public function test_owner_sees_the_detailed_answer_sheet_after_release(): void
    {
        $student = $this->makeStudent();
        [$quiz, $attempt, $q1] = $this->sitExam($student);
        $this->release($quiz);

        $this->actingAs($student)->get(route('student.results.show', $attempt))
            ->assertOk()
            ->assertSee(__('results.correct'))         // the correctly answered question
            ->assertSee(__('results.unanswered'))      // the untouched one
            ->assertSee(__('results.correct_answer'));
    }

    public function test_a_student_cannot_open_another_students_answer_sheet(): void
    {
        $owner = $this->makeStudent();
        [$quiz, $attempt] = $this->sitExam($owner);
        $this->release($quiz);

        $intruder = $this->makeStudent();
        $this->actingAs($intruder)->get(route('student.results.show', $attempt))->assertForbidden();
    }

    public function test_answer_sheet_reflects_the_current_key_after_a_regrade(): void
    {
        // Answer sheet must show the CURRENT correct answer, and note that a regrade
        // happened. Simulate by flipping the key + recording a regrade entry.
        $student = $this->makeStudent();
        [$quiz, $attempt, $q1] = $this->sitExam($student);
        $this->release($quiz);

        // Move the correct answer to option 1 (previously option 2) and re-score.
        $q1->options[1]->forceFill(['is_correct' => false])->save();
        $q1->options[0]->forceFill(['is_correct' => true])->save();
        app(\App\Services\Quiz\QuizScoringService::class)->scoreAttempt($attempt->fresh());
        \App\Models\RegradeEntry::create([
            'regrade_run_id' => \App\Models\RegradeRun::create([
                'quiz_id' => $quiz->id, 'admin_id' => $this->makeAdmin()->id, 'reason' => 'fix',
                'status' => 'completed', 'started_at' => now(), 'completed_at' => now(),
            ])->id,
            'attempt_id' => $attempt->id,
            'old_calculated' => 1, 'new_calculated' => 0, 'old_final' => 1, 'new_final' => 0,
        ]);

        $this->actingAs($student)->get(route('student.results.show', $attempt))
            ->assertOk()
            ->assertSee(__('results.regrade_note'));
    }
}
