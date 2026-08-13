<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocaleTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_interface_language_is_bengali(): void
    {
        $this->get(route('login'))->assertOk()->assertSee('অ্যাকাউন্টে প্রবেশ করুন');
    }

    public function test_a_guest_can_switch_the_interface_to_english_via_the_session(): void
    {
        $this->post(route('locale.update'), ['locale' => 'en'])->assertRedirect();

        // The choice is remembered for the guest and renders the English chrome.
        $this->get(route('login'))->assertOk()
            ->assertSee('Sign in to your account')
            ->assertDontSee('অ্যাকাউন্টে প্রবেশ করুন');
    }

    public function test_an_invalid_locale_is_rejected(): void
    {
        $this->from(route('login'))->post(route('locale.update'), ['locale' => 'fr'])
            ->assertSessionHasErrors('locale');
    }

    public function test_a_signed_in_user_preference_is_persisted_and_applied(): void
    {
        $student = $this->makeStudent();

        $this->actingAs($student)->post(route('locale.update'), ['locale' => 'en'])->assertRedirect();
        $this->assertSame('en', $student->fresh()->locale);

        // The saved preference drives the dashboard chrome on a later request.
        $this->actingAs($student->fresh())->get(route('student.dashboard'))
            ->assertOk()
            ->assertSee('Dashboard')
            ->assertSee('Points')
            ->assertDontSee('পয়েন্ট হিস্ট্রি');
    }

    public function test_the_saved_preference_overrides_the_app_default(): void
    {
        $student = $this->makeStudent(['locale' => 'en']);

        $this->actingAs($student)->get(route('student.dashboard'))
            ->assertOk()->assertSee('Recent points');
    }

    public function test_numbers_render_in_latin_digits_in_english_and_bengali_digits_otherwise(): void
    {
        $en = $this->makeStudent(['locale' => 'en', 'roll' => '101']);
        $this->actingAs($en)->get(route('student.dashboard'))->assertOk()->assertSee('101');

        $bn = $this->makeStudent(['locale' => 'bn', 'roll' => '202']);
        $this->actingAs($bn)->get(route('student.dashboard'))->assertOk()->assertSee('২০২');
    }

    public function test_content_stays_bengali_regardless_of_interface_language(): void
    {
        // A course title (content) must remain Bengali even in the English interface.
        $course = \App\Models\Course::factory()->create(['title' => 'সীরাত কোর্স', 'is_published' => true]);
        \App\Models\Lesson::factory()->for($course)->create(['is_published' => true, 'title' => 'সীরাত-০১']);
        $student = $this->makeStudent(['locale' => 'en']);

        $this->actingAs($student)->get(route('student.courses.index'))
            ->assertOk()
            ->assertSee('Courses & Lessons')   // chrome is English
            ->assertSee('সীরাত-০১');            // content stays Bengali
    }
}
