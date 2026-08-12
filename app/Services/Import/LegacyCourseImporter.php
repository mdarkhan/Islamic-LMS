<?php

namespace App\Services\Import;

use App\Models\Course;
use App\Models\Lesson;
use App\Models\LessonResource;
use Illuminate\Support\Facades\DB;

/**
 * Imports the course catalogue extracted from the legacy single-file app
 * (legacy/extracted/course-data.json).
 *
 * Idempotent: re-running matches on lessons.slug and updates in place.
 * Content that is genuinely absent in the source stays absent and is reported —
 * nothing is invented (MIGRATION.md §3).
 */
class LegacyCourseImporter
{
    private const COURSE_TITLES = [
        'seerat' => 'সীরাত',
        'tafsir' => 'তাফসির',
        'jummah' => 'জুমার বয়ান',
        'halakah' => 'মহিলাদের হালাকাহ',
    ];

    private const PLACEHOLDER_DESCRIPTION = 'এই ক্লাসের বিস্তারিত তথ্য ও কুইজ শীঘ্রই আপডেট করা হবে।';

    /**
     * @return array{
     *   courses:int, lessons:int, resources:int,
     *   gaps:array{placeholder_descriptions:array<int,string>, missing_dates:array<int,string>,
     *              resources_without_url:int, lessons_without_resources:array<int,string>,
     *              lessons_without_syllabus:int, empty_courses:array<int,string>}
     * }
     */
    public function import(string $jsonPath, bool $dryRun = false): array
    {
        $payload = json_decode(file_get_contents($jsonPath), true, flags: JSON_THROW_ON_ERROR);

        $report = [
            'courses' => 0,
            'lessons' => 0,
            'resources' => 0,
            'gaps' => [
                'placeholder_descriptions' => [],
                'missing_dates' => [],
                'resources_without_url' => 0,
                'lessons_without_resources' => [],
                'lessons_without_syllabus' => 0,
                'empty_courses' => [],
            ],
        ];

        $run = function () use ($payload, &$report, $dryRun) {
            $order = 0;
            foreach (self::COURSE_TITLES as $slug => $title) {
                $course = Course::query()->updateOrCreate(
                    ['slug' => $slug],
                    ['title' => $title, 'sort_order' => $order++, 'is_published' => true],
                );
                $report['courses']++;

                $lessons = array_values(array_filter(
                    $payload['lessons'],
                    fn ($l) => $l['category'] === $slug,
                ));

                if ($lessons === []) {
                    // e.g. tafsir: a homepage filter exists but no lesson was ever added.
                    $report['gaps']['empty_courses'][] = $slug;
                }

                foreach ($lessons as $i => $legacy) {
                    $this->importLesson($course, $legacy, $i, $report);
                }
            }

            if ($dryRun) {
                throw new DryRunComplete;
            }
        };

        try {
            DB::transaction($run);
        } catch (DryRunComplete) {
            // Preview only — everything rolled back by design.
        }

        return $report;
    }

    private function importLesson(Course $course, array $legacy, int $index, array &$report): void
    {
        $title = BengaliText::normalise($legacy['title']);
        $description = $legacy['description'] !== null
            ? BengaliText::normalise($legacy['description'])
            : null;

        $lesson = Lesson::query()->updateOrCreate(
            ['slug' => $legacy['quiz_slug'] ?? \Illuminate\Support\Str::slug($title)],
            [
                'course_id' => $course->getKey(),
                'title' => $title,
                'description' => $description,
                'summary' => $legacy['summary'] ?: null,
                'syllabus' => $legacy['syllabus'] ? BengaliText::normalise($legacy['syllabus']) : null,

                // Keep both: the label is the only faithful record for the lessons
                // whose "date" is the word সংগৃহীত. Normalised to NFC like every other
                // Bengali string so equality comparisons behave — this canonicalises
                // the encoding, it does not change what renders.
                'held_on' => BengaliText::parseDate($legacy['date_label']),
                'date_label' => BengaliText::normalise($legacy['date_label']),

                'duration_minutes' => BengaliText::parseDurationMinutes($legacy['duration_label']),
                'duration_label' => BengaliText::normalise($legacy['duration_label']),

                'media_provider' => $legacy['drive_link'] ? 'google_drive' : 'none',
                'media_url' => $legacy['drive_link'],
                'media_file_id' => $this->driveFileId($legacy['drive_link'] ?? null),

                'sort_order' => $index,
                'is_published' => true,
                'legacy_id' => $legacy['legacy_id'],
            ],
        );

        $report['lessons']++;

        if ($description === BengaliText::normalise(self::PLACEHOLDER_DESCRIPTION)) {
            $report['gaps']['placeholder_descriptions'][] = $lesson->slug;
        }
        if ($lesson->held_on === null) {
            $report['gaps']['missing_dates'][] = $lesson->slug;
        }
        if ($lesson->syllabus === null) {
            $report['gaps']['lessons_without_syllabus']++;
        }
        if ($legacy['resources'] === []) {
            $report['gaps']['lessons_without_resources'][] = $lesson->slug;
        }

        $lesson->resources()->delete();

        foreach ($legacy['resources'] as $i => $resource) {
            // Every legacy resource URL is the placeholder "#": store NULL so it
            // renders as plain text rather than a dead link.
            $url = ($resource['url'] ?? '#') === '#' ? null : $resource['url'];

            if ($url === null) {
                $report['gaps']['resources_without_url']++;
            }

            LessonResource::query()->create([
                'lesson_id' => $lesson->getKey(),
                'label' => BengaliText::normalise($resource['label']),
                'url' => $url,
                'kind' => 'book',
                'sort_order' => $i,
            ]);

            $report['resources']++;
        }
    }

    private function driveFileId(?string $url): ?string
    {
        if ($url === null) {
            return null;
        }

        return preg_match('#/file/d/([^/]+)/#', $url, $m) ? $m[1] : null;
    }
}
