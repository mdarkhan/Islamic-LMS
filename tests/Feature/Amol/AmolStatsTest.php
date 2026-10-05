<?php

namespace Tests\Feature\Amol;

use App\Models\Amol;
use App\Models\AmolEntry;
use App\Services\Amol\AmolStatsService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AmolStatsTest extends TestCase
{
    use RefreshDatabase;

    private CarbonImmutable $today;

    protected function setUp(): void
    {
        parent::setUp();
        $this->today = CarbonImmutable::parse('2026-09-24', 'Asia/Dhaka')->startOfDay();
        Amol::query()->delete();
        foreach (['a', 'b', 'c'] as $i => $k) {
            Amol::create(['key' => $k, 'label' => $k, 'group' => null, 'sort_order' => $i, 'is_active' => true]);
        }
    }

    /** Mark $count deeds done on $daysAgo days before today. */
    private function done($student, int $daysAgo, int $count): void
    {
        foreach (Amol::query()->ordered()->take($count)->get() as $amol) {
            AmolEntry::create(['user_id' => $student->id, 'amol_id' => $amol->id, 'date' => $this->today->subDays($daysAgo)->toDateString(), 'is_done' => true]);
        }
    }

    public function test_streaks_count_consecutive_days_and_an_unfinished_today_does_not_break_them(): void
    {
        $s = $this->makeStudent();
        // yesterday & the two days before: all 3 deeds; 4 days ago: gap; 5 & 6 days ago: one deed.
        $this->done($s, 1, 3);
        $this->done($s, 2, 3);
        $this->done($s, 3, 3);
        $this->done($s, 5, 1);
        $this->done($s, 6, 1);

        $r = app(AmolStatsService::class)->forStudent($s, null, $this->today);

        $this->assertSame(3, $r['current_full']);
        $this->assertSame(3, $r['best_full']);
        $this->assertSame(3, $r['current_active']);
        $this->assertSame(3, $r['best_active']);   // 3-day run, gap, then a 2-day run
    }

    public function test_a_missed_yesterday_resets_the_current_streak_but_keeps_the_best(): void
    {
        $s = $this->makeStudent();
        $this->done($s, 2, 3);
        $this->done($s, 3, 3);
        $this->done($s, 4, 3);

        $r = app(AmolStatsService::class)->forStudent($s, null, $this->today);

        $this->assertSame(0, $r['current_full']);
        $this->assertSame(3, $r['best_full']);
    }

    public function test_finishing_today_extends_the_streak(): void
    {
        $s = $this->makeStudent();
        $this->done($s, 1, 3);
        $this->done($s, 0, 3);

        $this->assertSame(2, app(AmolStatsService::class)->forStudent($s, null, $this->today)['current_full']);
    }

    public function test_partial_days_do_not_count_as_full_and_deactivated_deeds_are_ignored(): void
    {
        $s = $this->makeStudent();
        $this->done($s, 1, 2);   // 2 of 3

        $r = app(AmolStatsService::class)->forStudent($s, null, $this->today);
        $this->assertSame(0, $r['current_full']);
        $this->assertSame(1, $r['current_active']);

        Amol::query()->where('key', 'c')->update(['is_active' => false]);
        $this->assertSame(1, app(AmolStatsService::class)->forStudent($s, null, $this->today)['current_full']);
    }

    public function test_the_month_view_reports_each_day_and_never_leaks_another_students_data(): void
    {
        $s = $this->makeStudent();
        $other = $this->makeStudent();
        $this->done($s, 1, 3);
        $this->done($other, 2, 3);

        $r = app(AmolStatsService::class)->forStudent($s, null, $this->today);

        $this->assertCount(30, $r['days']);
        $this->assertSame(3, $r['days'][22]['done']);   // 23 Sept
        $this->assertSame(0, $r['days'][21]['done']);   // 22 Sept belongs to $other
        $this->assertTrue($r['days'][25]['future']);
        $this->assertSame(1, $r['month_full_days']);
    }

    public function test_the_page_renders_the_card_and_survives_junk_month_params(): void
    {
        $s = $this->makeStudent();

        $this->actingAs($s)->get(route('student.amol.index'))->assertOk()->assertSee(__('amol.stats_heading'));
        $this->actingAs($s)->get(route('student.amol.index', ['month' => '2099-01']))->assertOk();
        $this->actingAs($s)->get(route('student.amol.index', ['month' => 'garbage']))->assertOk();
        $this->actingAs($s)->get(route('student.amol.index', ['month' => '2026-08']))->assertOk();
    }
}
