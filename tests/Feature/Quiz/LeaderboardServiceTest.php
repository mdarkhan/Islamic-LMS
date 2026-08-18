<?php

namespace Tests\Feature\Quiz;

use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\User;
use App\Services\Quiz\LeaderboardService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The ranking algorithm is centralised here so every screen agrees; these lock in its
 * exact semantics (brief §56–57).
 */
class LeaderboardServiceTest extends TestCase
{
    use RefreshDatabase;

    private LeaderboardService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(LeaderboardService::class);
    }

    /** A quiz whose window has already ended, so its results are released. */
    private function endedQuiz(array $overrides = []): Quiz
    {
        return Quiz::factory()->create(array_merge([
            'status' => Quiz::STATUS_PUBLISHED,
            'starts_at' => now()->subDays(2),
            'ends_at' => now()->subHour(),
        ], $overrides));
    }

    private function attempt(Quiz $quiz, User $user, int $score, int $time, int $total = 100, string $status = QuizAttempt::STATUS_SUBMITTED, int $no = 1, string $kind = QuizAttempt::KIND_OFFICIAL): QuizAttempt
    {
        $attempt = QuizAttempt::factory()->create([
            'quiz_id' => $quiz->id,
            'user_id' => $user->id,
            'kind' => $kind,
            'attempt_no' => $no,
            'status' => $status,
            'started_at' => now()->subHour(),
            'submitted_at' => now(),
        ]);

        $attempt->forceFill([
            'final_score' => $score,
            'calculated_score' => $score,
            'time_taken_seconds' => $time,
            'total_marks_snapshot' => $total,
            'counts_toward_cumulative' => $kind === QuizAttempt::KIND_OFFICIAL,
        ])->save();

        return $attempt;
    }

    public function test_quiz_leaderboard_orders_by_score_then_time(): void
    {
        $quiz = $this->endedQuiz();
        $a = $this->makeStudent(['name' => 'A']);
        $b = $this->makeStudent(['name' => 'B']);
        $c = $this->makeStudent(['name' => 'C']);

        $this->attempt($quiz, $a, score: 80, time: 500);
        $this->attempt($quiz, $b, score: 90, time: 900);   // top: highest score
        $this->attempt($quiz, $c, score: 80, time: 300);   // ties A on score, faster

        $rows = $this->service->quizLeaderboard($quiz);

        $this->assertSame([$b->id, $c->id, $a->id], $rows->pluck('user_id')->all());
        $this->assertSame([1, 2, 3], $rows->pluck('rank')->all());
    }

    public function test_quiz_leaderboard_uses_competition_ranking_for_exact_ties(): void
    {
        $quiz = $this->endedQuiz();
        $top = $this->makeStudent();
        $tieA = $this->makeStudent();
        $tieB = $this->makeStudent();
        $last = $this->makeStudent();

        $this->attempt($quiz, $top, score: 100, time: 400);
        $this->attempt($quiz, $tieA, score: 80, time: 600);   // identical score+time
        $this->attempt($quiz, $tieB, score: 80, time: 600);   // → shared rank 2
        $this->attempt($quiz, $last, score: 70, time: 100);

        $rows = $this->service->quizLeaderboard($quiz)->keyBy('user_id');

        $this->assertSame(1, $rows[$top->id]['rank']);
        $this->assertSame(2, $rows[$tieA->id]['rank']);
        $this->assertSame(2, $rows[$tieB->id]['rank']);
        $this->assertSame(4, $rows[$last->id]['rank']);   // 3 is skipped
    }

    public function test_each_student_appears_once_using_their_best_attempt(): void
    {
        $quiz = $this->endedQuiz(['max_official_attempts' => 3]);
        $student = $this->makeStudent();

        $this->attempt($quiz, $student, score: 60, time: 500, no: 1);
        $this->attempt($quiz, $student, score: 95, time: 800, no: 2);   // best by score
        $this->attempt($quiz, $student, score: 95, time: 400, no: 3);   // best by time among the 95s

        $rows = $this->service->quizLeaderboard($quiz);

        $this->assertCount(1, $rows);
        $this->assertSame(95, $rows->first()['obtained']);
        $this->assertSame(400, $rows->first()['time_taken_seconds']);
    }

    public function test_practice_and_voided_never_appear(): void
    {
        $quiz = $this->endedQuiz();
        $official = $this->makeStudent();
        $practicer = $this->makeStudent();
        $voided = $this->makeStudent();

        $this->attempt($quiz, $official, score: 50, time: 500);
        $this->attempt($quiz, $practicer, score: 100, time: 100, kind: QuizAttempt::KIND_PRACTICE);
        $this->attempt($quiz, $voided, score: 100, time: 100, status: QuizAttempt::STATUS_VOIDED);

        $rows = $this->service->quizLeaderboard($quiz);

        $this->assertSame([$official->id], $rows->pluck('user_id')->all());
    }

    public function test_expired_attempts_are_ranked_on_what_they_scored(): void
    {
        $quiz = $this->endedQuiz();
        $finished = $this->makeStudent();
        $ranOut = $this->makeStudent();

        $this->attempt($quiz, $finished, score: 40, time: 500);
        $this->attempt($quiz, $ranOut, score: 70, time: 900, status: QuizAttempt::STATUS_EXPIRED);

        $rows = $this->service->quizLeaderboard($quiz);
        $this->assertSame($ranOut->id, $rows->first()['user_id']);
    }

    public function test_percentage_is_safe_when_total_is_zero(): void
    {
        $quiz = $this->endedQuiz();
        $student = $this->makeStudent();
        $this->attempt($quiz, $student, score: 0, time: 100, total: 0);

        $this->assertSame(0.0, $this->service->quizLeaderboard($quiz)->first()['percentage']);
    }

    public function test_overall_leaderboard_aggregates_best_official_attempts(): void
    {
        $q1 = $this->endedQuiz();
        $q2 = $this->endedQuiz();
        $alice = $this->makeStudent(['name' => 'Alice']);
        $bob = $this->makeStudent(['name' => 'Bob']);

        $this->attempt($q1, $alice, score: 40, time: 500, total: 50);
        $this->attempt($q2, $alice, score: 45, time: 500, total: 50);   // Alice: 85 / 100
        $this->attempt($q1, $bob, score: 50, time: 500, total: 50);
        $this->attempt($q2, $bob, score: 30, time: 500, total: 50);     // Bob: 80 / 100

        $rows = $this->service->overallLeaderboard(CarbonImmutable::now());

        $this->assertSame([$alice->id, $bob->id], $rows->pluck('user_id')->all());
        $aliceRow = $rows->firstWhere('user_id', $alice->id);
        $this->assertSame(2, $aliceRow['exams_counted']);
        $this->assertSame(85, $aliceRow['obtained']);
        $this->assertSame(100, $aliceRow['possible']);
        $this->assertSame(85.0, $aliceRow['percentage']);
        $this->assertSame(1, $aliceRow['rank']);
    }

    public function test_overall_excludes_practice_voided_and_non_counting_quizzes(): void
    {
        $counted = $this->endedQuiz();
        $excluded = $this->endedQuiz(['counts_toward_overall' => false]);
        $student = $this->makeStudent();

        $this->attempt($counted, $student, score: 30, time: 500, total: 50);
        $this->attempt($excluded, $student, score: 50, time: 500, total: 50);       // quiz excluded
        // practice on the counted quiz must not add to the total:
        $this->attempt($counted, $student, score: 50, time: 100, total: 50, no: 2, kind: QuizAttempt::KIND_PRACTICE);

        $row = $this->service->studentOverall($student, CarbonImmutable::now());

        $this->assertSame(1, $row['exams_counted']);
        $this->assertSame(30, $row['obtained']);
        $this->assertSame(50, $row['possible']);
    }

    public function test_overall_excludes_an_official_attempt_marked_non_cumulative(): void
    {
        $quiz = $this->endedQuiz();
        $student = $this->makeStudent();
        $attempt = $this->attempt($quiz, $student, score: 50, time: 100, total: 50);
        $attempt->forceFill(['counts_toward_cumulative' => false])->save();

        $this->assertNull($this->service->studentOverall($student, CarbonImmutable::now()));
    }

    public function test_overall_excludes_unreleased_quizzes(): void
    {
        $live = Quiz::factory()->create([
            'status' => Quiz::STATUS_PUBLISHED,
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addHour(),   // not ended → not released
        ]);
        $student = $this->makeStudent();
        $this->attempt($live, $student, score: 40, time: 500, total: 50);

        $this->assertTrue($this->service->overallLeaderboard(CarbonImmutable::now())->isEmpty());
    }

    public function test_student_dashboard_rank_matches_the_leaderboard(): void
    {
        $quiz = $this->endedQuiz();
        $first = $this->makeStudent();
        $second = $this->makeStudent();
        $this->attempt($quiz, $first, score: 90, time: 500, total: 100);
        $this->attempt($quiz, $second, score: 50, time: 500, total: 100);

        $board = $this->service->overallLeaderboard(CarbonImmutable::now());
        $secondOverall = $this->service->studentOverall($second, CarbonImmutable::now());

        $this->assertSame(
            $board->firstWhere('user_id', $second->id)['rank'],
            $secondOverall['rank'],
        );
    }
}
