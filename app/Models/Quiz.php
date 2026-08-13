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

    // Student-facing lifecycle states (computed from status + the clock).
    public const STATE_DRAFT = 'draft';        // admin-only, never visible
    public const STATE_UPCOMING = 'upcoming';  // scheduled/published, before starts_at
    public const STATE_OPEN = 'open';          // scheduled/published, within the window
    public const STATE_CLOSED = 'closed';      // scheduled/published, past ends_at
    public const STATE_ARCHIVED = 'archived';  // no new official attempt (practice may remain)

    /**
     * Whether a new official attempt may START now.
     *
     * Both `scheduled` and `published` are live-eligible; the time window governs
     * actual openness, so a quiz opens at `starts_at` and closes at `ends_at`
     * WITHOUT any status mutation — nothing depends on a cron job flipping a flag.
     * `draft` and `archived` are never open. Server clock is authoritative.
     */
    public function isOpenAt(\DateTimeInterface $now): bool
    {
        if (! in_array($this->status, [self::STATUS_SCHEDULED, self::STATUS_PUBLISHED], true)) {
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

    /**
     * The student-facing state used for grouping on the Exams page. Presentation
     * only — QuizAttemptService remains authoritative for actually starting one.
     */
    public function officialState(\DateTimeInterface $now): string
    {
        if ($this->status === self::STATUS_DRAFT) {
            return self::STATE_DRAFT;
        }
        if ($this->status === self::STATUS_ARCHIVED) {
            return self::STATE_ARCHIVED;
        }
        if ($this->starts_at && $now < $this->starts_at) {
            return self::STATE_UPCOMING;
        }
        if ($this->ends_at && $now >= $this->ends_at) {
            return self::STATE_CLOSED;
        }

        return self::STATE_OPEN;
    }

    /** Draft quizzes are never shown to students; every other state may appear. */
    public function isVisibleToStudents(): bool
    {
        return $this->status !== self::STATUS_DRAFT;
    }

    /** Practice is available whenever enabled and the quiz is not a draft. */
    public function practiceAvailable(): bool
    {
        return $this->practice_enabled && $this->status !== self::STATUS_DRAFT;
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

    /**
     * Whether any official attempt exists. Once one does, scoring-sensitive fields
     * (questions, options, correct answers, marks, type) are locked in the builder —
     * changing them would leave stored scores stale. Corrections then go through the
     * dedicated Regrade workflow (a later phase), not casual edits.
     */
    public function hasOfficialAttempts(): bool
    {
        return $this->attempts()->where('kind', QuizAttempt::KIND_OFFICIAL)->exists();
    }

    /** Alias that reads well at call sites guarding destructive scoring changes. */
    public function scoringLocked(): bool
    {
        return $this->hasOfficialAttempts();
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
