<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One regrade run: an answer-key or marks correction applied to a quiz, with a
 * per-attempt before/after in regrade_entries. The whole run is one transaction.
 */
#[Fillable([
    'quiz_id', 'admin_id', 'reason', 'attempts_affected', 'attempts_skipped',
    'status', 'started_at', 'completed_at',
])]
class RegradeRun extends Model
{
    public $timestamps = false;

    public const STATUS_RUNNING = 'running';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';

    protected function casts(): array
    {
        return [
            'attempts_affected' => 'integer',
            'attempts_skipped' => 'integer',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Quiz, $this> */
    public function quiz(): BelongsTo
    {
        return $this->belongsTo(Quiz::class);
    }

    /** @return BelongsTo<User, $this> */
    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    /** @return HasMany<RegradeEntry, $this> */
    public function entries(): HasMany
    {
        return $this->hasMany(RegradeEntry::class);
    }
}
