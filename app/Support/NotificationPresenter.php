<?php

namespace App\Support;

use App\Models\AppNotification;
use Illuminate\Support\Collection;

/**
 * Shapes AppNotification rows for the bell dropdown / poll response. Timestamps are
 * exact (`d/m/Y H:i`), not a relative "2 minutes ago" string — this app never localises
 * Carbon's diffForHumans(), and every other timestamp here (results, audit, messages)
 * is shown the same exact way.
 */
class NotificationPresenter
{
    /**
     * @param  Collection<int, AppNotification>  $notifications
     * @return array<int, array{id:int, title:string, body:?string, read:bool, openUrl:string, deleteUrl:string, at:string}>
     */
    public static function collection(Collection $notifications): array
    {
        return $notifications->map(fn (AppNotification $n) => self::one($n))->values()->all();
    }

    /** @return array{id:int, title:string, body:?string, read:bool, openUrl:string, deleteUrl:string, at:string} */
    public static function one(AppNotification $n): array
    {
        return [
            'id' => (int) $n->getKey(),
            'title' => $n->title,
            'body' => $n->body,
            'read' => $n->read_at !== null,
            'openUrl' => route('notifications.open', $n),
            'deleteUrl' => route('notifications.destroy', $n),
            'at' => $n->updated_at?->format('d/m/Y H:i') ?? '',
        ];
    }
}
