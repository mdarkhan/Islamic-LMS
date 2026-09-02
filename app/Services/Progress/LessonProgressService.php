<?php

namespace App\Services\Progress;

use App\Models\Course;
use App\Models\CourseCompletion;
use App\Models\Lesson;
use App\Models\LessonView;
use App\Models\User;

/**
 * The single place that tracks which lessons a student has opened — the basis for
 * "continue watching", per-course progress bars, the "what to study next" suggestion and
 * course-completion badges. A pure display/engagement concern: it never touches points,
 * scoring or the reward ledger.
 */
class LessonProgressService
{
    /** Record (or refresh) that $user opened $lesson, then check for course completion. */
    public function recordView(User $user, Lesson $lesson): void
    {
        LessonView::query()->updateOrCreate(
            ['user_id' => $user->getKey(), 'lesson_id' => $lesson->getKey()],
            ['viewed_at' => now()],
        );

        $this->maybeMarkCourseComplete($user, $lesson->course);
    }

    /** @return array{viewed: int, total: int} */
    public function courseProgress(User $user, Course $course): array
    {
        $lessonIds = $this->publishedLessonIds($course);
        $total = $lessonIds->count();

        $viewed = $total === 0 ? 0 : LessonView::query()
            ->where('user_id', $user->getKey())
            ->whereIn('lesson_id', $lessonIds)
            ->count();

        return ['viewed' => $viewed, 'total' => $total];
    }

    public function hasCompletedCourse(User $user, Course $course): bool
    {
        return CourseCompletion::query()
            ->where('user_id', $user->getKey())
            ->where('course_id', $course->getKey())
            ->exists();
    }

    /** The lesson $user most recently opened (published lesson, published course), if any. */
    public function recentlyViewedLesson(User $user): ?Lesson
    {
        $view = LessonView::query()
            ->where('user_id', $user->getKey())
            ->whereHas('lesson', fn ($q) => $q->where('is_published', true)
                ->whereHas('course', fn ($c) => $c->where('is_published', true)))
            ->with('lesson')
            ->orderByDesc('viewed_at')
            ->first();

        return $view?->lesson;
    }

    /**
     * The next lesson $user has not yet opened, in course/sort order. Scoped to $course
     * when given, else the first unviewed lesson across every published course.
     */
    public function nextUnviewedLesson(User $user, ?Course $course = null): ?Lesson
    {
        return Lesson::query()
            ->where('is_published', true)
            ->whereHas('course', fn ($q) => $q->where('is_published', true))
            ->whereDoesntHave('views', fn ($q) => $q->where('user_id', $user->getKey()))
            ->when($course, fn ($q) => $q->where('course_id', $course->getKey()))
            ->orderBy('course_id')
            ->orderBy('sort_order')
            ->first();
    }

    private function maybeMarkCourseComplete(User $user, Course $course): void
    {
        $lessonIds = $this->publishedLessonIds($course);
        if ($lessonIds->isEmpty()) {
            return;
        }

        $viewed = LessonView::query()
            ->where('user_id', $user->getKey())
            ->whereIn('lesson_id', $lessonIds)
            ->count();

        if ($viewed >= $lessonIds->count()) {
            CourseCompletion::query()->firstOrCreate(
                ['user_id' => $user->getKey(), 'course_id' => $course->getKey()],
                ['completed_at' => now()],
            );
        }
    }

    /** @return \Illuminate\Support\Collection<int, int> */
    private function publishedLessonIds(Course $course): \Illuminate\Support\Collection
    {
        return Lesson::query()->where('course_id', $course->getKey())->where('is_published', true)->pluck('id');
    }
}
