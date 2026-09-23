<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The ustaz's comment on one student's whole day — not per-deed. One row per
 * (student, date); written only by AmolService::saveNote(). Unlike AmolEntry, a note
 * may be added or edited for ANY date (the ustaz reviews after the fact), not just today.
 *
 * `seen_at` drives the "আমলনামা" sidebar badge: null means the student has not opened
 * this day since the note was last written or revised. AmolService resets it to null
 * on every save, so an edited comment re-surfaces as new.
 */
#[Fillable(['user_id', 'date', 'note', 'commented_by', 'commented_at', 'seen_at'])]
class AmolDayNote extends Model
{
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'commented_at' => 'datetime',
            'seen_at' => 'datetime',
        ];
    }

    /** @param Builder<AmolDayNote> $query */
    public function scopeUnseen(Builder $query): void
    {
        $query->whereNull('seen_at');
    }

    /**
     * The student the note is about.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The admin who wrote it.
     *
     * @return BelongsTo<User, $this>
     */
    public function commentedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'commented_by');
    }
}
