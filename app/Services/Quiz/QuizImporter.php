<?php

namespace App\Services\Quiz;

use App\Models\Quiz;
use App\Models\QuizOption;
use App\Models\QuizQuestion;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Support\Slug;
use Illuminate\Support\Facades\DB;

/**
 * Persists a parsed quiz. Fully transactional (Part 22): the quiz, every question
 * and every option are saved together or nothing is — a half-imported quiz can never
 * remain. Only questions that parsed without a fatal error are written; malformed /
 * errored rows are dropped (the admin was shown them in the preview).
 *
 * Imported quizzes always start as `draft` so the admin reviews before publishing.
 */
class QuizImporter
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array<string, mixed>  $parsed  output of QuizImportParser::parse()
     * @param  array{title:string, course_id:?int, lesson_id:?int, point_cost:int, legacy_import_batch_id?:?int, legacy_source_key?:?string}  $options
     */
    public function import(array $parsed, array $options, User $actor): Quiz
    {
        return DB::transaction(function () use ($parsed, $options, $actor) {
            $config = $parsed['config'];

            $quiz = Quiz::query()->create([
                'course_id' => $options['course_id'],
                'lesson_id' => $options['lesson_id'],
                'slug' => Slug::unique(
                    $options['title'],
                    fn (string $slug) => Quiz::query()->where('slug', $slug)->exists(),
                    'quiz',
                ),
                'title' => $options['title'],
                'status' => Quiz::STATUS_DRAFT,
                'practice_enabled' => false,
                // Historical/imported quizzes require an explicit admin decision before
                // they can affect the modern cumulative leaderboard.
                'counts_toward_overall' => false,
                'point_cost' => $options['point_cost'],
                'duration_seconds' => $config['duration_seconds'],
                'starts_at' => $config['starts_at'],
                'ends_at' => $config['ends_at'],
                'max_official_attempts' => 1,
                'created_by' => $actor->getKey(),
                'legacy_import_batch_id' => $options['legacy_import_batch_id'] ?? null,
                'legacy_source_key' => $options['legacy_source_key'] ?? null,
            ]);

            $order = 0;
            $imported = 0;
            foreach ($parsed['questions'] as $q) {
                if (($q['malformed'] ?? false) || $q['errors'] !== []) {
                    continue;
                }

                $question = QuizQuestion::query()->create([
                    'quiz_id' => $quiz->getKey(),
                    'sort_order' => $order++,
                    'type' => $q['type'],
                    'body' => $q['body'],
                    'marks' => $q['marks'],
                    'is_active' => true,
                ]);

                foreach ($q['options'] as $i => $optionBody) {
                    QuizOption::query()->create([
                        'question_id' => $question->getKey(),
                        'sort_order' => $i,
                        'body' => $optionBody,
                        // correct_positions are 1-based sheet indices.
                        'is_correct' => in_array($i + 1, $q['correct_positions'], true),
                    ]);
                }

                $imported++;
            }

            $quiz->recalculateTotalMarks();

            // Summary only — never the questions or the answer key.
            $this->audit->log('quiz.imported', $quiz, after: [
                'title' => $quiz->title,
                'questions' => $imported,
                'total_marks' => $quiz->total_marks,
            ], actor: $actor);

            return $quiz;
        });
    }
}
