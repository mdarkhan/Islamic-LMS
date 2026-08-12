<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'quiz_id', 'user_id', 'kind', 'attempt_no', 'status', 'started_at', 'expires_at',
    'counts_toward_cumulative', 'is_legacy_import', 'answer_details_available',
    'point_transaction_id', 'total_marks_snapshot',
])]
class QuizAttempt extends Model
{
    public const KIND_OFFICIAL = 'official';
    public const KIND_PRACTICE = 'practice';

    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_VOIDED = 'voided';

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'expires_at' => 'datetime',
            'submitted_at' => 'datetime',
            'calculated_score' => 'integer',
            'manual_adjustment' => 'integer',
            'final_score' => 'integer',
            'total_marks_snapshot' => 'integer',
            'time_taken_seconds' => 'integer',
            'submission_seq' => 'integer',
            'counts_toward_cumulative' => 'boolean',
            'is_legacy_import' => 'boolean',
            'answer_details_available' => 'boolean',
        ];
    }

    public function isOfficial(): bool
    {
        return $this->kind === self::KIND_OFFICIAL;
    }

    public function isSubmitted(): bool
    {
        return $this->status === self::STATUS_SUBMITTED;
    }

    public function isInProgress(): bool
    {
        return $this->status === self::STATUS_IN_PROGRESS;
    }

    /**
     * A terminal attempt is finished for good: submitted, expired or voided.
     *
     * Terminal is one-way. Nothing — not a late submit(), not a retried request —
     * may reopen a terminal attempt or convert it to a different terminal state.
     * Only an in_progress attempt can transition.
     */
    public function isTerminal(): bool
    {
        return in_array($this->status, [
            self::STATUS_SUBMITTED,
            self::STATUS_EXPIRED,
            self::STATUS_VOIDED,
        ], true);
    }

    /** Server-side expiry check. The browser timer is presentation only. */
    public function hasExpiredAt(\DateTimeInterface $now): bool
    {
        return $this->expires_at !== null && $now >= $this->expires_at;
    }

    /** @return HasMany<QuizAnswer, $this> */
    public function answers(): HasMany
    {
        return $this->hasMany(QuizAnswer::class, 'attempt_id');
    }

    /** @return BelongsTo<Quiz, $this> */
    public function quiz(): BelongsTo
    {
        return $this->belongsTo(Quiz::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<PointTransaction, $this> */
    public function pointTransaction(): BelongsTo
    {
        return $this->belongsTo(PointTransaction::class);
    }
}
