<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One bonus award to one student — the idempotency guard AND the source for the
 * "congratulations" screen. Created in the same transaction as its point_transactions
 * row (never on its own). `seen_at` is the only mutable field: it flips once the student
 * has been shown the congrats modal.
 */
#[Fillable([
    'user_id', 'type', 'quiz_id', 'course_id', 'position', 'points',
    'point_transaction_id', 'seen_at',
])]
class RewardGrant extends Model
{
    public const UPDATED_AT = null;

    public const TYPE_QUIZ_ACHIEVEMENT = 'quiz_achievement';
    public const TYPE_COURSE_TOPPER = 'course_topper';

    protected function casts(): array
    {
        return [
            'points' => 'integer',
            'position' => 'integer',
            'seen_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    /** @param Builder<RewardGrant> $query */
    public function scopeUnseen(Builder $query): void
    {
        $query->whereNull('seen_at');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Quiz, $this> */
    public function quiz(): BelongsTo
    {
        return $this->belongsTo(Quiz::class);
    }

    /** @return BelongsTo<Course, $this> */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }
}
