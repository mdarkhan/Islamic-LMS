<?php

namespace Database\Factories;

use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<QuizAttempt> */
class QuizAttemptFactory extends Factory
{
    protected $model = QuizAttempt::class;

    public function definition(): array
    {
        return [
            'quiz_id' => Quiz::factory(),
            'user_id' => User::factory(),
            'kind' => QuizAttempt::KIND_OFFICIAL,
            'attempt_no' => 1,
            'status' => QuizAttempt::STATUS_SUBMITTED,
            'started_at' => now()->subHour(),
            'submitted_at' => now(),
            'time_taken_seconds' => 600,
            'calculated_score' => 0,
            'manual_adjustment' => 0,
            'final_score' => 0,
            'total_marks_snapshot' => 0,
            'counts_toward_cumulative' => true,
        ];
    }
}
