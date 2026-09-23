<?php

namespace App\Http\Controllers;

use App\Models\AppNotification;
use App\Services\Notifications\NotificationService;
use App\Support\NotificationPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The bell-icon feed. Shared by both students and admins — a notification always
 * belongs to the authenticated user (never a route parameter for "whose"), so this one
 * controller serves both roles without any role check of its own; only ownership of the
 * individual $notification row is checked, per action.
 */
class NotificationController extends Controller
{
    public function __construct(private readonly NotificationService $notifications) {}

    public function index(Request $request): View
    {
        return view('notifications.index', [
            'notifications' => $this->notifications->paginatedFor($request->user()),
        ]);
    }

    /** Polled by the header bell: unread count plus the fresh recent list, so both the
     *  badge and an already-open dropdown update without a page load. */
    public function poll(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'count' => $this->notifications->unreadCountFor($user),
            'notifications' => NotificationPresenter::collection($this->notifications->recentFor($user)),
        ]);
    }

    /** Click a notification: mark it read, then go where it points. */
    public function open(Request $request, AppNotification $notification): RedirectResponse
    {
        abort_unless($notification->user_id === $request->user()->getKey(), 403);

        $this->notifications->markRead($notification);

        return redirect($notification->url);
    }

    public function markAllRead(Request $request): RedirectResponse|JsonResponse
    {
        $this->notifications->markAllRead($request->user());

        return $request->wantsJson() ? response()->json(['ok' => true]) : back();
    }

    public function destroy(Request $request, AppNotification $notification): RedirectResponse|JsonResponse
    {
        abort_unless($notification->user_id === $request->user()->getKey(), 403);

        $this->notifications->delete($notification);

        return $request->wantsJson() ? response()->json(['ok' => true]) : back();
    }
}
