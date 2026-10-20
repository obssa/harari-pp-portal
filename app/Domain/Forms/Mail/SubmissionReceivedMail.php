<?php

namespace App\Domain\Forms\Mail;

use App\Domain\Forms\Models\FormSubmission;
use App\Domain\Forms\Models\FormTranslation;
use App\Domain\Organisation\Queries\PublicDirectory;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * The automatic reply in the sender's language (PLAN.md §4 Contact): the
 * form's own text when written, with the ticket reference and where to
 * follow it.
 */
final class SubmissionReceivedMail extends Mailable implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public function __construct(public FormSubmission $submission) {}

    public function envelope(): Envelope
    {
        $subject = $this->translation()?->autoreply_subject;

        return new Envelope(subject: filled($subject)
            ? $subject.' ('.$this->submission->reference.')'
            : __('forms.mail.received.subject', ['reference' => $this->submission->reference]));
    }

    public function content(): Content
    {
        $translation = $this->translation();

        return new Content(markdown: 'mail.forms.received', with: [
            'name' => $this->submission->name,
            'title' => $translation->title ?? $this->submission->form->title($this->submission->locale),
            'body' => $translation?->autoreply_body,
            'reference' => $this->submission->reference,
            'due' => $this->submission->due_at,
            'url' => route('tickets.show', ['locale' => $this->submission->locale, 'reference' => $this->submission->reference]),
        ]);
    }

    private function translation(): ?FormTranslation
    {
        $shown = PublicDirectory::shown($this->submission->form, $this->submission->locale);

        return $shown instanceof FormTranslation ? $shown : null;
    }
}
