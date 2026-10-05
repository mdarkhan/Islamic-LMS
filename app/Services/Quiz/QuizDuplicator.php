<?php

namespace App\Services\Quiz;

use App\Models\Quiz;
use App\Models\QuizOption;
use App\Models\QuizQuestion;
use App\Support\Slug;
use Illuminate\Support\Facades\DB;

/**
 * Duplicates a quiz's configuration, questions, options, correct answers, marks and
 * explanations — but never its attempts, answers, point transactions, results or
 * submission serials. The copy starts as a fresh draft with a unique title/slug.
 */
class QuizDuplicator
{
    public function duplicate(Quiz $quiz): Quiz
    {
        return DB::transaction(function () use ($quiz) {
            $title = $quiz->title.' (কপি)';

            $copy = Quiz::query()->create([
                'course_id' => $quiz->course_id,
                'lesson_id' => $quiz->lesson_id,
                'slug' => Slug::unique($title, fn (string $slug) => Quiz::query()->where('slug', $slug)->exists(), 'quiz'),
                'title' => $title,
                'description' => $quiz->description,
                'status' => Quiz::STATUS_DRAFT,
                'practice_enabled' => $quiz->practice_enabled,
                'point_cost' => $quiz->point_cost,
                'duration_seconds' => $quiz->duration_seconds,
                'starts_at' => $quiz->starts_at,
                'ends_at' => $quiz->ends_at,
                'result_release_at' => $quiz->result_release_at,
                'leaderboard_visible' => $quiz->leaderboard_visible,
                'shuffle_per_student' => $quiz->shuffle_per_student,
                'max_official_attempts' => $quiz->max_official_attempts,
                'created_by' => auth()->id(),
            ]);

            foreach ($quiz->questions()->with('options')->get() as $question) {
                $newQuestion = QuizQuestion::query()->create([
                    'quiz_id' => $copy->getKey(),
                    'sort_order' => $question->sort_order,
                    'type' => $question->type,
                    'body' => $question->body,
                    'explanation' => $question->explanation,
                    'marks' => $question->marks,
                    'is_active' => $question->is_active,
                ]);

                foreach ($question->options as $option) {
                    QuizOption::query()->create([
                        'question_id' => $newQuestion->getKey(),
                        'sort_order' => $option->sort_order,
                        'body' => $option->body,
                        'is_correct' => $option->is_correct,
                    ]);
                }
            }

            $copy->recalculateTotalMarks();

            return $copy;
        });
    }
}
