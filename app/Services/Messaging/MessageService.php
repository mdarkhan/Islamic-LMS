<?php

namespace App\Services\Messaging;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\Role;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Notifications\NotificationService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The only writer of conversations/messages.
 *
 * Sides are derived from the data, never from roles: a thread belongs to exactly one
 * student (`conversations.user_id`), so a message is "from the student" when its
 * sender_id equals that column and "from the ustaz side" otherwise. That keeps the
 * read/unread logic correct no matter which admin happens to reply.
 *
 * The ustaz side is a SHARED inbox (any admin holding messages.view): the first admin to
 * open a thread marks its student messages read for all of them. That is deliberate —
 * the alternative (per-admin read state) would show the same question as "new" to every
 * admin forever.
 *
 * NOTE: this is the authenticated messaging feature, not the public Ask Ustaz form.
 * The public form remains email-only and is still never persisted (CLAUDE.md rule 1).
 */
class MessageService
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly NotificationService $notifications,
    ) {}

    /** The student's thread, created on first use by either side. */
    public function threadFor(User $student): Conversation
    {
        return Conversation::query()->firstOrCreate(['user_id' => $student->getKey()]);
    }

    /**
     * Append a message and bump the thread's ordering timestamp, atomically. Then ping
     * the bell-icon feed of whoever did NOT just write it.
     * `read_at` stays null: it means "not yet read by the other side".
     */
    public function send(Conversation $conversation, User $sender, string $body): Message
    {
        $message = DB::transaction(function () use ($conversation, $sender, $body) {
            $message = $conversation->messages()->create([
                'sender_id' => $sender->getKey(),
                'body' => trim($body),
            ]);

            $conversation->forceFill(['last_message_at' => $message->created_at])->save();

            return $message;
        });

        $this->notifyOfMessage($conversation, $sender, $message);

        return $message;
    }

    /**
     * A student's message pings every admin who can see the shared inbox; an ustaz reply
     * pings just that one student. Upserted by (conversation) — see NotificationService::
     * notify() — so a burst of messages in one thread surfaces as one feed item, not many.
     */
    private function notifyOfMessage(Conversation $conversation, User $sender, Message $message): void
    {
        if ($this->readsAsStudent($conversation, $sender)) {
            $conversation->loadMissing('student:id,name');

            foreach ($this->adminsWithMessagingAccess() as $admin) {
                $this->notifications->notify(
                    $admin,
                    'message',
                    $conversation->student->name.' আপনাকে একটি মেসেজ পাঠিয়েছেন',
                    $message->body,
                    route('admin.messages.show', $conversation),
                    'conversation',
                    $conversation->getKey(),
                );
            }

            return;
        }

        $this->notifications->notify(
            $conversation->student,
            'message',
            'উস্তায আপনাকে একটি নতুন মেসেজ পাঠিয়েছেন',
            $message->body,
            route('student.messages.index'),
            'conversation',
            $conversation->getKey(),
        );
    }

    /** Every admin/super_admin who actually holds messages.view (super_admin bypasses grants). */
    private function adminsWithMessagingAccess(): Collection
    {
        return User::query()
            ->whereHas('roles', fn ($q) => $q->whereIn('name', [Role::SUPER_ADMIN, Role::ADMIN]))
            ->get()
            ->filter(fn (User $u) => $u->hasPermission('messages.view'));
    }

    /** Mark everything the other side wrote as read, for whichever side $reader is on. */
    public function markRead(Conversation $conversation, User $reader): void
    {
        $query = $conversation->messages()->unread();

        if ($this->readsAsStudent($conversation, $reader)) {
            $query->where('sender_id', '!=', $conversation->user_id);   // ustaz replies
        } else {
            $query->where('sender_id', $conversation->user_id);         // the student's own
        }

        $query->update(['read_at' => now()]);
    }

    /**
     * The header badge count. An admin sees every student message still unread across all
     * threads; a student sees the ustaz replies they have not opened yet.
     */
    public function unreadCountFor(User $user): int
    {
        if ($user->isAdmin()) {
            return Message::query()
                ->join('conversations', 'conversations.id', '=', 'messages.conversation_id')
                ->whereNull('messages.read_at')
                ->whereColumn('messages.sender_id', 'conversations.user_id')
                ->count();
        }

        $conversation = Conversation::query()->where('user_id', $user->getKey())->first();

        if ($conversation === null) {
            return 0;
        }

        return $conversation->messages()->unread()
            ->where('sender_id', '!=', $user->getKey())
            ->count();
    }

    /**
     * Thread contents, oldest first. `$afterId` makes this the polling query: the client
     * asks only for what it has not already rendered.
     *
     * @return Collection<int, Message>
     */
    public function messages(Conversation $conversation, ?int $afterId = null): Collection
    {
        return $conversation->messages()
            ->when($afterId !== null, fn ($q) => $q->where('id', '>', $afterId))
            ->with('sender:id,name')
            ->orderBy('id')
            ->get();
    }

    /**
     * The ustaz-side inbox: every thread, newest activity first, each with its student,
     * last message and the number of student messages still unread.
     *
     * @return Collection<int, Conversation>
     */
    public function inbox(): Collection
    {
        return Conversation::query()
            ->with(['student:id,name,roll', 'latestMessage'])
            ->withCount(['messages as unread_count' => fn ($q) => $q
                ->whereNull('messages.read_at')
                ->whereColumn('messages.sender_id', 'conversations.user_id'),
            ])
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * Delete a whole thread. The content is genuinely removed (messages cascade), which is
     * the point — but the FACT of the deletion is audited, so the action is never silent.
     * The message bodies are deliberately NOT copied into the audit payload.
     */
    public function deleteThread(Conversation $conversation, User $actor): void
    {
        $summary = [
            'conversation_id' => $conversation->getKey(),
            'student_id' => $conversation->user_id,
            'messages_deleted' => $conversation->messages()->count(),
        ];

        DB::transaction(function () use ($conversation, $actor, $summary) {
            $conversation->delete();

            $this->audit->log('message.thread_deleted', null, $summary, null, $actor);
        });
    }

    /** True when $reader is the student who owns the thread (rather than the ustaz side). */
    private function readsAsStudent(Conversation $conversation, User $reader): bool
    {
        return (int) $conversation->user_id === (int) $reader->getKey();
    }
}
