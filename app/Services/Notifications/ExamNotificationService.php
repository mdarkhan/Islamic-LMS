<?php

namespace App\Services\Notifications;

use App\Mail\ExamNotification;
use App\Models\NotificationDelivery;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Reminds students about exams and tells them when results are out — in the bell feed
 * for everyone, and by email for those who have an address (and have not opted out).
 * Run from the scheduler (`exams:send-notifications`), so it needs only the existing
 * `schedule:run` cron — no queue worker.
 *
 *   exam_soon     a scheduled/published exam opens within the next 24 h      → every student
 *   exam_closing  an open exam closes within 3 h                             → students who have not started it
 *   results       results were released within the last 3 days               → students with a finished attempt
 *
 * Once-only per (student, quiz, kind) via notification_deliveries, so it is safe to run
 * every few minutes and to re-run after a crash. Bounded work per run (shared-hosting
 * SMTP limits): leftover students are simply picked up by the next run. The recent-only
 * windows mean old archived quizzes never trigger a flood on the first deploy.
 */
class ExamNotificationService
{
    public const SOON_HOURS = 24;

    public const CLOSING_HOURS = 3;

    public const RESULTS_LOOKBACK_DAYS = 3;

    public function __construct(private readonly NotificationService $notifications) {}

    /** @return array{notified: int, emailed: int} */
    public function run(?CarbonImmutable $now = null, int $limit = 200): array
    {
        $now ??= CarbonImmutable::now();
        $stats = ['notified' => 0, 'emailed' => 0];

        $quizzes = Quiz::query()->whereIn('status', [Quiz::STATUS_SCHEDULED, Quiz::STATUS_PUBLISHED, Quiz::STATUS_ARCHIVED])->get();

        foreach ($quizzes as $quiz) {
            foreach ($this->kindsDue($quiz, $now) as $kind) {
                if ($stats['notified'] >= $limit) {
                    return $stats;
                }
                $this->deliver($quiz, $kind, $now, $limit - $stats['notified'], $stats);
            }
        }

        return $stats;
    }

    /** @return list<string> */
    private function kindsDue(Quiz $quiz, CarbonImmutable $now): array
    {
        $due = [];
        $live = in_array($quiz->status, [Quiz::STATUS_SCHEDULED, Quiz::STATUS_PUBLISHED], true);

        if ($live && $quiz->starts_at && $quiz->starts_at > $now && $quiz->starts_at <= $now->addHours(self::SOON_HOURS)) {
            $due[] = 'exam_soon';
        }
        if ($live && $quiz->isOpenAt($now) && $quiz->ends_at && $quiz->ends_at <= $now->addHours(self::CLOSING_HOURS)) {
            $due[] = 'exam_closing';
        }

        $releasedAt = $this->releaseMoment($quiz);
        if ($releasedAt && $releasedAt <= $now && $releasedAt >= $now->subDays(self::RESULTS_LOOKBACK_DAYS)) {
            $due[] = 'results';
        }

        return $due;
    }

    /** When results became (or become) visible to students; null if there is no such moment. */
    private function releaseMoment(Quiz $quiz): ?CarbonImmutable
    {
        $moment = $quiz->results_released_at ?? $quiz->result_release_at ?? $quiz->ends_at;

        return $moment ? CarbonImmutable::instance($moment) : null;
    }

    /** @param array{notified: int, emailed: int} $stats */
    private function deliver(Quiz $quiz, string $kind, CarbonImmutable $now, int $budget, array &$stats): void
    {
        $recipients = User::query()->students()
            ->where('status', User::STATUS_ACTIVE)
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('notification_deliveries as d')
                ->whereColumn('d.user_id', 'users.id')->where('d.quiz_id', $quiz->getKey())->where('d.kind', $kind))
            ->when($kind === 'exam_closing', fn (Builder $q) => $q->whereDoesntHave('quizAttempts', fn ($a) => $a
                ->where('quiz_id', $quiz->getKey())->where('kind', QuizAttempt::KIND_OFFICIAL)))
            ->when($kind === 'results', fn (Builder $q) => $q->whereHas('quizAttempts', fn ($a) => $a
                ->where('quiz_id', $quiz->getKey())->where('kind', QuizAttempt::KIND_OFFICIAL)
                ->whereIn('status', QuizAttempt::RANKABLE_STATUSES)))
            ->orderBy('id')
            ->limit($budget)
            ->get();

        [$mailSubject, $headline, $line, $url, $button, $bellTitle] = $this->copy($quiz, $kind);

        foreach ($recipients as $student) {
            try {
                $delivery = NotificationDelivery::query()->create(['user_id' => $student->getKey(), 'quiz_id' => $quiz->getKey(), 'kind' => $kind]);
            } catch (QueryException) {
                continue;   // another run claimed this one first
            }

            $this->notifications->notify($student, $kind, $bellTitle, $quiz->title, $url, $kind, (int) $quiz->getKey());
            $stats['notified']++;

            if (blank($student->email) || ! $student->notify_by_email) {
                continue;
            }

            try {
                Mail::to($student->email)->send(new ExamNotification($mailSubject, $headline, $line, $url, $button, $student->name));
                $delivery->forceFill(['emailed' => true])->save();
                $stats['emailed']++;
            } catch (\Throwable $e) {
                // The bell copy already exists; a failing mailbox must not stall the sweep or retry forever.
                Log::warning('Exam notification email failed', ['user_id' => $student->getKey(), 'quiz_id' => $quiz->getKey(), 'kind' => $kind, 'error' => $e::class]);
            }
        }
    }

    /** @return array{0: string, 1: string, 2: string, 3: string, 4: string, 5: string} */
    private function copy(Quiz $quiz, string $kind): array
    {
        $exams = route('student.exams.index');
        $when = fn ($t) => bn($t->format('d/m/Y')).', '.bn($t->format('H:i'));

        return match ($kind) {
            'exam_soon' => [
                'পরীক্ষার রিমাইন্ডার: '.$quiz->title,
                'পরীক্ষা শীঘ্রই শুরু হচ্ছে',
                '"'.$quiz->title.'" পরীক্ষা শুরু হবে '.$when($quiz->starts_at).'-এ।'
                    .($quiz->duration_seconds ? ' সময়: '.bn((int) ceil($quiz->duration_seconds / 60)).' মিনিট।' : '')
                    .' প্রস্তুত থাকুন।',
                $exams, 'পরীক্ষার তালিকা দেখুন', 'শীঘ্রই পরীক্ষা শুরু',
            ],
            'exam_closing' => [
                'পরীক্ষার সময় শেষ হয়ে আসছে: '.$quiz->title,
                'পরীক্ষার সময় প্রায় শেষ',
                '"'.$quiz->title.'" পরীক্ষা শেষ হবে '.$when($quiz->ends_at).'-এ, আপনি এখনো শুরু করেননি।',
                $exams, 'এখনই পরীক্ষা দিন', 'পরীক্ষার সময় শেষ হয়ে আসছে',
            ],
            default => [
                'ফলাফল প্রকাশিত: '.$quiz->title,
                'ফলাফল প্রকাশিত হয়েছে',
                '"'.$quiz->title.'" পরীক্ষার ফলাফল এখন দেখা যাচ্ছে। লগইন করে আপনার ফলাফল ও উত্তরপত্র দেখুন।',
                route('student.results.index'), 'ফলাফল দেখুন', 'ফলাফল প্রকাশিত হয়েছে',
            ],
        };
    }
}
