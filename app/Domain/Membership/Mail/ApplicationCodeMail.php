<?php

namespace App\Domain\Membership\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * The six-digit code that confirms an application's email address
 * (ADR-0067). Sent at once, not queued: the person is waiting for it.
 */
final class ApplicationCodeMail extends Mailable
{
    public function __construct(
        public readonly string $code,
        public readonly int $minutes,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('membership.mail.code_subject'));
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.membership.code', with: ['code' => $this->code, 'minutes' => $this->minutes]);
    }
}
