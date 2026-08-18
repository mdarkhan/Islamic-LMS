<?php

namespace Tests\Feature\Student;

use App\Models\PointTransaction;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\User;
use App\Services\Points\PointService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\QuizBuilder;
use Tests\TestCase;

class PracticeModeTest extends TestCase
{
    use RefreshDatabase;

    /** A practice-enabled quiz whose official window has ended and results released. */
    private function availableQuiz(): array
    {
        $quiz = Quiz::factory()->practice()->create([
            'status' => Quiz::STATUS_PUBLISHED,
            'starts_at' => now()->subDays(2),
            'ends_at' => now()->subHour(),
        ]);
        $q1 = QuizBuilder::for($quiz)->question(['ক', 'খ', 'গ'], correctPositions: [2]);
        $q1->forceFill(['explanation' => 'কারণ ব্যাখ্যা'])->save();

        return [$quiz->fresh(), $q1];
    }

    private function withPoints(User $user, int $n = 5): User
    {
        app(PointService::class)->credit($user, $n, PointTransaction::TYPE_ADJUSTMENT, 'seed');

        return $user->fresh();
    }

    public function test_practice_cannot_open_during_a_live_official_window(): void
    {
        $quiz = Quiz::factory()->practice()->create();   // open now → key still secret
        QuizBuilder::for($quiz)->question(['ক', 'খ'], correctPositions: [1]);
        $student = $this->makeStudent();

        $this->actingAs($student)->post(route('student.practice.start', $quiz->fresh()))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame(0, QuizAttempt::query()->count());
    }

    public function test_practice_is_blocked_before_official_results_are_released(): void
    {
        // Window closed, but release deliberately deferred to the future.
        $quiz = Quiz::factory()->practice()->create([
            'status' => Quiz::STATUS_PUBLISHED,
            'starts_at' => now()->subDays(2),
            'ends_at' => now()->subHour(),
            'result_release_at' => now()->addDay(),
        ]);
        QuizBuilder::for($quiz)->question(['ক', 'খ'], correctPositions: [1]);

        $this->actingAs($this->makeStudent())->post(route('student.practice.start', $quiz->fresh()))
            ->assertRedirect()->assertSessionHas('error');
        $this->assertSame(0, QuizAttempt::query()->count());
    }

    public function test_starting_practice_costs_no_points_and_creates_a_practice_attempt(): void
    {
        [$quiz] = $this->availableQuiz();
        $student = $this->withPoints($this->makeStudent(), 3);

        $this->actingAs($student)->post(route('student.practice.start', $quiz))->assertRedirect();

        $attempt = QuizAttempt::query()->where('user_id', $student->id)->first();
        $this->assertNotNull($attempt);
        $this->assertSame(QuizAttempt::KIND_PRACTICE, $attempt->kind);
        $this->assertNull($attempt->point_transaction_id);
        $this->assertNull($attempt->expires_at, 'practice is untimed');
        $this->assertSame(3, $student->fresh()->points_balance);
    }

    public function test_repeated_practice_is_free_and_keeps_no_history(): void
    {
        [$quiz] = $this->availableQuiz();
        $student = $this->withPoints($this->makeStudent(), 3);

        $this->actingAs($student)->post(route('student.practice.start', $quiz))->assertRedirect();
        $this->actingAs($student)->post(route('student.practice.start', $quiz))->assertRedirect();

        // Practice is not retained as history: only the current attempt exists.
        $this->assertSame(1, QuizAttempt::query()->where('user_id', $student->id)->count());
        $this->assertSame(3, $student->fresh()->points_balance);
        $this->assertSame(0, PointTransaction::query()->where('type', PointTransaction::TYPE_DEDUCTION)->count());
    }

    public function test_admin_enabled_timer_gives_practice_a_deadline(): void
    {
        [$quiz] = $this->availableQuiz();
        $quiz->forceFill(['practice_timer_enabled' => true, 'duration_seconds' => 600])->save();
        $student = $this->makeStudent();

        $this->actingAs($student)->post(route('student.practice.start', $quiz->fresh()))->assertRedirect();

        $attempt = QuizAttempt::query()->where('user_id', $student->id)->first();
        $this->assertNotNull($attempt->expires_at, 'a timed practice attempt has a deadline');
    }

    public function test_practice_shows_the_score_and_key_immediately_after_submit(): void
    {
        [$quiz, $q1] = $this->availableQuiz();
        $student = $this->makeStudent();

        $this->actingAs($student)->post(route('student.practice.start', $quiz))->assertRedirect();
        $attempt = QuizAttempt::query()->where('user_id', $student->id)->first();

        $this->actingAs($student)->putJson(
            route('student.practice.answer', ['attempt' => $attempt, 'question' => $q1]),
            ['option_ids' => [$q1->options[1]->id]]
        )->assertOk();

        $this->actingAs($student)->post(route('student.practice.submit', $attempt))
            ->assertRedirect(route('student.practice.result', $attempt));

        $this->actingAs($student)->get(route('student.practice.result', $attempt))
            ->assertOk()
            ->assertSee(__('results.correct_answer'))
            ->assertSee('কারণ ব্যাখ্যা')                 // explanation shown immediately
            ->assertSee(__('practice.is_practice_note'));

        $this->assertSame(1, $attempt->fresh()->final_score);
    }

    public function test_a_student_cannot_open_another_students_practice_attempt(): void
    {
        [$quiz] = $this->availableQuiz();
        $owner = $this->makeStudent();
        $this->actingAs($owner)->post(route('student.practice.start', $quiz));
        $attempt = QuizAttempt::query()->where('user_id', $owner->id)->first();

        $intruder = $this->makeStudent();
        $this->actingAs($intruder)->get(route('student.practice.show', $attempt))->assertForbidden();
        $this->actingAs($intruder)->get(route('student.practice.result', $attempt))->assertForbidden();
        $this->actingAs($intruder)->post(route('student.practice.submit', $attempt))->assertForbidden();
    }

    public function test_the_practice_library_only_lists_available_quizzes(): void
    {
        [$available] = $this->availableQuiz();
        $available->forceFill(['title' => 'উপলব্ধ অনুশীলন কুইজ'])->save();

        $live = Quiz::factory()->practice()->create(['title' => 'চলমান অফিসিয়াল কুইজ']);   // still live → excluded
        QuizBuilder::for($live)->question(['ক', 'খ'], correctPositions: [1]);

        $this->actingAs($this->makeStudent())->get(route('student.practice.index'))
            ->assertOk()
            ->assertSee('উপলব্ধ অনুশীলন কুইজ')
            ->assertDontSee('চলমান অফিসিয়াল কুইজ');
    }
}
