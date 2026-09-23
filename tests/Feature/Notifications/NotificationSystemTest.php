<?php

namespace Tests\Feature\Notifications;

use App\Models\Amol;
use App\Models\AppNotification;
use App\Models\Permission;
use App\Services\Amol\AmolService;
use App\Services\Messaging\MessageService;
use App\Services\Notifications\NotificationService;
use Carbon\CarbonImmutable;
use Database\Seeders\AmolSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationSystemTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AmolSeeder::class);
    }

    private function messages(): MessageService
    {
        return app(MessageService::class);
    }

    private function amol(): AmolService
    {
        return app(AmolService::class);
    }

    public function test_a_students_message_notifies_every_admin_who_can_see_the_inbox(): void
    {
        // super_admin so detaching messages.view from the (shared) admin role below,
        // for $blindAdmin, cannot also strip it from this one.
        $studentAdmin = $this->makeSuperAdmin();
        $blindAdmin = $this->makeAdmin();
        $blindAdmin->roles->first()->permissions()->detach(
            Permission::query()->where('name', 'messages.view')->value('id')
        );
        $student = $this->makeStudent(['name' => 'প্রশ্নকারী']);
        $conversation = $this->messages()->threadFor($student);

        $this->messages()->send($conversation, $student, 'আসসালামু আলাইকুম');

        $this->assertDatabaseHas('app_notifications', [
            'user_id' => $studentAdmin->id, 'type' => 'message', 'subject_type' => 'conversation', 'subject_id' => $conversation->id,
        ]);
        // An admin without messages.view is never fanned out to.
        $this->assertDatabaseMissing('app_notifications', ['user_id' => $blindAdmin->id]);
    }

    public function test_an_ustaz_reply_notifies_only_that_one_student(): void
    {
        $admin = $this->makeAdmin();
        $me = $this->makeStudent();
        $someoneElse = $this->makeStudent();
        $conversation = $this->messages()->threadFor($me);

        $this->messages()->send($conversation, $admin, 'ওয়া আলাইকুমুস সালাম');

        $this->assertDatabaseHas('app_notifications', ['user_id' => $me->id, 'type' => 'message']);
        $this->assertDatabaseMissing('app_notifications', ['user_id' => $someoneElse->id]);
    }

    public function test_several_messages_in_one_thread_collapse_into_a_single_notification_row(): void
    {
        $admin = $this->makeAdmin();
        $student = $this->makeStudent();
        $conversation = $this->messages()->threadFor($student);

        $this->messages()->send($conversation, $student, 'এক');
        $this->messages()->send($conversation, $student, 'দুই');
        $this->messages()->send($conversation, $student, 'তিন');

        $this->assertSame(1, AppNotification::query()->where('user_id', $admin->id)->count());
        $this->assertDatabaseHas('app_notifications', ['user_id' => $admin->id, 'body' => 'তিন']);
    }

    public function test_opening_the_thread_clears_only_that_admins_own_notification(): void
    {
        $adminA = $this->makeAdmin();
        $adminB = $this->makeAdmin();
        $student = $this->makeStudent();
        $conversation = $this->messages()->threadFor($student);
        $this->messages()->send($conversation, $student, 'প্রশ্ন আছে');

        $this->actingAs($adminA)->get(route('admin.messages.show', $conversation));

        $this->assertNotNull(AppNotification::query()->where('user_id', $adminA->id)->first()->read_at);
        $this->assertNull(AppNotification::query()->where('user_id', $adminB->id)->first()->read_at);
    }

    public function test_opening_the_student_message_screen_clears_the_bell_notification(): void
    {
        $admin = $this->makeAdmin();
        $student = $this->makeStudent();
        $conversation = $this->messages()->threadFor($student);
        $this->messages()->send($conversation, $admin, 'উত্তর দিচ্ছি');

        $this->assertSame(1, app(NotificationService::class)->unreadCountFor($student));

        $this->actingAs($student)->get(route('student.messages.index'));

        $this->assertSame(0, app(NotificationService::class)->unreadCountFor($student->fresh()));
    }

    public function test_an_amol_note_notifies_the_student_and_is_cleared_by_viewing_that_date(): void
    {
        $admin = $this->makeAdmin();
        $student = $this->makeStudent();
        $date = CarbonImmutable::now()->toDateString();

        $this->amol()->saveNote($student, CarbonImmutable::now(), 'চমৎকার হয়েছে মাশাআল্লাহ', $admin);

        $this->assertDatabaseHas('app_notifications', [
            'user_id' => $student->id, 'type' => 'amol_note', 'subject_type' => 'amol_day_note',
        ]);
        $notifications = app(NotificationService::class);
        $this->assertSame(1, $notifications->unreadCountFor($student));

        $this->actingAs($student)->get(route('student.amol.index', ['date' => $date]));

        $this->assertSame(0, $notifications->unreadCountFor($student->fresh()));
    }

    public function test_the_bell_poll_endpoint_returns_the_count_and_recent_list(): void
    {
        $admin = $this->makeAdmin();
        $student = $this->makeStudent();
        $this->messages()->send($this->messages()->threadFor($student), $admin, 'হ্যালো');

        $response = $this->actingAs($student)->getJson(route('notifications.poll'));

        $response->assertOk()->assertJson(['count' => 1]);
        $response->assertJsonCount(1, 'notifications');
        $this->assertSame('উস্তায আপনাকে একটি নতুন মেসেজ পাঠিয়েছেন', $response->json('notifications.0.title'));
        $this->assertFalse($response->json('notifications.0.read'));
    }

    public function test_opening_a_notification_marks_it_read_and_redirects_to_its_url(): void
    {
        $admin = $this->makeAdmin();
        $student = $this->makeStudent();
        $this->messages()->send($this->messages()->threadFor($student), $admin, 'হ্যালো');
        $notification = AppNotification::query()->where('user_id', $student->id)->sole();

        $this->actingAs($student)->get(route('notifications.open', $notification))
            ->assertRedirect(route('student.messages.index'));

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_a_user_cannot_open_someone_elses_notification(): void
    {
        $admin = $this->makeAdmin();
        $student = $this->makeStudent();
        $other = $this->makeStudent();
        $this->messages()->send($this->messages()->threadFor($student), $admin, 'হ্যালো');
        $notification = AppNotification::query()->where('user_id', $student->id)->sole();

        $this->actingAs($other)->get(route('notifications.open', $notification))->assertForbidden();
        $this->assertNull($notification->fresh()->read_at);
    }

    public function test_a_user_cannot_delete_someone_elses_notification(): void
    {
        $admin = $this->makeAdmin();
        $student = $this->makeStudent();
        $other = $this->makeStudent();
        $this->messages()->send($this->messages()->threadFor($student), $admin, 'হ্যালো');
        $notification = AppNotification::query()->where('user_id', $student->id)->sole();

        $this->actingAs($other)->delete(route('notifications.destroy', $notification))->assertForbidden();
        $this->assertDatabaseHas('app_notifications', ['id' => $notification->id]);
    }

    public function test_a_user_can_delete_their_own_notification_facebook_style(): void
    {
        $admin = $this->makeAdmin();
        $student = $this->makeStudent();
        $this->messages()->send($this->messages()->threadFor($student), $admin, 'হ্যালো');
        $notification = AppNotification::query()->where('user_id', $student->id)->sole();

        $this->actingAs($student)->delete(route('notifications.destroy', $notification))->assertRedirect();

        $this->assertDatabaseMissing('app_notifications', ['id' => $notification->id]);
    }

    public function test_mark_all_read_clears_every_unread_notification_for_that_user_only(): void
    {
        $admin = $this->makeAdmin();
        $student = $this->makeStudent();
        $other = $this->makeStudent();
        $this->amol()->saveNote($student, CarbonImmutable::now(), 'এক', $admin);
        $this->messages()->send($this->messages()->threadFor($student), $admin, 'দুই');
        $this->messages()->send($this->messages()->threadFor($other), $admin, 'অন্য ছাত্রের জন্য');

        $this->actingAs($student)->put(route('notifications.read-all'))->assertRedirect();

        $this->assertSame(0, AppNotification::query()->where('user_id', $student->id)->unread()->count());
        $this->assertSame(1, AppNotification::query()->where('user_id', $other->id)->unread()->count());
    }

    public function test_the_see_all_page_lists_notifications_and_uses_the_viewers_own_layout(): void
    {
        $admin = $this->makeAdmin();
        $student = $this->makeStudent();
        $this->messages()->send($this->messages()->threadFor($student), $admin, 'হ্যালো');

        $this->actingAs($student)->get(route('notifications.index'))
            ->assertOk()->assertSee(__('notifications.heading'))->assertSee('উস্তায আপনাকে একটি নতুন মেসেজ পাঠিয়েছেন');

        $this->actingAs($admin)->get(route('notifications.index'))->assertOk();
    }

    public function test_guests_cannot_reach_any_notification_route(): void
    {
        $this->get(route('notifications.index'))->assertRedirect(route('login'));
    }

    public function test_toggling_an_amol_item_creates_no_notification(): void
    {
        // Only the day's NOTE is notifiable; a student checking their own box is not
        // something anyone else needs to be pinged about.
        $student = $this->makeStudent();
        $amol = Amol::query()->first();

        $this->actingAs($student)->putJson(route('student.amol.toggle', $amol));

        $this->assertSame(0, AppNotification::query()->count());
    }
}
