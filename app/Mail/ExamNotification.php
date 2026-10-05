<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Exam reminder / results-published email. Deliberately carries NO score or answer —
 * only what happened and a link; the result itself is behind login and the release gate.
 */
class ExamNotification extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $mailSubject,
        public string $headline,
        public string $line,
        public string $url,
        public string $buttonLabel,
        public string $recipientName,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->mailSubject);
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.exam-notification');
    }
}
