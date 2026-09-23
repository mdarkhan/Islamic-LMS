<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Http\Requests\SendMessageRequest;
use App\Services\Messaging\MessageService;
use App\Services\Notifications\NotificationService;
use App\Support\MessagePresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * A student's single thread with the ustaz. Every action resolves the thread from the
 * AUTHENTICATED user, never from a route parameter, so a student can only ever reach
 * their own conversation — there is no id to tamper with.
 */
class MessageController extends Controller
{
    public function __construct(
        private readonly MessageService $messages,
        private readonly NotificationService $notifications,
    ) {}

    public function index(Request $request): View
    {
        $student = $request->user();
        $conversation = $this->messages->threadFor($student);

        // Opening the thread is what marks the ustaz's replies read — in the message
        // list itself and in the bell-icon feed alike.
        $this->messages->markRead($conversation, $student);
        $this->notifications->markReadForSubject($student, 'conversation', $conversation->getKey());

        return view('student.messages.index', [
            'messages' => MessagePresenter::collection(
                $this->messages->messages($conversation), $student,
            ),
        ]);
    }

    public function store(SendMessageRequest $request): RedirectResponse|JsonResponse
    {
        $student = $request->user();
        $conversation = $this->messages->threadFor($student);

        $message = $this->messages->send($conversation, $student, $request->validated()['body']);

        if ($request->wantsJson()) {
            return response()->json([
                'message' => MessagePresenter::one($message->load('sender:id,name'), $student),
            ], 201);
        }

        return back();
    }

    /** Poll: anything the ustaz has written since the client's last known message id. */
    public function poll(Request $request): JsonResponse
    {
        $student = $request->user();
        $conversation = $this->messages->threadFor($student);

        $after = $request->integer('after') ?: null;
        $new = $this->messages->messages($conversation, $after);

        // Seeing them in an open thread is reading them.
        $this->messages->markRead($conversation, $student);
        $this->notifications->markReadForSubject($student, 'conversation', $conversation->getKey());

        return response()->json([
            'messages' => MessagePresenter::collection($new, $student),
        ]);
    }
}
