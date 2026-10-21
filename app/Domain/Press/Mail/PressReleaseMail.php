<?php

namespace App\Domain\Press\Mail;

use App\Domain\Content\Models\PostTranslation;
use App\Domain\Press\Models\MediaContact;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;

/**
 * A press release for a journalist (ADR-0054): the title, the summary and a
 * link to the full text on the portal, with a link to stop receiving them.
 */
final class PressReleaseMail extends Mailable
{
    use Queueable;

    public function __construct(
        public readonly PostTranslation $translation,
        public readonly MediaContact $contact,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('press.mail.subject', ['title' => $this->translation->title]));
    }

    public function headers(): Headers
    {
        return new Headers(text: [
            'List-Unsubscribe' => '<'.$this->optOutUrl().'>',
            'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click',
        ]);
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.press.release', with: [
            'name' => $this->contact->name,
            'title' => $this->translation->title,
            'kicker' => $this->translation->kicker,
            'excerpt' => $this->translation->excerpt,
            'url' => route('news.show', ['locale' => $this->translation->locale, 'slug' => $this->translation->slug]),
            'optOut' => $this->optOutUrl(),
        ]);
    }

    private function optOutUrl(): string
    {
        return route('press.opt-out', ['locale' => $this->translation->locale, 'token' => $this->contact->token]);
    }
}
