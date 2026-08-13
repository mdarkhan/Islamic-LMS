<?php

namespace Tests\Feature\Student;

use App\Models\Quiz;
use App\Models\QuizAttempt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\QuizBuilder;
use Tests\TestCase;

class ExamListingTest extends TestCase
{
    use RefreshDatabase;

    public function test_draft_quizzes_are_hidden_from_students(): void
    {
        Quiz::factory()->draft()->create(['title' => 'গোপন খসড়া']);

        $this->actingAs($this->makeStudent())->get(route('student.exams.index'))
            ->assertOk()->assertDontSee('গোপন খসড়া');
    }

    public function test_a_scheduled_upcoming_quiz_is_shown_as_upcoming(): void
    {
        Quiz::factory()->create([
            'title' => 'আসন্ন সীরাত',
            'status' => Quiz::STATUS_SCHEDULED,
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDays(2),
        ]);

        $this->actingAs($this->makeStudent())->get(route('student.exams.index'))
            ->assertOk()
            ->assertSee('আসন্ন সীরাত')
            ->assertSee('আসন্ন পরীক্ষা');
    }

    public function test_an_open_quiz_is_shown_as_running(): void
    {
        Quiz::factory()->create([
            'title' => 'চলমান কুইজ',
            'status' => Quiz::STATUS_PUBLISHED,
            'starts_at' => now()->subMinute(),
            'ends_at' => now()->addHour(),
        ]);

        $this->actingAs($this->makeStudent())->get(route('student.exams.index'))
            ->assertOk()->assertSee('চলমান কুইজ')->assertSee('এখন চলছে');
    }

    public function test_an_archived_practice_quiz_shows_practice_available(): void
    {
        Quiz::factory()->practice()->create([
            'title' => 'আর্কাইভ অনুশীলন',
            'status' => Quiz::STATUS_ARCHIVED,
        ]);

        $this->actingAs($this->makeStudent())->get(route('student.exams.index'))
            ->assertOk()->assertSee('আর্কাইভ অনুশীলন')->assertSee('অনুশীলন উপলব্ধ');
    }

    public function test_a_completed_exam_appears_in_the_completed_group(): void
    {
        $student = $this->makeStudent();
        $quiz = Quiz::factory()->ended()->create(['title' => 'সম্পন্ন কুইজ']);
        QuizAttempt::factory()->for($quiz)->for($student)->create([
            'kind' => 'official', 'status' => 'submitted', 'final_score' => 8, 'total_marks_snapshot' => 10,
        ]);

        $this->actingAs($student)->get(route('student.exams.index'))
            ->assertOk()->assertSee('সম্পন্ন কুইজ')->assertSee('সম্পন্ন');
    }

    // ── Security: the answer key must never reach a student (Part 30) ─────────────

    public function test_the_exams_page_never_leaks_the_answer_key(): void
    {
        $student = $this->makeStudent();
        $quiz = Quiz::factory()->create([
            'title' => 'নিরাপত্তা কুইজ',
            'status' => Quiz::STATUS_PUBLISHED,
            'starts_at' => now()->subMinute(),
            'ends_at' => now()->addHour(),
        ]);
        // Distinctive option bodies so we can prove none of them (correct or not) appear.
        QuizBuilder::for($quiz)->question(
            ['SENTINEL_WRONG_OPTION', 'SENTINEL_CORRECT_OPTION'],
            correctPositions: [2],
        );

        $html = $this->actingAs($student)->get(route('student.exams.index'))->assertOk()->getContent();

        // No option bodies at all, and no answer-key markers.
        $this->assertStringNotContainsString('SENTINEL_CORRECT_OPTION', $html);
        $this->assertStringNotContainsString('SENTINEL_WRONG_OPTION', $html);
        $this->assertStringNotContainsString('is_correct', $html);
        $this->assertStringNotContainsString('correctOption', $html);
        $this->assertStringNotContainsString('correctIndices', $html);
    }

    public function test_an_authenticated_student_still_cannot_read_admin_answer_data(): void
    {
        // Serialising a quiz with options must not expose is_correct even if a
        // developer accidentally passes options to a student view.
        $quiz = Quiz::factory()->create();
        $question = QuizBuilder::for($quiz)->question(['ক', 'খ'], correctPositions: [1]);

        $json = $question->load('options')->toArray();

        foreach ($json['options'] as $option) {
            $this->assertArrayNotHasKey('is_correct', $option, 'is_correct is hidden from serialisation');
        }
    }
}
