<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Carries one Contact form submission to the configured recipient. The message exists
 * ONLY as this in-flight mail — it is never written to the database, an audit log, or
 * the application log (same non-persistence contract as AskUstazQuestion). Reply-To is
 * the sender so the recipient can reply directly.
 */
class ContactMessage extends Mailable
{
    use Queueable, SerializesModels;

    // Named $topic, not $subject — Mailable already declares an untyped $subject
    // property internally and a typed re-declaration fatals (same reason
    // AskUstazQuestion uses $topic).
    public function __construct(
        public string $name,
        public string $email,
        public ?string $mobile,
        public ?string $topic,
        public string $message,
        public string $submittedAt,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'যোগাযোগ ফর্ম: '.($this->topic ?: 'সাধারণ বার্তা'),
            replyTo: [new Address($this->email, $this->name)],
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.contact');
    }
}
