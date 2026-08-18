<?php

namespace App\Support;

use App\Models\QuizAnswer;
use App\Models\QuizAttempt;
use App\Models\QuizQuestion;
use Carbon\CarbonImmutable;

/**
 * Builds the ONLY question payload a student may see during a live official exam.
 *
 * This is a strict allow-list. It emits question id, type and body plus each
 * option's id and body — nothing else. It NEVER emits is_correct, marks or
 * explanation, because any of those reaching the browser while an attempt is open
 * would leak the answer key or steer the student toward high-value questions
 * (CLAUDE.md rules 2 & 7, SECURITY.md §2.3). Grading reads those fields
 * server-side; they must never travel to the client.
 */
class ExamAttemptPresenter
{
    /**
     * The active questions for the attempt's quiz, answer-key stripped.
     *
     * @return array<int, array{id:int, type:string, body:string, options:array<int, array{id:int, body:string}>}>
     */
    public static function questions(QuizAttempt $attempt): array
    {
        return QuizQuestion::query()
            ->where('quiz_id', $attempt->quiz_id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->with(['options' => fn ($q) => $q->orderBy('sort_order')])
            ->get()
            ->map(fn (QuizQuestion $question): array => [
                'id' => (int) $question->id,
                'type' => $question->type,
                'body' => $question->body,
                // Only id + body. is_correct is deliberately absent (not merely
                // #[Hidden]) so it cannot leak through this path.
                'options' => $question->options
                    ->map(fn ($option): array => [
                        'id' => (int) $option->id,
                        'body' => $option->body,
                    ])
                    ->values()
                    ->all(),
            ])
            ->values()
            ->all();
    }

    /**
     * The student's own current selections, so a resumed attempt restores state.
     *
     * @return array<int, array<int, int>>  question_id => [selected option ids]
     */
    public static function selections(QuizAttempt $attempt): array
    {
        return QuizAnswer::query()
            ->where('attempt_id', $attempt->getKey())
            ->with('selections')
            ->get()
            ->mapWithKeys(fn (QuizAnswer $answer): array => [
                (int) $answer->question_id => $answer->selectedOptionIds(),
            ])
            ->all();
    }

    /**
     * Whole seconds left on the authoritative deadline, floored at zero.
     *
     * The server clock is the only timer that counts. The browser countdown is
     * presentation; this value re-synchronises it. NULL means no time limit.
     */
    public static function remainingSeconds(QuizAttempt $attempt, CarbonImmutable $now): ?int
    {
        if ($attempt->expires_at === null) {
            return null;
        }

        return max(0, $attempt->expires_at->getTimestamp() - $now->getTimestamp());
    }
}
