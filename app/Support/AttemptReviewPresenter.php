<?php

namespace App\Support;

use App\Models\QuizAnswer;
use App\Models\QuizAttempt;
use App\Models\QuizQuestion;

/**
 * Builds the detailed answer-sheet review for a TERMINAL attempt — the reveal
 * counterpart to ExamAttemptPresenter.
 *
 * This one DOES expose the answer key (correct options, marks, explanation), so it
 * must only ever be rendered when revealing the key is safe:
 *   - an official attempt whose quiz results have been released, or
 *   - a submitted practice attempt (its key is public the moment practice is available).
 *
 * The CALLER is responsible for that gate; this class assumes it has already passed.
 *
 * Correctness and marks are read from the stored, already-graded answer rows, which
 * QuizScoringService keeps in sync with the CURRENT answer key — so after a regrade the
 * review shows the corrected key and the recomputed outcome, while the option TEXT the
 * student saw is unchanged (the Builder freezes it once official attempts exist).
 */
class AttemptReviewPresenter
{
    /**
     * @return array<int, array{
     *     number:int, id:int, type:string, body:string, explanation:?string,
     *     marks_available:int, marks_earned:int, answered:bool, state:string,
     *     options:array<int, array{id:int, body:string, selected:bool, correct:bool}>
     * }>
     */
    public static function questions(QuizAttempt $attempt): array
    {
        $questions = QuizQuestion::query()
            ->where('quiz_id', $attempt->quiz_id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->with(['options' => fn ($q) => $q->orderBy('sort_order')])
            ->get();

        $answers = QuizAnswer::query()
            ->where('attempt_id', $attempt->getKey())
            ->with('selections')
            ->get()
            ->keyBy('question_id');

        $rows = [];
        $number = 0;

        foreach ($questions as $question) {
            $number++;
            $answer = $answers->get($question->id);
            $selected = $answer ? $answer->selectedOptionIds() : [];
            $answered = $selected !== [];

            $state = match (true) {
                ! $answered => 'unanswered',
                (bool) $answer->is_correct => 'correct',
                default => 'wrong',
            };

            $rows[] = [
                'number' => $number,
                'id' => (int) $question->id,
                'type' => $question->type,
                'body' => $question->body,
                'explanation' => $question->explanation,
                'marks_available' => (int) $question->marks,
                'marks_earned' => $answer ? (int) $answer->marks_awarded : 0,
                'answered' => $answered,
                'state' => $state,
                'options' => $question->options->map(fn ($option): array => [
                    'id' => (int) $option->id,
                    'body' => $option->body,
                    'selected' => in_array((int) $option->id, $selected, true),
                    'correct' => (bool) $option->is_correct,
                ])->values()->all(),
            ];
        }

        return $rows;
    }
}
