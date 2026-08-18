<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'type', 'source_name', 'source_sha256', 'status', 'source_rows', 'valid_rows',
    'imported_rows', 'skipped_rows', 'failed_rows', 'summary', 'completed_at',
])]
class LegacyImportBatch extends Model
{
    public const TYPE_STUDENTS = 'students';

    public const TYPE_RESULTS = 'results';

    public const TYPE_QUIZZES = 'quizzes';

    public const STATUS_RUNNING = 'running';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    protected function casts(): array
    {
        return [
            'summary' => 'array',
            'completed_at' => 'datetime',
            'source_rows' => 'integer',
            'valid_rows' => 'integer',
            'imported_rows' => 'integer',
            'skipped_rows' => 'integer',
            'failed_rows' => 'integer',
        ];
    }

    /** @return HasMany<User, $this> */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /** @return HasMany<QuizAttempt, $this> */
    public function attempts(): HasMany
    {
        return $this->hasMany(QuizAttempt::class);
    }
}
