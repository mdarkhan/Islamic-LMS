<?php

namespace Tests\Feature\Student;

use App\Models\Course;
use App\Models\Lesson;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CourseAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_student_can_view_a_published_lesson(): void
    {
        $course = Course::factory()->create(['is_published' => true]);
        $lesson = Lesson::factory()->for($course)->create(['is_published' => true, 'title' => 'সীরাত-০১']);

        $this->actingAs($this->makeStudent())
            ->get(route('student.courses.show', $lesson->slug))
            ->assertOk()
            ->assertSee('সীরাত-০১');
    }

    public function test_google_drive_audio_uses_the_reliable_drive_preview_player(): void
    {
        // A native <audio> cannot stream Drive files, so the lesson embeds Drive's own
        // player (its preview URL) which streams reliably.
        $course = Course::factory()->create(['is_published' => true]);
        $lesson = Lesson::factory()->for($course)->create([
            'is_published' => true,
            'media_provider' => 'google_drive',
            'media_url' => 'https://drive.google.com/file/d/ABC123/view',
            'media_file_id' => 'ABC123',
        ]);

        $this->actingAs($this->makeStudent())
            ->get(route('student.courses.show', $lesson->slug))
            ->assertOk()
            ->assertSee('<iframe', false)
            ->assertSee('https://drive.google.com/file/d/ABC123/preview', false);
    }

    public function test_an_unpublished_lesson_is_hidden(): void
    {
        $course = Course::factory()->create(['is_published' => true]);
        $lesson = Lesson::factory()->for($course)->create(['is_published' => false]);

        $this->actingAs($this->makeStudent())
            ->get(route('student.courses.show', $lesson->slug))
            ->assertNotFound();
    }

    public function test_a_lesson_in_an_unpublished_course_is_hidden(): void
    {
        $course = Course::factory()->create(['is_published' => false]);
        $lesson = Lesson::factory()->for($course)->create(['is_published' => true]);

        $this->actingAs($this->makeStudent())
            ->get(route('student.courses.show', $lesson->slug))
            ->assertNotFound();
    }

    public function test_course_material_requires_authentication(): void
    {
        $course = Course::factory()->create(['is_published' => true]);
        $lesson = Lesson::factory()->for($course)->create(['is_published' => true]);

        $this->get(route('student.courses.show', $lesson->slug))->assertRedirect(route('login'));
    }

    public function test_the_course_index_lists_only_published_lessons(): void
    {
        $course = Course::factory()->create(['is_published' => true]);
        Lesson::factory()->for($course)->create(['is_published' => true, 'title' => 'দৃশ্যমান ক্লাস']);
        Lesson::factory()->for($course)->create(['is_published' => false, 'title' => 'গোপন ক্লাস']);

        $this->actingAs($this->makeStudent())
            ->get(route('student.courses.index'))
            ->assertOk()
            ->assertSee('দৃশ্যমান ক্লাস')
            ->assertDontSee('গোপন ক্লাস');
    }
}
