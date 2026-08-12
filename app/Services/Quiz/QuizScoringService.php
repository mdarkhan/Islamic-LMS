<?php

namespace App\Services\Quiz;

use App\Models\QuizAnswer;
use App\Models\QuizAttempt;
use App\Models\QuizQuestion;
use Illuminate\Support\Facades\DB;

/**
 * The single source of truth for scores.
 *
 * A score is NEVER accepted from a request — it is always recomputed here from
 * quiz_answer_options rows against the current answer key (SECURITY.md §2.4).
 *
 * Rule, reproducing the legacy behaviour exactly: the selected set must equal the
 * correct set. There is no partial credit; a partially-correct multi-answer
 * question scores zero, as does an unanswered one.
 */
class QuizScoringService
{
    /**
     * Grade a single question's answer. Returns marks awarded.
     *
     * @param  array<int, int>  $selectedOptionIds
     * @param  array<int, int>  $correctOptionIds
     */
    public function gradeSelection(array $selectedOptionIds, array $correctOptionIds, int $marks): int
    {
        $selected = array_values(array_unique(array_map(intval(...), $selectedOptionIds)));
        $correct = array_values(array_unique(array_map(intval(...), $correctOptionIds)));

        sort($selected);
        sort($correct);

        // An answer key with no correct option cannot be satisfied; award nothing
        // rather than treating "selected nothing" as a match.
        if ($correct === []) {
            return 0;
        }

        return $selected === $correct ? $marks : 0;
    }

    /**
     * Recompute and persist an attempt's score from its stored answers.
     *
     * Leaves manual_adjustment untouched, so an admin's goodwill mark survives an
     * answer-key correction and a later regrade (brief §20).
     */
    public function scoreAttempt(QuizAttempt $attempt): QuizAttempt
    {
        return DB::transaction(function () use ($attempt) {
            $questions = QuizQuestion::query()
                ->where('quiz_id', $attempt->quiz_id)
                ->where('is_active', true)
                ->with('options')
                ->get()
                ->keyBy('id');

            $answers = QuizAnswer::query()
                ->where('attempt_id', $attempt->getKey())
                ->with('selections')
                ->get()
                ->keyBy('question_id');

            $calculated = 0;
            $totalMarks = 0;

            foreach ($questions as $questionId => $question) {
                $totalMarks += $question->marks;

                $answer = $answers->get($questionId);

                if ($answer === null) {
                    continue;   // unanswered scores zero
                }

                $awarded = $this->gradeSelection(
                    $answer->selectedOptionIds(),
                    $question->correctOptionIds(),
                    $question->marks,
                );

                $answer->forceFill([
                    'is_correct' => $awarded > 0,
                    'marks_awarded' => $awarded,
                ])->save();

                $calculated += $awarded;
            }

            // Answers to questions since deactivated must not keep contributing.
            $answers->reject(fn ($a) => $questions->has($a->question_id))
                ->each(fn (QuizAnswer $a) => $a->forceFill([
                    'is_correct' => null,
                    'marks_awarded' => 0,
                ])->save());

            $attempt->forceFill([
                'calculated_score' => $calculated,
                'final_score' => $calculated + $attempt->manual_adjustment,
                'total_marks_snapshot' => $totalMarks,
            ])->save();

            return $attempt;
        });
    }
}
