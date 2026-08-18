<?php

namespace App\Services\Quiz;

use App\Models\QuizAnswer;
use App\Models\QuizAttempt;
use App\Models\QuizOption;
use App\Models\QuizQuestion;
use App\Models\RegradeEntry;
use App\Models\RegradeRun;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Answer-key / marks correction with automatic regrading (brief §32–37).
 *
 * A regrade recomputes calculated_score for every affected official attempt from its
 * STORED selections against the corrected key, leaves each admin manual_adjustment
 * untouched, and sets final_score = calculated + manual. Terminal metadata
 * (started_at, submitted_at, expires_at, time_taken, submission_seq, status) is never
 * touched — only scores move. The whole run is ONE transaction, so a failure leaves no
 * attempt half-regraded. Every run records a before/after per attempt in regrade_entries
 * and an audit event.
 *
 * Only correct-option set, marks and type (and, non-scoring, explanation) change here —
 * never question or option TEXT — which is what keeps a student's historical answer
 * sheet faithful to what they were actually asked.
 *
 * The regrade set is official, terminal (submitted/expired), non-legacy attempts. Legacy
 * imports have no stored answers, so they are counted as skipped and never touched.
 */
class QuizRegradeService
{
    public function __construct(
        private readonly QuizScoringService $scoring,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * Dry-run impact of a proposed change — persists nothing. Exact, because scoring is
     * additive per question and only the target question changes: a score moves by the
     * delta of that one question's award.
     *
     * @param  array<int, int>  $newCorrectIds
     * @return array<string, mixed>
     */
    public function preview(QuizQuestion $question, array $newCorrectIds, int $newMarks): array
    {
        $question->loadMissing('options');
        $oldCorrect = $question->correctOptionIds();
        $oldMarks = (int) $question->marks;
        $newCorrect = collect($newCorrectIds)->map(intval(...))->unique()->sort()->values()->all();

        $attempts = $this->regradeableAttempts($question->quiz_id);
        $answers = QuizAnswer::query()
            ->where('question_id', $question->getKey())
            ->whereIn('attempt_id', $attempts->pluck('id'))
            ->with('selections')
            ->get()
            ->keyBy('attempt_id');

        $changed = 0;
        $samples = [];

        foreach ($attempts as $attempt) {
            $selected = $answers->get($attempt->id)?->selectedOptionIds() ?? [];
            $oldAward = $this->scoring->gradeSelection($selected, $oldCorrect, $oldMarks);
            $newAward = $this->scoring->gradeSelection($selected, $newCorrect, $newMarks);

            if ($newAward !== $oldAward) {
                $changed++;
                if (count($samples) < 10) {
                    $samples[] = [
                        'roll' => $attempt->user->roll,
                        'name' => $attempt->user->name,
                        'old_final' => (int) $attempt->final_score,
                        'new_final' => (int) $attempt->final_score + ($newAward - $oldAward),
                    ];
                }
            }
        }

        return [
            'old_correct' => $this->optionLabels($question, $oldCorrect),
            'new_correct' => $this->optionLabels($question, $newCorrect),
            'old_marks' => $oldMarks,
            'new_marks' => $newMarks,
            'affected_total' => $attempts->count(),
            'changed_count' => $changed,
            'unchanged_count' => $attempts->count() - $changed,
            'skipped_count' => $this->legacyCount($question->quiz_id),
            'samples' => $samples,
        ];
    }

    /**
     * Apply the correction and regrade every affected attempt, transactionally.
     *
     * @param  array<int, int>  $newCorrectIds
     */
    public function apply(
        QuizQuestion $question,
        array $newCorrectIds,
        int $newMarks,
        string $newType,
        ?string $explanation,
        User $admin,
        string $reason,
        ?CarbonImmutable $now = null,
    ): RegradeRun {
        $now ??= CarbonImmutable::now();
        $quiz = $question->quiz;

        return DB::transaction(function () use ($question, $newCorrectIds, $newMarks, $newType, $explanation, $admin, $reason, $quiz, $now) {
            $before = [
                'question_id' => $question->id,
                'correct_option_ids' => $question->correctOptionIds(),
                'marks' => (int) $question->marks,
                'type' => $question->type,
            ];

            $run = RegradeRun::create([
                'quiz_id' => $quiz->getKey(),
                'admin_id' => $admin->getKey(),
                'reason' => $reason,
                'status' => RegradeRun::STATUS_RUNNING,
                'started_at' => $now,
            ]);

            // Apply the corrected key/marks/type. Option and question TEXT are never
            // touched here, preserving historical answer sheets.
            $ids = collect($newCorrectIds)->map(intval(...))->all();
            QuizOption::query()->where('question_id', $question->getKey())->update(['is_correct' => false]);
            if ($ids !== []) {
                QuizOption::query()->where('question_id', $question->getKey())->whereIn('id', $ids)->update(['is_correct' => true]);
            }
            $question->update([
                'marks' => $newMarks,
                'type' => $newType,
                'explanation' => $explanation,
            ]);

            // The cached total must reflect the corrected marks (brief §43).
            $quiz->recalculateTotalMarks();

            $changed = 0;
            foreach ($this->regradeableAttempts($quiz->getKey()) as $attempt) {
                $oldCalc = (int) $attempt->calculated_score;
                $oldFinal = (int) $attempt->final_score;

                $this->scoring->scoreAttempt($attempt);   // recompute; manual_adjustment preserved
                $attempt->refresh();

                RegradeEntry::create([
                    'regrade_run_id' => $run->getKey(),
                    'attempt_id' => $attempt->getKey(),
                    'old_calculated' => $oldCalc,
                    'new_calculated' => (int) $attempt->calculated_score,
                    'old_final' => $oldFinal,
                    'new_final' => (int) $attempt->final_score,
                ]);

                if ((int) $attempt->final_score !== $oldFinal || (int) $attempt->calculated_score !== $oldCalc) {
                    $changed++;
                }
            }

            $run->update([
                'status' => RegradeRun::STATUS_COMPLETED,
                'attempts_affected' => $changed,
                'attempts_skipped' => $this->legacyCount($quiz->getKey()),
                'completed_at' => $now,
            ]);

            $this->audit->log(
                'quiz.regraded',
                $quiz,
                before: $before,
                after: [
                    'question_id' => $question->id,
                    'correct_option_ids' => $ids,
                    'marks' => $newMarks,
                    'type' => $newType,
                    'attempts_affected' => $changed,
                    'reason' => $reason,
                ],
                actor: $admin,
            );

            return $run;
        });
    }

    /**
     * The attempts a regrade touches: official, terminal (submitted/expired), non-legacy.
     *
     * @return \Illuminate\Support\Collection<int, QuizAttempt>
     */
    private function regradeableAttempts(int $quizId): \Illuminate\Support\Collection
    {
        return QuizAttempt::query()
            ->where('quiz_id', $quizId)
            ->where('kind', QuizAttempt::KIND_OFFICIAL)
            ->whereIn('status', QuizAttempt::RANKABLE_STATUSES)
            ->where('is_legacy_import', false)
            ->with('user:id,roll,name')
            ->orderBy('id')
            ->get();
    }

    private function legacyCount(int $quizId): int
    {
        return QuizAttempt::query()
            ->where('quiz_id', $quizId)
            ->where('kind', QuizAttempt::KIND_OFFICIAL)
            ->whereIn('status', QuizAttempt::RANKABLE_STATUSES)
            ->where('is_legacy_import', true)
            ->count();
    }

    /**
     * @param  array<int, int>  $ids
     * @return array<int, string>
     */
    private function optionLabels(QuizQuestion $question, array $ids): array
    {
        return $question->options
            ->whereIn('id', $ids)
            ->pluck('body')
            ->values()
            ->all();
    }
}
