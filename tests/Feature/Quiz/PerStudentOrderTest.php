<?php

namespace Tests\Feature\Quiz;

use App\Models\PointTransaction;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\User;
use App\Services\Points\PointService;
use App\Services\Quiz\QuizAttemptService;
use App\Support\ExamAttemptPresenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\QuizBuilder;
use Tests\TestCase;

class PerStudentOrderTest extends TestCase
{
    use RefreshDatabase;

    private function quiz(bool $shuffle): Quiz
    {
        $quiz = Quiz::factory()->create(['shuffle_per_student' => $shuffle]);
        $b = QuizBuilder::for($quiz);
        for ($i = 1; $i <= 10; $i++) {
            $b->question(["ক$i", "খ$i", "গ$i", "ঘ$i"], correctPositions: [1]);
        }

        return $quiz->fresh();
    }

    private function attemptFor(Quiz $quiz, string $kind = 'official'): QuizAttempt
    {
        $user = $this->makeStudent();
        app(PointService::class)->credit($user, 5, PointTransaction::TYPE_ADJUSTMENT, 'seed');
        $svc = app(QuizAttemptService::class);
        $user = $user->fresh();

        return $kind === 'official' ? $svc->startOfficial($quiz, $user) : $svc->startPractice($quiz, $user);
    }

    private function questionIds(QuizAttempt $a): array
    {
        return array_column(ExamAttemptPresenter::questions($a), 'id');
    }

    public function test_off_by_default_everyone_sees_the_authored_order(): void
    {
        $quiz = $this->quiz(false);
        $canonical = $quiz->questions()->pluck('id')->all();

        $this->assertSame($canonical, $this->questionIds($this->attemptFor($quiz)));
        $this->assertSame($canonical, $this->questionIds($this->attemptFor($quiz)));
    }

    public function test_when_on_orders_differ_between_students_but_never_within_one_attempt(): void
    {
        $quiz = $this->quiz(true);
        $canonical = $quiz->questions()->pluck('id')->all();

        $orders = [];
        for ($i = 0; $i < 4; $i++) {
            $a = $this->attemptFor($quiz);
            $first = $this->questionIds($a);
            $this->assertSame($first, $this->questionIds($a), 'stable across reloads');
            $this->assertEqualsCanonicalizing($canonical, $first, 'same questions, only reordered');
            $orders[] = implode(',', $first);
        }

        $this->assertGreaterThan(1, count(array_unique($orders)), 'students get different orders');
    }

    public function test_options_are_reordered_too_and_the_payload_still_carries_no_answer_key(): void
    {
        $quiz = $this->quiz(true);
        $canonicalOptions = $quiz->questions->first()->options()->orderBy('sort_order')->pluck('id')->all();

        $sawDifferent = false;
        for ($i = 0; $i < 6; $i++) {
            $payload = ExamAttemptPresenter::questions($this->attemptFor($quiz));
            foreach ($payload as $q) {
                $this->assertSame(['id', 'body', 'options'], array_keys($q));
                foreach ($q['options'] as $o) {
                    $this->assertSame(['id', 'body'], array_keys($o));
                }
            }
            $first = collect($payload)->firstWhere('id', $quiz->questions->first()->id);
            $ids = array_column($first['options'], 'id');
            $this->assertEqualsCanonicalizing($canonicalOptions, $ids);
            $sawDifferent = $sawDifferent || $ids !== $canonicalOptions;
        }

        $this->assertTrue($sawDifferent);
    }

    public function test_scoring_is_by_option_id_so_order_never_changes_the_score(): void
    {
        $quiz = $this->quiz(true);
        $a = $this->attemptFor($quiz);
        $user = User::find($a->user_id);

        foreach ($quiz->questions()->with('options')->get() as $q) {
            $correct = $q->options->firstWhere('is_correct', true);
            $this->actingAs($user)->putJson(route('student.attempts.answer', ['attempt' => $a, 'question' => $q]), ['option_ids' => [$correct->id]])->assertOk();
        }
        $this->actingAs($user)->post(route('student.attempts.submit', $a))->assertRedirect();

        $this->assertSame(10, $a->fresh()->final_score);
    }

    public function test_practice_attempts_are_never_shuffled(): void
    {
        $quiz = $this->quiz(true);
        $canonical = $quiz->questions()->pluck('id')->all();
        $quiz->forceFill(['practice_enabled' => true])->save();

        $practice = new QuizAttempt(['quiz_id' => $quiz->id, 'kind' => QuizAttempt::KIND_PRACTICE]);
        $practice->id = 12345;

        $this->assertSame($canonical, $this->questionIds($practice));
    }

    public function test_the_admin_can_switch_it_on_until_official_attempts_exist(): void
    {
        $admin = $this->makeAdmin();
        $quiz = $this->quiz(false);
        $payload = fn (bool $on) => array_filter([
            'title' => $quiz->title, 'status' => $quiz->status, 'point_cost' => $quiz->point_cost,
            'max_official_attempts' => $quiz->max_official_attempts, 'duration_minutes' => 30,
            'shuffle_per_student' => $on ? '1' : null,
        ], fn ($v) => $v !== null);

        $this->actingAs($admin)->put(route('admin.quizzes.update', $quiz), $payload(true))->assertSessionHasNoErrors();
        $this->assertTrue($quiz->fresh()->shuffle_per_student);

        $this->attemptFor($quiz->fresh());

        $this->actingAs($admin)->put(route('admin.quizzes.update', $quiz), $payload(false));
        $this->assertTrue($quiz->fresh()->shuffle_per_student, 'frozen once official attempts exist');
    }
}
