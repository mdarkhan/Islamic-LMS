<?php

namespace App\Services\Quiz;

use App\Models\Quiz;
use App\Models\QuizAnswer;
use App\Models\QuizAnswerOption;
use App\Models\QuizAttempt;
use App\Models\QuizQuestion;

/**
 * Per-question performance for one quiz: which questions most students got wrong, and
 * which wrong option they picked. Read-only, derived on demand from stored answers, so
 * it always reflects the current (regraded) key and can never drift.
 *
 * Population: each student's BEST finished official attempt (highest final_score, then
 * earliest), the same "best attempt" rule the leaderboards use, so a student who retried
 * is not counted twice. Practice, in-progress, voided and legacy-imported attempts
 * (which have no stored answers) are excluded.
 */
class QuestionAnalysisService
{
    public const EASY_FROM = 70;   // % correct at or above → easy

    public const HARD_BELOW = 40;  // % correct below → hard

    /**
     * @return array{
     *   students: int, average_percent: int|null,
     *   questions: list<array{
     *     number: int, id: int, body: string, marks: int, answered: int, correct: int, wrong: int,
     *     skipped: int, percent: int, level: string,
     *     options: list<array{id: int, body: string, is_correct: bool, picked: int, percent: int}>
     *   }>
     * }
     */
    public function forQuiz(Quiz $quiz): array
    {
        $attempts = QuizAttempt::query()
            ->where('quiz_id', $quiz->getKey())
            ->where('kind', QuizAttempt::KIND_OFFICIAL)
            ->whereIn('status', [QuizAttempt::STATUS_SUBMITTED, QuizAttempt::STATUS_EXPIRED])
            ->where('answer_details_available', true)
            ->orderByDesc('final_score')
            ->orderBy('id')
            ->get(['id', 'user_id', 'final_score'])
            ->unique('user_id')
            ->values();

        $students = $attempts->count();
        $questions = QuizQuestion::query()
            ->where('quiz_id', $quiz->getKey())
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->with(['options' => fn ($q) => $q->orderBy('sort_order')])
            ->get();

        $answers = QuizAnswer::query()
            ->whereIn('attempt_id', $attempts->pluck('id'))
            ->get(['id', 'question_id', 'is_correct'])
            ->groupBy('question_id');

        $picks = QuizAnswerOption::query()
            ->whereIn('answer_id', $answers->flatten(1)->pluck('id'))
            ->get(['answer_id', 'option_id']);
        $pickedBy = $picks->groupBy('option_id')->map->count();
        $answerHasPick = $picks->pluck('answer_id')->flip();

        $rows = [];
        foreach ($questions as $i => $question) {
            $forQuestion = $answers->get($question->getKey(), collect())->filter(fn ($a) => $answerHasPick->has($a->id));
            $answered = $forQuestion->count();
            $correct = $forQuestion->where('is_correct', true)->count();
            $percent = $students > 0 ? (int) round($correct * 100 / $students) : 0;

            $rows[] = [
                'number' => $i + 1,
                'id' => (int) $question->getKey(),
                'body' => $question->body,
                'marks' => (int) $question->marks,
                'answered' => $answered,
                'correct' => $correct,
                'wrong' => $answered - $correct,
                'skipped' => $students - $answered,
                'percent' => $percent,
                'level' => $percent >= self::EASY_FROM ? 'easy' : ($percent < self::HARD_BELOW ? 'hard' : 'medium'),
                'options' => $question->options->map(function ($o) use ($pickedBy, $students) {
                    $picked = (int) ($pickedBy[$o->getKey()] ?? 0);

                    return [
                        'id' => (int) $o->getKey(),
                        'body' => $o->body,
                        'is_correct' => (bool) $o->is_correct,
                        'picked' => $picked,
                        'percent' => $students > 0 ? (int) round($picked * 100 / $students) : 0,
                    ];
                })->values()->all(),
            ];
        }

        $possible = (int) $quiz->total_marks;
        $average = ($students > 0 && $possible > 0)
            ? (int) round($attempts->avg('final_score') * 100 / $possible)
            : null;

        return ['students' => $students, 'average_percent' => $average, 'questions' => $rows];
    }
}
