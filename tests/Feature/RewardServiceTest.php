<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\PointTransaction;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\RewardGrant;
use App\Models\User;
use App\Services\Rewards\RewardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RewardServiceTest extends TestCase
{
    use RefreshDatabase;

    private function rewards(): RewardService
    {
        return app(RewardService::class);
    }

    /** A released, bonus-enabled quiz with full marks = 100. */
    private function bonusQuiz(array $overrides = []): Quiz
    {
        $quiz = Quiz::factory()->create(array_merge([
            'status' => Quiz::STATUS_PUBLISHED,
            'starts_at' => now()->subDays(2),
            'ends_at' => now()->subHour(),
            'bonus_enabled' => true,
            'bonus_threshold_type' => Quiz::BONUS_THRESHOLD_FULL,
            'bonus_points' => 25,
        ], $overrides));

        $quiz->forceFill(['total_marks' => 100])->save();

        return $quiz->fresh();
    }

    private function attempt(Quiz $quiz, User $user, int $score): void
    {
        QuizAttempt::factory()->create([
            'quiz_id' => $quiz->id, 'user_id' => $user->id,
            'kind' => QuizAttempt::KIND_OFFICIAL, 'status' => QuizAttempt::STATUS_SUBMITTED,
            'started_at' => now()->subHour(), 'submitted_at' => now(),
        ])->forceFill([
            'final_score' => $score, 'time_taken_seconds' => 300,
            'total_marks_snapshot' => 100, 'counts_toward_cumulative' => true,
        ])->save();
    }

    public function test_full_marks_earns_the_quiz_bonus_exactly_once(): void
    {
        $quiz = $this->bonusQuiz();
        $ace = $this->makeStudent();
        $below = $this->makeStudent();
        $this->attempt($quiz, $ace, 100);   // full marks
        $this->attempt($quiz, $below, 80);   // below threshold

        $this->assertSame(1, $this->rewards()->awardQuizAchievements());
        $this->assertSame(25, $ace->fresh()->points_balance);
        $this->assertSame(0, $below->fresh()->points_balance);

        // Idempotent — a second sweep grants nothing more.
        $this->assertSame(0, $this->rewards()->awardQuizAchievements());
        $this->assertSame(25, $ace->fresh()->points_balance);
        $this->assertSame(1, RewardGrant::query()->where('user_id', $ace->id)->count());

        // Ledger invariant: cached balance equals the sum of the ledger.
        $this->assertSame(25, (int) PointTransaction::query()->where('user_id', $ace->id)->sum('amount'));
    }

    public function test_a_custom_mark_threshold_is_respected(): void
    {
        $quiz = $this->bonusQuiz([
            'bonus_threshold_type' => Quiz::BONUS_THRESHOLD_MARKS,
            'bonus_threshold_marks' => 80, 'bonus_points' => 10,
        ]);
        $reaches = $this->makeStudent();
        $misses = $this->makeStudent();
        $this->attempt($quiz, $reaches, 85);
        $this->attempt($quiz, $misses, 79);

        $this->rewards()->awardQuizAchievements();

        $this->assertSame(10, $reaches->fresh()->points_balance);
        $this->assertSame(0, $misses->fresh()->points_balance);
    }

    public function test_no_bonus_before_the_results_are_released(): void
    {
        $quiz = $this->bonusQuiz(['starts_at' => now()->subHour(), 'ends_at' => now()->addHour()]);   // still live
        $ace = $this->makeStudent();
        $this->attempt($quiz, $ace, 100);

        $this->assertSame(0, $this->rewards()->awardQuizAchievements());
        $this->assertSame(0, $ace->fresh()->points_balance);
    }

    public function test_course_toppers_are_awarded_by_position_and_only_once(): void
    {
        $course = Course::factory()->create([
            'topper_rewards' => [['position' => 1, 'points' => 100], ['position' => 2, 'points' => 50]],
        ]);
        $quiz = $this->bonusQuiz(['course_id' => $course->id, 'bonus_enabled' => false]);

        $first = $this->makeStudent();
        $second = $this->makeStudent();
        $third = $this->makeStudent();
        $this->attempt($quiz, $first, 100);
        $this->attempt($quiz, $second, 80);
        $this->attempt($quiz, $third, 60);

        $this->assertSame(2, $this->rewards()->awardCourseToppers($course->fresh()));
        $this->assertSame(100, $first->fresh()->points_balance);
        $this->assertSame(50, $second->fresh()->points_balance);
        $this->assertSame(0, $third->fresh()->points_balance);

        // Idempotent — the same toppers are never awarded twice for this course.
        $this->assertSame(0, $this->rewards()->awardCourseToppers($course->fresh()));
        $this->assertSame(100, $first->fresh()->points_balance);
    }

    public function test_the_student_sees_the_congrats_screen_then_can_dismiss_it(): void
    {
        $quiz = $this->bonusQuiz();
        $ace = $this->makeStudent();
        $this->attempt($quiz, $ace, 100);
        $this->rewards()->awardQuizAchievements();

        // Shown after login on a student page…
        $this->actingAs($ace)->get(route('student.dashboard'))
            ->assertOk()->assertSee(__('rewards.congrats_title'));

        // …dismissing marks it seen, and it does not return.
        $this->actingAs($ace)->post(route('student.rewards.seen'))->assertRedirect();
        $this->assertNotNull(RewardGrant::query()->where('user_id', $ace->id)->first()->seen_at);
        $this->actingAs($ace)->get(route('student.dashboard'))
            ->assertOk()->assertDontSee(__('rewards.congrats_title'));
    }
}
