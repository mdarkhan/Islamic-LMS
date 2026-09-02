<?php

namespace Tests\Feature\Student;

use App\Models\Course;
use App\Models\Lesson;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LessonNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_next_lesson_button_links_to_the_following_lesson_in_sort_order(): void
    {
        $student = $this->makeStudent();
        $course = Course::factory()->create(['is_published' => true]);
        $first = Lesson::factory()->create(['course_id' => $course->id, 'is_published' => true, 'sort_order' => 1, 'title' => 'প্রথম ক্লাস']);
        $second = Lesson::factory()->create(['course_id' => $course->id, 'is_published' => true, 'sort_order' => 2, 'title' => 'দ্বিতীয় ক্লাস']);

        $this->actingAs($student)->get(route('student.courses.show', $first->slug))
            ->assertOk()
            ->assertSee(__('lessons.next_lesson'))
            ->assertSee('দ্বিতীয় ক্লাস')
            ->assertSee(route('student.courses.show', $second->slug), false);
    }

    public function test_the_previous_lesson_button_links_to_the_preceding_lesson(): void
    {
        $student = $this->makeStudent();
        $course = Course::factory()->create(['is_published' => true]);
        $first = Lesson::factory()->create(['course_id' => $course->id, 'is_published' => true, 'sort_order' => 1, 'title' => 'প্রথম ক্লাস']);
        $second = Lesson::factory()->create(['course_id' => $course->id, 'is_published' => true, 'sort_order' => 2, 'title' => 'দ্বিতীয় ক্লাস']);

        $this->actingAs($student)->get(route('student.courses.show', $second->slug))
            ->assertOk()
            ->assertSee(__('lessons.previous_lesson'))
            ->assertSee('প্রথম ক্লাস');
    }

    public function test_the_first_lesson_has_no_previous_button(): void
    {
        $student = $this->makeStudent();
        $course = Course::factory()->create(['is_published' => true]);
        $first = Lesson::factory()->create(['course_id' => $course->id, 'is_published' => true, 'sort_order' => 1]);
        Lesson::factory()->create(['course_id' => $course->id, 'is_published' => true, 'sort_order' => 2]);

        $this->actingAs($student)->get(route('student.courses.show', $first->slug))
            ->assertOk()
            ->assertDontSee(__('lessons.previous_lesson'));
    }

    public function test_the_last_lesson_has_no_next_button(): void
    {
        $student = $this->makeStudent();
        $course = Course::factory()->create(['is_published' => true]);
        Lesson::factory()->create(['course_id' => $course->id, 'is_published' => true, 'sort_order' => 1]);
        $last = Lesson::factory()->create(['course_id' => $course->id, 'is_published' => true, 'sort_order' => 2]);

        $this->actingAs($student)->get(route('student.courses.show', $last->slug))
            ->assertOk()
            ->assertDontSee(__('lessons.next_lesson'));
    }

    public function test_navigation_skips_unpublished_lessons(): void
    {
        $student = $this->makeStudent();
        $course = Course::factory()->create(['is_published' => true]);
        $first = Lesson::factory()->create(['course_id' => $course->id, 'is_published' => true, 'sort_order' => 1]);
        Lesson::factory()->create(['course_id' => $course->id, 'is_published' => false, 'sort_order' => 2, 'title' => 'অপ্রকাশিত ক্লাস']);
        $third = Lesson::factory()->create(['course_id' => $course->id, 'is_published' => true, 'sort_order' => 3, 'title' => 'তৃতীয় ক্লাস']);

        $this->actingAs($student)->get(route('student.courses.show', $first->slug))
            ->assertOk()
            ->assertSee('তৃতীয় ক্লাস')
            ->assertDontSee('অপ্রকাশিত ক্লাস');
    }

    public function test_navigation_never_crosses_into_another_course(): void
    {
        $student = $this->makeStudent();
        $courseA = Course::factory()->create(['is_published' => true]);
        $courseB = Course::factory()->create(['is_published' => true]);
        $lastOfA = Lesson::factory()->create(['course_id' => $courseA->id, 'is_published' => true, 'sort_order' => 1]);
        Lesson::factory()->create(['course_id' => $courseB->id, 'is_published' => true, 'sort_order' => 1, 'title' => 'অন্য কোর্সের ক্লাস']);

        $this->actingAs($student)->get(route('student.courses.show', $lastOfA->slug))
            ->assertOk()
            ->assertDontSee(__('lessons.next_lesson'))
            ->assertDontSee('অন্য কোর্সের ক্লাস');
    }
}
