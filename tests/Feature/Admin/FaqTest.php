<?php

namespace Tests\Feature\Admin;

use App\Models\Faq;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FaqTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_creates_a_faq(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin)->post(route('admin.faqs.store'), [
            'question' => 'কীভাবে ভর্তি হব?', 'answer' => 'রেজিস্ট্রেশন ফর্ম পূরণ করুন।', 'is_published' => '1',
        ])->assertRedirect();

        $this->assertDatabaseHas('faqs', ['question' => 'কীভাবে ভর্তি হব?', 'is_published' => true]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'faq.created']);
    }

    public function test_admin_can_update_and_toggle_a_faq(): void
    {
        $admin = $this->makeAdmin();
        $faq = Faq::query()->create(['question' => 'Q', 'answer' => 'A', 'is_published' => true]);

        $this->actingAs($admin)->put(route('admin.faqs.update', $faq), [
            'question' => 'Updated Q', 'answer' => 'Updated A', 'sort_order' => 5,
        ])->assertRedirect();
        $this->assertSame('Updated Q', $faq->fresh()->question);
        $this->assertFalse($faq->fresh()->is_published, 'unchecked checkbox unpublishes');

        $this->actingAs($admin)->put(route('admin.faqs.toggle', $faq))->assertRedirect();
        $this->assertTrue($faq->fresh()->is_published);
    }

    public function test_admin_can_delete_a_faq(): void
    {
        $admin = $this->makeAdmin();
        $faq = Faq::query()->create(['question' => 'Q', 'answer' => 'A']);

        $this->actingAs($admin)->delete(route('admin.faqs.destroy', $faq))->assertRedirect();

        $this->assertDatabaseMissing('faqs', ['id' => $faq->id]);
    }

    public function test_only_published_faqs_appear_on_the_homepage(): void
    {
        Faq::query()->create(['question' => 'প্রকাশিত প্রশ্ন', 'answer' => 'A', 'is_published' => true, 'sort_order' => 1]);
        Faq::query()->create(['question' => 'অপ্রকাশিত প্রশ্ন', 'answer' => 'A', 'is_published' => false, 'sort_order' => 2]);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('প্রকাশিত প্রশ্ন')
            ->assertDontSee('অপ্রকাশিত প্রশ্ন');
    }

    public function test_a_student_cannot_manage_faqs(): void
    {
        $this->actingAs($this->makeStudent())->get(route('admin.faqs.index'))->assertForbidden();
        $this->actingAs($this->makeStudent())->post(route('admin.faqs.store'), ['question' => 'x', 'answer' => 'y'])->assertForbidden();
    }
}
