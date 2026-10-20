<?php

namespace App\Domain\Events\Notifications;

use App\Domain\Events\Enums\RegistrationStatus;
use App\Domain\Events\Models\Event;
use App\Domain\Notifications\Enums\NoticeKind;
use App\Domain\Notifications\Notifications\StaffNotice;
use App\Filament\Resources\Events\EventResource;

/**
 * Someone registered for an event (ADR-0036). The counts are taken when the
 * registration arrives; an unread notice for the same event is replaced, so
 * staff see one notice per event with the latest numbers. No names.
 */
final class RegistrationNotice extends StaffNotice
{
    /**
     * @param  array<string, string>  $titles  the event's title per language
     */
    public function __construct(
        public readonly string $eventId,
        public readonly array $titles,
        public readonly int $registered,
        public readonly ?int $placesLeft,
        public readonly int $waiting,
    ) {}

    public static function for(Event $event): self
    {
        return new self(
            $event->id,
            self::perLanguage(fn (string $locale): string => $event->title($locale)),
            $event->registeredCount(),
            $event->placesLeft(),
            $event->registrations()->where('status', RegistrationStatus::Waitlisted->value)->count(),
        );
    }

    public function kind(): NoticeKind
    {
        return NoticeKind::Registrations;
    }

    public function group(): string
    {
        return "registrations:{$this->eventId}";
    }

    protected function title(string $locale): string
    {
        $event = self::pick($this->titles, $locale);

        return $this->placesLeft === 0
            ? __('notifications.registration.full_title', ['event' => $event], $locale)
            : __('notifications.registration.title', ['event' => $event], $locale);
    }

    protected function lines(string $locale): array
    {
        $lines = [$this->placesLeft === null
            ? __('notifications.registration.count_open', ['count' => $this->registered], $locale)
            : __('notifications.registration.count', ['count' => $this->registered, 'left' => $this->placesLeft], $locale)];
        if ($this->waiting > 0) {
            $lines[] = __('notifications.registration.waiting', ['count' => $this->waiting], $locale);
        }

        return $lines;
    }

    protected function url(): string
    {
        return EventResource::getUrl('view', ['record' => $this->eventId], panel: 'admin');
    }

    protected function actionLabel(string $locale): string
    {
        return __('notifications.registration.open', [], $locale);
    }

    protected function icon(): string
    {
        return 'heroicon-o-ticket';
    }
}
