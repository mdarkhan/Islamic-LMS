<?php

namespace Tests\Feature\Amol;

use App\Models\Amol;
use App\Models\AmolDayNote;
use App\Models\AmolEntry;
use App\Models\Permission;
use App\Models\User;
use App\Services\Amol\AmolService;
use Carbon\CarbonImmutable;
use Database\Seeders\AmolSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AmolTrackerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AmolSeeder::class);
    }

    private function inboxAdmin(): User
    {
        return $this->makeAdmin();
    }

    public function test_the_catalog_seeds_all_twenty_four_items_in_order(): void
    {
        $this->assertSame(24, Amol::query()->count());
        $this->assertSame('fajr_sunnah', Amol::query()->ordered()->first()->key);
        $this->assertSame('parents_obedience', Amol::query()->ordered()->get()->last()->key);
    }

    public function test_a_student_sees_todays_checklist_fully_unchecked(): void
    {
        $student = $this->makeStudent();

        $response = $this->actingAs($student)->get(route('student.amol.index'));

        $response->assertOk()->assertSee('ফজরের নামাজ')->assertSee('মা-বাবার অবাধ্য না হওয়া');
        $this->assertSame(0, AmolEntry::query()->count());
    }

    public function test_a_student_can_toggle_an_item_for_today(): void
    {
        $student = $this->makeStudent();
        $amol = Amol::query()->where('key', 'fajr_prayer')->firstOrFail();

        $response = $this->actingAs($student)->putJson(route('student.amol.toggle', $amol));

        $response->assertOk()->assertJson(['is_done' => true]);
        $this->assertDatabaseHas('amol_entries', [
            'user_id' => $student->id, 'amol_id' => $amol->id,
            'date' => CarbonImmutable::now()->toDateString(), 'is_done' => 1,
        ]);
    }

    public function test_toggling_twice_clears_the_checkmark(): void
    {
        $student = $this->makeStudent();
        $amol = Amol::query()->where('key', 'witr_prayer')->firstOrFail();

        $this->actingAs($student)->putJson(route('student.amol.toggle', $amol))->assertJson(['is_done' => true]);
        $this->actingAs($student)->putJson(route('student.amol.toggle', $amol))->assertJson(['is_done' => false]);

        $this->assertSame(1, AmolEntry::query()->count(), 'the same row flips, never accumulates');
    }

    public function test_toggling_a_retired_amol_is_refused(): void
    {
        $student = $this->makeStudent();
        $amol = Amol::query()->create(['key' => 'retired_item', 'label' => 'অবসরপ্রাপ্ত', 'sort_order' => 99, 'is_active' => false]);

        $this->actingAs($student)->putJson(route('student.amol.toggle', $amol))->assertNotFound();
    }

    public function test_one_students_toggle_never_affects_another_students_entry(): void
    {
        $alice = $this->makeStudent();
        $bob = $this->makeStudent();
        $amol = Amol::query()->where('key', 'miswak')->firstOrFail();

        $this->actingAs($alice)->putJson(route('student.amol.toggle', $amol))->assertJson(['is_done' => true]);

        $this->actingAs($bob)->get(route('student.amol.index'))->assertOk();
        $this->assertFalse(
            AmolEntry::query()->where('user_id', $bob->id)->where('amol_id', $amol->id)->exists()
        );
    }

    public function test_a_past_days_entries_are_frozen_once_the_day_passes(): void
    {
        $student = $this->makeStudent();
        $amol = Amol::query()->where('key', 'ayatul_kursi')->firstOrFail();

        CarbonImmutable::setTestNow('2026-09-10 08:00:00');
        $this->actingAs($student)->putJson(route('student.amol.toggle', $amol))->assertJson(['is_done' => true]);

        // A day passes. The toggle route STILL has no date to target — it can only ever
        // write "today" as the server now sees it, so this creates a NEW row for the new
        // day rather than touching yesterday's.
        CarbonImmutable::setTestNow('2026-09-11 08:00:00');
        $this->actingAs($student)->putJson(route('student.amol.toggle', $amol))->assertJson(['is_done' => true]);

        $this->assertDatabaseHas('amol_entries', ['user_id' => $student->id, 'amol_id' => $amol->id, 'date' => '2026-09-10', 'is_done' => 1]);
        $this->assertDatabaseHas('amol_entries', ['user_id' => $student->id, 'amol_id' => $amol->id, 'date' => '2026-09-11', 'is_done' => 1]);
        $this->assertSame(2, AmolEntry::query()->count());

        CarbonImmutable::setTestNow();
    }

    public function test_viewing_a_past_date_renders_the_checklist_read_only(): void
    {
        $student = $this->makeStudent();
        $amol = Amol::query()->where('key', 'teen_tasbih')->firstOrFail();

        CarbonImmutable::setTestNow('2026-09-10 08:00:00');
        $this->actingAs($student)->putJson(route('student.amol.toggle', $amol));

        CarbonImmutable::setTestNow('2026-09-11 08:00:00');
        $response = $this->actingAs($student)->get(route('student.amol.index', ['date' => '2026-09-10']));

        $response->assertOk()
            ->assertSee(__('amol.view_only'))
            // Read-only rows never carry a toggle URL for the client to POST to.
            ->assertDontSee(route('student.amol.toggle', $amol), false);

        CarbonImmutable::setTestNow();
    }

    public function test_a_future_date_query_is_silently_clamped_to_today(): void
    {
        $student = $this->makeStudent();
        $today = CarbonImmutable::now();

        $response = $this->actingAs($student)->get(route('student.amol.index', ['date' => $today->addYear()->toDateString()]));

        $response->assertOk()->assertDontSee(__('amol.view_only'));
    }

    public function test_the_progress_line_counts_only_active_checked_items(): void
    {
        $student = $this->makeStudent();
        $first = Amol::query()->ordered()->first();

        $this->actingAs($student)->putJson(route('student.amol.toggle', $first));

        $response = $this->actingAs($student)->get(route('student.amol.index'));

        $response->assertOk()->assertSee(__('amol.progress', ['done' => bn(1), 'total' => bn(24)]));
    }

    public function test_amol_labels_stay_bengali_in_the_english_interface(): void
    {
        $student = $this->makeStudent(['locale' => 'en']);

        $this->actingAs($student)->get(route('student.amol.index'))
            ->assertOk()
            ->assertSee('Daily Amol Tracker')   // chrome, translated
            ->assertSee('ফজরের নামাজ');          // content, never translated
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get(route('student.amol.index'))->assertRedirect(route('login'));

        // A JSON request gets 401, not a redirect — the standard Laravel behaviour for
        // an unauthenticated request that asked for application/json.
        $amol = Amol::query()->first();
        $this->putJson(route('student.amol.toggle', $amol))->assertUnauthorized();
    }

    public function test_an_admin_with_permission_can_browse_and_search_students(): void
    {
        $admin = $this->inboxAdmin();
        $student = $this->makeStudent(['name' => 'তালিকাভুক্ত ছাত্র']);

        $this->actingAs($admin)->get(route('admin.amol.index'))->assertOk()->assertSee('তালিকাভুক্ত ছাত্র');
        $this->actingAs($admin)->get(route('admin.amol.index', ['q' => 'তালিকাভুক্ত']))
            ->assertOk()->assertSee('তালিকাভুক্ত ছাত্র');
    }

    public function test_an_admin_sees_the_students_checklist_read_only(): void
    {
        $admin = $this->inboxAdmin();
        $student = $this->makeStudent();
        $amol = Amol::query()->where('key', 'fajr_sunnah')->firstOrFail();

        $this->actingAs($student)->putJson(route('student.amol.toggle', $amol));

        $response = $this->actingAs($admin)->get(route('admin.amol.show', $student));

        $response->assertOk()
            ->assertSee('ফজরের সুন্নাত')
            // The admin screen never renders a toggle endpoint — it cannot write a checkmark.
            ->assertDontSee(route('student.amol.toggle', $amol), false);
    }

    public function test_an_admin_can_save_a_note_and_the_student_sees_it(): void
    {
        $admin = $this->inboxAdmin();
        $student = $this->makeStudent();
        $date = CarbonImmutable::now()->subDay()->toDateString();

        $this->actingAs($admin)->post(route('admin.amol.note', $student), [
            'date' => $date, 'note' => 'মাশাআল্লাহ, আজকের আমলনামা চমৎকার হয়েছে।',
        ])->assertRedirect();

        $this->assertDatabaseHas('amol_day_notes', [
            'user_id' => $student->id, 'date' => $date, 'commented_by' => $admin->id,
        ]);

        $this->actingAs($student)->get(route('student.amol.index', ['date' => $date]))
            ->assertOk()->assertSee('মাশাআল্লাহ, আজকের আমলনামা চমৎকার হয়েছে।');
    }

    public function test_a_note_cannot_be_dated_in_the_future(): void
    {
        $admin = $this->inboxAdmin();
        $student = $this->makeStudent();
        $tomorrow = CarbonImmutable::now()->addDay()->toDateString();

        $this->actingAs($admin)->post(route('admin.amol.note', $student), [
            'date' => $tomorrow, 'note' => 'ভবিষ্যতের মন্তব্য',
        ])->assertSessionHasErrors('date');

        $this->assertDatabaseMissing('amol_day_notes', ['user_id' => $student->id, 'date' => $tomorrow]);
    }

    public function test_re_saving_a_note_for_the_same_day_updates_it_rather_than_duplicating(): void
    {
        $admin = $this->inboxAdmin();
        $student = $this->makeStudent();
        $date = CarbonImmutable::now()->toDateString();

        $this->actingAs($admin)->post(route('admin.amol.note', $student), ['date' => $date, 'note' => 'প্রথম মন্তব্য']);
        $this->actingAs($admin)->post(route('admin.amol.note', $student), ['date' => $date, 'note' => 'সংশোধিত মন্তব্য']);

        $this->assertSame(1, AmolDayNote::query()->count());
        $this->assertDatabaseHas('amol_day_notes', ['user_id' => $student->id, 'date' => $date, 'note' => 'সংশোধিত মন্তব্য']);
    }

    public function test_the_inbox_is_gated_by_the_amol_permission(): void
    {
        $admin = $this->makeAdmin();
        $admin->roles->first()->permissions()->detach(
            Permission::query()->where('name', 'amol.view')->value('id')
        );
        $student = $this->makeStudent();

        $this->actingAs($admin)->get(route('admin.amol.index'))->assertForbidden();
        $this->actingAs($admin)->get(route('admin.amol.show', $student))->assertForbidden();
        $this->actingAs($admin)->post(route('admin.amol.note', $student), ['date' => CarbonImmutable::now()->toDateString(), 'note' => 'x'])
            ->assertForbidden();
    }

    public function test_a_student_cannot_reach_any_admin_amol_route(): void
    {
        $student = $this->makeStudent();
        $other = $this->makeStudent();

        $this->actingAs($student)->get(route('admin.amol.index'))->assertForbidden();
        $this->actingAs($student)->get(route('admin.amol.show', $other))->assertForbidden();
    }

    public function test_the_five_prayers_partition_into_their_groups_in_order(): void
    {
        $checklist = Amol::query()->active()->ordered()->get()
            ->map(fn (Amol $amol) => ['amol' => $amol, 'is_done' => false]);

        $parts = Amol::partition($checklist);

        $this->assertSame(['fajr', 'zuhr', 'asr', 'maghrib', 'isha'], $parts['grouped']->keys()->all());
        $this->assertSame(
            ['fajr_sunnah', 'fajr_prayer', 'fajr_takbir_e_oola'],
            $parts['grouped']['fajr']->pluck('amol.key')->all(),
        );
        $this->assertSame(
            ['zuhr_before_sunnah', 'zuhr_prayer', 'zuhr_takbir_e_oola', 'zuhr_after_sunnah'],
            $parts['grouped']['zuhr']->pluck('amol.key')->all(),
        );
        $this->assertSame(['asr_prayer', 'asr_takbir_e_oola'], $parts['grouped']['asr']->pluck('amol.key')->all());
        $this->assertSame(
            ['isha_prayer', 'isha_takbir_e_oola', 'isha_sunnah', 'witr_prayer'],
            $parts['grouped']['isha']->pluck('amol.key')->all(),
        );

        // Everything else — teen tasbih onward — stays flat, ungrouped, in its original order.
        $this->assertSame('teen_tasbih', $parts['ungrouped']->first()['amol']->key);
        $this->assertSame('parents_obedience', $parts['ungrouped']->last()['amol']->key);
        $this->assertCount(8, $parts['ungrouped']);

        // Nothing is dropped or duplicated by partitioning.
        $regrouped = $parts['grouped']->flatten(1)->concat($parts['ungrouped']);
        $this->assertSame(24, $regrouped->count());
    }

    public function test_the_student_screen_renders_prayer_groups_as_dropdowns(): void
    {
        $student = $this->makeStudent();

        $response = $this->actingAs($student)->get(route('student.amol.index'));

        $response->assertOk()
            ->assertSee(__('amol.group_fajr'))
            ->assertSee(__('amol.group_isha'))
            // A native <details> accordion — no JS is required to open/close a group.
            ->assertSee('<details', false)
            ->assertSee('বিতরের নামাজ')   // witr sits inside the Isha dropdown
            ->assertSee('তিন তাসবীহ');    // ungrouped items still render flat
    }

    public function test_checking_a_grouped_item_still_works_normally(): void
    {
        $student = $this->makeStudent();
        $amol = Amol::query()->where('key', 'zuhr_takbir_e_oola')->firstOrFail();

        $this->actingAs($student)->putJson(route('student.amol.toggle', $amol))
            ->assertOk()->assertJson(['is_done' => true]);

        $this->assertDatabaseHas('amol_entries', ['user_id' => $student->id, 'amol_id' => $amol->id, 'is_done' => 1]);
    }

    public function test_a_new_note_shows_an_unseen_badge_that_clears_on_viewing_that_date(): void
    {
        $admin = $this->inboxAdmin();
        $student = $this->makeStudent();
        $date = CarbonImmutable::now()->toDateString();

        $this->assertSame(0, app(AmolService::class)->unseenNoteCountFor($student));

        $this->actingAs($admin)->post(route('admin.amol.note', $student), ['date' => $date, 'note' => 'চমৎকার হয়েছে']);
        $this->assertSame(1, app(AmolService::class)->unseenNoteCountFor($student));

        // The sidebar badge is visible from ANY student page, not just the amol screen.
        $this->actingAs($student)->get(route('student.dashboard'))
            ->assertOk()->assertSee('rounded-full bg-brand text-brand-ink', false);

        // Opening that specific date is what clears it.
        $this->actingAs($student)->get(route('student.amol.index', ['date' => $date]));
        $this->assertSame(0, app(AmolService::class)->unseenNoteCountFor($student->fresh()));
    }

    public function test_editing_a_note_the_student_already_saw_marks_it_unseen_again(): void
    {
        $admin = $this->inboxAdmin();
        $student = $this->makeStudent();
        $date = CarbonImmutable::now()->toDateString();
        $amolService = app(AmolService::class);

        $this->actingAs($admin)->post(route('admin.amol.note', $student), ['date' => $date, 'note' => 'প্রথম']);
        $this->actingAs($student)->get(route('student.amol.index', ['date' => $date]));
        $this->assertSame(0, $amolService->unseenNoteCountFor($student->fresh()));

        $this->actingAs($admin)->post(route('admin.amol.note', $student), ['date' => $date, 'note' => 'সংশোধিত']);
        $this->assertSame(1, $amolService->unseenNoteCountFor($student->fresh()));
    }
}
