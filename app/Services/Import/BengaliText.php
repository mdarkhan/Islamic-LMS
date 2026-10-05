<?php

namespace App\Services\Import;

/**
 * Parsing helpers for the Bengali display strings used throughout the legacy data.
 *
 * Everything normalises to NFC first: Bengali য়/ড়/ঢ় have precomposed and
 * decomposed forms that render identically but compare unequal, and text authored
 * in Google Sheets will not reliably match text authored elsewhere.
 */
class BengaliText
{
    private const DIGITS = [
        '০' => '0', '১' => '1', '২' => '2', '৩' => '3', '৪' => '4',
        '৫' => '5', '৬' => '6', '৭' => '7', '৮' => '8', '৯' => '9',
    ];

    private const MONTHS = [
        'জানুয়ারি' => 1, 'ফেব্রুয়ারি' => 2, 'মার্চ' => 3, 'এপ্রিল' => 4,
        'মে' => 5, 'জুন' => 6, 'জুলাই' => 7, 'আগস্ট' => 8,
        'সেপ্টেম্বর' => 9, 'অক্টোবর' => 10, 'নভেম্বর' => 11, 'ডিসেম্বর' => 12,
    ];

    public static function normalise(string $value): string
    {
        $value = trim($value);

        return \Normalizer::normalize($value, \Normalizer::FORM_C) ?: $value;
    }

    /**
     * Every spelling a Bengali search term may be STORED as. য়/ড়/ঢ় are one glyph but
     * two byte sequences — precomposed (U+09DF/09DC/09DD) or base + nukta (U+09BC) — and
     * MySQL's LIKE treats them as different strings (verified, utf8mb4_unicode_ci), so a
     * search typed on one keyboard would miss text entered with another. NFC yields the
     * decomposed form (these characters are composition-excluded); the second entry is the
     * precomposed one. Search for both.
     *
     * @return array<int, string>
     */
    public static function searchVariants(string $term): array
    {
        $nfc = self::normalise($term);
        $precomposed = strtr($nfc, [
            "\u{09AF}\u{09BC}" => "\u{09DF}",
            "\u{09A1}\u{09BC}" => "\u{09DC}",
            "\u{09A2}\u{09BC}" => "\u{09DD}",
        ]);

        return array_values(array_unique([$nfc, $precomposed]));
    }

    /** Convert Bengali digits to Latin, leaving everything else untouched. */
    public static function toLatinDigits(string $value): string
    {
        return strtr(self::normalise($value), self::DIGITS);
    }

    /** Convert Latin digits to Bengali, for display. */
    public static function toBengaliDigits(string|int $value): string
    {
        return strtr((string) $value, array_flip(self::DIGITS));
    }

    /**
     * Parse a label such as "০২ জানুয়ারি ২০২৬" into Y-m-d.
     *
     * Returns null for non-dates such as the literal "সংগৃহীত" ("collected"),
     * which 17 legacy lessons use instead of a real date.
     */
    public static function parseDate(?string $label): ?string
    {
        if ($label === null) {
            return null;
        }

        $normalised = self::normalise($label);

        foreach (self::MONTHS as $bnMonth => $month) {
            if (! str_contains($normalised, self::normalise($bnMonth))) {
                continue;
            }

            $numbers = self::extractNumbers(str_replace(self::normalise($bnMonth), ' ', $normalised));

            if (count($numbers) < 2) {
                return null;
            }

            [$day, $year] = [$numbers[0], $numbers[1]];

            if (! checkdate($month, $day, $year)) {
                return null;
            }

            return sprintf('%04d-%02d-%02d', $year, $month, $day);
        }

        return null;
    }

    /**
     * Format a Y-m-d date into a Bengali label such as "০২ জানুয়ারি ২০২৬" — the
     * inverse of parseDate(). Used so an admin only ever picks a date from a
     * calendar; the display label is always derived from it, never typed by hand.
     */
    public static function formatDateLabel(?string $date): ?string
    {
        if ($date === null || trim($date) === '') {
            return null;
        }

        try {
            $parsed = new \DateTimeImmutable($date);
        } catch (\Exception) {
            return null;
        }

        $monthNames = array_flip(self::MONTHS);
        $day = self::toBengaliDigits($parsed->format('d'));
        $month = $monthNames[(int) $parsed->format('n')] ?? '';
        $year = self::toBengaliDigits($parsed->format('Y'));

        return trim("{$day} {$month} {$year}");
    }

    /**
     * Parse a label such as "২ ঘণ্টা ১৫ মিনিট" or "৫৮ মিনিট" into total minutes.
     */
    public static function parseDurationMinutes(?string $label): ?int
    {
        if ($label === null) {
            return null;
        }

        $normalised = self::normalise($label);
        $hourWord = self::normalise('ঘণ্টা');
        $minuteWord = self::normalise('মিনিট');

        $hours = 0;
        $minutes = 0;

        if (preg_match('/(\S+)\s*'.preg_quote($hourWord, '/').'/u', $normalised, $m)) {
            $hours = self::extractNumbers($m[1])[0] ?? 0;
        }

        if (preg_match('/(\S+)\s*'.preg_quote($minuteWord, '/').'/u', $normalised, $m)) {
            $minutes = self::extractNumbers($m[1])[0] ?? 0;
        }

        $total = $hours * 60 + $minutes;

        return $total > 0 ? $total : null;
    }

    /**
     * Parse a correct-answer cell: "1", "২", "1, 2", "১,২,৩" → [1, 2, 3] (1-based).
     *
     * @return array<int, int>
     */
    public static function parseAnswerPositions(?string $value): array
    {
        if ($value === null || trim($value) === '') {
            return [];
        }

        $positions = [];
        foreach (explode(',', self::toLatinDigits($value)) as $part) {
            $part = trim($part);
            if ($part !== '' && ctype_digit($part)) {
                $positions[] = (int) $part;
            }
        }

        return array_values(array_unique($positions));
    }

    /** @return array<int, int> */
    private static function extractNumbers(string $value): array
    {
        preg_match_all('/\d+/', self::toLatinDigits($value), $matches);

        return array_map(intval(...), $matches[0]);
    }
}
