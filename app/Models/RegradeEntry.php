<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The before/after of one attempt inside a regrade run, so every score change is
 * recoverable.
 */
#[Fillable(['regrade_run_id', 'attempt_id', 'old_calculated', 'new_calculated', 'old_final', 'new_final'])]
class RegradeEntry extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'old_calculated' => 'integer',
            'new_calculated' => 'integer',
            'old_final' => 'integer',
            'new_final' => 'integer',
        ];
    }

    /** @return BelongsTo<RegradeRun, $this> */
    public function run(): BelongsTo
    {
        return $this->belongsTo(RegradeRun::class, 'regrade_run_id');
    }

    /** @return BelongsTo<QuizAttempt, $this> */
    public function attempt(): BelongsTo
    {
        return $this->belongsTo(QuizAttempt::class, 'attempt_id');
    }
}
