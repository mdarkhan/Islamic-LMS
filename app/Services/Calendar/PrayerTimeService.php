<?php

namespace App\Services\Calendar;

use App\Services\Settings\SettingService;
use Carbon\CarbonImmutable;

/**
 * The five prayer times (plus sunrise) for the configured location, computed offline
 * from solar position — no API, no internet, no cost. The same approach (and the same
 * configured latitude/longitude) CalendarService uses for sunset, so Maghrib IS the
 * sunset the Hijri day-rollover uses.
 *
 * Method: University of Islamic Sciences, Karachi (Fajr & Isha at 18° below the
 * horizon — the convention used across Bangladesh) with Hanafi Asr (shadow = 2× the
 * object + noon shadow). Angles and the Asr factor are admin settings.
 * Algorithm follows the widely used PrayTimes.org formulation.
 */
class PrayerTimeService
{
    public const PRAYERS = ['fajr', 'sunrise', 'zuhr', 'asr', 'maghrib', 'isha'];

    /** Sun-centre angle at sunrise/sunset: refraction + solar radius. */
    private const HORIZON_ANGLE = 0.833;

    public function __construct(private readonly SettingService $settings) {}

    /**
     * @return array<string, CarbonImmutable> keyed by PRAYERS, in the configured timezone
     */
    public function timesFor(?CarbonImmutable $day = null): array
    {
        $tz = (string) $this->settings->get('calendar_timezone', 'Asia/Dhaka');
        $lat = (float) $this->settings->get('calendar_latitude', '23.8103');
        $lng = (float) $this->settings->get('calendar_longitude', '90.4125');
        $fajrAngle = (float) $this->settings->get('prayer_fajr_angle', '18');
        $ishaAngle = (float) $this->settings->get('prayer_isha_angle', '18');
        $asrFactor = (int) $this->settings->get('prayer_asr_factor', 2) === 1 ? 1 : 2;

        $date = ($day ?? CarbonImmutable::now())->setTimezone($tz)->startOfDay();
        $offsetHours = $date->setTime(12, 0)->getOffset() / 3600;

        $jd = $this->julian((int) $date->format('Y'), (int) $date->format('n'), (int) $date->format('j')) - $lng / 360;

        // Each time is refined once around its own estimate (the sun moves during the day).
        $t = fn (float $h) => $h / 24;
        $noon = fn (float $h) => $this->midDay($jd + $t($h));
        $angleTime = function (float $angle, float $h, bool $ccw) use ($jd, $lat, $t): float {
            $decl = $this->sunDeclination($jd + $t($h));
            $noon = $this->midDay($jd + $t($h));
            $cos = (-$this->sin($angle) - $this->sin($decl) * $this->sin($lat)) / ($this->cos($decl) * $this->cos($lat));
            if ($cos < -1 || $cos > 1) {
                return NAN;   // sun never reaches this angle (extreme latitude)
            }

            return $noon + ($ccw ? -1 : 1) * $this->arccos($cos) / 15;
        };
        $asrTime = function (float $h) use ($jd, $lat, $asrFactor, $t, $angleTime): float {
            $decl = $this->sunDeclination($jd + $t($h));
            $angle = -$this->arccot($asrFactor + $this->tan(abs($lat - $decl)));

            return $angleTime($angle, $h, false);
        };

        [$fajr, $sunrise, $zuhr, $asr, $sunset, $isha] = [5.0, 6.0, 12.0, 13.0, 18.0, 18.0];
        for ($i = 0; $i < 2; $i++) {
            $fajr = $angleTime($fajrAngle, $fajr, true);
            $sunrise = $angleTime(self::HORIZON_ANGLE, $sunrise, true);
            $zuhr = $noon($zuhr);
            $asr = $asrTime($asr);
            $sunset = $angleTime(self::HORIZON_ANGLE, $sunset, false);
            $isha = $angleTime($ishaAngle, $isha, false);
        }

        $shift = $offsetHours - $lng / 15;
        $toDate = function (float $hours) use ($date, $shift): CarbonImmutable {
            $h = $this->fixHour($hours + $shift + 0.5 / 60);   // +30 s: round to the nearest minute
            $minutes = (int) floor($h * 60);

            return $date->addMinutes($minutes);
        };

        return [
            'fajr' => $toDate($fajr),
            'sunrise' => $toDate($sunrise),
            'zuhr' => $toDate($zuhr + 1 / 60),     // +1 min after true noon, the common precaution
            'asr' => $toDate($asr),
            'maghrib' => $toDate($sunset),
            'isha' => $toDate($isha),
        ];
    }

    /** Which prayer is current / next at $now. */
    public function status(?CarbonImmutable $now = null): array
    {
        $now = $now ?? CarbonImmutable::now();
        $times = $this->timesFor($now);
        $order = ['fajr', 'zuhr', 'asr', 'maghrib', 'isha'];

        $current = null;
        foreach ($order as $key) {
            if ($now->getTimestamp() >= $times[$key]->getTimestamp()) {
                $current = $key;
            }
        }
        $next = null;
        foreach ($order as $key) {
            if ($now->getTimestamp() < $times[$key]->getTimestamp()) {
                $next = $key;
                break;
            }
        }
        $nextAt = $next ? $times[$next] : $this->timesFor($now->addDay())['fajr'];

        return ['times' => $times, 'current' => $current, 'next' => $next ?? 'fajr', 'next_at' => $nextAt];
    }

    // ── astronomy ────────────────────────────────────────────────────────────────

    private function julian(int $y, int $m, int $d): float
    {
        if ($m <= 2) {
            $y--;
            $m += 12;
        }
        $a = floor($y / 100);
        $b = 2 - $a + floor($a / 4);

        return floor(365.25 * ($y + 4716)) + floor(30.6001 * ($m + 1)) + $d + $b - 1524.5;
    }

    /** @return array{0: float, 1: float} declination (deg), equation of time (hours) */
    private function sunPosition(float $jd): array
    {
        $D = $jd - 2451545.0;
        $g = $this->fixAngle(357.529 + 0.98560028 * $D);
        $q = $this->fixAngle(280.459 + 0.98564736 * $D);
        $L = $this->fixAngle($q + 1.915 * $this->sin($g) + 0.020 * $this->sin(2 * $g));
        $e = 23.439 - 0.00000036 * $D;
        $ra = $this->fixHour($this->arctan2($this->cos($e) * $this->sin($L), $this->cos($L)) / 15);

        return [$this->arcsin($this->sin($e) * $this->sin($L)), $q / 15 - $ra];
    }

    private function sunDeclination(float $jd): float
    {
        return $this->sunPosition($jd)[0];
    }

    private function midDay(float $jd): float
    {
        return $this->fixHour(12 - $this->sunPosition($jd)[1]);
    }

    private function sin(float $d): float { return sin(deg2rad($d)); }
    private function cos(float $d): float { return cos(deg2rad($d)); }
    private function tan(float $d): float { return tan(deg2rad($d)); }
    private function arcsin(float $x): float { return rad2deg(asin($x)); }
    private function arccos(float $x): float { return rad2deg(acos($x)); }
    private function arctan2(float $y, float $x): float { return rad2deg(atan2($y, $x)); }
    private function arccot(float $x): float { return rad2deg(atan(1 / $x)); }

    private function fixAngle(float $a): float { return $a - 360 * floor($a / 360); }
    private function fixHour(float $h): float { return $h - 24 * floor($h / 24); }
}
