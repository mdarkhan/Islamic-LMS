<?php

namespace Tests\Feature\Student;

use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeaderboardPageTest extends TestCase
{
    use RefreshDatabase;

    private function endedQuiz(array $overrides = []): Quiz
    {
        return Quiz::factory()->create(array_merge([
            'status' => Quiz::STATUS_PUBLISHED,
            'starts_at' => now()->subDays(2),
            'ends_at' => now()->subHour(),
        ], $overrides));
    }

    private function rankedAttempt(Quiz $quiz, User $user, int $score): void
    {
        QuizAttempt::factory()->create([
            'quiz_id' => $quiz->id, 'user_id' => $user->id,
            'kind' => QuizAttempt::KIND_OFFICIAL, 'status' => QuizAttempt::STATUS_SUBMITTED,
            'started_at' => now()->subHour(), 'submitted_at' => now(),
        ])->forceFill(['final_score' => $score, 'time_taken_seconds' => 500, 'total_marks_snapshot' => 100])->save();
    }

    public function test_a_quiz_leaderboard_is_hidden_before_results_are_released(): void
    {
        $quiz = Quiz::factory()->create([
            'status' => Quiz::STATUS_PUBLISHED,
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addHour(),   // still live → not released
        ]);
        $this->rankedAttempt($quiz, $this->makeStudent(), 80);

        $this->actingAs($this->makeStudent())->get(route('student.leaderboards.quiz', $quiz))->assertNotFound();
    }

    public function test_a_quiz_leaderboard_is_hidden_when_the_admin_disables_visibility(): void
    {
        $quiz = $this->endedQuiz(['leaderboard_visible' => false]);
        $this->rankedAttempt($quiz, $this->makeStudent(), 80);

        $this->actingAs($this->makeStudent())->get(route('student.leaderboards.quiz', $quiz))->assertNotFound();
    }

    public function test_a_released_visible_quiz_leaderboard_ranks_students(): void
    {
        $quiz = $this->endedQuiz();
        $top = $this->makeStudent(['name' => 'Top Scorer']);
        $me = $this->makeStudent(['name' => 'Me Myself']);
        $this->rankedAttempt($quiz, $top, 95);
        $this->rankedAttempt($quiz, $me, 60);

        $this->actingAs($me)->get(route('student.leaderboards.quiz', $quiz))
            ->assertOk()
            ->assertSee('Top Scorer')
            ->assertSee('Me Myself')
            ->assertSee(__('leaderboards.you'));
    }

    public function test_the_overall_leaderboard_renders_and_marks_the_current_student(): void
    {
        $q1 = $this->endedQuiz();
        $me = $this->makeStudent(['name' => 'Overall Me']);
        $this->rankedAttempt($q1, $me, 70);

        $this->actingAs($me)->get(route('student.leaderboards.overall'))
            ->assertOk()
            ->assertSee('Overall Me')
            ->assertSee(__('leaderboards.you'));
    }

    public function test_privacy_the_quiz_leaderboard_never_exposes_contact_details(): void
    {
        $quiz = $this->endedQuiz();
        $student = $this->makeStudent(['name' => 'Private Person', 'email' => 'secret@example.test', 'phone' => '01700000000', 'guardian_name' => 'Guardian X']);
        $this->rankedAttempt($quiz, $student, 80);

        $this->actingAs($student)->get(route('student.leaderboards.quiz', $quiz))
            ->assertOk()
            ->assertSee('Private Person')
            ->assertDontSee('secret@example.test')
            ->assertDontSee('01700000000')
            ->assertDontSee('Guardian X');
    }
}
