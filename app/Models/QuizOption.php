<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * is_correct is hidden from array/JSON serialisation by default so it cannot leak
 * into a response by accident during a live exam (SECURITY.md §2.3). Grading reads
 * the attribute directly, which is unaffected by $hidden.
 */
#[Fillable(['question_id', 'sort_order', 'body', 'is_correct'])]
#[Hidden(['is_correct'])]
class QuizOption extends Model
{
    /** @use HasFactory<\Database\Factories\QuizOptionFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'is_correct' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /** @return BelongsTo<QuizQuestion, $this> */
    public function question(): BelongsTo
    {
        return $this->belongsTo(QuizQuestion::class, 'question_id');
    }
}
