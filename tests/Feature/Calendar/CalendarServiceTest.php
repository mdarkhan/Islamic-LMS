<?php

namespace Tests\Feature\Calendar;

use App\Services\Calendar\CalendarService;
use App\Services\Settings\SettingService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CalendarServiceTest extends TestCase
{
    use RefreshDatabase;

    private CalendarService $calendar;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calendar = app(CalendarService::class);
    }

    private function at(string $dhakaDateTime): CarbonImmutable
    {
        return CarbonImmutable::parse($dhakaDateTime, 'Asia/Dhaka');
    }

    // ── Gregorian ─────────────────────────────────────────────────────────────────

    public function test_gregorian_is_reported_in_dhaka(): void
    {
        // 20:00 UTC is 02:00 the next day in Dhaka (+6) — the Dhaka date must win.
        $g = $this->calendar->gregorian(CarbonImmutable::parse('2026-08-18 20:00', 'UTC'));

        $this->assertSame(19, $g['day']);
        $this->assertSame(8, $g['month']);
        $this->assertSame(2026, $g['year']);
        $this->assertSame('আগস্ট', $g['month_bn']);
    }

    // ── Bangla (revised Bangladesh rule) ──────────────────────────────────────────

    public function test_pohela_boishakh_is_14_april(): void
    {
        $b = $this->calendar->bangla($this->at('2026-04-14 10:00'));
        $this->assertSame(1, $b['day']);
        $this->assertSame(1, $b['month']);
        $this->assertSame('বৈশাখ', $b['month_name']);
        $this->assertSame(1433, $b['year']);
    }

    public function test_day_before_pohela_boishakh_is_end_of_choitro(): void
    {
        // 13 Apr 2024 → last day of Choitro 1430; Choitro is 31 days because Feb 2024
        // (inside Bangla year 1430) is a leap February.
        $b = $this->calendar->bangla($this->at('2024-04-13 10:00'));
        $this->assertSame(31, $b['day']);
        $this->assertSame(12, $b['month']);   // Choitro
        $this->assertSame(1430, $b['year']);
    }

    public function test_bangla_second_of_boishakh_and_midyear(): void
    {
        $this->assertSame(2, $this->calendar->bangla($this->at('2024-04-15 10:00'))['day']);

        $mid = $this->calendar->bangla($this->at('2026-08-18 10:00'));
        $this->assertSame(5, $mid['month']);   // ভাদ্র
        $this->assertSame('ভাদ্র', $mid['month_name']);
        $this->assertSame(1433, $mid['year']);
    }

    // ── Hijri: sunset rollover ────────────────────────────────────────────────────

    public function test_hijri_advances_only_at_sunset_not_midnight(): void
    {
        $sunset = $this->calendar->sunsetAt($this->at('2026-08-18 12:00'));

        $before = $this->calendar->hijri($sunset->subMinute());
        $after = $this->calendar->hijri($sunset);

        $this->assertFalse($before['after_sunset']);
        $this->assertTrue($after['after_sunset']);
        // Exactly one Islamic day apart across the sunset boundary.
        $this->assertSame($before['day'] + 1, $after['day']);
    }

    public function test_hijri_does_not_change_at_gregorian_midnight(): void
    {
        // 23:59 on the 18th (after sunset) and 00:01 on the 19th (before sunset) are the
        // SAME Islamic day — midnight must not shift the Hijri date.
        $late = $this->calendar->hijri($this->at('2026-08-18 23:59'));
        $early = $this->calendar->hijri($this->at('2026-08-19 00:01'));

        $this->assertSame([$late['day'], $late['month'], $late['year']],
            [$early['day'], $early['month'], $early['year']]);
    }

    public function test_hijri_base_value_is_the_tabular_civil_date(): void
    {
        // 18 Aug 2026, before sunset → 4 Rabi al-Awwal 1448 (tabular civil).
        $h = $this->calendar->hijri($this->at('2026-08-18 15:00'));
        $this->assertSame(4, $h['day']);
        $this->assertSame(3, $h['month']);
        $this->assertSame(1448, $h['year']);
        $this->assertSame('রবিউল আউয়াল', $h['month_name']);
    }

    // ── Hijri: admin offset ───────────────────────────────────────────────────────

    public function test_hijri_offset_shifts_the_day(): void
    {
        $settings = app(SettingService::class);
        $moment = $this->at('2026-08-18 15:00');   // base 4/3/1448 before sunset

        $settings->set(['hijri_offset_days' => 1]);
        $this->assertSame(5, $this->calendar->hijri($moment)['day']);

        $settings->set(['hijri_offset_days' => -1]);
        $this->assertSame(3, $this->calendar->hijri($moment)['day']);

        $settings->set(['hijri_offset_days' => 0]);
        $this->assertSame(4, $this->calendar->hijri($moment)['day']);
    }

    public function test_offset_across_a_hijri_year_boundary_stays_valid(): void
    {
        // 26 Jun 2025 (before sunset) = 29 Dhu al-Hijjah 1446; +1 offset must roll to
        // 1 Muharram 1447 — a real date operation, never "29 + 1 = 30".
        app(SettingService::class)->set(['hijri_offset_days' => 1]);

        $h = $this->calendar->hijri($this->at('2025-06-26 15:00'));
        $this->assertSame(1, $h['day']);
        $this->assertSame(1, $h['month']);   // Muharram
        $this->assertSame(1447, $h['year']);
    }

    // ── Sunset ────────────────────────────────────────────────────────────────────

    public function test_sunset_is_a_real_computed_time_not_hardcoded_six_pm(): void
    {
        $sunset = $this->calendar->sunsetAt($this->at('2026-08-18 12:00'));

        $this->assertSame('Asia/Dhaka', $sunset->timezoneName);
        $this->assertSame(18, $sunset->hour);      // ~18:29 for Dhaka on this date
        $this->assertNotSame(0, $sunset->minute);   // not a hard-coded 18:00
    }
}
