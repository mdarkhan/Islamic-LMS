<?php

namespace Tests\Feature\Notifications;

use App\Mail\ExamNotification;
use App\Models\AppNotification;
use App\Models\NotificationDelivery;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\User;
use App\Services\Notifications\ExamNotificationService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ExamNotificationTest extends TestCase
{
    use RefreshDatabase;

    private CarbonImmutable $now;

    protected function setUp(): void
    {
        parent::setUp();
        $this->now = CarbonImmutable::parse('2026-09-24 10:00', 'Asia/Dhaka');
    }

    private function student(?string $email = 'kid@example.com', bool $optIn = true): User
    {
        $s = $this->makeStudent();
        $s->forceFill(['email' => $email, 'notify_by_email' => $optIn])->save();

        return $s->fresh();
    }

    private function sweep(int $limit = 200): array
    {
        return app(ExamNotificationService::class)->run($this->now, $limit);
    }

    public function test_an_exam_opening_within_24h_notifies_everyone_and_emails_those_with_an_address(): void
    {
        Mail::fake();
        $quiz = Quiz::factory()->create(['starts_at' => $this->now->addHours(5), 'ends_at' => $this->now->addDays(2)]);
        $withMail = $this->student('a@example.com');
        $noMail = $this->student(null);
        $optedOut = $this->student('b@example.com', optIn: false);

        $r = $this->sweep();

        $this->assertSame(['notified' => 3, 'emailed' => 1], $r);
        Mail::assertSent(ExamNotification::class, 1);
        Mail::assertSent(ExamNotification::class, fn ($m) => $m->hasTo('a@example.com'));
        foreach ([$withMail, $noMail, $optedOut] as $s) {
            $this->assertDatabaseHas('app_notifications', ['user_id' => $s->id, 'type' => 'exam_soon']);
        }
    }

    public function test_it_is_once_only_however_often_it_runs(): void
    {
        Mail::fake();
        Quiz::factory()->create(['starts_at' => $this->now->addHours(5), 'ends_at' => $this->now->addDays(2)]);
        $this->student();

        $this->sweep();
        $second = $this->sweep();

        $this->assertSame(['notified' => 0, 'emailed' => 0], $second);
        Mail::assertSent(ExamNotification::class, 1);
        $this->assertSame(1, NotificationDelivery::count());
    }

    public function test_far_future_drafts_and_already_open_exams_do_not_send_the_opening_reminder(): void
    {
        Mail::fake();
        Quiz::factory()->create(['starts_at' => $this->now->addDays(3), 'ends_at' => $this->now->addDays(4)]);
        Quiz::factory()->create(['status' => Quiz::STATUS_DRAFT, 'starts_at' => $this->now->addHours(2), 'ends_at' => $this->now->addDays(2)]);
        Quiz::factory()->create(['starts_at' => $this->now->subHour(), 'ends_at' => $this->now->addDays(2)]);
        $this->student();

        $this->assertSame(['notified' => 0, 'emailed' => 0], $this->sweep());
    }

    public function test_the_closing_reminder_skips_students_who_already_started(): void
    {
        Mail::fake();
        $quiz = Quiz::factory()->create(['starts_at' => $this->now->subDay(), 'ends_at' => $this->now->addHours(2)]);
        $started = $this->student('s@example.com');
        $notStarted = $this->student('n@example.com');
        QuizAttempt::factory()->create(['quiz_id' => $quiz->id, 'user_id' => $started->id]);

        $this->sweep();

        $this->assertDatabaseHas('app_notifications', ['user_id' => $notStarted->id, 'type' => 'exam_closing']);
        $this->assertDatabaseMissing('app_notifications', ['user_id' => $started->id, 'type' => 'exam_closing']);
        Mail::assertSent(ExamNotification::class, fn ($m) => $m->hasTo('n@example.com'));
        Mail::assertNotSent(ExamNotification::class, fn ($m) => $m->hasTo('s@example.com'));
    }

    public function test_recently_released_results_notify_only_students_with_a_finished_attempt_and_carry_no_score(): void
    {
        Mail::fake();
        $quiz = Quiz::factory()->create(['starts_at' => $this->now->subDays(2), 'ends_at' => $this->now->subHours(3)]);
        $took = $this->student('t@example.com');
        $skipped = $this->student('k@example.com');
        QuizAttempt::factory()->create(['quiz_id' => $quiz->id, 'user_id' => $took->id, 'final_score' => 7]);

        $this->sweep();

        $this->assertDatabaseHas('app_notifications', ['user_id' => $took->id, 'type' => 'results']);
        $this->assertDatabaseMissing('app_notifications', ['user_id' => $skipped->id, 'type' => 'results']);
        Mail::assertSent(ExamNotification::class, function (ExamNotification $m) {
            return ! str_contains($m->line.$m->headline.$m->mailSubject, '৭') && ! str_contains($m->line, '7');
        });
    }

    public function test_old_released_quizzes_never_trigger_a_flood(): void
    {
        Mail::fake();
        $quiz = Quiz::factory()->create(['status' => Quiz::STATUS_ARCHIVED, 'starts_at' => $this->now->subMonths(6), 'ends_at' => $this->now->subMonths(6)->addHour()]);
        $s = $this->student();
        QuizAttempt::factory()->create(['quiz_id' => $quiz->id, 'user_id' => $s->id]);

        $this->assertSame(['notified' => 0, 'emailed' => 0], $this->sweep());
    }

    public function test_a_failing_mailbox_keeps_the_bell_copy_and_does_not_retry_forever_or_stop_others(): void
    {
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('smtp down'));
        Quiz::factory()->create(['starts_at' => $this->now->addHours(5), 'ends_at' => $this->now->addDays(2)]);
        $this->student('a@example.com');
        $this->student('b@example.com');

        $r = $this->sweep();

        $this->assertSame(['notified' => 2, 'emailed' => 0], $r);
        $this->assertSame(2, AppNotification::where('type', 'exam_soon')->count());
        $this->assertSame(['notified' => 0, 'emailed' => 0], $this->sweep());
        $this->assertSame(0, NotificationDelivery::where('emailed', true)->count());
    }

    public function test_the_per_run_limit_defers_the_rest_to_the_next_run(): void
    {
        Mail::fake();
        Quiz::factory()->create(['starts_at' => $this->now->addHours(5), 'ends_at' => $this->now->addDays(2)]);
        foreach (range(1, 5) as $i) {
            $this->student("s$i@example.com");
        }

        $this->assertSame(3, $this->sweep(3)['notified']);
        $this->assertSame(2, $this->sweep(3)['notified']);
        $this->assertSame(0, $this->sweep(3)['notified']);
    }

    public function test_suspended_students_are_not_notified(): void
    {
        Mail::fake();
        Quiz::factory()->create(['starts_at' => $this->now->addHours(5), 'ends_at' => $this->now->addDays(2)]);
        $s = $this->student();
        $s->forceFill(['status' => User::STATUS_SUSPENDED])->save();

        $this->assertSame(0, $this->sweep()['notified']);
    }

    public function test_the_command_runs_and_the_profile_toggle_persists(): void
    {
        $this->artisan('exams:send-notifications')->assertSuccessful();

        $s = $this->student();
        $this->actingAs($s)->put(route('student.profile.update'), ['email' => 'kid@example.com'])->assertRedirect();
        $this->assertFalse($s->fresh()->notify_by_email);

        $this->actingAs($s)->put(route('student.profile.update'), ['email' => 'kid@example.com', 'notify_by_email' => '1'])->assertRedirect();
        $this->assertTrue($s->fresh()->notify_by_email);
    }
}
