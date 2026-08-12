<?php

namespace Tests\Feature;

use App\Models\Quiz;
use App\Models\User;
use App\Services\Quiz\QuizAttemptService;
use App\Services\Quiz\QuizScoringService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\QuizBuilder;
use Tests\TestCase;

/**
 * Reproduces the legacy scoring rule exactly: the selected set must equal the
 * correct set, with no partial credit, and custom "mega" marks applied on a match.
 */
class QuizScoringTest extends TestCase
{
    use RefreshDatabase;

    private QuizScoringService $scoring;

    private QuizAttemptService $attempts;

    protected function setUp(): void
    {
        parent::setUp();
        $this->scoring = app(QuizScoringService::class);
        $this->attempts = app(QuizAttemptService::class);
    }

    public function test_single_answer_correct_scores_full_marks(): void
    {
        $this->assertSame(1, $this->scoring->gradeSelection([10], [10], 1));
    }

    public function test_single_answer_wrong_scores_zero(): void
    {
        $this->assertSame(0, $this->scoring->gradeSelection([11], [10], 1));
    }

    public function test_multiple_answer_requires_an_exact_set_match(): void
    {
        $this->assertSame(1, $this->scoring->gradeSelection([10, 11], [11, 10], 1), 'order must not matter');
        $this->assertSame(0, $this->scoring->gradeSelection([10], [10, 11], 1), 'partial selection scores zero');
        $this->assertSame(0, $this->scoring->gradeSelection([10, 11, 12], [10, 11], 1), 'extra selection scores zero');
    }

    public function test_unanswered_question_scores_zero(): void
    {
        $this->assertSame(0, $this->scoring->gradeSelection([], [10], 1));
    }

    public function test_mega_question_awards_its_custom_marks(): void
    {
        $this->assertSame(10, $this->scoring->gradeSelection([10, 11], [10, 11], 10));
        $this->assertSame(0, $this->scoring->gradeSelection([10], [10, 11], 10));
    }

    public function test_question_with_no_correct_option_awards_nothing(): void
    {
        // Guards against "selected nothing" accidentally matching an empty key.
        $this->assertSame(0, $this->scoring->gradeSelection([], [], 5));
    }

    public function test_full_attempt_is_scored_server_side_with_mixed_question_types(): void
    {
        $quiz = Quiz::factory()->create();
        $builder = QuizBuilder::for($quiz);

        $single = $builder->question(['ক', 'খ', 'গ'], correctPositions: [2]);
        $multi = $builder->question(['ক', 'খ', 'গ', 'ঘ'], correctPositions: [1, 3]);
        $mega = $builder->question(['ক', 'খ'], correctPositions: [2], marks: 10);
        $builder->question(['ক', 'খ'], correctPositions: [1]);   // left unanswered

        $this->assertSame(13, $quiz->fresh()->total_marks);

        $user = User::factory()->withPoints(5)->create();
        $attempt = $this->attempts->startOfficial($quiz->fresh(), $user);

        $opt = fn ($q, $pos) => $q->options[$pos - 1]->id;

        $this->attempts->saveAnswer($attempt, $single->id, [$opt($single, 2)]);          // +1
        $this->attempts->saveAnswer($attempt, $multi->id, [$opt($multi, 3), $opt($multi, 1)]); // +1
        $this->attempts->saveAnswer($attempt, $mega->id, [$opt($mega, 1)]);              // wrong, +0

        $submitted = $this->attempts->submit($attempt);

        $this->assertSame(2, $submitted->calculated_score);
        $this->assertSame(2, $submitted->final_score);
        $this->assertSame(13, $submitted->total_marks_snapshot);
    }

    public function test_manual_adjustment_survives_a_rescore(): void
    {
        $quiz = Quiz::factory()->create();
        $question = QuizBuilder::for($quiz)->question(['ক', 'খ'], correctPositions: [1]);

        $user = User::factory()->withPoints(5)->create();
        $attempt = $this->attempts->startOfficial($quiz->fresh(), $user);
        $this->attempts->saveAnswer($attempt, $question->id, [$question->options[0]->id]);
        $attempt = $this->attempts->submit($attempt);

        $this->assertSame(1, $attempt->final_score);

        // An admin awards a goodwill mark.
        $attempt->forceFill(['manual_adjustment' => 2, 'final_score' => 3])->save();

        // A later regrade must not discard it.
        $rescored = $this->scoring->scoreAttempt($attempt->fresh());

        $this->assertSame(1, $rescored->calculated_score);
        $this->assertSame(2, $rescored->manual_adjustment);
        $this->assertSame(3, $rescored->final_score);
    }

    public function test_changing_the_answer_key_and_rescoring_updates_the_score(): void
    {
        $quiz = Quiz::factory()->create();
        $question = QuizBuilder::for($quiz)->question(['ক', 'খ'], correctPositions: [1]);

        $user = User::factory()->withPoints(5)->create();
        $attempt = $this->attempts->startOfficial($quiz->fresh(), $user);

        // Student picks option 2, which is currently wrong.
        $this->attempts->saveAnswer($attempt, $question->id, [$question->options[1]->id]);
        $attempt = $this->attempts->submit($attempt);
        $this->assertSame(0, $attempt->calculated_score);

        // Admin discovers the key was wrong and corrects it.
        $question->options[0]->forceFill(['is_correct' => false])->save();
        $question->options[1]->forceFill(['is_correct' => true])->save();

        $rescored = $this->scoring->scoreAttempt($attempt->fresh());

        // Works only because the raw selection was stored, not just the score.
        $this->assertSame(1, $rescored->calculated_score);
        $this->assertSame(1, $rescored->final_score);
    }
}
