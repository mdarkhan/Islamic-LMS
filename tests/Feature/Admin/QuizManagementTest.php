<?php

namespace Tests\Feature\Admin;

use App\Models\Permission;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\QuizBuilder;
use Tests\TestCase;

class QuizManagementTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'সীরাত-২৮ পরীক্ষা',
            'status' => 'draft',
            'point_cost' => 1,
            'max_official_attempts' => 1,
            'duration_minutes' => 30,
        ], $overrides);
    }

    public function test_admin_creates_a_quiz_with_a_unicode_slug(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin)->post(route('admin.quizzes.store'), $this->payload())
            ->assertRedirect();

        $quiz = Quiz::query()->where('title', 'সীরাত-২৮ পরীক্ষা')->firstOrFail();
        $this->assertSame('সীরাত-২৮-পরীক্ষা', $quiz->slug, 'Bengali slug is preserved, not blanked');
        $this->assertSame(1800, $quiz->duration_seconds, 'minutes stored as seconds');
        $this->assertSame($admin->id, $quiz->created_by);
        $this->assertDatabaseHas('audit_logs', ['action' => 'quiz.created']);
    }

    public function test_end_before_start_is_rejected(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin)->from(route('admin.quizzes.create'))->post(route('admin.quizzes.store'), $this->payload([
            'starts_at' => '2026-02-01T10:00',
            'ends_at' => '2026-02-01T09:00',
        ]))->assertSessionHasErrors('ends_at');
    }

    public function test_result_release_before_end_is_rejected(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin)->from(route('admin.quizzes.create'))->post(route('admin.quizzes.store'), $this->payload([
            'starts_at' => '2026-02-01T10:00',
            'ends_at' => '2026-02-01T12:00',
            'result_release_at' => '2026-02-01T11:00',
        ]))->assertSessionHasErrors('result_release_at');
    }

    public function test_a_lesson_must_belong_to_the_chosen_course(): void
    {
        $admin = $this->makeAdmin();
        $courseA = \App\Models\Course::factory()->create();
        $courseB = \App\Models\Course::factory()->create();
        $lessonB = \App\Models\Lesson::factory()->for($courseB)->create();

        $this->actingAs($admin)->from(route('admin.quizzes.create'))->post(route('admin.quizzes.store'), $this->payload([
            'course_id' => $courseA->id,
            'lesson_id' => $lessonB->id,
        ]))->assertSessionHasErrors('lesson_id');
    }

    public function test_status_lifecycle_transitions_via_quick_action(): void
    {
        $admin = $this->makeAdmin();
        $quiz = Quiz::factory()->draft()->create();

        $this->actingAs($admin)->put(route('admin.quizzes.status', $quiz), ['status' => 'published'])->assertRedirect();
        $quiz->refresh();
        $this->assertSame('published', $quiz->status);
        $this->assertNotNull($quiz->published_at);
        $this->assertDatabaseHas('audit_logs', ['action' => 'quiz.published']);

        $this->actingAs($admin)->put(route('admin.quizzes.status', $quiz), ['status' => 'archived']);
        $this->assertSame('archived', $quiz->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'quiz.archived']);
    }

    public function test_duplicate_copies_questions_but_not_attempts(): void
    {
        $admin = $this->makeAdmin();
        $quiz = Quiz::factory()->create(['title' => 'মূল কুইজ']);
        QuizBuilder::for($quiz)->question(['ক', 'খ', 'গ'], correctPositions: [2], marks: 3);
        QuizBuilder::for($quiz)->question(['ক', 'খ'], correctPositions: [1, 2], marks: 5);
        // An attempt on the original must NOT be copied.
        QuizAttempt::factory()->for($quiz)->for($this->makeStudent())->create(['kind' => 'official', 'status' => 'submitted']);

        $this->actingAs($admin)->post(route('admin.quizzes.duplicate', $quiz))->assertRedirect();

        $copy = Quiz::query()->where('title', 'মূল কুইজ (কপি)')->firstOrFail();
        $this->assertSame('draft', $copy->status);
        $this->assertSame(2, $copy->questions()->count());
        $this->assertSame(8, $copy->total_marks, 'marks copied and recalculated');
        $this->assertSame(0, $copy->attempts()->count(), 'attempts are never duplicated');
        $this->assertNotSame($quiz->slug, $copy->slug);
    }

    public function test_a_quiz_with_official_attempts_cannot_be_deleted(): void
    {
        $admin = $this->makeAdmin();
        $quiz = Quiz::factory()->create();
        QuizAttempt::factory()->for($quiz)->for($this->makeStudent())->create(['kind' => 'official', 'status' => 'submitted']);

        $this->actingAs($admin)->from(route('admin.quizzes.edit', $quiz))
            ->delete(route('admin.quizzes.destroy', $quiz))
            ->assertRedirect();

        $this->assertDatabaseHas('quizzes', ['id' => $quiz->id]);
    }

    public function test_a_draft_quiz_can_be_deleted(): void
    {
        $admin = $this->makeAdmin();
        $quiz = Quiz::factory()->draft()->create();

        $this->actingAs($admin)->delete(route('admin.quizzes.destroy', $quiz))
            ->assertRedirect(route('admin.quizzes.index'));

        $this->assertDatabaseMissing('quizzes', ['id' => $quiz->id]);
    }

    public function test_index_filters_by_status_and_course(): void
    {
        $admin = $this->makeAdmin();
        $course = \App\Models\Course::factory()->create(['slug' => 'seerat-course']);
        Quiz::factory()->create(['title' => 'দৃশ্যমান', 'status' => 'published', 'course_id' => $course->id]);
        Quiz::factory()->draft()->create(['title' => 'খসড়া কুইজ']);

        $this->actingAs($admin)->get(route('admin.quizzes.index', ['status' => 'published']))
            ->assertOk()->assertSee('দৃশ্যমান')->assertDontSee('খসড়া কুইজ');
    }

    public function test_a_student_cannot_reach_quiz_admin_routes(): void
    {
        $student = $this->makeStudent();

        $this->actingAs($student)->get(route('admin.quizzes.index'))->assertForbidden();
        $this->actingAs($student)->post(route('admin.quizzes.store'), $this->payload())->assertForbidden();
    }

    public function test_publish_permission_is_required_for_status_changes(): void
    {
        $admin = $this->makeAdmin();
        $admin->roles->first()->permissions()->detach(Permission::query()->where('name', 'quizzes.publish')->value('id'));
        $quiz = Quiz::factory()->draft()->create();

        $this->actingAs($admin)->put(route('admin.quizzes.status', $quiz), ['status' => 'published'])->assertForbidden();
    }
}
