<?php

namespace Tests\Feature\Student;

use App\Models\Course;
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
        $course = Course::factory()->create(['title' => 'Seerat Course']);
        $q1 = $this->endedQuiz(['course_id' => $course->id]);
        $me = $this->makeStudent(['name' => 'Overall Me', 'locale' => 'en']);
        $this->rankedAttempt($q1, $me, 70);

        $response = $this->actingAs($me)->get(route('student.leaderboards.overall'));

        $response->assertOk()
            ->assertSee('Leaderboard')
            ->assertDontSee('Overall leaderboard')
            ->assertSee('Time taken')
            ->assertDontSee('>Exams</th>', false)
            ->assertSee('Total marks')
            ->assertSee('Course')
            ->assertSee('Exam')
            ->assertSee('Seerat Course')
            ->assertSee('8 min 20 sec')
            ->assertSee('Overall Me')
            ->assertSee(__('leaderboards.you'));

        $this->assertSame(14, substr_count($response->getContent(), 'text-center align-middle'));
    }

    public function test_a_quiz_leaderboard_keeps_the_generic_heading_and_uses_the_shared_table(): void
    {
        $course = Course::factory()->create(['title' => 'Aqidah Course']);
        $quiz = $this->endedQuiz([
            'course_id' => $course->id,
            'title' => 'Changed Exam Name',
        ]);
        $me = $this->makeStudent(['name' => 'Filtered Student', 'locale' => 'en']);
        $this->rankedAttempt($quiz, $me, 75);

        $response = $this->actingAs($me)->get(route('student.leaderboards.quiz', $quiz));

        $response->assertOk()
            ->assertSee('<h1 class="font-bold text-ink truncate">Leaderboard</h1>', false)
            ->assertSee('Aqidah Course')
            ->assertSee('Changed Exam Name')
            ->assertSee('Time taken')
            ->assertSee('Obtained')
            ->assertSee('Total marks')
            ->assertSee('Percentage')
            ->assertSee('text-lg font-bold', false)
            ->assertDontSee('Ranked by marks, then by time taken. Equal performances share a rank.');

        $this->assertSame(14, substr_count($response->getContent(), 'text-center align-middle'));
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
