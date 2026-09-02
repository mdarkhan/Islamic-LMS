<?php

namespace Tests\Feature\Student;

use App\Models\Lesson;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LessonVideoTest extends TestCase
{
    use RefreshDatabase;

    public function test_youtube_embed_url_extracts_video_id_from_standard_url(): void
    {
        $lesson = Lesson::factory()->withVideo('https://www.youtube.com/watch?v=dQw4w9WgXcQ')->create();

        $this->assertSame('https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ', $lesson->youtubeEmbedUrl());
    }

    public function test_youtube_embed_url_extracts_video_id_from_short_url(): void
    {
        $lesson = Lesson::factory()->withVideo('https://youtu.be/dQw4w9WgXcQ')->create();

        $this->assertSame('dQw4w9WgXcQ', $lesson->youtubeVideoId());
        $this->assertSame('https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ', $lesson->youtubeEmbedUrl());
    }

    public function test_youtube_embed_url_extracts_video_id_from_embed_url(): void
    {
        $lesson = Lesson::factory()->withVideo('https://www.youtube.com/embed/dQw4w9WgXcQ')->create();

        $this->assertSame('dQw4w9WgXcQ', $lesson->youtubeVideoId());
    }

    public function test_youtube_embed_url_extracts_video_id_from_shorts_url(): void
    {
        $lesson = Lesson::factory()->withVideo('https://www.youtube.com/shorts/dQw4w9WgXcQ')->create();

        $this->assertSame('dQw4w9WgXcQ', $lesson->youtubeVideoId());
    }

    public function test_youtube_embed_url_returns_null_when_no_video_url(): void
    {
        $lesson = Lesson::factory()->create(['video_url' => null]);

        $this->assertNull($lesson->youtubeEmbedUrl());
        $this->assertNull($lesson->youtubeVideoId());
    }

    public function test_youtube_embed_url_returns_null_for_non_youtube_url(): void
    {
        $lesson = Lesson::factory()->withVideo('https://vimeo.com/123456')->create();

        $this->assertNull($lesson->youtubeEmbedUrl());
    }

    public function test_lesson_page_shows_youtube_player_when_video_url_is_set(): void
    {
        $student = $this->makeStudent();
        $lesson = Lesson::factory()->withVideo()->create();

        $this->actingAs($student)
            ->get(route('student.courses.show', $lesson->slug))
            ->assertOk()
            ->assertSee('youtube-nocookie.com/embed/dQw4w9WgXcQ');
    }

    public function test_lesson_page_does_not_show_youtube_player_when_no_video(): void
    {
        $student = $this->makeStudent();
        $lesson = Lesson::factory()->create();

        $this->actingAs($student)
            ->get(route('student.courses.show', $lesson->slug))
            ->assertOk()
            ->assertDontSee('youtube-nocookie.com');
    }

    public function test_admin_can_save_video_url_on_lesson(): void
    {
        $admin = $this->makeAdmin();
        $lesson = Lesson::factory()->create();

        $this->actingAs($admin)->put(route('admin.lessons.update', $lesson), [
            'course_id' => $lesson->course_id,
            'title' => $lesson->title,
            'media_provider' => 'none',
            'video_url' => 'https://www.youtube.com/watch?v=abc123xyz90',
        ])->assertRedirect();

        $this->assertSame('https://www.youtube.com/watch?v=abc123xyz90', $lesson->fresh()->video_url);
    }

    public function test_admin_can_clear_video_url(): void
    {
        $admin = $this->makeAdmin();
        $lesson = Lesson::factory()->withVideo()->create();

        $this->actingAs($admin)->put(route('admin.lessons.update', $lesson), [
            'course_id' => $lesson->course_id,
            'title' => $lesson->title,
            'media_provider' => 'none',
            'video_url' => '',
        ])->assertRedirect();

        $this->assertNull($lesson->fresh()->video_url);
    }

    public function test_lesson_listing_shows_video_badge_when_video_exists(): void
    {
        $student = $this->makeStudent();
        Lesson::factory()->withVideo()->create(['title' => 'ভিডিও ক্লাস']);

        $this->actingAs($student)
            ->get(route('student.courses.index'))
            ->assertOk()
            ->assertSee('ভিডিও ক্লাস');
    }
}
