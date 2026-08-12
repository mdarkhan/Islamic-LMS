<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['attempt_id', 'question_id', 'is_correct', 'marks_awarded', 'answered_at'])]
class QuizAnswer extends Model
{
    protected function casts(): array
    {
        return [
            'is_correct' => 'boolean',
            'marks_awarded' => 'integer',
            'answered_at' => 'datetime',
        ];
    }

    /** @return array<int, int> Option ids the student selected, sorted ascending. */
    public function selectedOptionIds(): array
    {
        return $this->selections->pluck('option_id')->map(intval(...))->sort()->values()->all();
    }

    /** @return HasMany<QuizAnswerOption, $this> */
    public function selections(): HasMany
    {
        return $this->hasMany(QuizAnswerOption::class, 'answer_id');
    }

    /** @return BelongsToMany<QuizOption, $this> */
    public function options(): BelongsToMany
    {
        return $this->belongsToMany(QuizOption::class, 'quiz_answer_options', 'answer_id', 'option_id');
    }

    /** @return BelongsTo<QuizAttempt, $this> */
    public function attempt(): BelongsTo
    {
        return $this->belongsTo(QuizAttempt::class, 'attempt_id');
    }

    /** @return BelongsTo<QuizQuestion, $this> */
    public function question(): BelongsTo
    {
        return $this->belongsTo(QuizQuestion::class, 'question_id');
    }
}
