<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SendMessageRequest;
use App\Models\Conversation;
use App\Models\User;
use App\Services\Messaging\MessageService;
use App\Services\Notifications\NotificationService;
use App\Support\MessagePresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The ustaz-side shared inbox. Reachable by any admin holding `messages.view`
 * (route middleware), so the thread id IS the addressing mechanism here — unlike the
 * student side, where the thread is always the caller's own.
 */
class MessageController extends Controller
{
    public function __construct(
        private readonly MessageService $messages,
        private readonly NotificationService $notifications,
    ) {}

    public function index(): View
    {
        return view('admin.messages.index', [
            'conversations' => $this->messages->inbox(),
            // For "start a new conversation": students who can be written to first.
            'students' => User::query()->students()
                ->where('status', User::STATUS_ACTIVE)
                ->orderBy('roll')
                ->get(['id', 'name', 'roll']),
        ]);
    }

    public function show(Request $request, Conversation $conversation): View
    {
        $admin = $request->user();

        // A shared inbox: opening the thread clears the message list for every admin,
        // but the bell notification is only THIS admin's own row — each admin's feed is
        // independent, so a colleague who has not opened it yet still sees it as new.
        $this->messages->markRead($conversation, $admin);
        $this->notifications->markReadForSubject($admin, 'conversation', $conversation->getKey());

        return view('admin.messages.show', [
            'conversation' => $conversation->load('student:id,name,roll'),
            'messages' => MessagePresenter::collection(
                $this->messages->messages($conversation), $admin,
            ),
        ]);
    }

    public function store(SendMessageRequest $request, Conversation $conversation): RedirectResponse|JsonResponse
    {
        $admin = $request->user();

        $message = $this->messages->send($conversation, $admin, $request->validated()['body']);

        if ($request->wantsJson()) {
            return response()->json([
                'message' => MessagePresenter::one($message->load('sender:id,name'), $admin),
            ], 201);
        }

        return back();
    }

    /** The ustaz opening a thread with a student who has never written in. */
    public function start(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'student_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $student = User::query()->findOrFail($validated['student_id']);

        abort_unless($student->isStudent(), 404);

        return redirect()->route('admin.messages.show', $this->messages->threadFor($student));
    }

    public function poll(Request $request, Conversation $conversation): JsonResponse
    {
        $admin = $request->user();

        $after = $request->integer('after') ?: null;
        $new = $this->messages->messages($conversation, $after);

        $this->messages->markRead($conversation, $admin);
        $this->notifications->markReadForSubject($admin, 'conversation', $conversation->getKey());

        return response()->json([
            'messages' => MessagePresenter::collection($new, $admin),
        ]);
    }

    public function destroy(Request $request, Conversation $conversation): RedirectResponse
    {
        $this->messages->deleteThread($conversation, $request->user());

        return redirect()->route('admin.messages.index')
            ->with('status', __('messages.thread_deleted'));
    }
}
