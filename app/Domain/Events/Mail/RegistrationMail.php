<?php

namespace App\Domain\Events\Mail;

use App\Domain\Events\Actions\SendRegistrationMessage;
use App\Domain\Events\Enums\RegistrationStatus;
use App\Domain\Events\Models\EventRegistration;
use App\Domain\Events\Models\EventTranslation;
use App\Domain\Events\Support\IcsCalendar;
use App\Domain\Organisation\Queries\PublicDirectory;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Attachment;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * The email for each step of a registration (ADR-0025): confirmed, on the
 * waiting list, moved up from it, the reminder the day before, cancelled.
 * A confirmed place carries the event as a calendar file.
 */
final class RegistrationMail extends Mailable implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public EventRegistration $registration,
        public string $kind,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: __("events.registration.mail.{$this->kind}.subject", ['event' => $this->title()]));
    }

    public function content(): Content
    {
        $event = $this->registration->event;

        return new Content(markdown: 'mail.events.registration', with: [
            'kind' => $this->kind,
            'name' => $this->registration->name,
            'event' => $event,
            'title' => $this->title(),
            'translation' => $this->translation(),
            'ticketUrl' => SendRegistrationMessage::ticketUrl($this->registration),
            'position' => $this->registration->waitlistPosition(),
        ]);
    }

    /**
     * @return list<Attachment>
     */
    public function attachments(): array
    {
        $translation = $this->translation();
        if ($this->registration->status !== RegistrationStatus::Registered || ! $translation instanceof EventTranslation) {
            return [];
        }

        $ics = IcsCalendar::render([[
            'event' => $this->registration->event,
            'translation' => $translation,
            'url' => route('events.show', ['locale' => $translation->locale, 'slug' => $translation->slug]),
        ]], $translation->title);

        return [Attachment::fromData(fn (): string => $ics, 'event.ics')->withMime('text/calendar')];
    }

    private function translation(): ?EventTranslation
    {
        $shown = PublicDirectory::shown($this->registration->event, $this->registration->locale);

        return $shown instanceof EventTranslation ? $shown : null;
    }

    private function title(): string
    {
        return $this->translation()->title ?? $this->registration->event->title($this->registration->locale);
    }
}
