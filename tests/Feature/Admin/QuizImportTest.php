<?php

namespace Tests\Feature\Admin;

use App\Models\Course;
use App\Models\Lesson;
use App\Models\Quiz;
use App\Services\Quiz\QuizImportParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Tests\TestCase;

class QuizImportTest extends TestCase
{
    use RefreshDatabase;

    /** Wrap 0-indexed cell rows into the {row, cells} shape the parser expects. */
    private function rows(array $cellRows): array
    {
        $out = [];
        foreach ($cellRows as $i => $cells) {
            $out[] = ['row' => $i + 1, 'cells' => $cells];
        }

        return $out;
    }

    private function header(): array
    {
        return array_merge(
            ['Question'],
            array_map(fn ($n) => "Option {$n}", range(1, 12)),
            ['Correct Answer', 'Timer (Seconds)', 'Password', 'Start Date', 'Start Time', 'End Date', 'End Time', 'Exam Name', 'Leaderboard Password', 'Mega Question'],
        );
    }

    /** A question row with the config columns filled (the legacy "same row" case). */
    private function firstRow(): array
    {
        $row = array_fill(0, 23, '');
        $row[0] = 'রাজধানী কোনটি?';
        $row[1] = 'ঢাকা';
        $row[2] = 'চট্টগ্রাম';
        $row[13] = '1';           // correct: option 1
        $row[14] = '600';         // timer seconds
        $row[15] = 'secret';      // password (ignored)
        $row[16] = '2026-02-01';  // start date
        $row[17] = '10:00';       // start time
        $row[18] = '2026-02-01';  // end date
        $row[19] = '11:00';       // end time
        $row[20] = 'সীরাত-২৭';    // exam name
        $row[21] = 'lbpass';      // leaderboard password (ignored)

        return $row;
    }

    public function test_config_and_the_first_question_share_a_row_and_neither_is_lost(): void
    {
        $rows = $this->rows([$this->header(), $this->firstRow()]);

        $parsed = app(QuizImportParser::class)->parse($rows, 'seerat-27');

        $this->assertSame('সীরাত-২৭', $parsed['config']['title']);
        $this->assertSame(600, $parsed['config']['duration_seconds']);
        $this->assertNotNull($parsed['config']['starts_at']);
        $this->assertSame('Asia/Dhaka', $parsed['config']['starts_at']->timezone->getName());
        $this->assertTrue($parsed['config']['legacy_password_detected']);

        // The config row is ALSO the first question — it must not be skipped.
        $this->assertSame(1, $parsed['valid_count']);
        $this->assertSame('রাজধানী কোনটি?', $parsed['questions'][0]['body']);
    }

    public function test_bengali_correct_indices_and_multiple_answers(): void
    {
        $multi = array_fill(0, 23, '');
        $multi[0] = 'কোনগুলো সঠিক?';
        $multi[1] = 'ক';
        $multi[2] = 'খ';
        $multi[3] = 'গ';
        $multi[13] = '১, ২';   // Bengali numerals, two answers
        $multi[22] = '৫';      // mega marks

        $parsed = app(QuizImportParser::class)->parse($this->rows([$this->header(), $multi]), 'x');
        $q = $parsed['questions'][0];

        $this->assertSame([1, 2], $q['correct_positions']);
        $this->assertSame('multiple', $q['type']);
        $this->assertSame(5, $q['marks'], 'mega column becomes canonical marks');
    }

    public function test_blank_mega_column_defaults_to_one_mark(): void
    {
        $parsed = app(QuizImportParser::class)->parse($this->rows([$this->header(), $this->firstRow()]), 'x');
        $this->assertSame(1, $parsed['questions'][0]['marks']);
    }

    public function test_twelve_options_are_supported(): void
    {
        $row = array_fill(0, 23, '');
        $row[0] = '১২টি অপশন';
        for ($i = 1; $i <= 12; $i++) {
            $row[$i] = "অপশন {$i}";
        }
        $row[13] = '12';

        $parsed = app(QuizImportParser::class)->parse($this->rows([$this->header(), $row]), 'x');
        $this->assertCount(12, $parsed['questions'][0]['options']);
        $this->assertSame([12], $parsed['questions'][0]['correct_positions']);
        $this->assertFalse($parsed['has_fatal']);
    }

    public function test_out_of_range_correct_index_is_a_fatal_error(): void
    {
        $row = array_fill(0, 23, '');
        $row[0] = 'ভুল সূচক';
        $row[1] = 'ক';
        $row[2] = 'খ';
        $row[13] = '5';   // only 2 options exist

        $parsed = app(QuizImportParser::class)->parse($this->rows([$this->header(), $row]), 'x');

        $this->assertTrue($parsed['has_fatal']);
        $this->assertNotEmpty($parsed['questions'][0]['errors']);
    }

    public function test_various_date_formats_parse_to_dhaka(): void
    {
        foreach (['2026-02-01' => '10:00', '01/02/2026' => '2:30 PM'] as $date => $time) {
            $row = array_fill(0, 23, '');
            $row[0] = 'প্রশ্ন';
            $row[1] = 'ক';
            $row[2] = 'খ';
            $row[13] = '1';
            $row[16] = $date;
            $row[17] = $time;

            $parsed = app(QuizImportParser::class)->parse($this->rows([$this->header(), $row]), 'x');
            $this->assertNotNull($parsed['config']['starts_at'], "failed for {$date}");
            $this->assertSame(2026, $parsed['config']['starts_at']->year);
            $this->assertSame(2, $parsed['config']['starts_at']->month);
        }
    }

    public function test_an_unparseable_filled_date_is_reported_not_guessed(): void
    {
        $row = array_fill(0, 23, '');
        $row[0] = 'প্রশ্ন';
        $row[1] = 'ক';
        $row[2] = 'খ';
        $row[13] = '1';
        $row[16] = 'গতকাল';   // not a date

        $parsed = app(QuizImportParser::class)->parse($this->rows([$this->header(), $row]), 'x');
        $this->assertNull($parsed['config']['starts_at']);
        $this->assertNotNull($parsed['config']['schedule_error']);
    }

    // ── Full HTTP flow ───────────────────────────────────────────────────────────

    private function csvContent(): string
    {
        $lines = [$this->header(), $this->firstRow()];
        $multi = array_fill(0, 23, '');
        $multi[0] = 'কোনগুলো সঠিক?';
        $multi[1] = 'ক';
        $multi[2] = 'খ';
        $multi[3] = 'গ';
        $multi[13] = '১,২';
        $multi[22] = '৫';
        $lines[] = $multi;

        return collect($lines)->map(fn ($row) => collect($row)->map(fn ($c) => '"'.str_replace('"', '""', (string) $c).'"')->implode(','))->implode("\n");
    }

    public function test_full_csv_import_creates_a_draft_quiz_with_questions_and_options(): void
    {
        Storage::fake('local');
        $admin = $this->makeAdmin();
        $course = Course::factory()->create(['slug' => 'seerat']);
        $lesson = Lesson::factory()->for($course)->create(['slug' => 'seerat-27', 'title' => 'সীরাত-২৭']);

        // upload → preview
        $preview = $this->actingAs($admin)->post(route('admin.quizzes.import.upload'), [
            'file' => UploadedFile::fake()->createWithContent('quiz.csv', $this->csvContent()),
        ]);
        $preview->assertOk();
        $token = $preview->viewData('token');
        $suggestion = $preview->viewData('suggestion');

        // Course/lesson auto-suggested from the sheet name.
        $this->assertSame($course->id, $suggestion['course_id']);
        $this->assertSame($lesson->id, $suggestion['lesson_id']);

        // confirm
        $this->actingAs($admin)->post(route('admin.quizzes.import.confirm'), [
            'token' => $token, 'sheet' => 'CSV',
            'title' => 'সীরাত-২৭ পরীক্ষা', 'course_id' => $course->id, 'lesson_id' => $lesson->id, 'point_cost' => 2,
        ])->assertRedirect();

        $quiz = Quiz::query()->where('title', 'সীরাত-২৭ পরীক্ষা')->firstOrFail();
        $this->assertSame('draft', $quiz->status);
        $this->assertSame(2, $quiz->point_cost);
        $this->assertSame(600, $quiz->duration_seconds);
        $this->assertSame(2, $quiz->questions()->count());
        $this->assertSame(6, $quiz->total_marks, '1 + 5 mega marks');
        // Multiple-answer question mapped correctly.
        $multi = $quiz->questions()->where('type', 'multiple')->firstOrFail();
        $this->assertCount(2, $multi->correctOptionIds());

        // Temp upload deleted after commit.
        $this->assertCount(0, Storage::disk('local')->allFiles('imports/quizzes'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'quiz.imported']);
    }

    public function test_missing_lesson_links_course_only_without_fabrication(): void
    {
        $course = Course::factory()->create(['slug' => 'seerat']);
        // No lesson seerat-99 exists.
        $matcher = app(\App\Services\Quiz\QuizCourseMatcher::class);
        $result = $matcher->suggest('Seerat-99', null);

        $this->assertSame($course->id, $result['course_id']);
        $this->assertNull($result['lesson_id'], 'no lesson is fabricated');
        $this->assertNotEmpty($result['warnings']);
    }

    public function test_duplicate_title_is_flagged_in_preview(): void
    {
        Storage::fake('local');
        $admin = $this->makeAdmin();
        Quiz::factory()->create(['title' => 'সীরাত-২৭']);   // Exam Name in the sheet is সীরাত-২৭

        $preview = $this->actingAs($admin)->post(route('admin.quizzes.import.upload'), [
            'file' => UploadedFile::fake()->createWithContent('quiz.csv', $this->csvContent()),
        ]);

        $this->assertNotNull($preview->viewData('duplicate'), 'existing same-title quiz is detected');
    }

    public function test_xlsx_with_multiple_sheets_prompts_for_worksheet_selection(): void
    {
        Storage::fake('local');
        $admin = $this->makeAdmin();

        $path = Storage::disk('local')->path('multi.xlsx');
        @mkdir(dirname($path), 0777, true);
        $writer = new Writer;
        $writer->openToFile($path);
        $writer->getCurrentSheet()->setName('Live');
        $writer->addRow(Row::fromValues($this->header()));
        $writer->addRow(Row::fromValues($this->firstRow()));
        $writer->addNewSheetAndMakeItCurrent()->setName('seerat-27');
        $writer->addRow(Row::fromValues($this->header()));
        $writer->close();

        $response = $this->actingAs($admin)->post(route('admin.quizzes.import.upload'), [
            'file' => new UploadedFile($path, 'multi.xlsx', null, null, true),
        ]);

        $response->assertOk()->assertViewIs('admin.quizzes.import.sheet');
        $this->assertSame(['Live', 'seerat-27'], $response->viewData('sheets'));
    }

    public function test_import_confirm_is_blocked_when_there_are_fatal_errors(): void
    {
        Storage::fake('local');
        $admin = $this->makeAdmin();

        // A sheet whose only question has an out-of-range correct index.
        $bad = array_fill(0, 23, '');
        $bad[0] = 'ভুল';
        $bad[1] = 'ক';
        $bad[2] = 'খ';
        $bad[13] = '9';
        $csv = collect([$this->header(), $bad])->map(fn ($r) => collect($r)->map(fn ($c) => '"'.$c.'"')->implode(','))->implode("\n");

        $token = $this->actingAs($admin)->post(route('admin.quizzes.import.upload'), [
            'file' => UploadedFile::fake()->createWithContent('bad.csv', $csv),
        ])->viewData('token');

        $this->actingAs($admin)->from(route('admin.quizzes.import.form'))->post(route('admin.quizzes.import.confirm'), [
            'token' => $token, 'sheet' => 'CSV', 'title' => 'ব্যর্থ', 'point_cost' => 1,
        ])->assertSessionHas('error');

        $this->assertSame(0, Quiz::query()->count(), 'nothing imported when fatal errors exist');
    }
}
