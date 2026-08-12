<?php

namespace Tests\Support;

use App\Models\Quiz;
use App\Models\QuizOption;
use App\Models\QuizQuestion;

/**
 * Small helper so tests read as the exam they describe rather than as a pile of
 * model creation. Mirrors the legacy sheet shape: up to 12 options, 1-based
 * correct indices, optional custom ("mega") marks.
 */
class QuizBuilder
{
    private int $order = 0;

    public function __construct(private readonly Quiz $quiz) {}

    public static function for(Quiz $quiz): self
    {
        return new self($quiz);
    }

    /**
     * @param  array<int, string>  $options
     * @param  array<int, int>  $correctPositions  1-based, as authored in the sheet
     */
    public function question(array $options, array $correctPositions, int $marks = 1): QuizQuestion
    {
        $question = QuizQuestion::query()->create([
            'quiz_id' => $this->quiz->getKey(),
            'sort_order' => $this->order++,
            'type' => count($correctPositions) > 1 ? QuizQuestion::TYPE_MULTIPLE : QuizQuestion::TYPE_SINGLE,
            'body' => 'প্রশ্ন '.$this->order,
            'marks' => $marks,
            'is_active' => true,
        ]);

        foreach (array_values($options) as $i => $body) {
            QuizOption::query()->create([
                'question_id' => $question->getKey(),
                'sort_order' => $i,
                'body' => $body,
                'is_correct' => in_array($i + 1, $correctPositions, true),
            ]);
        }

        $this->quiz->recalculateTotalMarks();

        return $question->load('options');
    }
}
