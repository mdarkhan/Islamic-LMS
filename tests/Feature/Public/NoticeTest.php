<?php

namespace Tests\Feature\Public;

use App\Models\Notice;
use App\Models\Permission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NoticeTest extends TestCase
{
    use RefreshDatabase;

    private function notice(array $o = []): Notice
    {
        return Notice::query()->create(array_merge([
            'body' => 'A notice', 'is_active' => true, 'priority' => 0, 'audience' => Notice::AUDIENCE_PUBLIC,
        ], $o));
    }

    public function test_an_active_public_notice_shows_on_the_homepage(): void
    {
        $this->notice(['body' => 'রমজানের ক্লাস শুরু']);
        $this->get(route('home'))->assertOk()->assertSee('রমজানের ক্লাস শুরু');
    }

    public function test_future_and_expired_notices_are_hidden(): void
    {
        $this->notice(['body' => 'Future notice', 'starts_at' => now()->addDay()]);
        $this->notice(['body' => 'Expired notice', 'ends_at' => now()->subDay()]);
        $this->notice(['body' => 'Inactive notice', 'is_active' => false]);

        $this->get(route('home'))
            ->assertOk()
            ->assertDontSee('Future notice')
            ->assertDontSee('Expired notice')
            ->assertDontSee('Inactive notice');
    }

    public function test_window_uses_server_time_without_cron(): void
    {
        $this->notice(['body' => 'Just started', 'starts_at' => now()->subMinute(), 'ends_at' => now()->addHour()]);
        $this->notice(['body' => 'Not yet', 'starts_at' => now()->addMinute()]);

        $this->get(route('home'))->assertOk()->assertSee('Just started')->assertDontSee('Not yet');
    }

    public function test_higher_priority_notices_come_first(): void
    {
        $this->notice(['body' => 'Low priority', 'priority' => 1]);
        $this->notice(['body' => 'High priority', 'priority' => 10]);

        $content = $this->get(route('home'))->assertOk()->getContent();
        $this->assertTrue(strpos($content, 'High priority') < strpos($content, 'Low priority'));
    }

    public function test_audience_is_respected(): void
    {
        $this->notice(['body' => 'Public only', 'audience' => Notice::AUDIENCE_PUBLIC]);
        $this->notice(['body' => 'Students only', 'audience' => Notice::AUDIENCE_STUDENTS]);
        $this->notice(['body' => 'Everyone notice', 'audience' => Notice::AUDIENCE_ALL]);

        // Homepage: public + all, not students-only.
        $this->get(route('home'))
            ->assertOk()->assertSee('Public only')->assertSee('Everyone notice')->assertDontSee('Students only');

        // Student dashboard: students + all, not public-only.
        $this->actingAs($this->makeStudent())->get(route('student.dashboard'))
            ->assertOk()->assertSee('Students only')->assertSee('Everyone notice')->assertDontSee('Public only');
    }

    public function test_notice_management_requires_permission(): void
    {
        $this->actingAs($this->makeStudent())->get(route('admin.notices.index'))->assertForbidden();

        $admin = $this->makeAdmin();
        $this->actingAs($admin)->get(route('admin.notices.index'))->assertOk();

        $admin->roles()->first()->permissions()->detach(Permission::query()->where('name', 'notices.manage')->pluck('id'));
        $this->actingAs($admin->fresh())->get(route('admin.notices.index'))->assertForbidden();
    }

    public function test_end_before_start_is_rejected(): void
    {
        $this->actingAs($this->makeAdmin())->post(route('admin.notices.store'), [
            'body' => 'bad window', 'priority' => 0, 'audience' => 'all',
            'starts_at' => now()->addDay()->format('Y-m-d\TH:i'),
            'ends_at' => now()->format('Y-m-d\TH:i'),
        ])->assertSessionHasErrors('ends_at');
    }
}
