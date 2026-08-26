<?php

namespace App\Support;

use App\Models\Message;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Shapes messages for the chat client. A strict allow-list, like the exam presenters:
 * id, body, author name, timestamp and which side wrote it — never a sender's email,
 * phone, roll or any other account field (SECURITY.md §2.13).
 *
 * `body` travels as plain text and MUST be rendered with x-text / {{ }}. It is never
 * HTML or Markdown: both sides of the thread are untrusted authors.
 */
class MessagePresenter
{
    /**
     * @param  Collection<int, Message>  $messages
     * @return array<int, array{id:int, body:string, mine:bool, author:string, at:string}>
     */
    public static function collection(Collection $messages, User $viewer): array
    {
        return $messages->map(fn (Message $message) => self::one($message, $viewer))->values()->all();
    }

    /**
     * @return array{id:int, body:string, mine:bool, author:string, at:string}
     */
    public static function one(Message $message, User $viewer): array
    {
        return [
            'id' => (int) $message->getKey(),
            'body' => $message->body,
            'mine' => (int) $message->sender_id === (int) $viewer->getKey(),
            'author' => $message->sender?->name ?? '—',
            'at' => $message->created_at?->format('d/m/Y H:i') ?? '',
        ];
    }
}
