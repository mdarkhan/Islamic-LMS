<?php

namespace Tests\Feature\Admin;

use App\Models\Course;
use App\Models\Lesson;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CourseLessonTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_creates_a_course(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin)->post(route('admin.courses.store'), [
            'title' => 'সীরাত কোর্স', 'is_published' => '1',
        ])->assertRedirect(route('admin.courses.index'));

        $this->assertDatabaseHas('courses', ['title' => 'সীরাত কোর্স', 'is_published' => true]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'course.created']);
    }

    public function test_a_course_with_lessons_cannot_be_deleted(): void
    {
        $admin = $this->makeAdmin();
        $course = Course::factory()->create();
        Lesson::factory()->for($course)->create();

        $this->actingAs($admin)->from(route('admin.courses.edit', $course))
            ->delete(route('admin.courses.destroy', $course))
            ->assertRedirect();

        $this->assertDatabaseHas('courses', ['id' => $course->id]);
    }

    public function test_admin_creates_a_lesson_with_resources_dropping_placeholder_urls(): void
    {
        $admin = $this->makeAdmin();
        $course = Course::factory()->create();

        $this->actingAs($admin)->post(route('admin.lessons.store'), [
            'course_id' => $course->id,
            'title' => 'সীরাত-২৬',
            'media_provider' => 'google_drive',
            'media_url' => 'https://drive.google.com/file/d/ABC123/view',
            'is_published' => '1',
            'resources' => [
                ['label' => 'বই একটি', 'url' => 'https://example.com/book', 'kind' => 'book'],
                ['label' => 'লিংকহীন নোট', 'url' => '#', 'kind' => 'note'],
                ['label' => '', 'url' => 'https://example.com/skip', 'kind' => 'link'],   // no label → skipped
            ],
        ])->assertRedirect();

        $lesson = Lesson::query()->where('title', 'সীরাত-২৬')->firstOrFail();
        $this->assertSame('ABC123', $lesson->media_file_id);
        $this->assertSame(2, $lesson->resources()->count(), 'labelless row dropped');
        $this->assertNull($lesson->resources()->where('label', 'লিংকহীন নোট')->value('url'), 'placeholder # → NULL');
    }

    public function test_the_date_label_is_derived_from_the_calendar_date(): void
    {
        $admin = $this->makeAdmin();
        $course = Course::factory()->create();

        $this->actingAs($admin)->post(route('admin.lessons.store'), [
            'course_id' => $course->id,
            'title' => 'সীরাত-২৭',
            'held_on' => '2026-01-09',
            'media_provider' => 'none',
            'is_published' => '1',
            // A client-submitted label must be ignored — the calendar date wins.
            'date_label' => 'এলোমেলো টেক্সট',
        ])->assertRedirect();

        $lesson = Lesson::query()->where('title', 'সীরাত-২৭')->firstOrFail();
        $this->assertSame('2026-01-09', $lesson->held_on->toDateString());
        $this->assertSame('০৯ জানুয়ারি ২০২৬', $lesson->date_label);
    }

    public function test_clearing_the_date_clears_the_derived_label(): void
    {
        $admin = $this->makeAdmin();
        $lesson = Lesson::factory()->create([
            'held_on' => '2026-01-09',
            'date_label' => '০৯ জানুয়ারি ২০২৬',
        ]);

        $this->actingAs($admin)->put(route('admin.lessons.update', $lesson), [
            'course_id' => $lesson->course_id,
            'title' => $lesson->title,
            'media_provider' => 'none',
            'held_on' => '',
        ])->assertRedirect();

        $fresh = $lesson->fresh();
        $this->assertNull($fresh->held_on);
        $this->assertNull($fresh->date_label);
    }

    public function test_updating_the_date_recomputes_the_label(): void
    {
        $admin = $this->makeAdmin();
        $lesson = Lesson::factory()->create([
            'held_on' => '2026-01-09',
            'date_label' => '০৯ জানুয়ারি ২০২৬',
        ]);

        $this->actingAs($admin)->put(route('admin.lessons.update', $lesson), [
            'course_id' => $lesson->course_id,
            'title' => $lesson->title,
            'media_provider' => 'none',
            'held_on' => '2026-02-06',
        ])->assertRedirect();

        $this->assertSame('০৬ ফেব্রুয়ারি ২০২৬', $lesson->fresh()->date_label);
    }

    public function test_toggling_publish_flips_visibility(): void
    {
        $admin = $this->makeAdmin();
        $lesson = Lesson::factory()->create(['is_published' => true]);

        $this->actingAs($admin)->put(route('admin.lessons.publish', $lesson));

        $this->assertFalse($lesson->fresh()->is_published);
    }

    public function test_a_student_cannot_manage_courses(): void
    {
        $this->actingAs($this->makeStudent())->get(route('admin.courses.index'))->assertForbidden();
        $this->actingAs($this->makeStudent())->post(route('admin.courses.store'), ['title' => 'x'])->assertForbidden();
    }
}
