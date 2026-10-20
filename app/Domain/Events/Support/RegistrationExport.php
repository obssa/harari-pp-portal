<?php

namespace App\Domain\Events\Support;

use App\Domain\Events\Models\Event;
use App\Domain\Events\Models\EventRegistration;
use App\Services\Calendar\EthiopianCalendar;
use Carbon\CarbonImmutable;

/**
 * The attendee list as CSV (PLAN.md §7: attendee export). Starts with a
 * byte-order mark so spreadsheet programs read Ethiopic names correctly,
 * and neutralises cells a spreadsheet would run as a formula.
 */
final class RegistrationExport
{
    /**
     * @param  resource  $out
     */
    public static function write(Event $event, $out): void
    {
        $timezone = EthiopianCalendar::timezone();
        $time = fn (?CarbonImmutable $at): string => $at?->setTimezone($timezone)->format('Y-m-d H:i') ?? '';

        fwrite($out, "\u{FEFF}");
        fputcsv($out, array_map(fn (string $key): string => __("events.registration.export.{$key}"), [
            'name', 'phone', 'email', 'status', 'registered_at', 'checked_in_at', 'language',
        ]), escape: '');

        $event->registrations()->orderBy('created_at')->orderBy('id')->chunk(500, function ($registrations) use ($out, $time): void {
            /** @var EventRegistration $registration */
            foreach ($registrations as $registration) {
                fputcsv($out, array_map(self::cell(...), [
                    $registration->name,
                    (string) $registration->phone,
                    (string) $registration->email,
                    $registration->status->label(),
                    $time($registration->created_at),
                    $time($registration->checked_in_at),
                    $registration->locale,
                ]), escape: '');
            }
        });
    }

    public static function cell(string $value): string
    {
        return $value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true) ? "'".$value : $value;
    }
}
