<?php

namespace Tests\Feature\Messaging;

use App\Models\AuditLog;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Permission;
use App\Models\User;
use App\Services\Messaging\MessageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MessagingTest extends TestCase
{
    use RefreshDatabase;

    private function messages(): MessageService
    {
        return app(MessageService::class);
    }

    /**
     * An admin who may open the shared inbox. The admin role carries every permission
     * (RoleSeeder), so messages.view needs no extra grant here.
     */
    private function inboxAdmin(): User
    {
        return $this->makeAdmin();
    }

    public function test_a_student_can_send_a_message_which_creates_their_thread(): void
    {
        $student = $this->makeStudent();

        $this->actingAs($student)
            ->post(route('student.messages.store'), ['body' => 'আসসালামু আলাইকুম উস্তায'])
            ->assertRedirect();

        $conversation = Conversation::query()->firstOrFail();
        $this->assertSame($student->id, $conversation->user_id);
        $this->assertSame('আসসালামু আলাইকুম উস্তায', $conversation->messages()->sole()->body);
        $this->assertNotNull($conversation->last_message_at);
    }

    public function test_the_student_screen_renders_the_history(): void
    {
        $student = $this->makeStudent();
        $conversation = $this->messages()->threadFor($student);
        $this->messages()->send($conversation, $student, 'আমার একটি প্রশ্ন আছে');

        $this->actingAs($student)
            ->get(route('student.messages.index'))
            ->assertOk()
            ->assertSee('আমার একটি প্রশ্ন আছে');
    }

    public function test_an_admin_sees_the_thread_in_the_inbox_and_can_reply(): void
    {
        $student = $this->makeStudent(['name' => 'প্রশ্নকারী ছাত্র']);
        $conversation = $this->messages()->threadFor($student);
        $this->messages()->send($conversation, $student, 'যাকাত সম্পর্কে জানতে চাই');

        $admin = $this->inboxAdmin();

        $this->actingAs($admin)->get(route('admin.messages.index'))
            ->assertOk()
            ->assertSee('প্রশ্নকারী ছাত্র')
            ->assertSee('যাকাত সম্পর্কে জানতে চাই');

        $this->actingAs($admin)
            ->post(route('admin.messages.store', $conversation), ['body' => 'ওয়া আলাইকুমুস সালাম'])
            ->assertRedirect();

        $this->assertSame(2, $conversation->messages()->count());
        $this->assertSame($admin->id, $conversation->messages()->latest('id')->first()->sender_id);
    }

    public function test_unread_counts_track_each_side_independently(): void
    {
        $student = $this->makeStudent();
        $admin = $this->inboxAdmin();
        $conversation = $this->messages()->threadFor($student);

        // The student writes: unread for the ustaz side, not for the student.
        $this->messages()->send($conversation, $student, 'প্রশ্ন');
        $this->assertSame(1, $this->messages()->unreadCountFor($admin));
        $this->assertSame(0, $this->messages()->unreadCountFor($student));

        // The admin opens the thread — that is what clears it.
        $this->actingAs($admin)->get(route('admin.messages.show', $conversation))->assertOk();
        $this->assertSame(0, $this->messages()->unreadCountFor($admin->fresh()));

        // The admin replies: now unread for the student.
        $this->messages()->send($conversation, $admin, 'উত্তর');
        $this->assertSame(1, $this->messages()->unreadCountFor($student));
        $this->assertSame(0, $this->messages()->unreadCountFor($admin->fresh()));

        $this->actingAs($student)->get(route('student.messages.index'))->assertOk();
        $this->assertSame(0, $this->messages()->unreadCountFor($student->fresh()));
    }

    public function test_the_unread_badge_appears_in_the_student_navigation(): void
    {
        $student = $this->makeStudent();
        $admin = $this->inboxAdmin();
        $conversation = $this->messages()->threadFor($student);
        $this->messages()->send($conversation, $admin, 'উস্তাযের উত্তর');

        // Any student page carries the sidebar, so the badge is visible on all of them.
        $this->actingAs($student)->get(route('student.dashboard'))
            ->assertOk()
            ->assertSee('rounded-full bg-brand text-brand-ink', false);
    }

    public function test_polling_returns_only_messages_after_the_given_id(): void
    {
        $student = $this->makeStudent();
        $admin = $this->inboxAdmin();
        $conversation = $this->messages()->threadFor($student);

        $first = $this->messages()->send($conversation, $student, 'প্রথম');
        $second = $this->messages()->send($conversation, $admin, 'দ্বিতীয়');

        $response = $this->actingAs($student)
            ->getJson(route('student.messages.poll', ['after' => $first->id]))
            ->assertOk();

        $response->assertJsonCount(1, 'messages');
        $this->assertSame($second->id, $response->json('messages.0.id'));
        $this->assertFalse($response->json('messages.0.mine'));
    }

    public function test_a_student_only_ever_reaches_their_own_thread(): void
    {
        // The student routes carry no conversation id at all, so one student's poll can
        // never return another's messages even though both threads exist.
        $mine = $this->makeStudent();
        $other = $this->makeStudent();

        $theirs = $this->messages()->threadFor($other);
        $this->messages()->send($theirs, $other, 'অন্য ছাত্রের গোপন প্রশ্ন');

        $this->actingAs($mine)->get(route('student.messages.index'))
            ->assertOk()
            ->assertDontSee('অন্য ছাত্রের গোপন প্রশ্ন');

        $this->actingAs($mine)->getJson(route('student.messages.poll'))
            ->assertOk()
            ->assertJsonCount(0, 'messages');
    }

    public function test_a_student_cannot_open_the_admin_inbox(): void
    {
        $student = $this->makeStudent();
        $conversation = $this->messages()->threadFor($student);

        $this->actingAs($student)->get(route('admin.messages.index'))->assertForbidden();
        $this->actingAs($student)->get(route('admin.messages.show', $conversation))->assertForbidden();
    }

    public function test_the_inbox_is_gated_by_the_messages_permission(): void
    {
        // The gate is real, not decorative: revoke messages.view and the route closes,
        // which is how the inbox can later be narrowed to specific admins.
        $admin = $this->makeAdmin();
        $admin->roles->first()->permissions()->detach(
            Permission::query()->where('name', 'messages.view')->value('id')
        );

        $this->actingAs($admin)->get(route('admin.messages.index'))->assertForbidden();
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get(route('student.messages.index'))->assertRedirect(route('login'));
    }

    public function test_the_ustaz_can_start_a_thread_with_a_student_who_never_wrote_in(): void
    {
        $student = $this->makeStudent();
        $admin = $this->inboxAdmin();

        $this->assertSame(0, Conversation::query()->count());

        $this->actingAs($admin)
            ->post(route('admin.messages.start'), ['student_id' => $student->id])
            ->assertRedirect();

        $conversation = Conversation::query()->sole();
        $this->assertSame($student->id, $conversation->user_id);
    }

    public function test_starting_a_thread_is_idempotent(): void
    {
        $student = $this->makeStudent();
        $admin = $this->inboxAdmin();
        $this->messages()->threadFor($student);

        $this->actingAs($admin)->post(route('admin.messages.start'), ['student_id' => $student->id]);

        $this->assertSame(1, Conversation::query()->count());
    }

    public function test_a_thread_cannot_be_started_with_a_non_student(): void
    {
        $admin = $this->inboxAdmin();
        $someoneElse = $this->makeAdmin();

        $this->actingAs($admin)
            ->post(route('admin.messages.start'), ['student_id' => $someoneElse->id])
            ->assertNotFound();
    }

    public function test_deleting_a_thread_removes_its_messages_and_is_audited(): void
    {
        $student = $this->makeStudent();
        $admin = $this->inboxAdmin();
        $conversation = $this->messages()->threadFor($student);
        $this->messages()->send($conversation, $student, 'মুছে ফেলার মতো মেসেজ');

        $this->actingAs($admin)
            ->delete(route('admin.messages.destroy', $conversation))
            ->assertRedirect(route('admin.messages.index'));

        $this->assertSame(0, Conversation::query()->count());
        $this->assertSame(0, Message::query()->count());

        // The fact of the deletion is recorded; the message bodies are not.
        $audit = AuditLog::query()->where('action', 'message.thread_deleted')->sole();
        $this->assertSame($admin->id, $audit->user_id);
        $this->assertSame(1, $audit->before['messages_deleted']);
        $this->assertStringNotContainsString('মুছে ফেলার মতো মেসেজ', json_encode($audit->before, JSON_UNESCAPED_UNICODE));
    }

    public function test_an_empty_message_is_rejected(): void
    {
        $student = $this->makeStudent();

        $this->actingAs($student)
            ->post(route('student.messages.store'), ['body' => '   '])
            ->assertSessionHasErrors('body');

        $this->assertSame(0, Message::query()->count());
    }

    public function test_a_message_body_is_escaped_not_rendered_as_html(): void
    {
        $student = $this->makeStudent();
        $conversation = $this->messages()->threadFor($student);
        $this->messages()->send($conversation, $student, '<script>alert(1)</script>');

        $this->actingAs($student)->get(route('student.messages.index'))
            ->assertOk()
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
    }
}
