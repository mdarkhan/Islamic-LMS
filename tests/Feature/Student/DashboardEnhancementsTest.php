<?php

namespace Tests\Feature\Student;

use App\Models\Course;
use App\Models\Lesson;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardEnhancementsTest extends TestCase
{
    use RefreshDatabase;

    private function openQuiz(array $overrides = []): Quiz
    {
        return Quiz::factory()->create(array_merge([
            'status' => Quiz::STATUS_PUBLISHED,
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addHour(),
        ], $overrides));
    }

    private function endedQuiz(array $overrides = []): Quiz
    {
        return Quiz::factory()->create(array_merge([
            'status' => Quiz::STATUS_PUBLISHED,
            'starts_at' => now()->subDays(2),
            'ends_at' => now()->subHour(),
        ], $overrides));
    }

    private function attempt(Quiz $quiz, User $user, array $overrides = []): QuizAttempt
    {
        return QuizAttempt::factory()->create(array_merge([
            'quiz_id' => $quiz->id,
            'user_id' => $user->id,
        ], $overrides));
    }

    // ── Exam banner ──────────────────────────────────────────────────────────────

    public function test_dashboard_announces_an_open_quiz_the_student_has_not_attempted(): void
    {
        $student = $this->makeStudent();
        $this->openQuiz(['title' => 'চলমান পরীক্ষা']);

        $this->actingAs($student)->get(route('student.dashboard'))
            ->assertOk()
            ->assertSee('চলমান পরীক্ষা');
    }

    public function test_dashboard_hides_the_exam_banner_once_the_student_has_any_attempt(): void
    {
        $student = $this->makeStudent();
        $quiz = $this->openQuiz(['title' => 'ইতিমধ্যে অংশগ্রহণকৃত']);
        $this->attempt($quiz, $student, ['status' => QuizAttempt::STATUS_SUBMITTED]);

        // The only quiz in play already has an attempt, so the "highlight one exam"
        // banner must find nothing — its heading disappears entirely (the quiz title
        // itself legitimately still appears in the "recent results" list below).
        $this->actingAs($student)->get(route('student.dashboard'))
            ->assertOk()
            ->assertDontSee(__('dashboard.exam_open_title'))
            ->assertDontSee(__('dashboard.exam_upcoming_title'));
    }

    public function test_dashboard_never_reveals_marks_or_answer_key_fields_via_the_exam_banner(): void
    {
        $student = $this->makeStudent();
        $this->openQuiz(['title' => 'নিরাপত্তা পরীক্ষা']);

        $body = $this->actingAs($student)->get(route('student.dashboard'))->assertOk()->getContent();

        $this->assertStringNotContainsString('is_correct', $body);
    }

    // ── Resume card ──────────────────────────────────────────────────────────────

    public function test_dashboard_offers_to_resume_an_in_progress_official_attempt(): void
    {
        $student = $this->makeStudent();
        $quiz = $this->openQuiz(['title' => 'রিজিউম পরীক্ষা']);
        $this->attempt($quiz, $student, [
            'status' => QuizAttempt::STATUS_IN_PROGRESS,
            'expires_at' => now()->addMinutes(30),
            'submitted_at' => null,
        ]);

        $this->actingAs($student)->get(route('student.dashboard'))
            ->assertOk()
            ->assertSee(__('dashboard.resume_button'))
            ->assertSee('রিজিউম পরীক্ষা');
    }

    public function test_dashboard_does_not_offer_to_resume_an_attempt_past_its_deadline(): void
    {
        $student = $this->makeStudent();
        $quiz = $this->openQuiz();
        $this->attempt($quiz, $student, [
            'status' => QuizAttempt::STATUS_IN_PROGRESS,
            'expires_at' => now()->subMinute(),
            'submitted_at' => null,
        ]);

        $this->actingAs($student)->get(route('student.dashboard'))
            ->assertOk()
            ->assertDontSee(__('dashboard.resume_button'));
    }

    public function test_dashboard_offers_to_resume_an_untimed_in_progress_practice_attempt(): void
    {
        $student = $this->makeStudent();
        $quiz = $this->endedQuiz(['practice_enabled' => true, 'title' => 'অনুশীলন কুইজ']);
        $this->attempt($quiz, $student, [
            'kind' => QuizAttempt::KIND_PRACTICE,
            'status' => QuizAttempt::STATUS_IN_PROGRESS,
            'expires_at' => null,
            'submitted_at' => null,
        ]);

        $this->actingAs($student)->get(route('student.dashboard'))
            ->assertOk()
            ->assertSee(__('dashboard.resume_practice'));
    }

    // ── Unseen results ───────────────────────────────────────────────────────────

    public function test_dashboard_flags_a_released_result_the_student_has_not_seen_yet(): void
    {
        $student = $this->makeStudent();
        $quiz = $this->endedQuiz(['title' => 'ফলাফল কুইজ']);
        $this->attempt($quiz, $student, ['status' => QuizAttempt::STATUS_SUBMITTED]);

        $this->actingAs($student)->get(route('student.dashboard'))
            ->assertOk()
            ->assertSee(__('dashboard.new_result_heading'));
    }

    public function test_visiting_results_marks_it_seen_and_clears_the_dashboard_notification(): void
    {
        $student = $this->makeStudent();
        $quiz = $this->endedQuiz();
        $attempt = $this->attempt($quiz, $student, ['status' => QuizAttempt::STATUS_SUBMITTED]);

        $this->actingAs($student)->get(route('student.results.index'))->assertOk();

        $this->assertNotNull($attempt->fresh()->results_seen_at);

        $this->actingAs($student)->get(route('student.dashboard'))
            ->assertOk()
            ->assertDontSee(__('dashboard.new_result_heading'));
    }

    public function test_a_result_not_yet_released_never_appears_as_an_unseen_result(): void
    {
        $student = $this->makeStudent();
        $quiz = $this->openQuiz();   // still open — results not released
        $this->attempt($quiz, $student, ['status' => QuizAttempt::STATUS_SUBMITTED]);

        $this->actingAs($student)->get(route('student.dashboard'))
            ->assertOk()
            ->assertDontSee(__('dashboard.new_result_heading'));
    }

    // ── Practice suggestions ─────────────────────────────────────────────────────

    public function test_dashboard_suggests_practice_for_an_attempted_quiz_once_practice_opens(): void
    {
        $student = $this->makeStudent();
        $quiz = $this->endedQuiz(['practice_enabled' => true, 'title' => 'অনুশীলন সাজেশন কুইজ']);
        $this->attempt($quiz, $student, ['status' => QuizAttempt::STATUS_SUBMITTED]);

        $this->actingAs($student)->get(route('student.dashboard'))
            ->assertOk()
            ->assertSee(__('dashboard.practice_suggestion_heading'))
            ->assertSee('অনুশীলন সাজেশন কুইজ');
    }

    public function test_practice_suggestion_disappears_once_the_student_has_practiced_it(): void
    {
        $student = $this->makeStudent();
        $quiz = $this->endedQuiz(['practice_enabled' => true, 'title' => 'ইতিমধ্যে অনুশীলিত']);
        $this->attempt($quiz, $student, ['status' => QuizAttempt::STATUS_SUBMITTED]);
        $this->attempt($quiz, $student, [
            'kind' => QuizAttempt::KIND_PRACTICE,
            'status' => QuizAttempt::STATUS_SUBMITTED,
            'attempt_no' => 1,
        ]);

        // The only attempted quiz has now been practiced, so the suggestion section
        // has nothing left to show and its heading disappears entirely (the quiz title
        // itself legitimately still appears in the "recent results" list below).
        $this->actingAs($student)->get(route('student.dashboard'))
            ->assertOk()
            ->assertDontSee(__('dashboard.practice_suggestion_heading'));
    }

    // ── Rank movement ────────────────────────────────────────────────────────────

    public function test_rank_movement_is_hidden_on_the_first_visit_and_reflects_change_afterwards(): void
    {
        $student = $this->makeStudent();
        $rival = $this->makeStudent();
        $quiz = $this->endedQuiz();

        $this->attempt($quiz, $student, ['status' => QuizAttempt::STATUS_SUBMITTED]);
        $this->attempt($quiz, $rival, ['status' => QuizAttempt::STATUS_SUBMITTED]);
        QuizAttempt::query()->where('user_id', $student->id)->update(['final_score' => 40, 'calculated_score' => 40, 'total_marks_snapshot' => 100]);
        QuizAttempt::query()->where('user_id', $rival->id)->update(['final_score' => 80, 'calculated_score' => 80, 'total_marks_snapshot' => 100]);

        // First visit: no prior rank stored yet, so no arrow — but the value is recorded.
        $this->actingAs($student)->get(route('student.dashboard'))->assertOk();
        $this->assertSame(2, $student->fresh()->last_seen_overall_rank);

        // The student overtakes the rival on a second quiz — rank should improve to 1.
        $quiz2 = $this->endedQuiz();
        $this->attempt($quiz2, $student, ['status' => QuizAttempt::STATUS_SUBMITTED]);
        QuizAttempt::query()->where('quiz_id', $quiz2->id)->where('user_id', $student->id)
            ->update(['final_score' => 100, 'calculated_score' => 100, 'total_marks_snapshot' => 100]);

        $this->actingAs($student)->get(route('student.dashboard'))
            ->assertOk()
            ->assertSee(__('dashboard.rank_up'));
        $this->assertSame(1, $student->fresh()->last_seen_overall_rank);
    }

    // ── Lesson progress, continue watching, completion badge ───────────────────────

    public function test_visiting_a_lesson_records_progress_shown_on_the_dashboard(): void
    {
        $student = $this->makeStudent();
        $course = Course::factory()->create(['is_published' => true, 'title' => 'অগ্রগতি কোর্স']);
        $lessons = Lesson::factory()->count(2)->create(['course_id' => $course->id, 'is_published' => true]);

        $this->actingAs($student)->get(route('student.courses.show', $lessons[0]->slug))->assertOk();

        $this->actingAs($student)->get(route('student.dashboard'))
            ->assertOk()
            ->assertSee(__('dashboard.continue_heading'))
            ->assertSee($lessons[0]->title)
            ->assertSee(__('dashboard.course_progress', ['viewed' => bn(1), 'total' => bn(2)]));
    }

    public function test_course_gets_a_completion_badge_once_every_published_lesson_is_viewed(): void
    {
        $student = $this->makeStudent();
        $course = Course::factory()->create(['is_published' => true]);
        $lesson = Lesson::factory()->create(['course_id' => $course->id, 'is_published' => true]);

        $this->actingAs($student)->get(route('student.courses.show', $lesson->slug))->assertOk();

        $this->actingAs($student)->get(route('student.dashboard'))
            ->assertOk()
            ->assertSee(__('dashboard.course_completed'));

        $this->assertDatabaseHas('course_completions', ['user_id' => $student->id, 'course_id' => $course->id]);
    }

    public function test_dashboard_recommends_the_next_unviewed_lesson_when_nothing_viewed_yet(): void
    {
        $student = $this->makeStudent();
        $course = Course::factory()->create(['is_published' => true]);
        Lesson::factory()->create(['course_id' => $course->id, 'is_published' => true, 'title' => 'প্রথম ক্লাস', 'sort_order' => 1]);

        $this->actingAs($student)->get(route('student.dashboard'))
            ->assertOk()
            ->assertSee(__('dashboard.next_lesson_heading'))
            ->assertSee('প্রথম ক্লাস');
    }
}
