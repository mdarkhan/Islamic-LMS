<?php

namespace Tests\Feature\Calendar;

use App\Services\Calendar\CalendarService;
use App\Services\Calendar\PrayerTimeService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PrayerTimeServiceTest extends TestCase
{
    use RefreshDatabase;

    private function day(string $d): CarbonImmutable
    {
        return CarbonImmutable::parse($d, 'Asia/Dhaka');
    }

    public function test_times_are_in_order_and_plausible_for_dhaka(): void
    {
        $t = app(PrayerTimeService::class)->timesFor($this->day('2026-12-21'));

        $this->assertSame(['fajr', 'sunrise', 'zuhr', 'asr', 'maghrib', 'isha'], array_keys($t));
        $stamps = array_map(fn ($c) => $c->getTimestamp(), array_values($t));
        $sorted = $stamps;
        sort($sorted);
        $this->assertSame($sorted, $stamps);

        $this->assertSame('11:57', $t['zuhr']->format('H:i'));
        $this->assertContains($t['fajr']->format('H:i'), ['05:14', '05:15', '05:16']);
        $this->assertSame('Asia/Dhaka', $t['fajr']->timezone->getName());
    }

    public function test_maghrib_is_the_same_sunset_the_hijri_rollover_uses(): void
    {
        $day = $this->day('2026-06-21');
        $maghrib = app(PrayerTimeService::class)->timesFor($day)['maghrib'];
        $sunset = app(CalendarService::class)->sunsetAt($day->setTime(12, 0));

        $this->assertLessThanOrEqual(60, abs($maghrib->getTimestamp() - $sunset->getTimestamp()));
    }

    public function test_hanafi_asr_is_later_than_shafii(): void
    {
        $svc = app(PrayerTimeService::class);
        $hanafi = $svc->timesFor($this->day('2026-03-21'))['asr'];

        app(\App\Services\Settings\SettingService::class)->set(['prayer_asr_factor' => 1]);
        $shafii = $svc->timesFor($this->day('2026-03-21'))['asr'];

        $this->assertTrue($hanafi->gt($shafii));
    }

    public function test_status_reports_current_and_next_prayer(): void
    {
        $svc = app(PrayerTimeService::class);

        $s = $svc->status($this->day('2026-12-21')->setTime(13, 0));
        $this->assertSame('zuhr', $s['current']);
        $this->assertSame('asr', $s['next']);

        $late = $svc->status($this->day('2026-12-21')->setTime(23, 0));
        $this->assertSame('isha', $late['current']);
        $this->assertSame('fajr', $late['next']);
        $this->assertTrue($late['next_at']->isSameDay($this->day('2026-12-22')));

        $early = $svc->status($this->day('2026-12-21')->setTime(2, 0));
        $this->assertNull($early['current']);
        $this->assertSame('fajr', $early['next']);
    }

    public function test_the_amol_page_shows_the_times(): void
    {
        $student = $this->makeStudent();

        $this->actingAs($student)->get(route('student.amol.index'))
            ->assertOk()
            ->assertSee(__('amol.prayer_times_heading'))
            ->assertSee(__('amol.next_prayer'));
    }
}
