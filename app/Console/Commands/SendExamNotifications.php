<?php

namespace App\Console\Commands;

use App\Services\Notifications\ExamNotificationService;
use Illuminate\Console\Command;

/**
 * Exam reminders and "results published" notices (bell + email). Idempotent and bounded,
 * so it runs every few minutes from the scheduler — the one existing cPanel cron.
 */
class SendExamNotifications extends Command
{
    protected $signature = 'exams:send-notifications {--limit=200 : Max students to notify per run}';

    protected $description = 'Send exam reminders and result-published notifications (bell + email)';

    public function handle(ExamNotificationService $service): int
    {
        $r = $service->run(limit: max(1, (int) $this->option('limit')));

        $this->info("Notified {$r['notified']} student(s); {$r['emailed']} email(s) sent.");

        return self::SUCCESS;
    }
}
