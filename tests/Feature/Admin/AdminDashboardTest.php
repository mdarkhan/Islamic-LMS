<?php

namespace Tests\Feature\Admin;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\Quiz;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_dashboard_flags_unread_messages(): void
    {
        $admin = $this->makeAdmin();
        $student = $this->makeStudent();
        $conversation = Conversation::query()->create(['user_id' => $student->id]);
        Message::query()->create(['conversation_id' => $conversation->id, 'sender_id' => $student->id, 'body' => 'প্রশ্ন আছে']);

        $this->actingAs($admin)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee(__('dashboard.attention_messages_heading'));
    }

    public function test_the_dashboard_is_quiet_when_nothing_needs_attention(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertDontSee(__('dashboard.attention_messages_heading'))
            ->assertDontSee(__('dashboard.attention_release_heading'));
    }

    public function test_the_dashboard_flags_quizzes_pending_release(): void
    {
        $admin = $this->makeAdmin();
        Quiz::factory()->create(['ends_at' => now()->subHour(), 'result_release_at' => now()->addDay()]);

        $this->actingAs($admin)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee(__('dashboard.attention_release_heading'));
    }

    public function test_quick_actions_include_new_quiz_and_new_notice_shortcuts(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee(__('dashboard.new_quiz'))
            ->assertSee(__('dashboard.new_notice'));
    }
}
