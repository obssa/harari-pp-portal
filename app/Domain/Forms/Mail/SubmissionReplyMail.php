<?php

namespace App\Domain\Forms\Mail;

use App\Domain\Forms\Models\FormSubmission;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * An answer from the office to the person who wrote, in their language
 * around the staff member's own words.
 */
final class SubmissionReplyMail extends Mailable implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public FormSubmission $submission,
        public string $text,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('forms.mail.reply.subject', ['reference' => $this->submission->reference]));
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.forms.reply', with: [
            'name' => $this->submission->name,
            'text' => $this->text,
            'reference' => $this->submission->reference,
            'url' => route('tickets.show', ['locale' => $this->submission->locale, 'reference' => $this->submission->reference]),
        ]);
    }
}
