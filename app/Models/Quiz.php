<?php

namespace App\Models;

use Database\Factories\QuizFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'course_id', 'lesson_id', 'slug', 'title', 'description', 'status',
    'practice_enabled', 'point_cost', 'duration_seconds', 'starts_at', 'ends_at',
    'result_release_at', 'results_released_at', 'leaderboard_visible',
    'max_official_attempts', 'created_by',
])]
class Quiz extends Model
{
    /** @use HasFactory<QuizFactory> */
    use HasFactory;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_SCHEDULED = 'scheduled';
    public const STATUS_PUBLISHED = 'published';
    public const STATUS_ARCHIVED = 'archived';

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'result_release_at' => 'datetime',
            'results_released_at' => 'datetime',
            'published_at' => 'datetime',
            'practice_enabled' => 'boolean',
            'leaderboard_visible' => 'boolean',
            'point_cost' => 'integer',
            'duration_seconds' => 'integer',
            'total_marks' => 'integer',
            'max_official_attempts' => 'integer',
        ];
    }

    /**
     * Server clock is authoritative — never trust a client-supplied time.
     */
    public function isOpenAt(\DateTimeInterface $now): bool
    {
        if ($this->status !== self::STATUS_PUBLISHED) {
            return false;
        }
        if ($this->starts_at && $now < $this->starts_at) {
            return false;
        }
        if ($this->ends_at && $now >= $this->ends_at) {
            return false;
        }

        return true;
    }

    public function resultsReleasedAt(\DateTimeInterface $now): bool
    {
        if ($this->results_released_at !== null) {
            return true;   // released early by an admin
        }
        if ($this->result_release_at !== null) {
            return $now >= $this->result_release_at;
        }

        // No explicit release time: results follow the exam end.
        return $this->ends_at === null || $now >= $this->ends_at;
    }

    /** Recompute the cached total from the questions, which remain the truth. */
    public function recalculateTotalMarks(): int
    {
        $total = (int) $this->questions()->where('is_active', true)->sum('marks');
        $this->forceFill(['total_marks' => $total])->save();

        return $total;
    }

    /** @return HasMany<QuizQuestion, $this> */
    public function questions(): HasMany
    {
        return $this->hasMany(QuizQuestion::class)->orderBy('sort_order');
    }

    /** @return HasMany<QuizAttempt, $this> */
    public function attempts(): HasMany
    {
        return $this->hasMany(QuizAttempt::class);
    }

    /** @return BelongsTo<Course, $this> */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /** @return BelongsTo<Lesson, $this> */
    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }
}
