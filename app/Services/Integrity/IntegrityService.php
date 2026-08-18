<?php

namespace App\Services\Integrity;

use App\Models\Quiz;
use App\Models\QuizQuestion;
use App\Services\Points\PointService;
use Illuminate\Support\Facades\DB;

class IntegrityService
{
    public function __construct(private readonly PointService $points) {}

    /** @return array<string, array{ok:bool,count:int,severity:string,details:array<int,mixed>}> */
    public function inspect(): array
    {
        $pointDrift = $this->points->findBalanceDrift();
        $markDrift = Quiz::query()->withSum(
            ['questions as active_marks_sum' => fn ($query) => $query->where('is_active', true)],
            'marks',
        )->get(['id', 'title', 'total_marks'])->filter(
            fn (Quiz $quiz) => (int) $quiz->total_marks !== (int) ($quiz->active_marks_sum ?? 0),
        )->map(fn (Quiz $quiz) => [
            'quiz_id' => $quiz->id,
            'cached' => (int) $quiz->total_marks,
            'calculated' => (int) ($quiz->active_marks_sum ?? 0),
        ])->values()->all();

        $invalidKeys = QuizQuestion::query()->withCount([
            'options',
            'options as correct_options_count' => fn ($query) => $query->where('is_correct', true),
        ])->get(['id', 'quiz_id', 'type'])->filter(fn (QuizQuestion $question) => $question->options_count < 2
            || ($question->type === QuizQuestion::TYPE_SINGLE && $question->correct_options_count !== 1)
            || ($question->type === QuizQuestion::TYPE_MULTIPLE && $question->correct_options_count < 1)
        )->map(fn (QuizQuestion $question) => [
            'question_id' => $question->id,
            'options' => $question->options_count,
            'correct' => $question->correct_options_count,
            'type' => $question->type,
        ])->values()->all();

        $checks = [
            'duplicate_rolls' => $this->rows(
                DB::table('users')->whereNotNull('roll')->groupBy('roll')->havingRaw('COUNT(*) > 1')
                    ->select('roll', DB::raw('COUNT(*) as copies'))->get()->map(fn ($r) => (array) $r)->all()
            ),
            'point_ledger_drift' => $this->rows($pointDrift),
            'negative_point_balances' => $this->rows(
                DB::table('users')->where('points_balance', '<', 0)->get(['id', 'points_balance'])->map(fn ($r) => (array) $r)->all()
            ),
            'quiz_total_marks_drift' => $this->rows($markDrift),
            'invalid_question_option_sets' => $this->rows($invalidKeys),
            'orphan_attempts' => $this->count(
                DB::table('quiz_attempts')->leftJoin('users', 'users.id', '=', 'quiz_attempts.user_id')
                    ->leftJoin('quizzes', 'quizzes.id', '=', 'quiz_attempts.quiz_id')
                    ->where(fn ($q) => $q->whereNull('users.id')->orWhereNull('quizzes.id'))->count()
            ),
            'orphan_answer_option_links' => $this->count(
                DB::table('quiz_answer_options')->leftJoin('quiz_answers', 'quiz_answers.id', '=', 'quiz_answer_options.answer_id')
                    ->leftJoin('quiz_options', 'quiz_options.id', '=', 'quiz_answer_options.option_id')
                    ->where(fn ($q) => $q->whereNull('quiz_answers.id')->orWhereNull('quiz_options.id'))->count()
            ),
            'duplicate_attempt_source_keys' => $this->rows(
                DB::table('quiz_attempts')->whereNotNull('legacy_source_key')->groupBy('legacy_source_key')
                    ->havingRaw('COUNT(*) > 1')->select('legacy_source_key', DB::raw('COUNT(*) as copies'))
                    ->get()->map(fn ($r) => (array) $r)->all()
            ),
            'legacy_attempts_without_provenance' => $this->count(
                DB::table('quiz_attempts')->where('is_legacy_import', true)
                    ->where(fn ($q) => $q->whereNull('legacy_source_key')->orWhereNull('legacy_import_batch_id')->orWhereNull('legacy_quiz_id'))
                    ->count()
            ),
            'quizzes_without_questions' => $this->count(
                Quiz::query()->doesntHave('questions')->count(), severity: 'warning'
            ),
        ];

        return $checks;
    }

    /** @param array<int, mixed> $details @return array{ok:bool,count:int,severity:string,details:array<int,mixed>} */
    private function rows(array $details, string $severity = 'error'): array
    {
        return ['ok' => $details === [], 'count' => count($details), 'severity' => $severity, 'details' => $details];
    }

    /** @return array{ok:bool,count:int,severity:string,details:array<int,mixed>} */
    private function count(int $count, string $severity = 'error'): array
    {
        return ['ok' => $count === 0, 'count' => $count, 'severity' => $severity, 'details' => []];
    }
}
