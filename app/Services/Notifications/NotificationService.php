<?php

namespace App\Services\Notifications;

use App\Models\AppNotification;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * The only writer of app_notifications. The bell icon (shared header, any role) and the
 * "সব নোটিফিকেশন" page both read through here — never a raw query in a controller.
 */
class NotificationService
{
    private const PREVIEW_LIMIT = 8;

    private const PER_PAGE = 20;

    /**
     * Record (or refresh) one notification. When $subjectType/$subjectId are given and a
     * row already exists for that (user, subject), its content is updated in place and it
     * is unread again — so three quick messages in one thread surface as ONE feed item
     * that keeps its latest preview, not three. A standalone notification (no subject)
     * always creates a fresh row.
     */
    public function notify(
        User $recipient,
        string $type,
        string $title,
        ?string $body,
        string $url,
        ?string $subjectType = null,
        ?int $subjectId = null,
    ): AppNotification {
        $body = $body !== null ? Str::limit(trim($body), 140) : null;

        if ($subjectType !== null && $subjectId !== null) {
            $notification = AppNotification::query()->firstOrNew([
                'user_id' => $recipient->getKey(),
                'subject_type' => $subjectType,
                'subject_id' => $subjectId,
            ]);
        } else {
            $notification = new AppNotification(['user_id' => $recipient->getKey()]);
        }

        $notification->fill([
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'url' => $url,
            'read_at' => null,
        ])->save();

        return $notification;
    }

    public function unreadCountFor(User $user): int
    {
        return AppNotification::query()->where('user_id', $user->getKey())->unread()->count();
    }

    /** @return Collection<int, AppNotification> */
    public function recentFor(User $user): Collection
    {
        return AppNotification::query()->where('user_id', $user->getKey())
            ->latest('updated_at')->limit(self::PREVIEW_LIMIT)->get();
    }

    /** @return LengthAwarePaginator<int, AppNotification> */
    public function paginatedFor(User $user): LengthAwarePaginator
    {
        return AppNotification::query()->where('user_id', $user->getKey())
            ->latest('updated_at')->paginate(self::PER_PAGE);
    }

    public function markRead(AppNotification $notification): void
    {
        if ($notification->read_at === null) {
            $notification->forceFill(['read_at' => now()])->save();
        }
    }

    public function markAllRead(User $user): void
    {
        AppNotification::query()->where('user_id', $user->getKey())->unread()->update(['read_at' => now()]);
    }

    /** Called when the user opens the thing a notification points at, e.g. a conversation. */
    public function markReadForSubject(User $user, string $subjectType, int $subjectId): void
    {
        AppNotification::query()
            ->where('user_id', $user->getKey())
            ->where('subject_type', $subjectType)
            ->where('subject_id', $subjectId)
            ->unread()
            ->update(['read_at' => now()]);
    }

    /** Facebook-style: the user removes it from their own feed. Never affects anyone else's. */
    public function delete(AppNotification $notification): void
    {
        $notification->delete();
    }
}
