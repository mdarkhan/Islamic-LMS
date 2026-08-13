<?php

namespace App\Services\Import;

use Carbon\CarbonImmutable;

/**
 * Robustly combines a legacy sheet's separate date and time cells into a single
 * instant, interpreted as Asia/Dhaka (the sheet values are naive local wall-clock).
 *
 * Handles the representations the legacy workbook produced: native spreadsheet
 * date/time cells (DateTime), ISO YYYY-MM-DD, UK DD/MM/YYYY, Bengali labels
 * ("০২ জানুয়ারি ২০২৬"), and times like "14:30", "2:30 PM", "2.30 pm".
 *
 * Returns null when the date cannot be parsed — the caller reports it rather than
 * silently guessing (Phase 6, Part 19).
 */
class SheetDate
{
    public const TZ = 'Asia/Dhaka';

    public static function parse(mixed $dateCell, mixed $timeCell = null): ?CarbonImmutable
    {
        $date = self::datePart($dateCell);
        if ($date === null) {
            return null;
        }

        $time = self::timePart($timeCell)
            ?? ($dateCell instanceof \DateTimeInterface
                ? ['h' => (int) $dateCell->format('H'), 'i' => (int) $dateCell->format('i')]
                : ['h' => 0, 'i' => 0]);

        if (! checkdate($date['m'], $date['d'], $date['y'])) {
            return null;
        }

        return CarbonImmutable::create($date['y'], $date['m'], $date['d'], $time['h'], $time['i'], 0, self::TZ);
    }

    /** @return array{y:int,m:int,d:int}|null */
    private static function datePart(mixed $cell): ?array
    {
        if ($cell instanceof \DateTimeInterface) {
            return ['y' => (int) $cell->format('Y'), 'm' => (int) $cell->format('n'), 'd' => (int) $cell->format('j')];
        }

        if (! is_string($cell) && ! is_numeric($cell)) {
            return null;
        }

        $str = trim((string) $cell);
        if ($str === '') {
            return null;
        }

        // Bengali label, e.g. "০২ জানুয়ারি ২০২৬".
        $iso = BengaliText::parseDate($str);
        if ($iso !== null) {
            [$y, $m, $d] = array_map('intval', explode('-', $iso));

            return ['y' => $y, 'm' => $m, 'd' => $d];
        }

        // ISO YYYY-MM-DD or YYYY/MM/DD.
        if (preg_match('#^(\d{4})[-/](\d{1,2})[-/](\d{1,2})#', $str, $m)) {
            return ['y' => (int) $m[1], 'm' => (int) $m[2], 'd' => (int) $m[3]];
        }

        // UK DD-MM-YYYY or DD/MM/YYYY.
        if (preg_match('#^(\d{1,2})[-/](\d{1,2})[-/](\d{4})#', $str, $m)) {
            return ['y' => (int) $m[3], 'm' => (int) $m[2], 'd' => (int) $m[1]];
        }

        return null;
    }

    /** @return array{h:int,i:int}|null */
    private static function timePart(mixed $cell): ?array
    {
        if ($cell instanceof \DateTimeInterface) {
            return ['h' => (int) $cell->format('H'), 'i' => (int) $cell->format('i')];
        }

        if (! is_string($cell) && ! is_numeric($cell)) {
            return null;
        }

        $str = mb_strtolower(trim((string) $cell));
        if ($str === '') {
            return null;
        }

        $str = str_replace('.', ':', $str);
        if (! preg_match('/(\d{1,2}):(\d{1,2})/', $str, $m)) {
            return null;
        }

        $h = (int) $m[1];
        $i = (int) $m[2];

        if (str_contains($str, 'pm') && $h < 12) {
            $h += 12;
        }
        if (str_contains($str, 'am') && $h === 12) {
            $h = 0;
        }

        return ['h' => min($h, 23), 'i' => min($i, 59)];
    }
}
