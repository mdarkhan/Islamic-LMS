<?php

namespace App\Console\Commands;

use App\Models\Course;
use App\Models\Lesson;
use App\Models\LessonResource;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class LegacyVerify extends Command
{
    protected $signature = 'legacy:verify';

    protected $description = 'Read-only verification of legacy course data and migration inputs';

    public function handle(): int
    {
        $sourcePath = base_path('legacy/extracted/course-data.json');
        if (! is_file($sourcePath)) {
            $this->error('Legacy course source is missing.');

            return self::FAILURE;
        }

        $source = json_decode(file_get_contents($sourcePath), true, flags: JSON_THROW_ON_ERROR);
        $expected = collect($source['lessons'])->pluck('quiz_slug')->filter()->unique()->sort()->values();
        $actual = Lesson::query()->whereHas('course', fn ($q) => $q->whereIn('slug', ['seerat', 'tafsir', 'halakah', 'jummah']))
            ->pluck('slug')->sort()->values();
        $missing = $expected->diff($actual)->values()->all();
        $unexpected = $actual->diff($expected)->values()->all();
        $mappedExpected = $expected->intersect($actual)->count();
        $duplicateCourses = DB::table('courses')->select('slug')->groupBy('slug')->havingRaw('COUNT(*) > 1')->get()->count();
        $duplicateLessons = DB::table('lessons')->select('slug')->groupBy('slug')->havingRaw('COUNT(*) > 1')->get()->count();
        $placeholderLinks = LessonResource::query()->where('url', '#')->count();
        $invalidUnicode = Lesson::query()->get(['id', 'title', 'description'])->filter(fn (Lesson $lesson) => ! mb_check_encoding($lesson->title, 'UTF-8')
            || ($lesson->description !== null && ! mb_check_encoding($lesson->description, 'UTF-8'))
        )->count();
        $tafsirLessons = Lesson::query()->whereRelation('course', 'slug', 'tafsir')->count();
        $exports = is_dir(base_path('legacy/db'))
            ? collect(scandir(base_path('legacy/db')))->filter(fn ($name) => preg_match('/\.(csv|xlsx|sql)$/i', $name))->values()
            : collect();

        $checks = [
            ['Expected source lessons', $expected->count(), $expected->count() === 42 ? 'PASS' : 'FAIL'],
            ['Mapped expected legacy lessons', $mappedExpected, $missing === [] ? 'PASS' : 'FAIL'],
            ['Missing expected lessons', count($missing), $missing === [] ? 'PASS' : 'FAIL'],
            ['Additional post-legacy lessons', count($unexpected), 'INFO'],
            ['Duplicate course slugs', $duplicateCourses, $duplicateCourses === 0 ? 'PASS' : 'FAIL'],
            ['Duplicate lesson slugs', $duplicateLessons, $duplicateLessons === 0 ? 'PASS' : 'FAIL'],
            ['Clickable # resource links', $placeholderLinks, $placeholderLinks === 0 ? 'PASS' : 'FAIL'],
            ['Invalid UTF-8 lesson fields', $invalidUnicode, $invalidUnicode === 0 ? 'PASS' : 'FAIL'],
            ['Tafsir lessons (known honest gap)', $tafsirLessons, $tafsirLessons === 0 ? 'PASS' : 'REVIEW'],
            ['Repository-local DB exports', $exports->count(), $exports->isNotEmpty() ? 'READY' : 'EXPECTED_PRIVATE'],
            ['Current course count', Course::query()->count(), 'INFO'],
        ];
        $this->table(['Check', 'Count', 'Status'], $checks);

        if ($exports->isEmpty()) {
            $this->warn('Legacy result migration tooling: READY');
            $this->warn('No result export is stored in legacy/db/ (expected for private inputs); assess external sources separately.');
        }
        if ($missing !== []) {
            $this->line('Missing: '.implode(', ', $missing));
        }
        if ($unexpected !== []) {
            $this->line('Additional post-legacy lessons: '.implode(', ', $unexpected));
        }

        $failed = $expected->count() !== 42 || $missing !== []
            || $duplicateCourses > 0 || $duplicateLessons > 0 || $placeholderLinks > 0 || $invalidUnicode > 0;

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
