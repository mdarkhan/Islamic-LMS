<?php

namespace App\Console\Commands;

use App\Services\Quiz\QuizAttemptService;
use Illuminate\Console\Command;

/**
 * Finalises official exam attempts whose deadline has passed but which were never
 * submitted (closed tab, lost connection). Each is recorded as EXPIRED with the
 * authoritative deadline as its end and scored from whatever was autosaved.
 *
 * A safety net, not the primary path: the live exam screen, autosave and resume all
 * finalise opportunistically. Runs every minute from the scheduler, which needs the
 * single cPanel cron documented in DEPLOYMENT.md:
 *
 *   * * * * * cd /home/<acct>/masudalimi && php artisan schedule:run >> /dev/null 2>&1
 */
class FinalizeExpiredAttempts extends Command
{
    protected $signature = 'attempts:finalize-expired';

    protected $description = 'Finalise official exam attempts whose deadline has passed';

    public function handle(QuizAttemptService $attempts): int
    {
        $count = $attempts->finalizeExpired();

        $this->info("Finalised {$count} expired attempt(s).");

        return self::SUCCESS;
    }
}
