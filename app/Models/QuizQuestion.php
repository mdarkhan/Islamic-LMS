<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['quiz_id', 'sort_order', 'type', 'body', 'explanation', 'marks', 'is_active'])]
class QuizQuestion extends Model
{
    /** @use HasFactory<\Database\Factories\QuizQuestionFactory> */
    use HasFactory;

    public const TYPE_SINGLE = 'single';
    public const TYPE_MULTIPLE = 'multiple';

    protected function casts(): array
    {
        return [
            'marks' => 'integer',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /** @return array<int, int> Option ids that are correct. */
    public function correctOptionIds(): array
    {
        return $this->options
            ->where('is_correct', true)
            ->pluck('id')
            ->sort()
            ->values()
            ->all();
    }

    /** True if any stored student answer references this question (scoring lock). */
    public function hasStoredAnswers(): bool
    {
        return QuizAnswer::query()->where('question_id', $this->getKey())->exists();
    }

    /** @return HasMany<QuizOption, $this> */
    public function options(): HasMany
    {
        return $this->hasMany(QuizOption::class, 'question_id')->orderBy('sort_order');
    }

    /** @return BelongsTo<Quiz, $this> */
    public function quiz(): BelongsTo
    {
        return $this->belongsTo(Quiz::class);
    }
}
