<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row in a user's bell-icon feed. Written only by NotificationService, which
 * upserts on (user_id, subject_type, subject_id) — see its `notify()` — so repeated
 * activity on the same thing (more messages in the same conversation, an edited amol
 * note) refreshes one row instead of piling up duplicates.
 *
 * `title`/`body` are plain Bengali text, always rendered escaped, same posture as a
 * message body — never HTML or Markdown.
 */
#[Fillable(['user_id', 'type', 'subject_type', 'subject_id', 'title', 'body', 'url', 'read_at'])]
class AppNotification extends Model
{
    protected function casts(): array
    {
        return [
            'read_at' => 'datetime',
        ];
    }

    /** @param Builder<AppNotification> $query */
    public function scopeUnread(Builder $query): void
    {
        $query->whereNull('read_at');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
