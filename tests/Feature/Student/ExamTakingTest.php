<?php

namespace Tests\Feature\Student;

use App\Models\PointTransaction;
use App\Models\Quiz;
use App\Models\QuizAnswerOption;
use App\Models\QuizAttempt;
use App\Models\User;
use App\Support\ExamAttemptPresenter;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\QuizBuilder;
use Tests\TestCase;

/**
 * HTTP-level coverage of the Phase 7 live exam flow. These assert the security
 * guarantees end to end (through the real routes, middleware and controller), on top
 * of the service-level guarantees in QuizAttemptTest.
 */
class ExamTakingTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: Quiz, 1: \App\Models\QuizQuestion, 2: \App\Models\QuizQuestion} */
    private function openQuiz(array $state = []): array
    {
        $quiz = Quiz::factory()->create($state);
        $q1 = QuizBuilder::for($quiz)->question(['ক', 'খ', 'গ'], correctPositions: [2]);
        $q2 = QuizBuilder::for($quiz)->question(['ক', 'খ'], correctPositions: [1]);

        return [$quiz->fresh(), $q1, $q2];
    }

    // ── Start / resume ──────────────────────────────────────────────────────────

    public function test_starting_an_exam_debits_one_point_and_opens_the_live_screen(): void
    {
        [$quiz] = $this->openQuiz();
        $user = $this->makeStudent();
        app(\App\Services\Points\PointService::class)->credit($user, 5, PointTransaction::TYPE_ADJUSTMENT, 'seed');

        $res = $this->actingAs($user->fresh())->post(route('student.exams.start', $quiz));

        $attempt = QuizAttempt::query()->where('user_id', $user->id)->first();
        $this->assertNotNull($attempt);
        $res->assertRedirect(route('student.attempts.show', $attempt));
        $this->assertSame(4, $user->fresh()->points_balance);
        $this->assertSame(QuizAttempt::STATUS_IN_PROGRESS, $attempt->status);
    }

    public function test_resuming_does_not_debit_a_second_point(): void
    {
        [$quiz] = $this->openQuiz();
        $user = $this->makeStudent();
        app(\App\Services\Points\PointService::class)->credit($user, 5, PointTransaction::TYPE_ADJUSTMENT, 'seed');
        $user = $user->fresh();

        $this->actingAs($user)->post(route('student.exams.start', $quiz))->assertRedirect();
        $this->actingAs($user)->post(route('student.exams.start', $quiz))->assertRedirect();

        $this->assertSame(1, QuizAttempt::query()->where('user_id', $user->id)->count());
        $this->assertSame(4, $user->fresh()->points_balance);
    }

    public function test_start_is_blocked_with_a_message_when_points_are_insufficient(): void
    {
        [$quiz] = $this->openQuiz();
        $user = $this->makeStudent();   // zero balance

        $this->actingAs($user)->post(route('student.exams.start', $quiz))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame(0, QuizAttempt::query()->count());
    }

    public function test_start_is_blocked_once_the_window_has_closed(): void
    {
        [$quiz] = $this->openQuiz();
        $quiz->forceFill(['starts_at' => now()->subDays(2), 'ends_at' => now()->subDay()])->save();
        $user = $this->makeStudent();
        app(\App\Services\Points\PointService::class)->credit($user, 5, PointTransaction::TYPE_ADJUSTMENT, 'seed');

        $this->actingAs($user->fresh())->post(route('student.exams.start', $quiz->fresh()))
            ->assertRedirect()->assertSessionHas('error');

        $this->assertSame(0, QuizAttempt::query()->count());
    }

    // ── Secure delivery ─────────────────────────────────────────────────────────

    public function test_the_live_screen_shows_options_but_never_the_answer_key(): void
    {
        $quiz = Quiz::factory()->create();
        QuizBuilder::for($quiz)->question(['অপশন-আলফা', 'অপশন-বিটা', 'অপশন-গামা'], correctPositions: [2]);
        $user = $this->startedStudent($quiz->fresh());
        $attempt = QuizAttempt::query()->where('user_id', $user->id)->first();

        $res = $this->actingAs($user)->get(route('student.attempts.show', $attempt));

        $res->assertOk()
            ->assertSee('অপশন-আলফা')->assertSee('অপশন-বিটা')->assertSee('অপশন-গামা')  // options delivered
            ->assertDontSee('is_correct')                                                 // …but never the key
            ->assertDontSee('explanation');
    }

    public function test_the_live_screen_does_not_show_answer_selection_hints(): void
    {
        $quiz = Quiz::factory()->create();
        $builder = QuizBuilder::for($quiz);
        $builder->question(['এক', 'দুই'], correctPositions: [1]);
        $builder->question(['এক', 'দুই', 'তিন'], correctPositions: [1, 2]);
        $user = $this->startedStudent($quiz->fresh());
        $attempt = QuizAttempt::query()->where('user_id', $user->id)->first();

        foreach (ExamAttemptPresenter::questions($attempt) as $question) {
            $this->assertArrayNotHasKey('type', $question);
        }

        $this->actingAs($user)
            ->get(route('student.attempts.show', $attempt))
            ->assertOk()
            ->assertSee('sel.indexOf(oid) !== -1 ? sel.filter((x) => x !== oid) : sel.concat([oid])', false)
            ->assertDontSee('question.type', false)
            ->assertDontSee('Choose one answer')
            ->assertDontSee('Choose one or more answers')
            ->assertDontSee('একটি উত্তর নির্বাচন করুন')
            ->assertDontSee('এক বা একাধিক উত্তর নির্বাচন করুন');
    }

    // ── Autosave ────────────────────────────────────────────────────────────────

    public function test_autosave_stores_the_selection_and_is_idempotent(): void
    {
        [$quiz, $q1] = $this->openQuiz();
        $user = $this->startedStudent($quiz);
        $attempt = QuizAttempt::query()->where('user_id', $user->id)->first();
        $opt = $q1->options[1]->id;

        $this->actingAs($user)->putJson(
            route('student.attempts.answer', ['attempt' => $attempt, 'question' => $q1]),
            ['option_ids' => [$opt]]
        )->assertOk()->assertJson(['ok' => true, 'answered' => true]);

        // Retry the same save — still one answer, one selection row.
        $this->actingAs($user)->putJson(
            route('student.attempts.answer', ['attempt' => $attempt, 'question' => $q1]),
            ['option_ids' => [$opt]]
        )->assertOk();

        $this->assertSame(1, $attempt->answers()->count());
        $this->assertSame(1, QuizAnswerOption::query()->count());
    }

    public function test_a_foreign_option_is_rejected_and_stores_nothing(): void
    {
        [$quiz, $q1] = $this->openQuiz();
        $otherQuiz = Quiz::factory()->create();
        $foreign = QuizBuilder::for($otherQuiz)->question(['ক', 'খ'], correctPositions: [1]);

        $user = $this->startedStudent($quiz);
        $attempt = QuizAttempt::query()->where('user_id', $user->id)->first();

        $this->actingAs($user)->putJson(
            route('student.attempts.answer', ['attempt' => $attempt, 'question' => $q1]),
            ['option_ids' => [$foreign->options[0]->id]]
        )->assertStatus(422);

        $this->assertSame(0, $attempt->answers()->count());
    }

    public function test_a_question_from_another_quiz_is_not_found(): void
    {
        [$quiz, $q1] = $this->openQuiz();
        $otherQuiz = Quiz::factory()->create();
        $foreignQuestion = QuizBuilder::for($otherQuiz)->question(['ক', 'খ'], correctPositions: [1]);

        $user = $this->startedStudent($quiz);
        $attempt = QuizAttempt::query()->where('user_id', $user->id)->first();

        $this->actingAs($user)->putJson(
            route('student.attempts.answer', ['attempt' => $attempt, 'question' => $foreignQuestion]),
            ['option_ids' => [$foreignQuestion->options[0]->id]]
        )->assertNotFound();
    }

    public function test_saving_after_the_deadline_is_refused_and_expires_the_attempt(): void
    {
        [$quiz, $q1] = $this->openQuiz();
        $user = $this->startedStudent($quiz);
        $attempt = QuizAttempt::query()->where('user_id', $user->id)->first();

        $this->travelTo(CarbonImmutable::instance($attempt->expires_at)->addSecond());

        $this->actingAs($user)->putJson(
            route('student.attempts.answer', ['attempt' => $attempt, 'question' => $q1]),
            ['option_ids' => [$q1->options[0]->id]]
        )->assertStatus(409)->assertJson(['expired' => true]);

        $this->assertSame(QuizAttempt::STATUS_EXPIRED, $attempt->fresh()->status);
        $this->travelBack();
    }

    // ── IDOR ────────────────────────────────────────────────────────────────────

    public function test_a_student_cannot_touch_another_students_attempt(): void
    {
        [$quiz, $q1] = $this->openQuiz();
        $owner = $this->startedStudent($quiz);
        $attempt = QuizAttempt::query()->where('user_id', $owner->id)->first();

        $intruder = $this->makeStudent();

        $this->actingAs($intruder)->get(route('student.attempts.show', $attempt))->assertForbidden();
        $this->actingAs($intruder)->get(route('student.attempts.result', $attempt))->assertForbidden();
        $this->actingAs($intruder)->getJson(route('student.attempts.status', $attempt))->assertForbidden();
        $this->actingAs($intruder)->post(route('student.attempts.submit', $attempt))->assertForbidden();
        $this->actingAs($intruder)->putJson(
            route('student.attempts.answer', ['attempt' => $attempt, 'question' => $q1]),
            ['option_ids' => [$q1->options[0]->id]]
        )->assertForbidden();

        // The intruder changed nothing.
        $this->assertSame(0, $attempt->answers()->count());
        $this->assertSame(QuizAttempt::STATUS_IN_PROGRESS, $attempt->fresh()->status);
    }

    // ── Submission ──────────────────────────────────────────────────────────────

    public function test_submitting_finalises_and_scores_the_attempt(): void
    {
        [$quiz, $q1, $q2] = $this->openQuiz();
        $user = $this->startedStudent($quiz);
        $attempt = QuizAttempt::query()->where('user_id', $user->id)->first();

        // q1 correct is option 2, q2 correct is option 1.
        $this->actingAs($user)->putJson(route('student.attempts.answer', ['attempt' => $attempt, 'question' => $q1]), ['option_ids' => [$q1->options[1]->id]])->assertOk();
        $this->actingAs($user)->putJson(route('student.attempts.answer', ['attempt' => $attempt, 'question' => $q2]), ['option_ids' => [$q2->options[0]->id]])->assertOk();

        $this->actingAs($user)->post(route('student.attempts.submit', $attempt))
            ->assertRedirect(route('student.attempts.result', $attempt));

        $fresh = $attempt->fresh();
        $this->assertSame(QuizAttempt::STATUS_SUBMITTED, $fresh->status);
        $this->assertSame(2, $fresh->final_score);
    }

    public function test_a_double_submit_does_not_change_the_terminal_attempt(): void
    {
        [$quiz, $q1] = $this->openQuiz();
        $user = $this->startedStudent($quiz);
        $attempt = QuizAttempt::query()->where('user_id', $user->id)->first();
        $this->actingAs($user)->putJson(route('student.attempts.answer', ['attempt' => $attempt, 'question' => $q1]), ['option_ids' => [$q1->options[1]->id]]);

        $this->actingAs($user)->post(route('student.attempts.submit', $attempt))->assertRedirect();
        $first = $attempt->fresh();

        $this->actingAs($user)->post(route('student.attempts.submit', $attempt))->assertRedirect(route('student.attempts.result', $attempt));
        $second = $attempt->fresh();

        $this->assertSame($first->submitted_at->toDateTimeString(), $second->submitted_at->toDateTimeString());
        $this->assertSame($first->final_score, $second->final_score);
        $this->assertSame(1, QuizAttempt::query()->count());
    }

    // ── Result release gate ─────────────────────────────────────────────────────

    public function test_the_result_hides_the_score_until_results_are_released(): void
    {
        [$quiz, $q1] = $this->openQuiz();
        $user = $this->startedStudent($quiz);
        $attempt = QuizAttempt::query()->where('user_id', $user->id)->first();
        $this->actingAs($user)->putJson(route('student.attempts.answer', ['attempt' => $attempt, 'question' => $q1]), ['option_ids' => [$q1->options[1]->id]]);
        $this->actingAs($user)->post(route('student.attempts.submit', $attempt));

        // Not released (ends_at is still in the future): pending, no number.
        $this->actingAs($user)->get(route('student.attempts.result', $attempt))
            ->assertOk()
            ->assertSee(__('exams.result_pending_title'))
            ->assertDontSee('1 / 1');

        // Admin releases early.
        $quiz->forceFill(['results_released_at' => now()])->save();

        $this->actingAs($user)->get(route('student.attempts.result', $attempt))
            ->assertOk()
            ->assertDontSee(__('exams.result_pending_title'))
            ->assertSee(bn($attempt->fresh()->final_score));
    }

    public function test_a_live_attempt_is_sent_back_to_the_exam_from_the_result_page(): void
    {
        [$quiz] = $this->openQuiz();
        $user = $this->startedStudent($quiz);
        $attempt = QuizAttempt::query()->where('user_id', $user->id)->first();

        $this->actingAs($user)->get(route('student.attempts.result', $attempt))
            ->assertRedirect(route('student.attempts.show', $attempt));
    }

    public function test_opening_a_terminal_attempts_live_screen_redirects_to_the_result(): void
    {
        [$quiz] = $this->openQuiz();
        $user = $this->startedStudent($quiz);
        $attempt = QuizAttempt::query()->where('user_id', $user->id)->first();
        $this->actingAs($user)->post(route('student.attempts.submit', $attempt));

        $this->actingAs($user)->get(route('student.attempts.show', $attempt))
            ->assertRedirect(route('student.attempts.result', $attempt));
    }

    // ── Status poll ─────────────────────────────────────────────────────────────

    public function test_status_reports_remaining_time_and_finalises_on_expiry(): void
    {
        [$quiz] = $this->openQuiz();
        $user = $this->startedStudent($quiz);
        $attempt = QuizAttempt::query()->where('user_id', $user->id)->first();

        $this->actingAs($user)->getJson(route('student.attempts.status', $attempt))
            ->assertOk()->assertJson(['terminal' => false]);

        $this->travelTo(CarbonImmutable::instance($attempt->expires_at)->addSecond());

        $this->actingAs($user)->getJson(route('student.attempts.status', $attempt))
            ->assertOk()
            ->assertJson(['terminal' => true, 'remaining_seconds' => 0]);
        $this->assertSame(QuizAttempt::STATUS_EXPIRED, $attempt->fresh()->status);
        $this->travelBack();
    }

    // ── Autosave reliability ────────────────────────────────────────────────────

    /**
     * The browser now resends any answer whose save failed (on a timer and on
     * reconnect). That is only safe because the PUT carries the WHOLE selection: an
     * answer that in fact landed and is then resent must end up stored exactly once.
     */
    public function test_resending_an_answer_is_idempotent(): void
    {
        [$quiz, $q1] = $this->openQuiz();
        $user = $this->startedStudent($quiz);
        $attempt = QuizAttempt::query()->where('user_id', $user->id)->firstOrFail();
        $url = route('student.attempts.answer', ['attempt' => $attempt, 'question' => $q1]);
        $body = ['option_ids' => [$q1->options[1]->id]];

        $this->actingAs($user)->putJson($url, $body)->assertOk();
        $this->actingAs($user)->putJson($url, $body)->assertOk();
        $this->actingAs($user)->putJson($url, $body)->assertOk();

        $this->assertSame(1, $attempt->answers()->where('question_id', $q1->id)->count());
        $this->assertSame([$q1->options[1]->id], QuizAnswerOption::query()
            ->whereIn('answer_id', $attempt->answers()->pluck('id'))->pluck('option_id')->all());
    }

    public function test_the_live_screen_never_submits_over_answers_that_failed_to_save(): void
    {
        [$quiz] = $this->openQuiz();
        $user = $this->startedStudent($quiz);
        $attempt = QuizAttempt::query()->where('user_id', $user->id)->firstOrFail();

        $page = $this->actingAs($user)->get(route('student.attempts.show', $attempt))->assertOk();

        // The retry loop and the submit guard are present…
        $page->assertSee('retryFailed()', false)
            ->assertSee("addEventListener('online'", false)
            ->assertSee('const allSaved = await this.flushSaves(', false)
            ->assertSee('saveFailedNotice = true', false);
        // …and the student is told, in Bengali by default, rather than left guessing.
        $page->assertSee(__('exams.live_unsaved_banner'))
            ->assertSee(__('exams.live_unsaved_title'))
            ->assertSee(__('exams.live_retry_submit'));
    }

    /** Start an official attempt for a fresh student with points, return the student. */
    private function startedStudent(Quiz $quiz): User
    {
        $user = $this->makeStudent();
        app(\App\Services\Points\PointService::class)->credit($user, 5, PointTransaction::TYPE_ADJUSTMENT, 'seed');
        $user = $user->fresh();
        app(\App\Services\Quiz\QuizAttemptService::class)->startOfficial($quiz, $user);

        return $user;
    }
}
