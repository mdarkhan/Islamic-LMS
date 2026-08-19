<?php

namespace App\Services\Rewards;

use App\Models\Course;
use App\Models\PointTransaction;
use App\Models\Quiz;
use App\Models\RewardGrant;
use App\Models\User;
use App\Services\Points\PointService;
use App\Services\Quiz\LeaderboardService;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Grants bonus points and records the congratulations-worthy award. Every point movement
 * goes through PointService (the only writer of points_balance), inside the same
 * transaction that writes the reward_grants row — so the ledger and the grant can never
 * disagree. Awards are idempotent: the (user, quiz) / (user, course) unique keys, plus a
 * pre-check, mean re-running never double-credits.
 */
class RewardService
{
    public function __construct(
        private readonly PointService $points,
        private readonly LeaderboardService $leaderboard,
    ) {}

    /**
     * Sweep every bonus quiz whose results are now released and award qualifying students.
     * Safe to run repeatedly (the scheduled entry point). Returns the number of new grants.
     */
    public function awardQuizAchievements(?CarbonImmutable $now = null): int
    {
        $now ??= CarbonImmutable::now();

        $granted = 0;

        Quiz::query()->where('bonus_enabled', true)->where('bonus_points', '>', 0)->get()
            ->filter(fn (Quiz $quiz) => $quiz->bonusActive() && $quiz->resultsReleasedAt($now))
            ->each(function (Quiz $quiz) use (&$granted) {
                $granted += $this->awardQuizAchievement($quiz);
            });

        return $granted;
    }

    /**
     * Award one quiz's achievement bonus to every student whose best official attempt
     * reached the threshold and who has not already been granted for this quiz.
     */
    public function awardQuizAchievement(Quiz $quiz): int
    {
        if (! $quiz->bonusActive()) {
            return 0;
        }

        $threshold = $quiz->bonusThreshold();
        $winners = $this->leaderboard->quizLeaderboard($quiz)
            ->filter(fn (array $row) => $row['obtained'] >= $threshold);

        if ($winners->isEmpty()) {
            return 0;
        }

        $already = RewardGrant::query()->where('quiz_id', $quiz->getKey())->pluck('user_id')->all();
        $granted = 0;

        foreach ($winners as $row) {
            if (in_array($row['user_id'], $already, true)) {
                continue;
            }
            if ($user = User::query()->find($row['user_id'])) {
                $granted += $this->grant($user, RewardGrant::TYPE_QUIZ_ACHIEVEMENT, $quiz->bonus_points,
                    ['quiz_id' => $quiz->getKey()], $quiz->title);
            }
        }

        return $granted;
    }

    /**
     * Award course-topper rewards from the course's configured position → points table,
     * using the live course standings (competition ranking). Idempotent per (student,
     * course): a student who already holds this course's topper reward is never re-awarded.
     */
    public function awardCourseToppers(Course $course, ?User $admin = null, ?CarbonImmutable $now = null): int
    {
        $rewards = collect($course->topperRewardRows())->keyBy('position');
        if ($rewards->isEmpty()) {
            return 0;
        }

        $already = RewardGrant::query()->where('course_id', $course->getKey())->pluck('user_id')->all();
        $granted = 0;

        foreach ($this->leaderboard->courseLeaderboard($course, $now) as $row) {
            $points = $rewards[$row['rank']]['points'] ?? null;   // competition rank → points
            if ($points === null || in_array($row['user_id'], $already, true)) {
                continue;
            }
            if ($user = User::query()->find($row['user_id'])) {
                $granted += $this->grant($user, RewardGrant::TYPE_COURSE_TOPPER, $points,
                    ['course_id' => $course->getKey(), 'position' => $row['rank']], $course->title, $admin);
            }
        }

        return $granted;
    }

    /** A student's unseen awards, newest first — the congratulations screen's source. */
    public function pendingFor(User $user): \Illuminate\Support\Collection
    {
        return RewardGrant::query()->where('user_id', $user->getKey())->unseen()
            ->with(['quiz:id,title', 'course:id,title'])
            ->latest('id')
            ->get();
    }

    public function markSeen(User $user): void
    {
        RewardGrant::query()->where('user_id', $user->getKey())->unseen()->update(['seen_at' => now()]);
    }

    /**
     * Credit the bonus and record the grant atomically. A concurrent duplicate hits the
     * unique key and rolls the whole thing back (credit included), so no double points.
     * Returns 1 on a fresh grant, 0 if it was already there.
     *
     * @param  array<string, mixed>  $refs
     */
    private function grant(User $user, string $type, int $points, array $refs, string $label, ?User $by = null): int
    {
        try {
            DB::transaction(function () use ($user, $type, $points, $refs, $label, $by) {
                $reason = ($type === RewardGrant::TYPE_COURSE_TOPPER)
                    ? 'Course topper: '.$label
                    : 'Achievement bonus: '.$label;

                $tx = $this->points->credit($user, $points, PointTransaction::TYPE_BONUS, $reason, $by);

                RewardGrant::query()->create(array_merge([
                    'user_id' => $user->getKey(),
                    'type' => $type,
                    'points' => $points,
                    'point_transaction_id' => $tx->getKey(),
                    'created_at' => now(),
                ], $refs));
            });
        } catch (UniqueConstraintViolationException) {
            return 0;   // already granted by a concurrent run
        }

        return 1;
    }
}
