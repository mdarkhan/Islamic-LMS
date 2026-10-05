<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** The self-service password-reset link. Carries a single-use, expiring token; nothing else. */
class PasswordResetLink extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $recipientName,
        public string $url,
        public int $minutes,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'পাসওয়ার্ড রিসেট — মাসউদ আলিমী');
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.password-reset');
    }
}
