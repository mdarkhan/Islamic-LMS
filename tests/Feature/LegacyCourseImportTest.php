<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Lesson;
use App\Models\LessonResource;
use App\Services\Import\BengaliText;
use App\Services\Import\LegacyCourseImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Runs against the real extracted legacy data, so a regression in parsing shows up
 * as a failing test rather than as corrupted Bengali in production.
 */
class LegacyCourseImportTest extends TestCase
{
    use RefreshDatabase;

    private function path(): string
    {
        return base_path('legacy/extracted/course-data.json');
    }

    protected function setUp(): void
    {
        parent::setUp();

        if (! is_file($this->path())) {
            $this->markTestSkipped('legacy/extracted/course-data.json is not present.');
        }
    }

    public function test_it_imports_every_legacy_lesson(): void
    {
        $report = app(LegacyCourseImporter::class)->import($this->path());

        $this->assertSame(4, $report['courses']);
        $this->assertSame(42, $report['lessons']);
        $this->assertSame(42, Lesson::query()->count());

        $this->assertSame(25, Lesson::query()->whereRelation('course', 'slug', 'seerat')->count());
        $this->assertSame(14, Lesson::query()->whereRelation('course', 'slug', 'halakah')->count());
        $this->assertSame(3, Lesson::query()->whereRelation('course', 'slug', 'jummah')->count());
        $this->assertSame(0, Lesson::query()->whereRelation('course', 'slug', 'tafsir')->count());
    }

    public function test_a_dry_run_writes_nothing(): void
    {
        $report = app(LegacyCourseImporter::class)->import($this->path(), dryRun: true);

        $this->assertSame(42, $report['lessons'], 'the preview still reports what it would do');
        $this->assertSame(0, Lesson::query()->count(), 'but nothing is persisted');
        $this->assertSame(0, Course::query()->count());
    }

    public function test_it_is_idempotent(): void
    {
        $importer = app(LegacyCourseImporter::class);
        $importer->import($this->path());
        $importer->import($this->path());

        $this->assertSame(42, Lesson::query()->count());
        $this->assertSame(25, LessonResource::query()->count());
    }

    public function test_bengali_text_survives_the_round_trip(): void
    {
        app(LegacyCourseImporter::class)->import($this->path());

        $lesson = Lesson::query()->where('slug', 'seerat-2')->firstOrFail();

        $this->assertSame('সীরাত-০২', $lesson->title);
        $this->assertStringContainsString('ইব্রাহিম', $lesson->description);
        $this->assertSame('সীরাত সংরক্ষণের ইতিহাস ও নির্ভরযোগ্যতা', $lesson->summary[0]);
    }

    public function test_dates_are_parsed_where_real_and_null_where_absent(): void
    {
        app(LegacyCourseImporter::class)->import($this->path());

        $seerat = Lesson::query()->where('slug', 'seerat-2')->firstOrFail();
        $this->assertSame('2026-01-09', $seerat->held_on->toDateString());

        // Compared after normalisation: this very assertion caught the hazard the
        // importer guards against — the literal typed here used decomposed য়
        // (U+09AF U+09BC) while the source file uses precomposed য় (U+09DF).
        $this->assertSame(
            BengaliText::normalise('০৯ জানুয়ারি ২০২৬'),
            $seerat->date_label,
            'original label preserved (NFC-normalised)'
        );

        // "সংগৃহীত" is not a date and must not be invented.
        $halakah = Lesson::query()->where('slug', 'halakah-1')->firstOrFail();
        $this->assertNull($halakah->held_on);
        $this->assertSame('সংগৃহীত', $halakah->date_label);

        $this->assertSame(25, Lesson::query()->whereNotNull('held_on')->count());
    }

    public function test_durations_are_parsed_for_every_lesson(): void
    {
        app(LegacyCourseImporter::class)->import($this->path());

        $this->assertSame(42, Lesson::query()->whereNotNull('duration_minutes')->count());
        $this->assertSame(115, Lesson::query()->where('slug', 'seerat-2')->value('duration_minutes'));
    }

    public function test_placeholder_resource_urls_become_null(): void
    {
        app(LegacyCourseImporter::class)->import($this->path());

        // All 25 legacy resources have url "#": keep the label, drop the dead link.
        $this->assertSame(25, LessonResource::query()->count());
        $this->assertSame(0, LessonResource::query()->whereNotNull('url')->count());
        $this->assertSame(0, LessonResource::query()->where('url', '#')->count());
    }

    public function test_drive_embed_url_is_derived_from_the_file_id(): void
    {
        app(LegacyCourseImporter::class)->import($this->path());

        $lesson = Lesson::query()->where('slug', 'seerat-2')->firstOrFail();

        $this->assertSame('1ZDWo-M7Ai0EFPyO2sF19OV7X38yDWDOk', $lesson->media_file_id);
        $this->assertSame(
            'https://drive.google.com/file/d/1ZDWo-M7Ai0EFPyO2sF19OV7X38yDWDOk/preview',
            $lesson->embedUrl(),
        );
    }

    public function test_it_reports_content_gaps_rather_than_inventing_content(): void
    {
        $report = app(LegacyCourseImporter::class)->import($this->path());
        $gaps = $report['gaps'];

        $this->assertCount(22, $gaps['placeholder_descriptions']);
        $this->assertCount(17, $gaps['missing_dates']);
        $this->assertCount(17, $gaps['lessons_without_resources']);
        $this->assertSame(35, $gaps['lessons_without_syllabus']);
        $this->assertSame(25, $gaps['resources_without_url']);

        // Tafsir has a homepage filter but no lesson was ever added to the source.
        $this->assertSame(['tafsir'], $gaps['empty_courses']);
    }
}
