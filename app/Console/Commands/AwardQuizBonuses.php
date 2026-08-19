<?php

namespace App\Console\Commands;

use App\Services\Rewards\RewardService;
use Illuminate\Console\Command;

/**
 * Awards achievement bonus points for every bonus-enabled quiz whose results are now
 * released, to each student whose best official attempt reached the threshold. Idempotent
 * — a student already granted for a quiz is skipped — so it is safe to run every few
 * minutes from the scheduler (the same single cPanel cron as the other scheduled jobs).
 */
class AwardQuizBonuses extends Command
{
    protected $signature = 'rewards:award-quiz-bonuses';

    protected $description = 'Grant achievement bonus points for quizzes whose results are released';

    public function handle(RewardService $rewards): int
    {
        $count = $rewards->awardQuizAchievements();

        $this->info("Awarded {$count} quiz bonus(es).");

        return self::SUCCESS;
    }
}
