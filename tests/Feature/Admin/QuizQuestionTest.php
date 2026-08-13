<?php

namespace Tests\Feature\Admin;

use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\QuizQuestion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\QuizBuilder;
use Tests\TestCase;

class QuizQuestionTest extends TestCase
{
    use RefreshDatabase;

    private function opts(array $bodies, array $correct): array
    {
        $out = [];
        foreach ($bodies as $i => $body) {
            $out[$i] = ['body' => $body];
            if (in_array($i + 1, $correct, true)) {
                $out[$i]['correct'] = '1';
            }
        }

        return $out;
    }

    public function test_single_choice_requires_exactly_one_correct_option(): void
    {
        $admin = $this->makeAdmin();
        $quiz = Quiz::factory()->draft()->create();

        // Two correct on a single-choice question → rejected.
        $this->actingAs($admin)->from(route('admin.quizzes.questions.create', $quiz))
            ->post(route('admin.quizzes.questions.store', $quiz), [
                'type' => 'single', 'body' => 'প্রশ্ন', 'marks' => 1,
                'options' => $this->opts(['ক', 'খ', 'গ'], [1, 2]),
            ])->assertSessionHasErrors('options');

        $this->assertSame(0, $quiz->questions()->count());
    }

    public function test_single_choice_with_one_correct_is_stored(): void
    {
        $admin = $this->makeAdmin();
        $quiz = Quiz::factory()->draft()->create();

        $this->actingAs($admin)->post(route('admin.quizzes.questions.store', $quiz), [
            'type' => 'single', 'body' => 'রাজধানী?', 'marks' => 2, 'is_active' => '1',
            'options' => $this->opts(['ঢাকা', 'চট্টগ্রাম'], [1]),
        ])->assertRedirect(route('admin.quizzes.edit', $quiz));

        $question = $quiz->questions()->firstOrFail();
        $this->assertSame('single', $question->type);
        $this->assertTrue($question->is_active);
        $this->assertSame([$question->options[0]->id], $question->correctOptionIds());
        $this->assertSame(2, $quiz->fresh()->total_marks);
    }

    public function test_multiple_choice_accepts_several_correct_options(): void
    {
        $admin = $this->makeAdmin();
        $quiz = Quiz::factory()->draft()->create();

        $this->actingAs($admin)->post(route('admin.quizzes.questions.store', $quiz), [
            'type' => 'multiple', 'body' => 'কোনগুলো সঠিক?', 'marks' => 5,
            'options' => $this->opts(['ক', 'খ', 'গ', 'ঘ'], [1, 3]),
        ])->assertRedirect();

        $question = $quiz->questions()->firstOrFail();
        $this->assertSame('multiple', $question->type);
        $this->assertCount(2, $question->correctOptionIds());
    }

    public function test_multiple_choice_with_a_single_correct_stays_multiple(): void
    {
        $admin = $this->makeAdmin();
        $quiz = Quiz::factory()->draft()->create();

        $this->actingAs($admin)->post(route('admin.quizzes.questions.store', $quiz), [
            'type' => 'multiple', 'body' => 'প্রশ্ন', 'marks' => 1,
            'options' => $this->opts(['ক', 'খ', 'গ'], [2]),
        ])->assertRedirect();

        // The explicit type is preserved, never inferred from the correct count.
        $this->assertSame('multiple', $quiz->questions()->first()->type);
    }

    public function test_at_least_two_options_are_required(): void
    {
        $admin = $this->makeAdmin();
        $quiz = Quiz::factory()->draft()->create();

        $this->actingAs($admin)->from(route('admin.quizzes.questions.create', $quiz))
            ->post(route('admin.quizzes.questions.store', $quiz), [
                'type' => 'single', 'body' => 'প্রশ্ন', 'marks' => 1,
                'options' => $this->opts(['ক'], [1]),
            ])->assertSessionHasErrors('options');
    }

    public function test_at_most_twelve_options_are_allowed(): void
    {
        $admin = $this->makeAdmin();
        $quiz = Quiz::factory()->draft()->create();

        $thirteen = array_map(fn ($n) => "অপশন {$n}", range(1, 13));

        $this->actingAs($admin)->from(route('admin.quizzes.questions.create', $quiz))
            ->post(route('admin.quizzes.questions.store', $quiz), [
                'type' => 'single', 'body' => 'প্রশ্ন', 'marks' => 1,
                'options' => $this->opts($thirteen, [1]),
            ])->assertSessionHasErrors('options');
    }

    public function test_at_least_one_correct_option_is_required(): void
    {
        $admin = $this->makeAdmin();
        $quiz = Quiz::factory()->draft()->create();

        $this->actingAs($admin)->from(route('admin.quizzes.questions.create', $quiz))
            ->post(route('admin.quizzes.questions.store', $quiz), [
                'type' => 'single', 'body' => 'প্রশ্ন', 'marks' => 1,
                'options' => $this->opts(['ক', 'খ'], []),
            ])->assertSessionHasErrors('options');
    }

    public function test_total_marks_ignores_inactive_questions(): void
    {
        $admin = $this->makeAdmin();
        $quiz = Quiz::factory()->draft()->create();

        $this->actingAs($admin)->post(route('admin.quizzes.questions.store', $quiz), [
            'type' => 'single', 'body' => 'সক্রিয়', 'marks' => 4, 'is_active' => '1',
            'options' => $this->opts(['ক', 'খ'], [1]),
        ]);
        $this->actingAs($admin)->post(route('admin.quizzes.questions.store', $quiz), [
            'type' => 'single', 'body' => 'নিষ্ক্রিয়', 'marks' => 10,   // is_active omitted → false
            'options' => $this->opts(['ক', 'খ'], [1]),
        ]);

        $this->assertSame(4, $quiz->fresh()->total_marks);
    }

    public function test_questions_can_be_reordered(): void
    {
        $admin = $this->makeAdmin();
        $quiz = Quiz::factory()->draft()->create();
        $q1 = QuizBuilder::for($quiz)->question(['ক', 'খ'], [1]);
        $q2 = QuizBuilder::for($quiz)->question(['ক', 'খ'], [1]);
        $q3 = QuizBuilder::for($quiz)->question(['ক', 'খ'], [1]);

        $this->actingAs($admin)->put(route('admin.quizzes.questions.reorder', $quiz), [
            'order' => [$q3->id, $q1->id, $q2->id],
        ])->assertRedirect();

        $this->assertSame([$q3->id, $q1->id, $q2->id], $quiz->questions()->pluck('id')->all());
    }

    public function test_deleting_a_question_recalculates_total_marks(): void
    {
        $admin = $this->makeAdmin();
        $quiz = Quiz::factory()->draft()->create();
        $q1 = QuizBuilder::for($quiz)->question(['ক', 'খ'], [1], marks: 3);
        QuizBuilder::for($quiz)->question(['ক', 'খ'], [1], marks: 5);
        $this->assertSame(8, $quiz->fresh()->total_marks);

        $this->actingAs($admin)->delete(route('admin.quizzes.questions.destroy', [$quiz, $q1]))->assertRedirect();

        $this->assertSame(5, $quiz->fresh()->total_marks);
    }

    // ── Scoring lock once official attempts exist (Part 9) ───────────────────────

    private function lockedQuiz(): Quiz
    {
        $quiz = Quiz::factory()->create();
        QuizBuilder::for($quiz)->question(['ক', 'খ'], [1]);
        QuizAttempt::factory()->for($quiz)->for($this->makeStudent())->create(['kind' => 'official', 'status' => 'submitted']);

        return $quiz;
    }

    public function test_new_questions_are_blocked_once_official_attempts_exist(): void
    {
        $admin = $this->makeAdmin();
        $quiz = $this->lockedQuiz();

        $this->actingAs($admin)->post(route('admin.quizzes.questions.store', $quiz), [
            'type' => 'single', 'body' => 'নতুন', 'marks' => 1,
            'options' => $this->opts(['ক', 'খ'], [1]),
        ])->assertSessionHas('error');

        $this->assertSame(1, $quiz->questions()->count(), 'no question added while locked');
    }

    public function test_editing_and_deleting_questions_is_blocked_while_locked(): void
    {
        $admin = $this->makeAdmin();
        $quiz = $this->lockedQuiz();
        $question = $quiz->questions()->first();

        $this->actingAs($admin)->get(route('admin.quizzes.questions.edit', [$quiz, $question]))
            ->assertRedirect(route('admin.quizzes.edit', $quiz));

        $this->actingAs($admin)->delete(route('admin.quizzes.questions.destroy', [$quiz, $question]))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('quiz_questions', ['id' => $question->id]);
    }
}
