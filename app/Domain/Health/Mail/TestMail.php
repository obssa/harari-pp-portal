<?php

namespace App\Domain\Health\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * "Send a test email" on the health page (ADR-0037): sent at once, not
 * queued, so the page can show whether the mail server accepted it.
 */
final class TestMail extends Mailable
{
    public function __construct(public readonly string $name) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('health.mail.subject'));
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.health.test', with: ['name' => $this->name]);
    }
}
