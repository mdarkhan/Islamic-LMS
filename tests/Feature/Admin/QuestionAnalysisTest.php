<?php

namespace Tests\Feature\Admin;

use App\Models\PointTransaction;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Services\Points\PointService;
use App\Services\Quiz\QuestionAnalysisService;
use App\Services\Quiz\QuizAttemptService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\QuizBuilder;
use Tests\TestCase;

class QuestionAnalysisTest extends TestCase
{
    use RefreshDatabase;

    private Quiz $quiz;

    private $q1;

    private $q2;

    protected function setUp(): void
    {
        parent::setUp();
        $this->quiz = Quiz::factory()->create();
        $b = QuizBuilder::for($this->quiz);
        $this->q1 = $b->question(['ক', 'খ', 'গ'], correctPositions: [1]);   // easy
        $this->q2 = $b->question(['ক', 'খ', 'গ'], correctPositions: [1]);   // hard
        $this->quiz = $this->quiz->fresh();
    }

    /** One finished official attempt; $picks maps question => option position (0-based) or null to skip. */
    private function attempt(array $picks): QuizAttempt
    {
        $user = $this->makeStudent();
        app(PointService::class)->credit($user, 5, PointTransaction::TYPE_ADJUSTMENT, 'seed');
        $svc = app(QuizAttemptService::class);
        $attempt = $svc->startOfficial($this->quiz, $user->fresh());

        foreach ([[$this->q1, $picks[0]], [$this->q2, $picks[1]]] as [$q, $pos]) {
            if ($pos !== null) {
                $svc->saveAnswer($attempt, $q->id, [$q->options[$pos]->id]);
            }
        }

        return $svc->submit($attempt);
    }

    public function test_it_reports_correct_wrong_and_skipped_per_question_and_option_picks(): void
    {
        $this->attempt([0, 0]);      // both right
        $this->attempt([0, 1]);      // q2 wrong → option 2
        $this->attempt([0, 1]);      // q2 wrong → option 2
        $this->attempt([0, null]);   // q2 skipped

        $r = app(QuestionAnalysisService::class)->forQuiz($this->quiz);

        $this->assertSame(4, $r['students']);
        [$a, $b] = $r['questions'];

        $this->assertSame([4, 0, 0, 100, 'easy'], [$a['correct'], $a['wrong'], $a['skipped'], $a['percent'], $a['level']]);
        $this->assertSame([1, 2, 1, 25, 'hard'], [$b['correct'], $b['wrong'], $b['skipped'], $b['percent'], $b['level']]);

        $picked = array_column($b['options'], 'picked');
        $this->assertSame([1, 2, 0], $picked);
        $this->assertTrue($b['options'][0]['is_correct']);
    }

    public function test_only_each_students_best_finished_official_attempt_counts(): void
    {
        $first = $this->attempt([1, 1]);   // wrong, wrong
        // The same student retries and does better; that best attempt is the only one counted.
        $best = QuizAttempt::factory()->create([
            'quiz_id' => $this->quiz->id, 'user_id' => $first->user_id, 'attempt_no' => 2, 'final_score' => 2,
            'answer_details_available' => true,
        ]);

        $r = app(QuestionAnalysisService::class)->forQuiz($this->quiz);
        $this->assertSame(1, $r['students']);
        $this->assertSame($best->final_score, 2);
    }

    public function test_practice_in_progress_and_legacy_attempts_are_ignored(): void
    {
        $this->attempt([0, 0]);

        QuizAttempt::factory()->create(['quiz_id' => $this->quiz->id, 'kind' => QuizAttempt::KIND_PRACTICE]);
        QuizAttempt::factory()->create(['quiz_id' => $this->quiz->id, 'status' => QuizAttempt::STATUS_IN_PROGRESS]);
        QuizAttempt::factory()->create(['quiz_id' => $this->quiz->id, 'is_legacy_import' => true, 'answer_details_available' => false]);

        $this->assertSame(1, app(QuestionAnalysisService::class)->forQuiz($this->quiz)['students']);
    }

    public function test_the_page_needs_the_results_view_permission_and_renders(): void
    {
        $this->attempt([0, 1]);
        $admin = $this->makeAdmin();

        $this->actingAs($admin)->get(route('admin.quizzes.analysis', $this->quiz))
            ->assertOk()
            ->assertSee(__('results_admin.analysis_heading'))
            ->assertSee(__('results_admin.analysis_level_hard'))
            ->assertSee("'order: ' + (sort === 'hardest'", false);

        $student = $this->makeStudent();
        $this->actingAs($student)->get(route('admin.quizzes.analysis', $this->quiz))->assertForbidden();

        $admin->roles()->first()->permissions()->detach(
            \App\Models\Permission::query()->where('name', 'results.view')->pluck('id')
        );
        $this->actingAs($admin->fresh())->get(route('admin.quizzes.analysis', $this->quiz))->assertForbidden();
    }

    public function test_an_empty_quiz_renders_a_friendly_message(): void
    {
        $this->actingAs($this->makeAdmin())->get(route('admin.quizzes.analysis', $this->quiz))
            ->assertOk()->assertSee(__('results_admin.analysis_empty'));
    }
}
