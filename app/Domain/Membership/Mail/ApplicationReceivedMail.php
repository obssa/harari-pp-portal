<?php

namespace App\Domain\Membership\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Confirms by email that an application arrived, with its reference
 * (ADR-0067), when it was confirmed by email or without a code.
 */
final class ApplicationReceivedMail extends Mailable implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $reference,
        public readonly string $language,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('membership.mail.received_subject', ['reference' => $this->reference]));
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.membership.received', with: [
            'reference' => $this->reference,
            'url' => route('membership.status', ['locale' => $this->language]),
        ]);
    }
}
