<?php

namespace App\Domain\Membership\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * The decision on a membership application, by email when the applicant gave
 * one (ADR-0045); the SMS carries the same news. The queued job is encrypted.
 */
final class ApplicationDecisionMail extends Mailable implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $reference,
        public readonly string $name,
        public readonly bool $approved,
        public readonly ?string $memberNo,
        public readonly ?string $reason,
        public readonly string $language,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->approved
            ? __('membership.mail.approved_subject')
            : __('membership.mail.rejected_subject', ['reference' => $this->reference]));
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.membership.decision', with: [
            'name' => $this->name,
            'approved' => $this->approved,
            'number' => $this->memberNo,
            'reason' => $this->reason,
            'reference' => $this->reference,
            'url' => route('membership.status', ['locale' => $this->language]),
        ]);
    }
}
