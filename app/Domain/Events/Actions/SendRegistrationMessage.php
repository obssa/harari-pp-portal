<?php

namespace App\Domain\Events\Actions;

use App\Domain\Events\Mail\RegistrationMail;
use App\Domain\Events\Models\EventRegistration;
use App\Domain\Events\Models\EventTranslation;
use App\Domain\Organisation\Queries\PublicDirectory;
use App\Services\Calendar\EthiopianCalendar;
use App\Services\Sms\SendSms;
use Illuminate\Support\Facades\Mail;

/**
 * Tells a registrant what happened, by email and by text message, in the
 * language they registered in (PLAN.md §4: SMS-first audience).
 */
final class SendRegistrationMessage
{
    public const CONFIRMED = 'confirmed';

    public const WAITLISTED = 'waitlisted';

    public const PROMOTED = 'promoted';

    public const REMINDER = 'reminder';

    public const CANCELLED = 'cancelled';

    /** The details were already registered: the same ticket again. */
    public const AGAIN = 'again';

    public const KINDS = [self::CONFIRMED, self::WAITLISTED, self::PROMOTED, self::REMINDER, self::CANCELLED, self::AGAIN];

    public function handle(EventRegistration $registration, string $kind): void
    {
        if ($registration->email !== null) {
            Mail::to($registration->email)->locale($registration->locale)->queue(new RegistrationMail($registration, $kind));
        }

        if ($registration->phone !== null) {
            SendSms::dispatch($registration->phone, self::text($registration, $kind));
        }
    }

    /** The short message for a phone: what happened, when, and the ticket link. */
    public static function text(EventRegistration $registration, string $kind): string
    {
        $locale = $registration->locale;
        $event = $registration->event;
        $translation = PublicDirectory::shown($event, $locale);
        $title = $translation instanceof EventTranslation ? $translation->title : $event->title($locale);

        return __("events.registration.sms.{$kind}", [
            'event' => $title,
            'date' => app(EthiopianCalendar::class)->format($event->starts_at, $locale, withTime: ! $event->all_day),
            'link' => self::ticketUrl($registration),
        ], $locale);
    }

    public static function ticketUrl(EventRegistration $registration): string
    {
        return route('events.ticket', ['locale' => $registration->locale, 'token' => $registration->token]);
    }
}
