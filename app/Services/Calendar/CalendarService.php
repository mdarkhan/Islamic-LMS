<?php

namespace App\Services\Calendar;

use App\Services\Settings\SettingService;
use Carbon\CarbonImmutable;

/**
 * The one place all date logic lives — Gregorian, the Bangladesh (revised) Bangla
 * calendar, and a Hijri date whose day rolls over at LOCAL SUNSET (not midnight),
 * with an admin offset for moon-sighting alignment.
 *
 * Algorithms, stated openly (they are calculations, not moon-sighting truth):
 *
 *  - **Hijri:** the arithmetic *tabular Islamic (civil)* calendar — a deterministic
 *    Gregorian↔Hijri conversion via the Julian Day Number, epoch 16 July 622 CE. It can
 *    differ from Bangladesh's sighting by a day, which is exactly why `hijri_offset_days`
 *    (−1/0/+1) exists. The offset and the sunset rollover are applied to the JDN and the
 *    result converted ONCE, so month/year boundaries can never produce an invalid date.
 *
 *  - **Bangla:** the revised Bangladesh rule (Bangla Academy, in force since 1426).
 *    Pohela Boishakh is fixed to 14 April. Months 1–5 have 31 days, months 6–11 have 30,
 *    and Choitro (12) has 30 — or 31 in a leap year, where "leap" means the Gregorian
 *    February inside that Bangla year (i.e. of Gregorian year Y+1) is a leap February.
 *    This is the Bangladesh system, deliberately NOT the West Bengal one.
 *
 *  - **Sunset:** PHP's built-in `date_sun_info()` for the configured institutional
 *    location (Dhaka by default) — offline, free, no prayer-time API.
 *
 * Everything is computed in the configured timezone (Asia/Dhaka). The server clock is
 * authoritative; `$now` is injectable so tests can freeze time.
 */
class CalendarService
{
    private const BANGLA_EPOCH_OFFSET = 593;   // Gregorian year − 593 = Bangla year at Pohela Boishakh

    /** @var array<int, string> */
    public const GREGORIAN_MONTHS_BN = [
        1 => 'জানুয়ারি', 'ফেব্রুয়ারি', 'মার্চ', 'এপ্রিল', 'মে', 'জুন',
        'জুলাই', 'আগস্ট', 'সেপ্টেম্বর', 'অক্টোবর', 'নভেম্বর', 'ডিসেম্বর',
    ];

    /** @var array<int, string> */
    public const BANGLA_MONTHS = [
        1 => 'বৈশাখ', 'জ্যৈষ্ঠ', 'আষাঢ়', 'শ্রাবণ', 'ভাদ্র', 'আশ্বিন',
        'কার্তিক', 'অগ্রহায়ণ', 'পৌষ', 'মাঘ', 'ফাল্গুন', 'চৈত্র',
    ];

    /** @var array<int, string> */
    public const HIJRI_MONTHS_BN = [
        1 => 'মুহাররম', 'সফর', 'রবিউল আউয়াল', 'রবিউস সানি', 'জুমাদাল উলা', 'জুমাদাস সানি',
        'রজব', 'শাবান', 'রমজান', 'শাওয়াল', 'জিলকদ', 'জিলহজ',
    ];

    /** @var array<int, string> */
    public const HIJRI_MONTHS_EN = [
        1 => 'Muharram', 'Safar', 'Rabi al-Awwal', 'Rabi al-Thani', 'Jumada al-Awwal', 'Jumada al-Thani',
        'Rajab', "Sha'ban", 'Ramadan', 'Shawwal', 'Dhu al-Qidah', 'Dhu al-Hijjah',
    ];

    public function __construct(private readonly SettingService $settings) {}

    private function timezone(): string
    {
        return (string) $this->settings->get('calendar_timezone', 'Asia/Dhaka');
    }

    /** "Now" pinned to the configured timezone. */
    private function localNow(?CarbonImmutable $now): CarbonImmutable
    {
        return ($now ?? CarbonImmutable::now())->setTimezone($this->timezone());
    }

    // ── Gregorian ────────────────────────────────────────────────────────────────

    /** @return array{carbon: CarbonImmutable, day: int, month: int, year: int, month_bn: string} */
    public function gregorian(?CarbonImmutable $now = null): array
    {
        $d = $this->localNow($now);

        return [
            'carbon' => $d,
            'day' => (int) $d->format('j'),
            'month' => (int) $d->format('n'),
            'year' => (int) $d->format('Y'),
            'month_bn' => self::GREGORIAN_MONTHS_BN[(int) $d->format('n')],
        ];
    }

    // ── Bangla (revised Bangladesh rule) ─────────────────────────────────────────

    /** @return array{day: int, month: int, year: int, month_name: string} */
    public function bangla(?CarbonImmutable $now = null): array
    {
        $d = $this->localNow($now);

        return $this->banglaFromYmd((int) $d->format('Y'), (int) $d->format('n'), (int) $d->format('j'));
    }

    /**
     * @return array{day: int, month: int, year: int, month_name: string}
     */
    private function banglaFromYmd(int $gy, int $gm, int $gd): array
    {
        // The Bangla year begins at Pohela Boishakh = 14 April. Find the epoch year.
        $onOrAfterBoishakh = ($gm > 4) || ($gm === 4 && $gd >= 14);
        $epochYear = $onOrAfterBoishakh ? $gy : $gy - 1;
        $banglaYear = $epochYear - self::BANGLA_EPOCH_OFFSET;

        $epochJdn = $this->gregorianToJdn($epochYear, 4, 14);
        $offset = $this->gregorianToJdn($gy, $gm, $gd) - $epochJdn;   // 0 = 1 Boishakh

        $lengths = $this->banglaMonthLengths($epochYear);
        $month = 1;
        foreach ($lengths as $i => $len) {
            if ($offset < $len) {
                $month = $i + 1;
                break;
            }
            $offset -= $len;
        }

        return [
            'day' => $offset + 1,
            'month' => $month,
            'year' => $banglaYear,
            'month_name' => self::BANGLA_MONTHS[$month],
        ];
    }

    /**
     * Month lengths for the Bangla year whose Pohela Boishakh falls in Gregorian
     * $epochYear. Choitro (12) is 31 days when the February inside the year — Feb of
     * $epochYear + 1 — is a leap February.
     *
     * @return array<int, int>
     */
    private function banglaMonthLengths(int $epochYear): array
    {
        $choitro = $this->isGregorianLeap($epochYear + 1) ? 31 : 30;

        return [31, 31, 31, 31, 31, 30, 30, 30, 30, 30, 30, $choitro];
    }

    // ── Hijri (tabular civil + sunset rollover + admin offset) ───────────────────

    /**
     * @return array{
     *     day: int, month: int, year: int, month_name: string, month_name_en: string,
     *     after_sunset: bool
     * }
     */
    public function hijri(?CarbonImmutable $now = null): array
    {
        $d = $this->localNow($now);
        $afterSunset = $this->isAfterSunset($d);
        $offset = (int) $this->settings->get('hijri_offset_days', 0);

        // Operate on the day count, then convert once — never mutate a day integer.
        $jdn = $this->gregorianToJdn((int) $d->format('Y'), (int) $d->format('n'), (int) $d->format('j'))
            + ($afterSunset ? 1 : 0)
            + $offset;

        $h = $this->jdnToIslamic($jdn);

        return [
            'day' => $h['day'],
            'month' => $h['month'],
            'year' => $h['year'],
            'month_name' => self::HIJRI_MONTHS_BN[$h['month']],
            'month_name_en' => self::HIJRI_MONTHS_EN[$h['month']],
            'after_sunset' => $afterSunset,
        ];
    }

    // ── Sunset ───────────────────────────────────────────────────────────────────

    /** Local sunset for the calendar date of $moment at the configured location. */
    public function sunsetAt(?CarbonImmutable $now = null): CarbonImmutable
    {
        $d = $this->localNow($now);
        $lat = (float) $this->settings->get('calendar_latitude', '23.8103');
        $lng = (float) $this->settings->get('calendar_longitude', '90.4125');

        // Anchor at local noon so date_sun_info resolves this calendar day's sunset.
        $noon = $d->setTime(12, 0)->getTimestamp();
        $info = date_sun_info($noon, $lat, $lng);
        $sunset = $info['sunset'] ?? false;

        if (! is_int($sunset)) {
            // Only at extreme latitudes; Dhaka never hits this. Fall back to 18:00 local.
            return $d->setTime(18, 0);
        }

        return CarbonImmutable::createFromTimestampUTC($sunset)->setTimezone($this->timezone());
    }

    private function isAfterSunset(CarbonImmutable $localNow): bool
    {
        return $localNow->getTimestamp() >= $this->sunsetAt($localNow)->getTimestamp();
    }

    // ── Upcoming Islamic occasions widget ────────────────────────────────────────

    /**
     * (Hijri month, Hijri day, Bengali label key, English label key). Deliberately
     * limited to occasions universally observed across schools of thought — no Mawlid
     * or similar, which different traditions treat differently; this site does not take
     * a position on those.
     *
     * @var array<int, array{0:int,1:int,2:string,3:string}>
     */
    private const EVENTS = [
        [1, 1, 'event_hijri_new_year', 'Hijri New Year'],
        [1, 10, 'event_ashura', 'Ashura'],
        [9, 1, 'event_ramadan_begins', 'Ramadan begins'],
        [10, 1, 'event_eid_fitr', 'Eid al-Fitr'],
        [12, 9, 'event_arafah', 'Day of Arafah'],
        [12, 10, 'event_eid_adha', 'Eid al-Adha'],
    ];

    /**
     * The next occurrence of each occasion in {@see self::EVENTS}, soonest first.
     *
     * Finds "the next Gregorian day whose Hijri date is month/day" via a bounded forward
     * scan through the already-tested {@see self::hijri()} conversion (checked at local
     * noon, so the sunset rollover within a single calendar day never matters here) —
     * deliberately NOT a hand-derived Islamic→JDN inverse formula. A civil Islamic year is
     * ~354–355 days, so 400 days always finds every occasion at least once.
     *
     * @return array<int, array{key:string, label_en:string, date:CarbonImmutable, days_until:int}>
     */
    public function upcomingIslamicOccasions(int $count = 3, ?CarbonImmutable $now = null): array
    {
        $today = $this->localNow($now)->startOfDay();

        $occurrences = collect(self::EVENTS)->map(function (array $event) use ($today) {
            [$month, $day, $labelKey, $labelEn] = $event;
            $date = $this->nextOccurrenceOf($month, $day, $today);

            return [
                'key' => $labelKey,
                'label_en' => $labelEn,
                'date' => $date,
                'days_until' => (int) $today->diffInDays($date),
            ];
        });

        return $occurrences->sortBy('days_until')->take($count)->values()->all();
    }

    /** The soonest date (today or later) whose Hijri date is $month/$day. */
    private function nextOccurrenceOf(int $month, int $day, CarbonImmutable $fromDate): CarbonImmutable
    {
        for ($i = 0; $i <= 400; $i++) {
            $candidate = $fromDate->addDays($i);
            $h = $this->hijri($candidate->setTime(12, 0));

            if ($h['month'] === $month && $h['day'] === $day) {
                return $candidate;
            }
        }

        // Unreachable: every Hijri (month, day) recurs within one civil year (~355 days).
        return $fromDate;
    }

    // ── Everything, for the widget ───────────────────────────────────────────────

    /**
     * The place name for the date card — the sunset and Hijri rollover are computed for the
     * configured coordinates, so the label must describe THOSE, not a fixed city.
     *
     * The admin's own label wins. With none set, the built-in "Dhaka, Bangladesh" is shown
     * only while the coordinates are still the Dhaka defaults; if an admin has moved them
     * without naming the place, no label is better than a wrong one.
     */
    public function locationLabel(): ?string
    {
        $label = trim((string) $this->settings->get('calendar_location_label', ''));
        if ($label !== '') {
            return $label;
        }

        $atDefaultLocation = abs((float) $this->settings->get('calendar_latitude', '23.8103') - 23.8103) < 0.01
            && abs((float) $this->settings->get('calendar_longitude', '90.4125') - 90.4125) < 0.01;

        return $atDefaultLocation ? __('public.location_label') : null;
    }

    /** @return array<string, mixed> */
    public function all(?CarbonImmutable $now = null): array
    {
        return [
            'gregorian' => $this->gregorian($now),
            'bangla' => $this->bangla($now),
            'hijri' => $this->hijri($now),
            'sunset' => $this->sunsetAt($now),
            'location' => $this->locationLabel(),
        ];
    }

    // ── Pure conversions (no timezone, no settings) ──────────────────────────────

    private function isGregorianLeap(int $year): bool
    {
        return ($year % 4 === 0 && $year % 100 !== 0) || $year % 400 === 0;
    }

    /** Gregorian (proleptic) date → Julian Day Number. */
    private function gregorianToJdn(int $year, int $month, int $day): int
    {
        $a = intdiv(14 - $month, 12);
        $y = $year + 4800 - $a;
        $m = $month + 12 * $a - 3;

        return $day + intdiv(153 * $m + 2, 5) + 365 * $y
            + intdiv($y, 4) - intdiv($y, 100) + intdiv($y, 400) - 32045;
    }

    /**
     * Julian Day Number → tabular Islamic (civil) date. Classic arithmetic conversion.
     *
     * @return array{day: int, month: int, year: int}
     */
    private function jdnToIslamic(int $jdn): array
    {
        $l = $jdn - 1948440 + 10632;
        $n = intdiv($l - 1, 10631);
        $l = $l - 10631 * $n + 354;
        $j = intdiv(10985 - $l, 5316) * intdiv(50 * $l, 17719)
            + intdiv($l, 5670) * intdiv(43 * $l, 15238);
        $l = $l - intdiv(30 - $j, 15) * intdiv(17719 * $j, 50)
            - intdiv($j, 16) * intdiv(15238 * $j, 43) + 29;
        $month = intdiv(24 * $l, 709);
        $day = $l - intdiv(709 * $month, 24);
        $year = 30 * $n + $j - 30;

        return ['day' => $day, 'month' => $month, 'year' => $year];
    }
}
