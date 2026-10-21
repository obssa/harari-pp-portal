<?php

namespace App\Domain\Engagement\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * The double opt-in email (ADR-0051): a private link that confirms the
 * address and opens the preference centre. Someone already subscribed gets
 * the same link, worded as a way to their preferences.
 */
final class ConfirmSubscriptionMail extends Mailable implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $token,
        public readonly string $language,
        public readonly bool $alreadyConfirmed,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->alreadyConfirmed ? __('newsletter.mail.preferences_subject') : __('newsletter.mail.confirm_subject'));
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.newsletter.confirm', with: [
            'alreadyConfirmed' => $this->alreadyConfirmed,
            'url' => route('newsletter.confirm', ['locale' => $this->language, 'token' => $this->token]),
        ]);
    }
}
