<?php

namespace App\Domain\Forms\Mail;

use App\Domain\Forms\Models\FormSubmission;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Tells the office or address a form routes to that a ticket arrived.
 * It carries no personal details: those stay in the admin inbox.
 */
final class NewSubmissionMail extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public function __construct(public FormSubmission $submission) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('forms.mail.notice.subject', [
            'form' => $this->submission->form->title(),
            'reference' => $this->submission->reference,
        ]));
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.forms.notice', with: [
            'form' => $this->submission->form->title(),
            'reference' => $this->submission->reference,
            'due' => $this->submission->due_at,
            'url' => url('/admin/submissions'),
        ]);
    }
}
