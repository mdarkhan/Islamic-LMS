<?php

namespace Database\Factories;

use App\Models\QuizOption;
use App\Models\QuizQuestion;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<QuizOption> */
class QuizOptionFactory extends Factory
{
    protected $model = QuizOption::class;

    public function definition(): array
    {
        return [
            'question_id' => QuizQuestion::factory(),
            'sort_order' => 0,
            'body' => fake()->word(),
            'is_correct' => false,
        ];
    }

    public function correct(): static
    {
        return $this->state(fn () => ['is_correct' => true]);
    }
}
