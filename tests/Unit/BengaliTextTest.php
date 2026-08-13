<?php

namespace Tests\Unit;

use App\Services\Import\BengaliText;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class BengaliTextTest extends TestCase
{
    #[DataProvider('dateLabels')]
    public function test_it_parses_bengali_date_labels(?string $label, ?string $expected): void
    {
        $this->assertSame($expected, BengaliText::parseDate($label));
    }

    public static function dateLabels(): array
    {
        return [
            'first seerat lesson' => ['০২ জানুয়ারি ২০২৬', '2026-01-02'],
            'february' => ['০৬ ফেব্রুয়ারি ২০২৬', '2026-02-06'],
            'single-syllable month' => ['০১ মে ২০২৬', '2026-05-01'],
            'last seerat lesson' => ['১৭ জুলাই ২০২৬', '2026-07-17'],
            // 17 legacy lessons carry this instead of a date; it must not be invented.
            'collected placeholder' => ['সংগৃহীত', null],
            'empty' => ['', null],
            'null' => [null, null],
            'nonsense' => ['কোনো তারিখ নেই', null],
        ];
    }

    #[DataProvider('formatDateLabels')]
    public function test_it_formats_a_date_into_a_bengali_label(?string $date, ?string $expected): void
    {
        $this->assertSame($expected, BengaliText::formatDateLabel($date));
    }

    public static function formatDateLabels(): array
    {
        return [
            'first seerat lesson' => ['2026-01-02', '০২ জানুয়ারি ২০২৬'],
            'february, single digit day' => ['2026-02-06', '০৬ ফেব্রুয়ারি ২০২৬'],
            'last seerat lesson' => ['2026-07-17', '১৭ জুলাই ২০২৬'],
            'empty' => ['', null],
            'null' => [null, null],
            'unparseable' => ['not-a-date', null],
        ];
    }

    /**
     * formatDateLabel() is the inverse of parseDate(): every label parseDate()
     * accepts as a real date must round-trip back to the same label, so the
     * calendar-derived label always matches what parsing expects.
     */
    public function test_format_and_parse_round_trip(): void
    {
        $label = '০৯ জানুয়ারি ২০২৬';

        $this->assertSame($label, BengaliText::formatDateLabel(BengaliText::parseDate($label)));
    }

    #[DataProvider('durationLabels')]
    public function test_it_parses_bengali_duration_labels(?string $label, ?int $expected): void
    {
        $this->assertSame($expected, BengaliText::parseDurationMinutes($label));
    }

    public static function durationLabels(): array
    {
        return [
            'hours and minutes' => ['২ ঘণ্টা ১৫ মিনিট', 135],
            'one hour fifty five' => ['১ ঘণ্টা ৫৫ মিনিট', 115],
            'minutes only' => ['৫৮ মিনিট', 58],
            'whole hour' => ['১ ঘণ্টা', 60],
            'long lecture' => ['৩ ঘণ্টা ৪২ মিনিট', 222],
            'unparseable' => ['অজানা', null],
            'null' => [null, null],
        ];
    }

    #[DataProvider('answerCells')]
    public function test_it_parses_correct_answer_cells(?string $cell, array $expected): void
    {
        $this->assertSame($expected, BengaliText::parseAnswerPositions($cell));
    }

    public static function answerCells(): array
    {
        return [
            'single latin' => ['1', [1]],
            'single bengali' => ['২', [2]],
            'multiple latin' => ['1, 2', [1, 2]],
            'multiple bengali' => ['১,২,৩', [1, 2, 3]],
            'mixed with spaces' => [' ১ , 3 ', [1, 3]],
            'duplicates collapse' => ['1, 1, 2', [1, 2]],
            'empty' => ['', []],
            'null' => [null, []],
            'non-numeric ignored' => ['abc', []],
        ];
    }

    public function test_digit_conversion_round_trips(): void
    {
        $this->assertSame('2026', BengaliText::toLatinDigits('২০২৬'));
        $this->assertSame('২০২৬', BengaliText::toBengaliDigits('2026'));
        $this->assertSame('২৫', BengaliText::toBengaliDigits(25));
    }

    /**
     * Bengali য় has a precomposed form (U+09DF) and a decomposed form
     * (U+09AF U+09BC) that render identically but compare unequal. Text authored
     * in Google Sheets will not reliably match text authored elsewhere, so the
     * importer must normalise before comparing.
     */
    public function test_normalisation_reconciles_the_two_forms_of_ya(): void
    {
        $precomposed = "\u{09AC}\u{09DF}\u{09BE}\u{09A8}";          // বয়ান
        $decomposed = "\u{09AC}\u{09AF}\u{09BC}\u{09BE}\u{09A8}";   // বয়ান

        $this->assertNotSame($precomposed, $decomposed, 'the two forms differ byte-wise');
        $this->assertSame(
            BengaliText::normalise($precomposed),
            BengaliText::normalise($decomposed),
            'normalisation must reconcile them'
        );
    }
}
