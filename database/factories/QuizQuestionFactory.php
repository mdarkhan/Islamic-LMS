<?php

namespace Database\Factories;

use App\Models\Quiz;
use App\Models\QuizQuestion;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<QuizQuestion> */
class QuizQuestionFactory extends Factory
{
    protected $model = QuizQuestion::class;

    public function definition(): array
    {
        return [
            'quiz_id' => Quiz::factory(),
            'sort_order' => 0,
            'type' => QuizQuestion::TYPE_SINGLE,
            'body' => 'প্রশ্ন '.fake()->numberBetween(1, 999),
            'marks' => 1,
            'is_active' => true,
        ];
    }

    public function multiple(): static
    {
        return $this->state(fn () => ['type' => QuizQuestion::TYPE_MULTIPLE]);
    }
}
