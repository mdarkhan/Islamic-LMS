<?php

namespace App\Services\Quiz;

use App\Models\Course;
use App\Models\Lesson;
use App\Services\Import\BengaliText;

/**
 * Suggests a course (and, when it exists, a lesson) for an imported quiz from its
 * sheet/tab name or Exam Name — e.g. "Seerat-27", "seerat-26", "tafseer-1".
 *
 * Matching is case-insensitive by known category prefix. When the category's course
 * exists but the numbered lesson does not (the audited Seerat 26/27 gap, or Tafsir
 * which has no lessons), the course is linked and the lesson left null with a
 * warning — a lesson is never fabricated. The admin can override before confirming.
 */
class QuizCourseMatcher
{
    /** category => [course slug, lesson slug prefix] */
    private const CATEGORIES = [
        'seerat' => ['seerat', 'seerat'],
        'tafsir' => ['tafsir', 'tafseer'],
        'halakah' => ['halakah', 'halakah'],
        'jummah' => ['jummah', 'jummah'],
    ];

    /** Latin and Bengali tokens that resolve to a category. */
    private const ALIASES = [
        'seerat' => 'seerat', 'সীরাত' => 'seerat',
        'tafseer' => 'tafsir', 'tafsir' => 'tafsir', 'তাফসির' => 'tafsir', 'তাফসীর' => 'tafsir',
        'halakah' => 'halakah', 'halaqah' => 'halakah', 'হালাকাহ' => 'halakah', 'হালাক্বাহ' => 'halakah',
        'jummah' => 'jummah', 'jumma' => 'jummah', 'জুমা' => 'jummah', 'জুমার' => 'jummah',
    ];

    /**
     * @return array{course_id:?int, lesson_id:?int, category:?string, number:?int, warnings:array<int,string>}
     */
    public function suggest(?string ...$sources): array
    {
        foreach ($sources as $source) {
            if ($source === null || trim($source) === '') {
                continue;
            }

            $category = $this->category($source);
            if ($category === null) {
                continue;
            }

            return $this->resolve($category, $this->number($source));
        }

        return ['course_id' => null, 'lesson_id' => null, 'category' => null, 'number' => null, 'warnings' => []];
    }

    private function category(string $source): ?string
    {
        $haystack = mb_strtolower(BengaliText::normalise($source));

        foreach (self::ALIASES as $token => $category) {
            if (str_contains($haystack, BengaliText::normalise($token))) {
                return $category;
            }
        }

        return null;
    }

    private function number(string $source): ?int
    {
        preg_match('/\d+/', BengaliText::toLatinDigits($source), $m);

        return isset($m[0]) ? (int) $m[0] : null;
    }

    /**
     * @return array{course_id:?int, lesson_id:?int, category:?string, number:?int, warnings:array<int,string>}
     */
    private function resolve(string $category, ?int $number): array
    {
        [$courseSlug, $lessonPrefix] = self::CATEGORIES[$category];
        $warnings = [];

        $course = Course::query()->where('slug', $courseSlug)->first();
        if ($course === null) {
            $warnings[] = "'{$courseSlug}' কোর্সটি এখনো তৈরি হয়নি — ম্যানুয়ালি নির্বাচন করুন।";

            return ['course_id' => null, 'lesson_id' => null, 'category' => $category, 'number' => $number, 'warnings' => $warnings];
        }

        $lesson = null;
        if ($number !== null) {
            $lesson = Lesson::query()->where('slug', $lessonPrefix.'-'.$number)->first();
            if ($lesson === null) {
                $warnings[] = "মিল থাকা ক্লাস ({$lessonPrefix}-{$number}) পাওয়া যায়নি — শুধু কোর্স যুক্ত করা হবে।";
            }
        }

        return [
            'course_id' => $course->getKey(),
            'lesson_id' => $lesson?->getKey(),
            'category' => $category,
            'number' => $number,
            'warnings' => $warnings,
        ];
    }
}
