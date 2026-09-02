<?php

namespace Tests\Feature\Admin;

use App\Models\Course;
use App\Models\Permission;
use App\Models\Quiz;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_finds_a_student_by_name_and_a_quiz_by_title(): void
    {
        $admin = $this->makeAdmin();
        $this->makeStudent(['name' => 'রফিকুল ইসলাম']);
        Quiz::factory()->create(['title' => 'সীরাত পরীক্ষা']);

        $this->actingAs($admin)->get(route('admin.search.index', ['q' => 'রফিকুল']))
            ->assertOk()->assertSee('রফিকুল ইসলাম');

        $this->actingAs($admin)->get(route('admin.search.index', ['q' => 'সীরাত পরীক্ষা']))
            ->assertOk()->assertSee('সীরাত পরীক্ষা');
    }

    public function test_an_empty_query_shows_a_prompt_not_results(): void
    {
        $admin = $this->makeAdmin();
        $this->makeStudent(['name' => 'যেকোনো একজন']);

        $this->actingAs($admin)->get(route('admin.search.index'))
            ->assertOk()
            ->assertDontSee('যেকোনো একজন');
    }

    public function test_search_never_surfaces_a_section_the_admin_lacks_permission_for(): void
    {
        $admin = $this->makeAdmin();
        $admin->roles()->first()->permissions()->detach(
            Permission::query()->where('name', 'courses.manage')->pluck('id')
        );
        Course::factory()->create(['title' => 'গোপন কোর্স']);

        $this->actingAs($admin->fresh())->get(route('admin.search.index', ['q' => 'গোপন']))
            ->assertOk()
            ->assertDontSee('গোপন কোর্স');
    }

    public function test_a_student_cannot_use_admin_search(): void
    {
        $this->actingAs($this->makeStudent())->get(route('admin.search.index'))->assertForbidden();
    }
}
