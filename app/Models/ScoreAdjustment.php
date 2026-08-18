<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only audit of a manual score adjustment. Never updated or deleted — a later
 * change writes a new row (brief §30). The reason is mandatory.
 */
#[Fillable(['attempt_id', 'admin_id', 'old_manual', 'new_manual', 'old_final', 'new_final', 'reason'])]
class ScoreAdjustment extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'old_manual' => 'integer',
            'new_manual' => 'integer',
            'old_final' => 'integer',
            'new_final' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<QuizAttempt, $this> */
    public function attempt(): BelongsTo
    {
        return $this->belongsTo(QuizAttempt::class, 'attempt_id');
    }

    /** @return BelongsTo<User, $this> */
    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }
}
