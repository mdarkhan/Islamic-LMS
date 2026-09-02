<?php

namespace Tests\Feature\Public;

use App\Models\Quiz;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UpcomingExamBannerTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_open_official_quiz_is_announced_on_the_homepage(): void
    {
        Quiz::factory()->create(['title' => 'চলমান পরীক্ষা']);

        $this->get(route('home'))->assertOk()->assertSee('চলমান পরীক্ষা');
    }

    public function test_an_upcoming_official_quiz_is_announced_on_the_homepage(): void
    {
        Quiz::factory()->notStarted()->create(['title' => 'আসন্ন পরীক্ষা']);

        $this->get(route('home'))->assertOk()->assertSee('আসন্ন পরীক্ষা');
    }

    public function test_a_draft_or_archived_or_closed_quiz_is_never_announced(): void
    {
        Quiz::factory()->draft()->create(['title' => 'খসড়া পরীক্ষা']);
        Quiz::factory()->create(['title' => 'আর্কাইভ পরীক্ষা', 'status' => Quiz::STATUS_ARCHIVED]);
        Quiz::factory()->ended()->create(['title' => 'শেষ হওয়া পরীক্ষা']);

        $this->get(route('home'))->assertOk()
            ->assertDontSee('খসড়া পরীক্ষা')
            ->assertDontSee('আর্কাইভ পরীক্ষা')
            ->assertDontSee('শেষ হওয়া পরীক্ষা');
    }

    public function test_the_banner_never_reveals_marks_or_answer_key_fields(): void
    {
        Quiz::factory()->create(['title' => 'নিরাপত্তা পরীক্ষা']);

        $body = $this->get(route('home'))->assertOk()->getContent();

        $this->assertStringNotContainsString('is_correct', $body);
    }
}
