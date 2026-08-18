<?php

namespace App\Services\Quiz;

use App\Models\QuizAttempt;
use App\Models\ScoreAdjustment;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;

/**
 * Applies an admin's manual score adjustment: validated, audited, never silent.
 *
 * calculated_score is never overwritten — only manual_adjustment changes, and
 * final_score is recomputed as calculated + manual. Every change appends a
 * score_adjustments row (old/new manual + final, reason, admin) and an audit event.
 */
class ScoreAdjustmentService
{
    public function __construct(
        private readonly QuizScoringService $scoring,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @throws AdjustmentOutOfBoundsException  when final would fall outside 0..total
     */
    public function adjust(QuizAttempt $attempt, int $newManual, string $reason, User $admin): QuizAttempt
    {
        $newFinal = $attempt->calculated_score + $newManual;

        // Validate rather than clamp: a surprising silent clamp is worse than a clear
        // refusal. final must stay within the marks that were actually available.
        if ($newFinal < 0 || $newFinal > $attempt->total_marks_snapshot) {
            throw new AdjustmentOutOfBoundsException(
                'সমন্বয়ের পর চূড়ান্ত নম্বর ০ থেকে মোট নম্বরের মধ্যে থাকতে হবে।'
            );
        }

        return DB::transaction(function () use ($attempt, $newManual, $reason, $admin) {
            $oldManual = $attempt->manual_adjustment;
            $oldFinal = $attempt->final_score;

            $this->scoring->setManualAdjustment($attempt, $newManual);

            ScoreAdjustment::create([
                'attempt_id' => $attempt->getKey(),
                'admin_id' => $admin->getKey(),
                'old_manual' => $oldManual,
                'new_manual' => $newManual,
                'old_final' => $oldFinal,
                'new_final' => $attempt->final_score,
                'reason' => $reason,
            ]);

            $this->audit->log(
                'result.adjusted',
                $attempt,
                before: ['manual_adjustment' => $oldManual, 'final_score' => $oldFinal],
                after: ['manual_adjustment' => $newManual, 'final_score' => $attempt->final_score, 'reason' => $reason],
                actor: $admin,
            );

            return $attempt->refresh();
        });
    }
}
