<?php

namespace App\Services\Quiz;

use App\Models\QuizQuestion;
use App\Services\Import\BengaliText;
use App\Services\Import\SheetDate;
use Carbon\CarbonImmutable;

/**
 * Parses the legacy quiz workbook layout into a validated, structured preview.
 * Pure — no database writes. Fatal errors block the import; warnings do not.
 *
 * Legacy columns (0-indexed): A(0) Question · B–M(1–12) Option 1–12 ·
 * N(13) Correct Answer · O(14) Timer seconds · P(15) Password · Q(16) Start Date ·
 * R(17) Start Time · S(18) End Date · T(19) End Time · U(20) Exam Name ·
 * V(21) Leaderboard Password · W(22) Mega Question / custom marks.
 *
 * The first real question may share the row that also carries the config values, so
 * the config row is never skipped as a question.
 */
class QuizImportParser
{
    private const COL_QUESTION = 0;
    private const COL_OPTION_FIRST = 1;
    private const COL_OPTION_LAST = 12;
    private const COL_CORRECT = 13;
    private const COL_TIMER = 14;
    private const COL_PASSWORD = 15;
    private const COL_START_DATE = 16;
    private const COL_START_TIME = 17;
    private const COL_END_DATE = 18;
    private const COL_END_TIME = 19;
    private const COL_EXAM_NAME = 20;
    private const COL_LEADERBOARD_PASSWORD = 21;
    private const COL_MARKS = 22;

    // Any of these being populated marks a row as carrying quiz configuration.
    private const CONFIG_COLS = [
        self::COL_TIMER, self::COL_PASSWORD, self::COL_START_DATE,
        self::COL_START_TIME, self::COL_END_DATE, self::COL_END_TIME, self::COL_EXAM_NAME,
    ];

    /**
     * @param  array<int, array{row:int, cells:array<int, mixed>}>  $rows  from SpreadsheetReader
     * @param  string|null  $sheetName  used as a title/course-matching fallback
     * @return array<string, mixed>
     */
    public function parse(array $rows, ?string $sheetName = null): array
    {
        $dataRows = $this->stripHeader($rows);

        $config = $this->parseConfig($dataRows, $sheetName);
        $questions = [];
        $bodies = [];
        $duplicateRows = [];
        $totalMarks = 0;

        foreach ($dataRows as $entry) {
            $cells = $entry['cells'];
            $body = $this->str($cells, self::COL_QUESTION);

            if ($body === '') {
                // A wholly blank row is ignored; a row with content but no question
                // text is a malformed row worth flagging.
                if ($this->rowHasContent($cells)) {
                    $questions[] = $this->malformedRow($entry['row']);
                }

                continue;
            }

            $question = $this->parseQuestion($entry['row'], $cells, $body);
            $questions[] = $question;

            if ($question['errors'] === []) {
                $totalMarks += $question['marks'];
            }

            $normal = BengaliText::normalise($body);
            if (isset($bodies[$normal])) {
                $duplicateRows[] = $entry['row'];
            }
            $bodies[$normal] = true;
        }

        $errors = [];
        $warnings = [];

        $valid = array_values(array_filter($questions, fn ($q) => $q['errors'] === [] && ! ($q['malformed'] ?? false)));
        if ($valid === []) {
            $errors[] = 'কোনো বৈধ প্রশ্ন পাওয়া যায়নি।';
        }
        if ($duplicateRows !== []) {
            $warnings[] = 'একই প্রশ্ন একাধিকবার আছে (সারি '.implode(', ', array_map(fn ($r) => (string) $r, $duplicateRows)).')।';
        }
        if ($config['legacy_password_detected']) {
            $warnings[] = 'পুরনো কুইজ পাসওয়ার্ড শনাক্ত হয়েছে, কিন্তু ইচ্ছাকৃতভাবে ইমপোর্ট করা হয়নি। নতুন সিস্টেমে শিক্ষার্থী অ্যাকাউন্ট ও পারমিশন ব্যবহৃত হয়।';
        }

        return [
            'config' => $config,
            'questions' => $questions,
            'valid_count' => count($valid),
            'total_marks' => $totalMarks,
            'errors' => $errors,
            'warnings' => $warnings,
            'has_fatal' => $errors !== [] || $this->anyFatal($questions),
        ];
    }

    /**
     * Drop a leading header row (col A is literally "Question"/"প্রশ্ন").
     *
     * @param  array<int, array{row:int, cells:array<int, mixed>}>  $rows
     * @return array<int, array{row:int, cells:array<int, mixed>}>
     */
    private function stripHeader(array $rows): array
    {
        if ($rows === []) {
            return [];
        }

        $first = mb_strtolower($this->str($rows[0]['cells'], self::COL_QUESTION));
        if (in_array(BengaliText::normalise($first), ['question', 'প্রশ্ন'], true)) {
            return array_slice($rows, 1);
        }

        return $rows;
    }

    /**
     * @param  array<int, array{row:int, cells:array<int, mixed>}>  $dataRows
     * @return array<string, mixed>
     */
    private function parseConfig(array $dataRows, ?string $sheetName): array
    {
        $configCells = null;
        $configRow = null;

        foreach ($dataRows as $entry) {
            foreach (self::CONFIG_COLS as $col) {
                if ($this->raw($entry['cells'], $col) !== '' && $this->raw($entry['cells'], $col) !== null) {
                    $configCells = $entry['cells'];
                    $configRow = $entry['row'];
                    break 2;
                }
            }
        }

        $examName = $configCells ? $this->str($configCells, self::COL_EXAM_NAME) : '';
        $title = $examName !== '' ? $examName : ($sheetName ?? 'নতুন কুইজ');

        $timer = $configCells ? $this->str($configCells, self::COL_TIMER) : '';
        $durationSeconds = ($timer !== '' && is_numeric(BengaliText::toLatinDigits($timer)))
            ? (int) BengaliText::toLatinDigits($timer)
            : null;
        if ($durationSeconds !== null && $durationSeconds <= 0) {
            $durationSeconds = null;
        }

        $startsAt = $configCells
            ? SheetDate::parse($configCells[self::COL_START_DATE] ?? null, $configCells[self::COL_START_TIME] ?? null)
            : null;
        $endsAt = $configCells
            ? SheetDate::parse($configCells[self::COL_END_DATE] ?? null, $configCells[self::COL_END_TIME] ?? null)
            : null;

        $legacyPassword = $configCells
            && ($this->str($configCells, self::COL_PASSWORD) !== '' || $this->str($configCells, self::COL_LEADERBOARD_PASSWORD) !== '');

        return [
            'title' => BengaliText::normalise($title),
            'duration_seconds' => $durationSeconds,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'schedule_error' => $this->scheduleError($configCells, $startsAt, $endsAt),
            'legacy_password_detected' => $legacyPassword,
            'config_row' => $configRow,
        ];
    }

    /**
     * @param  array<int, mixed>|null  $cells
     */
    private function scheduleError(?array $cells, ?CarbonImmutable $startsAt, ?CarbonImmutable $endsAt): ?string
    {
        if ($cells === null) {
            return null;
        }

        // A date column was filled but could not be parsed → report, never guess.
        if ($this->str($cells, self::COL_START_DATE) !== '' && $startsAt === null) {
            return 'শুরুর তারিখ পড়া যায়নি।';
        }
        if ($this->str($cells, self::COL_END_DATE) !== '' && $endsAt === null) {
            return 'শেষের তারিখ পড়া যায়নি।';
        }
        if ($startsAt && $endsAt && $endsAt->lessThanOrEqualTo($startsAt)) {
            return 'শেষ সময় অবশ্যই শুরুর সময়ের পরে হতে হবে।';
        }

        return null;
    }

    /**
     * @param  array<int, mixed>  $cells
     * @return array<string, mixed>
     */
    private function parseQuestion(int $row, array $cells, string $body): array
    {
        $options = [];
        for ($col = self::COL_OPTION_FIRST; $col <= self::COL_OPTION_LAST; $col++) {
            $opt = $this->str($cells, $col);
            if ($opt !== '') {
                $options[] = $opt;
            }
        }

        $correct = BengaliText::parseAnswerPositions($this->str($cells, self::COL_CORRECT));

        $marksRaw = BengaliText::toLatinDigits($this->str($cells, self::COL_MARKS));
        $marks = (is_numeric($marksRaw) && (int) $marksRaw >= 1) ? (int) $marksRaw : 1;

        $errors = [];
        $warnings = [];

        if (count($options) < 2) {
            $errors[] = 'কমপক্ষে ২টি অপশন প্রয়োজন।';
        }
        if ($correct === []) {
            $errors[] = 'সঠিক উত্তর নির্ধারণ করা হয়নি।';
        }
        $outOfRange = array_filter($correct, fn ($pos) => $pos < 1 || $pos > count($options));
        if ($outOfRange !== []) {
            $errors[] = 'সঠিক উত্তরের সূচক অপশনের সীমার বাইরে: '.implode(', ', array_map(fn ($p) => bn($p), $outOfRange)).'।';
        }

        return [
            'row' => $row,
            'body' => BengaliText::normalise($body),
            'options' => $options,
            'correct_positions' => array_values($correct),
            'type' => count($correct) > 1 ? QuizQuestion::TYPE_MULTIPLE : QuizQuestion::TYPE_SINGLE,
            'marks' => $marks,
            'errors' => $errors,
            'warnings' => $warnings,
            'malformed' => false,
        ];
    }

    /** @return array<string, mixed> */
    private function malformedRow(int $row): array
    {
        return [
            'row' => $row,
            'body' => '',
            'options' => [],
            'correct_positions' => [],
            'type' => QuizQuestion::TYPE_SINGLE,
            'marks' => 1,
            'errors' => ['প্রশ্নের ঘর খালি কিন্তু সারিতে অন্য তথ্য আছে।'],
            'warnings' => [],
            'malformed' => true,
        ];
    }

    /** @param array<int, array<string, mixed>> $questions */
    private function anyFatal(array $questions): bool
    {
        foreach ($questions as $q) {
            if ($q['errors'] !== []) {
                return true;
            }
        }

        return false;
    }

    /** @param array<int, mixed> $cells */
    private function rowHasContent(array $cells): bool
    {
        foreach ($cells as $cell) {
            if ($cell instanceof \DateTimeInterface) {
                return true;
            }
            if (is_scalar($cell) && trim((string) $cell) !== '') {
                return true;
            }
        }

        return false;
    }

    /** @param array<int, mixed> $cells */
    private function str(array $cells, int $col): string
    {
        $value = $cells[$col] ?? '';
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i');
        }

        return is_scalar($value) ? trim((string) $value) : '';
    }

    /** @param array<int, mixed> $cells */
    private function raw(array $cells, int $col): mixed
    {
        return $cells[$col] ?? '';
    }
}
