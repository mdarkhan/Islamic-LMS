<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Carries one Ask Ustaz question to the configured recipient. The question exists ONLY
 * as this in-flight message — it is never written to the database, an audit log, or the
 * application log (SECURITY.md §2.8, brief §16). Reply-To is the questioner so the Ustaz
 * can answer directly.
 */
class AskUstazQuestion extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $name,
        public string $email,
        public ?string $mobile,
        public ?string $topic,
        public string $question,
        public string $submittedAt,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'উস্তাযকে জিজ্ঞাসা: '.($this->topic ?: 'সাধারণ'),
            replyTo: [new Address($this->email, $this->name)],
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.ask-ustaz');
    }
}
