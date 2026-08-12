<?php

namespace Database\Factories;

use App\Models\Quiz;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Quiz>
 */
class QuizFactory extends Factory
{
    protected $model = Quiz::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'slug' => 'quiz-'.Str::random(8),
            'title' => 'সীরাত কুইজ',
            'status' => Quiz::STATUS_PUBLISHED,
            'practice_enabled' => false,
            'point_cost' => 1,
            'duration_seconds' => 3600,
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addHour(),
            'max_official_attempts' => 1,
            'total_marks' => 0,
        ];
    }

    public function draft(): static
    {
        return $this->state(fn () => ['status' => Quiz::STATUS_DRAFT]);
    }

    public function notStarted(): static
    {
        return $this->state(fn () => [
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDays(2),
        ]);
    }

    public function ended(): static
    {
        return $this->state(fn () => [
            'starts_at' => now()->subDays(2),
            'ends_at' => now()->subDay(),
        ]);
    }

    public function practice(): static
    {
        return $this->state(fn () => ['practice_enabled' => true]);
    }

    public function free(): static
    {
        return $this->state(fn () => ['point_cost' => 0]);
    }
}
