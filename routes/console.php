<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Hourly sweep of abandoned import uploads and expired credential exports.
// Requires the cPanel cron entry documented in DEPLOYMENT.md:
//   * * * * * cd /path && php artisan schedule:run >> /dev/null 2>&1
Schedule::command('imports:cleanup')->hourly();

// Safety net for live exams: finalise attempts whose deadline has passed but which
// were never submitted (closed tab, lost connection). The screen, autosave and
// resume already finalise opportunistically; this catches the abandoned ones.
Schedule::command('attempts:finalize-expired')->everyMinute()->withoutOverlapping();

// Grant achievement bonus points once a bonus quiz's results are released. Idempotent,
// so a few-minute cadence simply catches newly-released quizzes; the student sees the
// congratulations on their next login.
Schedule::command('rewards:award-quiz-bonuses')->everyFiveMinutes()->withoutOverlapping();
