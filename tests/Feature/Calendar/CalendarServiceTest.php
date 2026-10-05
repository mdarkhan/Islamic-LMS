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

    // ── Upcoming Islamic occasions ────────────────────────────────────────────────

    public function test_upcoming_occasions_are_strictly_ordered_soonest_first(): void
    {
        $events = $this->calendar->upcomingIslamicOccasions(6, $this->at('2026-08-18 12:00'));

        $this->assertCount(6, $events);
        $days = array_column($events, 'days_until');
        $sorted = $days;
        sort($sorted);
        $this->assertSame($sorted, $days, 'events must already be soonest-first');
    }

    public function test_every_returned_occasion_really_maps_back_to_its_claimed_hijri_date(): void
    {
        $now = $this->at('2026-08-18 12:00');
        $events = $this->calendar->upcomingIslamicOccasions(6, $now);

        $expected = [
            'event_hijri_new_year' => [1, 1],
            'event_ashura' => [1, 10],
            'event_ramadan_begins' => [9, 1],
            'event_eid_fitr' => [10, 1],
            'event_arafah' => [12, 9],
            'event_eid_adha' => [12, 10],
        ];

        foreach ($events as $event) {
            [$expectedMonth, $expectedDay] = $expected[$event['key']];
            $hijriOnThatDay = $this->calendar->hijri($event['date']->setTime(12, 0));

            $this->assertSame($expectedMonth, $hijriOnThatDay['month'], "{$event['key']} resolved to the wrong Hijri month");
            $this->assertSame($expectedDay, $hijriOnThatDay['day'], "{$event['key']} resolved to the wrong Hijri day");
            $this->assertGreaterThanOrEqual(0, $event['days_until']);
            $this->assertLessThan(355, $event['days_until'], 'must find the NEXT occurrence, not one a full year away');
        }
    }

    public function test_an_occasion_that_is_today_has_zero_days_remaining(): void
    {
        // Locate the next Hijri New Year (1 Muharram) from a known reference point using
        // the SAME already-tested hijri() oracle the production code relies on — this is
        // an independent consistency check of upcomingIslamicOccasions()'s selection and
        // days_until arithmetic, not a re-derivation of the conversion itself.
        $reference = $this->at('2026-08-18 12:00');
        $newYearDate = null;
        for ($i = 0; $i <= 400; $i++) {
            $candidate = $reference->addDays($i);
            $h = $this->calendar->hijri($candidate);
            if ($h['month'] === 1 && $h['day'] === 1) {
                $newYearDate = $candidate;
                break;
            }
        }
        $this->assertNotNull($newYearDate, 'a Hijri New Year must occur within 400 days');

        $events = collect($this->calendar->upcomingIslamicOccasions(6, $newYearDate->setTime(12, 0)));
        $newYearEvent = $events->firstWhere('key', 'event_hijri_new_year');

        $this->assertNotNull($newYearEvent);
        $this->assertSame(0, $newYearEvent['days_until']);
    }

    // ── Location label (homepage date card badge) ─────────────────────────────────

    public function test_the_location_label_is_dhaka_only_while_the_coordinates_are_the_dhaka_defaults(): void
    {
        $this->assertSame('ঢাকা, বাংলাদেশ', $this->calendar->locationLabel());

        // Moved to Riyadh with no name given: no badge beats a wrong "Dhaka".
        app(SettingService::class)->set(['calendar_latitude' => '24.7136', 'calendar_longitude' => '46.6753']);
        $this->assertNull($this->calendar->locationLabel());
    }

    public function test_an_admin_set_location_label_always_wins(): void
    {
        app(SettingService::class)->set([
            'calendar_latitude' => '24.7136', 'calendar_longitude' => '46.6753',
            'calendar_location_label' => 'রিয়াদ, সৌদি আরব',
        ]);

        $this->assertSame('রিয়াদ, সৌদি আরব', $this->calendar->locationLabel());
        $this->assertSame('রিয়াদ, সৌদি আরব', $this->calendar->all()['location']);
    }
}
